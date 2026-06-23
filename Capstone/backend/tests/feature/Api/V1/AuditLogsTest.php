<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AuditActionType;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use App\Services\AuditService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class AuditLogsTest extends CIUnitTestCase
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
    }

    private function seedRoles(): void
    {
        $roleModel = new RoleModel();
        $roleModel->insertBatch([
            ['name' => 'admin'],
            ['name' => 'dapur'],
            ['name' => 'gudang'],
        ]);
    }

    private function seedUsers(): void
    {
        $roleModel    = new RoleModel();
        $userProvider = new AppUserProvider();

        foreach ([
            ['role' => 'admin', 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com'],
            ['role' => 'gudang', 'name' => 'Gudang User', 'username' => 'gudang', 'email' => 'gudang@example.com'],
        ] as $userData) {
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

    public function testAuditLogsEndpointIsAdminOnly(): void
    {
        $gudangToken = $this->login('gudang');

        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/audit-logs')
            ->assertStatus(403);
    }

    public function testAuditLogsEndpointReturnsCollectionForAdmin(): void
    {
        $adminToken = $this->login('admin');
        $auditService = new AuditService();
        $auditService->log(1, AuditActionType::Post, 'stock_opnames', 7, 'Audit entry created for test');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?q=Audit&page=1&perPage=10&sortBy=created_at&sortDir=DESC');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame(true, $json['meta']['paginated']);
        $this->assertNotEmpty($json['data']);
        $this->assertSame('Audit entry created for test', $json['data'][0]['detail']);
        $this->assertSame('Stok', $json['data'][0]['module']);
    }
    protected function login(string $username): string
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => $username,
                'password' => 'password123',
            ]);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);

        return $json['access_token'];
    }

    public function testAuditLogResponseIncludesRole(): void
    {
        $adminToken = $this->login('admin');
        $auditService = new AuditService();

        // Entry with user_id=1 (admin) — role should be 'admin'
        $auditService->log(
            1,
            AuditActionType::Create,
            'test_table',
            1,
            'Role test entry with user'
        );

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?paginate=false');
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $entry = null;
        foreach ($json['data'] as $item) {
            if ($item['detail'] === 'Role test entry with user') {
                $entry = $item;
                break;
            }
        }
        $this->assertNotNull($entry, 'Should find audit entry with role test message');
        $this->assertArrayHasKey('actorInfo', $entry);
        $this->assertArrayHasKey('role', $entry['actorInfo']);
        $this->assertSame('admin', $entry['actorInfo']['role']);

        // Entry with null user_id — role should be null
        $this->db->table('audit_logs')->insert([
            'user_id'     => null,
            'action_type' => 'create',
            'table_name'  => 'test_table',
            'record_id'   => 2,
            'message'     => 'Role test entry null user',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        $result2 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?paginate=false');
        $result2->assertStatus(200);
        $json2 = json_decode($result2->getJSON(), true);

        $nullEntry = null;
        foreach ($json2['data'] as $item) {
            if ($item['detail'] === 'Role test entry null user') {
                $nullEntry = $item;
                break;
            }
        }
        $this->assertNotNull($nullEntry, 'Should find audit entry with null user');
        $this->assertNull($nullEntry['actorInfo']['role']);
    }

    public function testAuditLogFiltersByDateRange(): void
    {
        $adminToken = $this->login('admin');

        // Insert old entries (before 2025-01-01)
        $this->db->table('audit_logs')->insert([
            'user_id'     => 1,
            'action_type' => 'create',
            'table_name'  => 'test_table',
            'record_id'   => 1,
            'message'     => 'Old entry 1',
            'created_at'  => '2024-06-01 10:00:00',
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id'     => 1,
            'action_type' => 'update',
            'table_name'  => 'test_table',
            'record_id'   => 2,
            'message'     => 'Old entry 2',
            'created_at'  => '2024-12-31 23:59:59',
        ]);

        // Insert new entries (after 2025-03-01)
        $this->db->table('audit_logs')->insert([
            'user_id'     => 1,
            'action_type' => 'delete',
            'table_name'  => 'test_table',
            'record_id'   => 3,
            'message'     => 'New entry 1',
            'created_at'  => '2025-03-15 08:00:00',
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id'     => 1,
            'action_type' => 'create',
            'table_name'  => 'test_table',
            'record_id'   => 4,
            'message'     => 'New entry 2',
            'created_at'  => '2025-06-01 12:00:00',
        ]);

        // Test start_date filter — should return only new entries
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?start_date=2025-01-01&paginate=false');
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $messages = array_column($json['data'], 'detail');
        $this->assertContains('New entry 1', $messages);
        $this->assertContains('New entry 2', $messages);
        $this->assertNotContains('Old entry 1', $messages);
        $this->assertNotContains('Old entry 2', $messages);

        // Test end_date filter — should return only old entries
        $result2 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?end_date=2024-12-31&paginate=false');
        $result2->assertStatus(200);
        $json2 = json_decode($result2->getJSON(), true);
        $messages2 = array_column($json2['data'], 'detail');
        $this->assertContains('Old entry 1', $messages2);
        $this->assertContains('Old entry 2', $messages2);
        $this->assertNotContains('New entry 1', $messages2);
        $this->assertNotContains('New entry 2', $messages2);

        // Test combined range — should return only entries in range
        $result3 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?start_date=2024-06-01&end_date=2024-12-31&paginate=false');
        $result3->assertStatus(200);
        $json3 = json_decode($result3->getJSON(), true);
        $messages3 = array_column($json3['data'], 'detail');
        $this->assertContains('Old entry 1', $messages3);
        $this->assertContains('Old entry 2', $messages3);
        $this->assertNotContains('New entry 1', $messages3);
        $this->assertNotContains('New entry 2', $messages3);
    }

    public function testAuditLogFiltersByUserId(): void
    {
        $adminToken = $this->login('admin');

        $adminUser = $this->db->table('users')->where('username', 'admin')->get()->getRowArray();
        $this->assertNotNull($adminUser);

        $gudangUser = $this->db->table('users')->where('username', 'gudang')->get()->getRowArray();
        $this->assertNotNull($gudangUser);

        // Create entries for admin and gudang users via DB
        $this->db->table('audit_logs')->insert([
            'user_id'     => $adminUser['id'],
            'action_type' => 'create',
            'table_name'  => 'test_table',
            'record_id'   => 1,
            'message'     => 'Admin entry',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id'     => $gudangUser['id'],
            'action_type' => 'update',
            'table_name'  => 'test_table',
            'record_id'   => 2,
            'message'     => 'Gudang entry',
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        // Filter by admin user_id — should only return admin's entries
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?user_id=' . $adminUser['id'] . '&paginate=false');
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $messages = array_column($json['data'], 'detail');
        $this->assertContains('Admin entry', $messages);
        $this->assertNotContains('Gudang entry', $messages);

        // Without filter, should see all entries
        $result2 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?paginate=false');
        $result2->assertStatus(200);
        $json2 = json_decode($result2->getJSON(), true);

        $messages2 = array_column($json2['data'], 'detail');
        $this->assertContains('Admin entry', $messages2);
        $this->assertContains('Gudang entry', $messages2);
    }

    public function testAuditLogSummaryEndpoint(): void
    {
        $adminToken = $this->login('admin');

        $adminUser = $this->db->table('users')->where('username', 'admin')->get()->getRowArray();
        $this->assertNotNull($adminUser);
        $gudangUser = $this->db->table('users')->where('username', 'gudang')->get()->getRowArray();
        $this->assertNotNull($gudangUser);

        // Insert several entries with different action_types and table_names
        $this->db->table('audit_logs')->insert([
            'user_id' => $adminUser['id'], 'action_type' => 'create', 'table_name' => 'dishes', 'record_id' => 1,
            'message' => 'Summary test 1', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id' => $adminUser['id'], 'action_type' => 'create', 'table_name' => 'dishes', 'record_id' => 2,
            'message' => 'Summary test 2', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id' => $adminUser['id'], 'action_type' => 'update', 'table_name' => 'dishes', 'record_id' => 3,
            'message' => 'Summary test 3', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id' => $adminUser['id'], 'action_type' => 'delete', 'table_name' => 'users', 'record_id' => 4,
            'message' => 'Summary test 4', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id' => $gudangUser['id'], 'action_type' => 'post', 'table_name' => 'stock_opnames', 'record_id' => 5,
            'message' => 'Summary test 5', 'created_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs/summary');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);

        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('total', $json['data']);
        $this->assertArrayHasKey('byRole', $json['data']);
        $this->assertArrayHasKey('byActionType', $json['data']);
        $this->assertArrayHasKey('byModule', $json['data']);

        // Total includes login entry + 5 inserted entries = 6
        $this->assertSame(6, $json['data']['total']);

        // byRole: admin=5 (login + 4 admin entries), gudang=1
        $this->assertArrayHasKey('admin', $json['data']['byRole']);
        $this->assertArrayHasKey('gudang', $json['data']['byRole']);
        $this->assertSame(5, $json['data']['byRole']['admin']);
        $this->assertSame(1, $json['data']['byRole']['gudang']);

        // byActionType: login=1, create=2, update=1, delete=1, post=1
        $this->assertArrayHasKey('login', $json['data']['byActionType']);
        $this->assertArrayHasKey('create', $json['data']['byActionType']);
        $this->assertArrayHasKey('update', $json['data']['byActionType']);
        $this->assertArrayHasKey('delete', $json['data']['byActionType']);
        $this->assertArrayHasKey('post', $json['data']['byActionType']);
        $this->assertSame(1, $json['data']['byActionType']['login']);
        $this->assertSame(2, $json['data']['byActionType']['create']);
        $this->assertSame(1, $json['data']['byActionType']['update']);
        $this->assertSame(1, $json['data']['byActionType']['delete']);
        $this->assertSame(1, $json['data']['byActionType']['post']);

        // byModule: Menu=3 (3 dishes), Pengguna=2 (login users + delete users), Stok=1 (stock_opnames)
        $this->assertArrayHasKey('Menu', $json['data']['byModule']);
        $this->assertArrayHasKey('Pengguna', $json['data']['byModule']);
        $this->assertArrayHasKey('Stok', $json['data']['byModule']);
        $this->assertSame(3, $json['data']['byModule']['Menu']);
        $this->assertSame(2, $json['data']['byModule']['Pengguna']);
        $this->assertSame(1, $json['data']['byModule']['Stok']);
    }

    public function testLoginCreatesAuditLog(): void
    {
        $adminToken = $this->login('admin');

        $user = $this->db->table('users')->where('username', 'admin')->get()->getRowArray();
        $this->assertNotNull($user);

        // Check audit_logs for the login entry created by login()
        $logEntry = $this->db->table('audit_logs')
            ->where('user_id', $user['id'])
            ->where('action_type', 'login')
            ->get()
            ->getRowArray();

        $this->assertNotNull($logEntry, 'Login should create an audit log entry');
        $this->assertSame('login', $logEntry['action_type']);
        $this->assertSame('users', $logEntry['table_name']);
        $this->assertEquals($user['id'], $logEntry['record_id']);
    }

    public function testAuditLogEndpointPaginationWithFilters(): void
    {
        $adminToken = $this->login('admin');

        // Insert entries with different action_types and table_names
        $this->db->table('audit_logs')->insert([
            'user_id' => 1, 'action_type' => 'create', 'table_name' => 'dishes', 'record_id' => 1,
            'message' => 'Paginated entry 1', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id' => 1, 'action_type' => 'update', 'table_name' => 'dishes', 'record_id' => 2,
            'message' => 'Paginated entry 2', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('audit_logs')->insert([
            'user_id' => 1, 'action_type' => 'delete', 'table_name' => 'stock_opnames', 'record_id' => 3,
            'message' => 'Paginated entry 3', 'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Test pagination with action_type filter
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?action_type=create&page=1&perPage=2');
        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertSame(1, $json['meta']['total']); // only 1 'create' entry inserted

        // Test pagination with table_name filter — 2 dish entries
        $result2 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?table_name=dishes&page=1&perPage=2');
        $result2->assertStatus(200);
        $json2 = json_decode($result2->getJSON(), true);
        $this->assertArrayHasKey('data', $json2);
        $this->assertArrayHasKey('meta', $json2);
        $this->assertSame(2, $json2['meta']['total']); // 2 dish entries

        // Test unknown query parameter returns 400
        $result3 = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/audit-logs?invalid_param=1');
        $result3->assertStatus(400);
        $json3 = json_decode($result3->getJSON(), true);
        $this->assertArrayHasKey('message', $json3);
        $this->assertSame('Validation failed.', $json3['message']);
        $this->assertArrayHasKey('errors', $json3);
        $this->assertArrayHasKey('query', $json3['errors']);
    }
}
