<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\ApprovalStatusModel;
use App\Models\DailyPatientModel;
use App\Models\DishCompositionModel;
use App\Models\DishModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;
use App\Models\MealTimeModel;
use App\Models\MenuDishModel;
use App\Models\MenuModel;
use App\Models\RoleModel;
use App\Models\SpkCalculationModel;
use App\Models\SpkRecommendationModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class DashboardTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedUsers();
        $this->seedLookupsAndItems();
        $this->seedMenuData();
        $this->seedPatientsAndTransactions();
        $this->seedSpkHistory();
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
        $roleModel    = new RoleModel();
        $userProvider = new AppUserProvider();

        foreach ([
            ['role' => 'admin', 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com'],
            ['role' => 'gudang', 'name' => 'Gudang User', 'username' => 'gudang', 'email' => 'gudang@example.com'],
            ['role' => 'dapur', 'name' => 'Dapur User', 'username' => 'dapur', 'email' => 'dapur@example.com'],
        ] as $data) {
            $role = $roleModel->findByName($data['role']);
            $user = new User([
                'role_id'   => $role['id'],
                'name'      => $data['name'],
                'username'  => $data['username'],
                'email'     => $data['email'],
                'is_active' => true,
                'active'    => true,
            ]);
            $user->fill(['password' => 'password123']);
            $userProvider->insert($user, true);
        }
    }

    protected function seedLookupsAndItems(): void
    {
        $categoryModel = new ItemCategoryModel();
        $unitModel     = new ItemUnitModel();

        $categoryModel->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);

        $unitModel->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
        ]);

        $basahId  = $categoryModel->getIdByName('BASAH');
        $keringId = $categoryModel->getIdByName('KERING');
        $gramId   = $unitModel->getIdByName('gram');
        $kgId     = $unitModel->getIdByName('kg');

        (new ItemModel())->insertBatch([
            [
                'item_category_id'     => $keringId,
                'name'                 => 'Beras',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 5000,
            ],
            [
                'item_category_id'     => $keringId,
                'name'                 => 'Gula',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 0,
            ],
            [
                'item_category_id'     => $basahId,
                'name'                 => 'Ayam',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 2500,
            ],
        ]);

        (new TransactionTypeModel())->insertBatch([
            ['name' => 'IN'],
            ['name' => 'OUT'],
            ['name' => 'RETURN_IN'],
        ]);

        (new ApprovalStatusModel())->insertBatch([
            ['name' => 'APPROVED'],
            ['name' => 'PENDING'],
            ['name' => 'REJECTED'],
        ]);
    }

    protected function seedMenuData(): void
    {
        $menuModel       = new MenuModel();
        $mealTimeModel   = new MealTimeModel();
        $dishModel       = new DishModel();
        $menuDishModel   = new MenuDishModel();
        $compositionModel = new DishCompositionModel();

        $menuModel->insert(['id' => 1, 'name' => 'Paket 1']);

        $mealTimeModel->insertBatch([
            ['name' => 'PAGI'],
            ['name' => 'SIANG'],
            ['name' => 'SORE'],
        ]);

        $dishModel->insertBatch([
            ['name' => 'Nasi Pagi'],
            ['name' => 'Sup Siang'],
            ['name' => 'Nasi Sore'],
        ]);

        $menuDishModel->insertBatch([
            ['menu_id' => 1, 'meal_time_id' => 1, 'dish_id' => 1],
            ['menu_id' => 1, 'meal_time_id' => 2, 'dish_id' => 2],
            ['menu_id' => 1, 'meal_time_id' => 3, 'dish_id' => 3],
        ]);

        $compositionModel->insertBatch([
            ['dish_id' => 1, 'item_id' => 1, 'qty_per_patient' => 100],
            ['dish_id' => 2, 'item_id' => 3, 'qty_per_patient' => 80],
            ['dish_id' => 3, 'item_id' => 1, 'qty_per_patient' => 120],
        ]);
    }

    protected function seedPatientsAndTransactions(): void
    {
        $dailyPatientModel = new DailyPatientModel();
        $dailyPatientModel->insertBatch([
            ['service_date' => date('Y-m-d', strtotime('-2 day')), 'total_patients' => 110],
            ['service_date' => date('Y-m-d', strtotime('-1 day')), 'total_patients' => 120],
            ['service_date' => date('Y-m-d'), 'total_patients' => 130],
        ]);

        $db = \Config\Database::connect();
        $outType = (new TransactionTypeModel())->getIdByName('OUT');
        $approved = (new ApprovalStatusModel())->getIdByName('APPROVED');

        $tx1 = $db->table('stock_transactions')->insert([
            'type_id' => $outType,
            'transaction_date' => date('Y-m-d', strtotime('-1 day')),
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approved,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => null,
        ], true);

        $tx2 = $db->table('stock_transactions')->insert([
            'type_id' => $outType,
            'transaction_date' => date('Y-m-d'),
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approved,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => null,
        ], true);

        $db->table('stock_transaction_details')->insertBatch([
            ['transaction_id' => (int) $tx1, 'item_id' => 1, 'qty' => 75, 'input_qty' => 75, 'input_unit' => 'base'],
            ['transaction_id' => (int) $tx2, 'item_id' => 3, 'qty' => 65, 'input_qty' => 65, 'input_unit' => 'base'],
        ]);
    }

    protected function seedSpkHistory(): void
    {
        $spkModel = new SpkCalculationModel();
        $recModel = new SpkRecommendationModel();

        $basahId = $spkModel->insert([
            'spk_type' => SpkCalculationModel::TYPE_BASAH,
            'calculation_scope' => SpkCalculationModel::SCOPE_COMBINED_WINDOW,
            'scope_key' => 'basah|combined_window|a',
            'version' => 1,
            'is_latest' => true,
            'calculation_date' => date('Y-m-d', strtotime('-1 day')),
            'target_date_start' => date('Y-m-d'),
            'target_date_end' => date('Y-m-d', strtotime('+1 day')),
            'target_month' => null,
            'daily_patient_id' => 1,
            'user_id' => 2,
            'category_id' => 1,
            'estimated_patients' => 120,
            'is_finish' => false,
        ], true);

        $keringId = $spkModel->insert([
            'spk_type' => SpkCalculationModel::TYPE_KERING_PENGEMAS,
            'calculation_scope' => SpkCalculationModel::SCOPE_MONTHLY,
            'scope_key' => 'kering|monthly|a',
            'version' => 1,
            'is_latest' => true,
            'calculation_date' => date('Y-m-d'),
            'target_date_start' => date('Y-m-01'),
            'target_date_end' => date('Y-m-t'),
            'target_month' => date('Y-m'),
            'daily_patient_id' => null,
            'user_id' => 2,
            'category_id' => 2,
            'estimated_patients' => 0,
            'is_finish' => false,
        ], true);

        $recModel->insertBatch([
            [
                'spk_id' => (int) $basahId,
                'item_id' => 3,
                'target_date' => date('Y-m-d'),
                'current_stock_qty' => 2500,
                'required_qty' => 1800,
                'system_recommended_qty' => 0,
                'recommended_qty' => 0,
                'is_overridden' => false,
            ],
            [
                'spk_id' => (int) $keringId,
                'item_id' => 1,
                'target_date' => null,
                'current_stock_qty' => 5000,
                'required_qty' => 4200,
                'system_recommended_qty' => 0,
                'recommended_qty' => 0,
                'is_overridden' => false,
            ],
        ]);
    }

    protected function login(string $username): string
    {
        $result = $this->withBodyFormat('json')->post('api/v1/auth/login', [
            'username' => $username,
            'password' => 'password123',
        ]);

        return json_decode($result->getJSON(), true)['access_token'];
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $result = $this->get('api/v1/dashboard');
        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('message', $json);
        $this->assertNotSame('', trim((string) $json['message']));
    }

    public function testDashboardRejectsInvalidTokenWith401Shape(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer invalid-token'])->get('api/v1/dashboard');
        $result->assertStatus(401);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('message', $json);
        $this->assertNotSame('', trim((string) $json['message']));
    }

    public function testDashboardReturnsRoleScopedAdminAggregate(): void
    {
        $token = $this->login('admin');
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('api/v1/dashboard');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('admin', $json['data']['role']);
        $this->assertArrayHasKey('stock_summary', $json['data']['aggregates']);
        $this->assertArrayHasKey('dry_stock_status', $json['data']['aggregates']);
        $this->assertArrayHasKey('spending_trend', $json['data']['aggregates']);
        $this->assertArrayHasKey('current_menu_cycle', $json['data']['aggregates']);
        $this->assertArrayHasKey('latest_spk_history', $json['data']['aggregates']);
        $this->assertArrayHasKey('patient_fluctuation', $json['data']['aggregates']);
    }

    public function testDashboardReturnsRoleScopedGudangAggregate(): void
    {
        $token = $this->login('gudang');
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('api/v1/dashboard');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('gudang', $json['data']['role']);
        $this->assertArrayHasKey('stock_summary', $json['data']['aggregates']);
        $this->assertArrayHasKey('dry_stock_status', $json['data']['aggregates']);
        $this->assertArrayHasKey('spending_trend', $json['data']['aggregates']);
        $this->assertArrayHasKey('latest_spk_history', $json['data']['aggregates']);
        $this->assertArrayHasKey('patient_fluctuation', $json['data']['aggregates']);
        $this->assertArrayNotHasKey('current_menu_cycle', $json['data']['aggregates']);
    }

    public function testDashboardReturnsRoleScopedDapurAggregate(): void
    {
        $token = $this->login('admin');
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('api/v1/dashboard');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('admin', $json['data']['role']);
        $this->assertArrayHasKey('current_menu_cycle', $json['data']['aggregates']);
        $this->assertArrayHasKey('latest_spk_history', $json['data']['aggregates']);
        $this->assertArrayHasKey('stock_summary', $json['data']['aggregates']);
        $this->assertArrayHasKey('dry_stock_status', $json['data']['aggregates']);
        $this->assertArrayHasKey('spending_trend', $json['data']['aggregates']);
        $this->assertArrayHasKey('patient_fluctuation', $json['data']['aggregates']);

        $today = date('Y-m-d');
        $calendarResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/menu-calendar?date=' . $today);
        $calendarResult->assertStatus(200);
        $calendarJson = json_decode($calendarResult->getJSON(), true);

        $this->assertSame($today, $calendarJson['data']['date'] ?? null);
        $this->assertSame(
            [
                'date' => $calendarJson['data']['date'] ?? null,
                'menu_id' => $calendarJson['data']['menu_id'] ?? null,
                'menu_name' => $calendarJson['data']['menu_name'] ?? null,
            ],
            $json['data']['aggregates']['current_menu_cycle']
        );
    }

    public function testDashboardReturns403ForInactiveAccount(): void
    {
        $token = $this->login('gudang');
        $userProvider = new AppUserProvider();
        $gudang = $userProvider->findByUsername('gudang');
        $this->assertNotNull($gudang);

        $userProvider->update($gudang->id, [
            'is_active' => false,
            'active' => false,
        ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('api/v1/dashboard');

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Account is inactive or has been deleted.']);
    }
}
