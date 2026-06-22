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
}
