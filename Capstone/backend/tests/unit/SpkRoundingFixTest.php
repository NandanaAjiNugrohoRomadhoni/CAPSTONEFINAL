<?php

namespace Tests\Unit;

use App\Models\DailyPatientModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\SpkCalculationModel;
use App\Services\SpkBasahGenerationService;
use App\Services\SpkKeringPengemasGenerationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class SpkRoundingFixTest extends CIUnitTestCase
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

    public function testSpkKeringRoundingFix(): void
    {
        $db = Database::connect();
        $keringId = $this->getCategoryId('KERING');
        
        $db->table('items')->insert([
            'item_category_id' => $keringId,
            'name' => 'Test Item',
            'unit_base' => 'pcs',
            'unit_convert' => 'pcs',
            'item_unit_base_id' => 1,
            'item_unit_convert_id' => 1,
            'conversion_base' => 1,
            'is_active' => true,
            'qty' => 2.2,
        ]);
        $itemId = $db->insertID();

        // Mock usage by adding a stock transaction
        $db->table('stock_transactions')->insert([
            'type_id' => 2, // OUT
            'approval_status_id' => 2, // APPROVED
            'transaction_date' => date('Y-m-d', strtotime('last month')),
            'is_revision' => 0,
            'user_id' => 1,
        ]);
        $txId = $db->insertID();
        $db->table('stock_transaction_details')->insert([
            'transaction_id' => $txId,
            'item_id' => $itemId,
            'qty' => 19.8,
        ]);

        $service = new SpkKeringPengemasGenerationService();
        $result = $service->generate(['target_month' => date('Y-m')], 1);
        
        $recommendationModel = new \App\Models\SpkRecommendationModel();
        $recommendations = $recommendationModel->where('item_id', $itemId)->findAll();
        
        $this->assertCount(1, $recommendations);
        $rec = $recommendations[0];
        
        $this->assertEquals(20.0, (float) $rec['system_recommended_qty']);
    }

    public function testSpkBasahBufferFix(): void
    {
        $db = Database::connect();
        $basahId = $this->getCategoryId('BASAH');
        
        $db->table('items')->insert([
            'item_category_id' => $basahId,
            'name' => 'Basah Item',
            'unit_base' => 'gram',
            'unit_convert' => 'gram',
            'item_unit_base_id' => 1,
            'item_unit_convert_id' => 1,
            'conversion_base' => 1,
            'is_active' => true,
            'qty' => 0,
        ]);
        $itemId = $db->insertID();

        $db->table('dishes')->insert(['name' => 'Test Dish']);
        $dishId = $db->insertID();
        
        $db->table('dish_compositions')->insert([
            'dish_id' => $dishId,
            'item_id' => $itemId,
            'qty_per_patient' => 100.0,
        ]);

        $db->table('menus')->insert(['id' => 1, 'name' => 'Test Menu']);
        $menuId = 1;
        
        $db->table('meal_times')->insert(['id' => 1, 'name' => 'Pagi']);
        
        $db->table('menu_dishes')->insert([
            'menu_id' => $menuId,
            'dish_id' => $dishId,
            'meal_time_id' => 1,
        ]);

        $serviceDate = date('Y-m-d');
        $db->table('menu_schedules')->insertBatch([
            ['day_of_month' => (int) date('j', strtotime($serviceDate)), 'menu_id' => $menuId],
            ['day_of_month' => (int) date('j', strtotime($serviceDate . ' +1 day')), 'menu_id' => $menuId],
        ]);

        $db->table('daily_patients')->insert([
            'service_date' => $serviceDate,
            'total_patients' => 1,
        ]);

        $service = new SpkBasahGenerationService();
        $result = $service->generate(['service_date' => $serviceDate], 1);
        if (!$result['success']) {
            $this->fail(json_encode($result['errors']));
        }
        
        $recommendationModel = new \App\Models\SpkRecommendationModel();
        $recommendations = $recommendationModel->where('item_id', $itemId)->findAll();
        
        $this->assertCount(2, $recommendations);
        $rec = $recommendations[0];
        
        $this->assertEquals(105.0, (float) $rec['system_recommended_qty']);
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

    private function getCategoryId(string $name): int
    {
        $db = Database::connect();
        return (int) $db->table('item_categories')->where('name', $name)->get()->getRowArray()['id'];
    }
}
