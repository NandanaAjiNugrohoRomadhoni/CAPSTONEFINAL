<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class AdditiveMultiMenuTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $DBGroup = 'tests';
    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;
    protected $namespace = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedUsers();
        $this->seedBaseData();
    }

    /**
     * Test Task 6 Scenario:
     * 1. Seeds 100 patients for a specific date.
     * 2. Assigns 2 menus to that date in the schedule.
     * 3. Calls SPK Basah generation or preview for that date.
     * 4. Asserts that the total required quantity is exactly (Qty_Menu1 + Qty_Menu2) × 100 (Adjusted by 1.05 buffer).
     */
    public function testAdditiveMultiMenuRequirements(): void
    {
        $db = Database::connect();
        $dapurToken = $this->login('dapur');
        $gudangToken = $this->login('gudang');
        $date = '2026-03-14';

        // 1. Seed 100 patients for a specific date.
        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/daily-patients', [
                'service_date' => $date,
                'total_patients' => 100,
            ])
            ->assertStatus(201);

        // 2. Assign menus for generated target dates (H+1 and H+2).
        $db->table('menu_schedules')->insertBatch([
            ['day_of_month' => 15, 'menu_id' => 1],
            ['day_of_month' => 15, 'menu_id' => 2],
            ['day_of_month' => 16, 'menu_id' => 1],
        ]);

        // 3. Call SPK Basah generation for that date.
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => $date,
            ]);

        $response->assertStatus(201);
        $json = json_decode($response->getJSON(), true);
        $spkId = $json['data']['id'];

        // 4. Asserts that the total required quantity is exactly (Qty_Menu1 + Qty_Menu2) × (Patients * 1.05).
        // Patients = 100, Buffer = 5% -> Estimated = 105.
        // Menu 1 uses Dish 1 (Dish 1 uses Item 1 with Qty 2.0).
        // Menu 2 uses Dish 2 (Dish 2 uses Item 1 with Qty 3.0).
        // Both menus use the same item (Item 1) to test summation.
        // Total Qty per adjusted patient = 2.0 + 3.0 = 5.0.
        // Expected total = 105 * 5.0 = 525.0.

        $recommendation = $db->table('spk_recommendations')
            ->where('spk_id', $spkId)
            ->where('target_date', '2026-03-15')
            ->where('item_id', 1)
            ->get()
            ->getRowArray();

        $this->assertNotNull($recommendation, 'Recommendation for item 1 on target date should exist');

        // Use number_format to avoid float precision issues in comparison
        $actualQty = number_format((float) $recommendation['required_qty'], 2, '.', '');
        $this->assertSame('525.00', $actualQty, 'Required quantity should be additive (105 patients * (2.0 + 3.0) qty)');
    }

    protected function seedRoles(): void
    {
        $roleModel = new RoleModel();
        $roleModel->insertBatch([
            ['name' => 'admin'],
            ['name' => 'dapur'],
            ['name' => 'gudang'],
        ]);
    }

    protected function seedUsers(): void
    {
        $roleModel = new RoleModel();
        $userProvider = new AppUserProvider();

        foreach ([
            ['role' => 'admin', 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com'],
            ['role' => 'dapur', 'name' => 'Dapur User', 'username' => 'dapur', 'email' => 'dapur@example.com'],
            ['role' => 'gudang', 'name' => 'Gudang User', 'username' => 'gudang', 'email' => 'gudang@example.com'],
        ] as $userData) {
            $role = $roleModel->findByName($userData['role']);

            $user = new User([
                'role_id' => $role['id'],
                'name' => $userData['name'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'is_active' => true,
                'active' => true,
            ]);
            $user->fill(['password' => 'password123']);
            $userProvider->insert($user, true);
        }
    }

    protected function seedBaseData(): void
    {
        $db = Database::connect();

        $db->table('item_categories')->insertBatch([
            ['id' => 1, 'name' => 'BASAH'],
        ]);

        $db->table('item_units')->insertBatch([
            ['id' => 1, 'name' => 'gram'],
            ['id' => 2, 'name' => 'kg'],
        ]);

        $db->table('items')->insert([
            'id' => 1,
            'item_category_id' => 1,
            'name' => 'Ayam Basah',
            'unit_base' => 'gram',
            'unit_convert' => 'kg',
            'item_unit_base_id' => 1,
            'item_unit_convert_id' => 2,
            'conversion_base' => 1000,
            'is_active' => true,
            'qty' => 0,
        ]);

        $db->table('dishes')->insertBatch([
            ['id' => 1, 'name' => 'Dish 1'],
            ['id' => 2, 'name' => 'Dish 2'],
        ]);

        $db->table('dish_compositions')->insertBatch([
            ['dish_id' => 1, 'item_id' => 1, 'qty_per_patient' => 2.0],
            ['dish_id' => 2, 'item_id' => 1, 'qty_per_patient' => 3.0],
        ]);

        $db->table('meal_times')->insertBatch([
            ['id' => 1, 'name' => 'Pagi'],
            ['id' => 2, 'name' => 'Siang'],
            ['id' => 3, 'name' => 'Sore'],
        ]);

        $db->table('menus')->insertBatch([
            ['id' => 1, 'name' => 'Menu 1'],
            ['id' => 2, 'name' => 'Menu 2'],
        ]);

        $db->table('menu_dishes')->insertBatch([
            ['menu_id' => 1, 'meal_time_id' => 1, 'dish_id' => 1],
            ['menu_id' => 2, 'meal_time_id' => 1, 'dish_id' => 2],
        ]);
    }

    protected function login(string $username): string
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => $username,
                'password' => 'password123',
            ]);

        $json = json_decode($result->getJSON(), true);
        return $json['access_token'];
    }
}
