<?php

namespace Tests\Unit;

use App\Models\DailyPatientModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\SpkCalculationModel;
use App\Services\SpkBasahGenerationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class SpkMultiMenuBugTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
    }

    public function testSpkBasahMultiMenuMultiplication(): void
    {
        $db      = Database::connect();
        $itemId  = $this->createBasahItem('Basah Item', 'gram', 0.0);
        $dishId  = $this->createDishWithComposition('Test Dish', $itemId, 100.0);
        $menuIds = $this->createMenusForDishes([$dishId, $dishId]);
        $date    = $this->seedScheduleAndPatients($menuIds, 100);

        $service = new SpkBasahGenerationService();
        $result  = $service->generate(['service_date' => $date], 1);

        if (! $result['success']) {
            $this->fail('Generation failed: ' . json_encode($result['errors']));
        }

        $recommendations = (new \App\Models\SpkRecommendationModel())
            ->where('item_id', $itemId)
            ->where('target_date', $date)
            ->findAll();

        $this->assertCount(1, $recommendations);
        $this->assertSame(10500.0, (float) $recommendations[0]['required_qty']);
        $this->assertSame(10500.0, (float) $recommendations[0]['recommended_qty']);
    }

    public function testSpkBasahRoundingBug(): void
    {
        $itemId  = $this->createBasahItem('Small Qty Item', 'kg', 0.0);
        $dishId  = $this->createDishWithComposition('Salted Dish', $itemId, 0.001);
        $menuIds = $this->createMenusForDishes([$dishId]);
        $date    = $this->seedScheduleAndPatients($menuIds, 10);

        $service = new SpkBasahGenerationService();
        $result  = $service->generate(['service_date' => $date], 1);

        if (! $result['success']) {
            $this->fail('Generation failed: ' . json_encode($result['errors']));
        }

        $recommendations = (new \App\Models\SpkRecommendationModel())
            ->where('item_id', $itemId)
            ->where('target_date', $date)
            ->findAll();

        $this->assertCount(1, $recommendations);
        $this->assertSame(0.0105, (float) $recommendations[0]['required_qty']);
        $this->assertSame(0.0105, (float) $recommendations[0]['recommended_qty']);
    }

    public function testSpkBasahDecimalStockSubtraction(): void
    {
        $itemId  = $this->createBasahItem('Small Stock Item', 'kg', 0.005);
        $dishId  = $this->createDishWithComposition('Stocked Dish', $itemId, 0.001);
        $menuIds = $this->createMenusForDishes([$dishId]);
        $date    = $this->seedScheduleAndPatients($menuIds, 10);

        $service = new SpkBasahGenerationService();
        $result  = $service->generate(['service_date' => $date], 1);

        if (! $result['success']) {
            $this->fail('Generation failed: ' . json_encode($result['errors']));
        }

        $recommendations = (new \App\Models\SpkRecommendationModel())
            ->where('item_id', $itemId)
            ->where('target_date', $date)
            ->findAll();

        $this->assertCount(1, $recommendations);
        $this->assertSame(0.0105, (float) $recommendations[0]['required_qty']);
        $this->assertSame(0.0055, (float) $recommendations[0]['recommended_qty']);
    }

    public function testSpkBasahDifferentDishesSameDateStillSum(): void
    {
        $itemId      = $this->createBasahItem('Shared Basah Item', 'gram', 0.0);
        $firstDishId = $this->createDishWithComposition('First Dish', $itemId, 100.0);
        $secondDishId = $this->createDishWithComposition('Second Dish', $itemId, 25.0);
        $menuIds     = $this->createMenusForDishes([$firstDishId, $secondDishId]);
        $date        = $this->seedScheduleAndPatients($menuIds, 100);

        $service = new SpkBasahGenerationService();
        $result  = $service->generate(['service_date' => $date], 1);

        if (! $result['success']) {
            $this->fail('Generation failed: ' . json_encode($result['errors']));
        }

        $recommendations = (new \App\Models\SpkRecommendationModel())
            ->where('item_id', $itemId)
            ->where('target_date', $date)
            ->findAll();

        $this->assertCount(1, $recommendations);
        $this->assertSame(13125.0, (float) $recommendations[0]['required_qty']);
        $this->assertSame(13125.0, (float) $recommendations[0]['recommended_qty']);
    }

    private function seedBaseline(): void
    {
        $db = Database::connect();
        $db->table('roles')->insertBatch([
            ['name' => 'admin'],
            ['name' => 'dapur'],
            ['name' => 'gudang'],
        ]);
        $db->table('users')->insert([
            'role_id' => 1,
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'secret',
            'is_active' => 1,
        ]);
        $db->table('item_categories')->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);
        $db->table('approval_statuses')->insertBatch([
            ['name' => 'PENDING'],
            ['name' => 'APPROVED'],
            ['name' => 'REJECTED'],
        ]);
        $db->table('transaction_types')->insertBatch([
            ['name' => 'IN'],
            ['name' => 'OUT'],
        ]);
    }

    private function createBasahItem(string $name, string $unitBase, float $qty): int
    {
        $db = Database::connect();
        $db->table('items')->insert([
            'item_category_id' => $this->getCategoryId('BASAH'),
            'name' => $name,
            'unit_base' => $unitBase,
            'unit_convert' => $unitBase,
            'item_unit_base_id' => 1,
            'item_unit_convert_id' => 1,
            'conversion_base' => 1,
            'is_active' => true,
            'qty' => $qty,
        ]);

        return (int) $db->insertID();
    }

    private function createDishWithComposition(string $name, int $itemId, float $qtyPerPatient): int
    {
        $db = Database::connect();
        $db->table('dishes')->insert(['name' => $name]);
        $dishId = (int) $db->insertID();

        $db->table('dish_compositions')->insert([
            'dish_id' => $dishId,
            'item_id' => $itemId,
            'qty_per_patient' => $qtyPerPatient,
        ]);

        return $dishId;
    }

    /**
     * @param array<int, int> $dishIds
     * @return array<int, int>
     */
    private function createMenusForDishes(array $dishIds): array
    {
        $db = Database::connect();
        $mealTimeId = 100;
        $db->table('meal_times')->insert(['id' => $mealTimeId, 'name' => 'Test Meal']);

        $menuIds = [];
        foreach ($dishIds as $index => $dishId) {
            $menuId = 100 + $index;
            $db->table('menus')->insert(['id' => $menuId, 'name' => 'Menu ' . $menuId]);
            $db->table('menu_dishes')->insert([
                'menu_id' => $menuId,
                'dish_id' => $dishId,
                'meal_time_id' => $mealTimeId,
            ]);
            $menuIds[] = $menuId;
        }

        return $menuIds;
    }

    /**
     * @param array<int, int> $menuIds
     */
    private function seedScheduleAndPatients(array $menuIds, int $patients): string
    {
        $db = Database::connect();
        $serviceDate = date('Y-m-d');
        $targetDates = [
            $serviceDate,
            date('Y-m-d', strtotime($serviceDate . ' +1 day')),
        ];

        foreach ($targetDates as $targetDate) {
            $day = (int) date('j', strtotime($targetDate));
            $db->table('menu_schedules')->where('day_of_month', $day)->delete();
            foreach ($menuIds as $menuId) {
                $db->table('menu_schedules')->insert([
                    'day_of_month' => $day,
                    'menu_id' => $menuId,
                ]);
            }

            $db->table('daily_patients')->where('service_date', $targetDate)->delete();
            $db->table('daily_patients')->insert([
                'service_date' => $targetDate,
                'total_patients' => $patients,
            ]);
        }

        return $serviceDate;
    }

    private function getCategoryId(string $name): int
    {
        $db = Database::connect();
        return (int) $db->table('item_categories')->where('name', $name)->get()->getRowArray()['id'];
    }
}
