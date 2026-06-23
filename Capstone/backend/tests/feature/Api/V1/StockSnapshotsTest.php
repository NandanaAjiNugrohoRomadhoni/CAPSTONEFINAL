<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\RoleModel;
use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\ItemUnitModel;
use App\Models\ItemModel;
use CodeIgniter\Shield\Entities\User;
use Config\Database;

class StockSnapshotsTest extends CIUnitTestCase
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
        $this->seedItemCategories();
        $this->seedItemUnits();
        $this->seedItems();
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

        $users = [
            ['role' => 'admin', 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com'],
            ['role' => 'gudang', 'name' => 'Gudang User', 'username' => 'gudang', 'email' => 'gudang@example.com'],
            ['role' => 'dapur', 'name' => 'Dapur User', 'username' => 'dapur', 'email' => 'dapur@example.com'],
        ];

        foreach ($users as $userData) {
            $role = $roleModel->findByName($userData['role']);

            $user = new User([
                'role_id'   => $role['id'],
                'name'      => $userData['name'],
                'username'  => $userData['username'],
                'email'     => $userData['email'],
                'is_active' => true,
                'active'    => true,
            ]);
            $user->fill(['password' => 'password123']);
            $userProvider->insert($user, true);
        }
    }

    protected function seedItemCategories(): void
    {
        $categoryModel = new ItemCategoryModel();
        $categoryModel->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);
    }

    protected function seedItemUnits(): void
    {
        $itemUnitModel = new ItemUnitModel();
        $itemUnitModel->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
            ['name' => 'ml'],
            ['name' => 'liter'],
            ['name' => 'butir'],
            ['name' => 'pack'],
        ]);
    }

    protected function seedItems(): void
    {
        $categoryModel = new ItemCategoryModel();
        $itemUnitModel = new ItemUnitModel();
        $db            = Database::connect();

        $basah  = $categoryModel->where('name', 'BASAH')->first();
        $kering = $categoryModel->where('name', 'KERING')->first();

        $gramId = $itemUnitModel->getIdByName('gram');
        $kgId   = $itemUnitModel->getIdByName('kg');

        $db->table('items')->insertBatch([
            [
                'item_category_id'  => $kering['id'],
                'name'              => 'Beras',
                'unit_base'         => 'gram',
                'unit_convert'      => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'   => 1000,
                'is_active'         => true,
                'qty'               => 5000,
            ],
            [
                'item_category_id'  => $basah['id'],
                'name'              => 'Ayam',
                'unit_base'         => 'gram',
                'unit_convert'      => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'   => 1000,
                'is_active'         => true,
                'qty'               => 3000,
            ],
        ]);
    }

    protected function getToken(string $username): string
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => $username,
                'password' => 'password123',
            ]);

        $json = json_decode($result->getJSON(), true);
        return $json['access_token'];
    }

    public function testTakeCreatesSnapshotForCurrentMonth(): void
    {
        $token = $this->getToken('admin');
        
        // Clean any existing snapshots for current month
        $db = Database::connect();
        $db->table('monthly_stock_snapshots')->where('period_month', date('Y-m') . '-01')->delete();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots');

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertGreaterThan(0, $json['count']);

        // Check DB
        $count = $db->table('monthly_stock_snapshots')->where('period_month', date('Y-m') . '-01')->countAllResults();
        $this->assertSame($json['count'], $count);
    }

    public function testTakeWithSpecificMonth(): void
    {
        $token = $this->getToken('admin');
        $month = '2026-06';
        $periodMonth = '2026-06-01';

        $db = Database::connect();
        $db->table('monthly_stock_snapshots')->where('period_month', $periodMonth)->delete();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => $month]);

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertGreaterThan(0, $json['count']);

        $count = $db->table('monthly_stock_snapshots')->where('period_month', $periodMonth)->countAllResults();
        $this->assertSame($json['count'], $count);
    }

    public function testTakeReturns200WhenSnapshotExists(): void
    {
        $token = $this->getToken('admin');
        $month = '2026-06';
        $periodMonth = '2026-06-01';

        $db = Database::connect();
        $db->table('monthly_stock_snapshots')->where('period_month', $periodMonth)->delete();

        // Take first time
        $result1 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => $month]);
        $result1->assertStatus(201);

        // Take second time (without force)
        $result2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => $month]);
        
        $result2->assertStatus(200);
        $json = json_decode($result2->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertSame(0, $json['count']);
        $this->assertStringContainsString('already exists', $json['message']);
    }

    public function testTakeWithForceRetakes(): void
    {
        $token = $this->getToken('admin');
        $month = '2026-06';
        $periodMonth = '2026-06-01';

        $db = Database::connect();
        $db->table('monthly_stock_snapshots')->where('period_month', $periodMonth)->delete();

        // Take first time
        $result1 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => $month]);
        $result1->assertStatus(201);

        // Modify a row in the snapshot to check if it gets replaced
        $db->table('monthly_stock_snapshots')
            ->where('period_month', $periodMonth)
            ->update(['opening_qty' => 999.9]);

        // Take second time with force = true
        $result2 = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => $month, 'force' => true]);

        $result2->assertStatus(201);
        $json = json_decode($result2->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertGreaterThan(0, $json['count']);

        // Verify the modified opening_qty is gone (replaced with original item qty, which is 5000/3000)
        $records = $db->table('monthly_stock_snapshots')
            ->where('period_month', $periodMonth)
            ->get()
            ->getResultArray();

        foreach ($records as $row) {
            $this->assertNotEquals(999.9, (float) $row['opening_qty']);
        }
    }

    public function testTakeInvalidMonthFormat(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => 'invalid-month']);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('month', $json['errors']);
    }

    public function testTakeInvalidMonthValue(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => '2026-13']);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('month', $json['errors']);
    }

    public function testTakeRequiresAuth(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/stock-snapshots');

        $result->assertStatus(401);
    }

    public function testTakeRequiresCorrectRole(): void
    {
        $token = $this->getToken('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots');

        $result->assertStatus(403);
    }

    public function testIndexReturnsPaginatedList(): void
    {
        $token = $this->getToken('admin');
        
        // Take a snapshot first
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => '2026-06']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-snapshots');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertNotEmpty($json['data']);
        
        // Check fields of snapshot row
        $row = $json['data'][0];
        $this->assertArrayHasKey('id', $row);
        $this->assertArrayHasKey('period_month', $row);
        $this->assertArrayHasKey('item_id', $row);
        $this->assertArrayHasKey('item_name', $row);
        $this->assertArrayHasKey('category_name', $row);
        $this->assertArrayHasKey('opening_qty', $row);
    }

    public function testIndexFiltersByPeriodMonth(): void
    {
        $token = $this->getToken('admin');
        
        // Take snapshots for two different months
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => '2026-05']);
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => '2026-06']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-snapshots?period_month=2026-05-01');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        foreach ($json['data'] as $row) {
            $this->assertSame('2026-05-01', $row['period_month']);
        }
    }

    public function testIndexFiltersByCategory(): void
    {
        $token = $this->getToken('admin');
        
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => '2026-06']);

        $db = Database::connect();
        $basah = $db->table('item_categories')->where('name', 'BASAH')->get()->getRowArray();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-snapshots?item_category_id=' . $basah['id']);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        foreach ($json['data'] as $row) {
            $this->assertSame('BASAH', $row['category_name']);
        }
    }

    public function testCurrentReturnsStatus(): void
    {
        $token = $this->getToken('dapur'); // Shared read allows dapur
        
        // Take snapshot for current month
        $adminToken = $this->getToken('admin');
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-snapshots', ['month' => date('Y-m')]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-snapshots/current');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        
        $this->assertSame(date('Y-m'), $json['month']);
        $this->assertTrue($json['has_snapshot']);
        $this->assertNotNull($json['item_count']);
        $this->assertGreaterThan(0, $json['item_count']);
    }

    public function testCurrentWhenNoSnapshot(): void
    {
        $token = $this->getToken('dapur');
        
        // Clean current month snapshot
        $db = Database::connect();
        $db->table('monthly_stock_snapshots')->where('period_month', date('Y-m') . '-01')->delete();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-snapshots/current');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame(date('Y-m'), $json['month']);
        $this->assertFalse($json['has_snapshot']);
        $this->assertNull($json['item_count']);
    }
}
