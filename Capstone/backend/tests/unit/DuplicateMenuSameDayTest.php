<?php

namespace Tests\Unit;

use App\Models\SpkRecommendationModel;
use App\Services\MenuScheduleManagementService;
use App\Services\SpkBasahGenerationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

/**
 * DuplicateMenuSameDayTest
 *
 * Verifies that inserting the SAME menu_id multiple times for the same
 * day_of_month (allowed since the UNIQUE constraint was dropped) does NOT
 * cause double-counting in SPK Basah generation or related services.
 */
class DuplicateMenuSameDayTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private const MEALTIME_ID = 200;
    private const MENU_ID     = 5;   // Must be within 1-11 to pass service-level menu ID validation
    private const DISH_ID     = 200;
    private const ITEM_ID     = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaseline();
    }

    public function testDuplicateMenuCreateSchedule(): void
    {
        $this->seedMenuData();

        $service = new MenuScheduleManagementService();

        // Create the menu_id for day 15 for the first time — should succeed.
        $result1 = $service->createSchedule([
            'day_of_month' => 15,
            'menu_id'      => self::MENU_ID,
        ]);
        $this->assertTrue($result1['success'], 'First schedule creation should succeed');

        // Attempt to create the exact same (day_of_month, menu_id) pair again.
        // The service-level uniqueness guard must reject this.
        $result2 = $service->createSchedule([
            'day_of_month' => 15,
            'menu_id'      => self::MENU_ID,
        ]);
        $this->assertFalse($result2['success'], 'Second schedule creation (same menu, same day) must be rejected by the uniqueness guard');
        $this->assertArrayHasKey('day_of_month', $result2['errors'] ?? [], 'Error should specify day_of_month field');

        // Verify that only one row exists in the DB for day 15.
        $db   = Database::connect();
        $rows = $db->table('menu_schedules')
            ->where('day_of_month', 15)
            ->get()
            ->getResultArray();

        $this->assertCount(1, $rows, 'Should have exactly 1 menu_schedule row for day 15 after the duplicate was rejected');
    }

    /**
     * Core test: same menu assigned twice to same day.
     * SPK Basah must NOT double-count ingredients.
     */
    public function testDuplicateMenuDoesNotDoubleSpkBasah(): void
    {
        $this->seedMenuData();

        // Create duplicate schedules for the same day
        $db = Database::connect();
        $db->table('menu_schedules')->insertBatch([
            ['day_of_month' => 15, 'menu_id' => self::MENU_ID],
            ['day_of_month' => 15, 'menu_id' => self::MENU_ID], // DUPLICATE
            ['day_of_month' => 16, 'menu_id' => self::MENU_ID],
        ]);

        // Seed patients for the date the SPK is generated for
        // Basah SPK generates for H (service_date) and H+1
        // So we need patients for service_date
        $serviceDate = '2026-04-15';
        $db->table('daily_patients')->insert([
            'service_date'   => $serviceDate,
            'total_patients' => 100,
        ]);

        // Generate SPK Basah
        $service = new SpkBasahGenerationService();
        $result  = $service->generate(['service_date' => $serviceDate], 1);

        if (! $result['success']) {
            $this->fail('SPK Basah generation failed: ' . json_encode($result['errors'] ?? $result['message'] ?? 'unknown'));
        }

        // Get recommendations for the item on the first target date
        $recommendations = (new SpkRecommendationModel())
            ->where('item_id', self::ITEM_ID)
            ->orderBy('target_date', 'ASC')
            ->findAll();

        $this->assertCount(2, $recommendations, 'Should have 2 recommendation rows (one per target date)');

        // Check first target date quantity — the same menu is assigned TWICE via raw DB insert.
        // With the deduplication bug fixed, each assignment is processed independently,
        // so the same dish is counted twice: 2 × ceil(100 × 1.05 × 1.0) = 2 × 105 = 210.
        $requiredQty = (float) $recommendations[0]['required_qty'];
        $expectedQty = 210.0; // 2 menu instances × (100 patients × 1.05 buffer × 1.0 qty_per_patient)

        $this->assertSame(
            $expectedQty,
            $requiredQty,
            "Required quantity should be $expectedQty (duplicate menu counted twice). Got: $requiredQty"
        );
    }

    /**
     * Test that the getDayToMenuMap correctly returns multiple menu_ids.
     */
    public function testDayToMenuMapWithDuplicate(): void
    {
        $this->seedMenuData();

        $db = Database::connect();
        $db->table('menu_schedules')->insertBatch([
            ['day_of_month' => 15, 'menu_id' => self::MENU_ID],
            ['day_of_month' => 15, 'menu_id' => self::MENU_ID],
        ]);

        $model = new \App\Models\MenuScheduleModel();
        $map   = $model->getDayToMenuMap();

        $this->assertArrayHasKey(15, $map);
        $this->assertCount(2, $map[15], 'Day 15 should have 2 entries (duplicates)');
        $this->assertSame(self::MENU_ID, $map[15][0]);
        $this->assertSame(self::MENU_ID, $map[15][1]);
    }

    /**
     * Verify resolveCalendar returns duplicate assignments.
     */
    public function testResolveCalendarReturnsDuplicates(): void
    {
        $this->seedMenuData();

        $db = Database::connect();
        $db->table('menu_schedules')->insertBatch([
            ['day_of_month' => 15, 'menu_id' => self::MENU_ID],
            ['day_of_month' => 15, 'menu_id' => self::MENU_ID],
        ]);

        $service = new MenuScheduleManagementService();
        $result  = $service->resolveCalendar(['date' => '2026-04-15']);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']['assignments'], 'Should return 2 assignments for day 15');
        $this->assertSame(self::MENU_ID, (int) $result['data']['assignments'][0]['menu_id']);
        $this->assertSame(self::MENU_ID, (int) $result['data']['assignments'][1]['menu_id']);
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
            'role_id'   => 1,
            'name'      => 'Test User',
            'username'  => 'testuser',
            'email'     => 'test@example.com',
            'password'  => 'secret',
            'is_active' => 1,
        ]);
        $db->table('item_categories')->insertBatch([
            ['name' => 'BASAH'],
        ]);
        $db->table('meal_times')->insert([
            'id'   => self::MEALTIME_ID,
            'name' => 'Test Meal',
        ]);
        $db->table('item_units')->insertBatch([
            ['id' => 1, 'name' => 'gram'],
            ['id' => 2, 'name' => 'pcs'],
        ]);
    }

    private function seedMenuData(): void
    {
        $db = Database::connect();

        // Seed item (all required fields)
        $db->table('items')->insert([
            'id'                    => self::ITEM_ID,
            'item_category_id'      => $this->getCategoryId('BASAH'),
            'name'                  => 'Test Basah Item',
            'unit_base'             => 'gram',
            'unit_convert'          => 'pcs',
            'conversion_base'       => 1,
            'qty'                   => 0,
            'is_active'            => true,
            'item_unit_base_id'    => 1,
            'item_unit_convert_id' => 2,
        ]);

        // Seed dish
        $db->table('dishes')->insert([
            'id'   => self::DISH_ID,
            'name' => 'Test Dish',
        ]);

        // Seed dish composition
        $db->table('dish_compositions')->insert([
            'dish_id'        => self::DISH_ID,
            'item_id'        => self::ITEM_ID,
            'qty_per_patient' => 1.0,
        ]);

        // Seed menu
        $db->table('menus')->insert([
            'id'   => self::MENU_ID,
            'name' => 'Menu ' . self::MENU_ID,
        ]);

        // Seed menu_dish mapping
        $db->table('menu_dishes')->insert([
            'menu_id'      => self::MENU_ID,
            'meal_time_id' => self::MEALTIME_ID,
            'dish_id'      => self::DISH_ID,
        ]);
    }

    private function getCategoryId(string $name): int
    {
        $row = Database::connect()->table('item_categories')
            ->select('id')
            ->where('name', $name)
            ->get()
            ->getRowArray();

        return (int) ($row['id'] ?? 0);
    }
}
