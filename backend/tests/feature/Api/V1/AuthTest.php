<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\GroupModel;
use App\Models\AppUserProvider;
use App\Models\RoleModel;

class AuthTest extends CIUnitTestCase
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

        $adminRole = $roleModel->findByName('admin');
        $gudangRole = $roleModel->findByName('gudang');

        $activeUser = new User([
            'role_id'   => $adminRole['id'],
            'name'      => 'Active User',
            'username'  => 'activeuser',
            'email'     => 'active@example.com',
            'is_active' => true,
            'active'    => true,
        ]);
        $activeUser->fill(['password' => 'password123']);
        $userProvider->insert($activeUser, true);

        $inactiveUser = new User([
            'role_id'   => $gudangRole['id'],
            'name'      => 'Inactive User',
            'username'  => 'inactiveuser',
            'email'     => 'inactive@example.com',
            'is_active' => false,
            'active'    => false,
        ]);
        $inactiveUser->fill(['password' => 'password123']);
        $userProvider->insert($inactiveUser, true);
    }

    public function testLoginWithValidCredentials(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Login successful.']);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('access_token', $json);
        $this->assertArrayHasKey('token_type', $json);
        $this->assertArrayHasKey('user', $json);
        $this->assertSame('Bearer', $json['token_type']);
        $this->assertSame('activeuser', $json['user']['username']);
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'wrongpassword',
            ]);

        $result->assertStatus(401);
        $result->assertJSONFragment(['message' => 'Invalid credentials.']);
    }

    public function testLoginWithNonexistentUsername(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'nonexistent',
                'password' => 'password123',
            ]);

        $result->assertStatus(401);
        $result->assertJSONFragment(['message' => 'Invalid credentials.']);
    }

    public function testLoginWithInactiveUser(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'inactiveuser',
                'password' => 'password123',
            ]);

        $result->assertStatus(401);
        $result->assertJSONFragment(['message' => 'Account is inactive or has been deleted.']);
    }

    public function testLoginWithMissingFields(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('password', $json['errors']);
    }

    public function testMeWithValidToken(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/auth/me');

        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertSame('activeuser', $json['data']['username']);
        $this->assertSame('Active User', $json['data']['name']);
        $this->assertArrayHasKey('role', $json['data']);
    }

    public function testMeWithoutToken(): void
    {
        $result = $this->get('api/v1/auth/me');

        $result->assertStatus(401);
    }

    public function testMeWithInvalidToken(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer invalid-token-here'])
            ->get('api/v1/auth/me');

        $result->assertStatus(401);
    }

    public function testLogoutWithValidToken(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->post('api/v1/auth/logout');

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Logout successful.']);

        $meResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/auth/me');

        $meResult->assertStatus(401);
    }

    public function testDeletedUserTokenIsRevoked(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $userProvider = new AppUserProvider();
        $user = $userProvider->findByUsername('activeuser');

        $this->assertNotNull($user);

        $userProvider->delete($user->id);

        $meResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/auth/me');

        $meResult->assertStatus(401);
    }

    public function testLogoutWithoutToken(): void
    {
        $result = $this->post('api/v1/auth/logout');

        $result->assertStatus(401);
    }

    public function testRoleFilterWithAllowedRole(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/roles');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertCount(3, $json['data']);
    }

    public function testRoleFilterWithForbiddenRole(): void
    {
        $roleModel = new RoleModel();
        $userProvider = new AppUserProvider();
        $gudangRole = $roleModel->findByName('gudang');

        $gudangUser = new User([
            'role_id'   => $gudangRole['id'],
            'name'      => 'Gudang Active',
            'username'  => 'gudangactive',
            'email'     => 'gudangactive@example.com',
            'is_active' => true,
            'active'    => true,
        ]);
        $gudangUser->fill(['password' => 'password123']);
        $userProvider->insert($gudangUser, true);

        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'gudangactive',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/roles');

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Insufficient permissions.']);
    }

    public function testShieldSupportTablesExistAndGroupLookupWorks(): void
    {
        $authConfig = config('Auth');
        $db = \Config\Database::connect();
        $userProvider = new AppUserProvider();
        $groupModel = model(GroupModel::class);

        $requiredTables = [
            $authConfig->tables['remember_tokens'],
            $authConfig->tables['groups_users'],
            $authConfig->tables['permissions_users'],
        ];

        foreach ($requiredTables as $tableName) {
            $this->assertTrue(
                $db->tableExists($tableName),
                "Shield support table '{$tableName}' must exist in the schema"
            );
        }

        $existingTables = [
            $authConfig->tables['identities'],
            $authConfig->tables['logins'],
            $authConfig->tables['token_logins'],
        ];

        foreach ($existingTables as $tableName) {
            $this->assertTrue(
                $db->tableExists($tableName),
                "Shield core table '{$tableName}' must exist in the schema"
            );
        }

        $user = $userProvider->findByUsername('activeuser');

        $this->assertNotNull($user);
        $this->assertSame([], $groupModel->getForUser($user));
        $this->assertSame([], $user->getGroups());
    }

    public function testSelfServicePasswordChangeSuccess(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $oldToken = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $oldToken])
            ->withBodyFormat('json')
            ->patch('api/v1/auth/password', [
                'current_password' => 'password123',
                'password'         => 'newpassword123',
            ]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Password changed successfully. All access tokens have been revoked.']);

        $meResult = $this->withHeaders(['Authorization' => 'Bearer ' . $oldToken])
            ->get('api/v1/auth/me');

        $meResult->assertStatus(401);

        $newLoginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'newpassword123',
            ]);

        $newLoginResult->assertStatus(200);
        $newLoginJson = json_decode($newLoginResult->getJSON(), true);
        $this->assertArrayHasKey('access_token', $newLoginJson);

        $oldPasswordResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $oldPasswordResult->assertStatus(401);
    }

    public function testSelfServicePasswordChangeRevokesAllExistingTokens(): void
    {
        $firstLoginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $firstLoginJson = json_decode($firstLoginResult->getJSON(), true);
        $firstToken = $firstLoginJson['access_token'];

        $secondLoginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $secondLoginJson = json_decode($secondLoginResult->getJSON(), true);
        $secondToken = $secondLoginJson['access_token'];

        $changeResult = $this->withHeaders(['Authorization' => 'Bearer ' . $secondToken])
            ->withBodyFormat('json')
            ->patch('api/v1/auth/password', [
                'current_password' => 'password123',
                'password'         => 'anothernewpassword',
            ]);

        $changeResult->assertStatus(200);

        $firstTokenResult = $this->withHeaders(['Authorization' => 'Bearer ' . $firstToken])
            ->get('api/v1/auth/me');
        $firstTokenResult->assertStatus(401);

        $secondTokenResult = $this->withHeaders(['Authorization' => 'Bearer ' . $secondToken])
            ->get('api/v1/auth/me');
        $secondTokenResult->assertStatus(401);
    }

    public function testSelfServicePasswordChangeWrongCurrentPassword(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->patch('api/v1/auth/password', [
                'current_password' => 'wrongpassword',
                'password'         => 'newpassword123',
            ]);

        $result->assertStatus(401);
        $result->assertJSONFragment(['message' => 'Current password is incorrect.']);

        $meResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/auth/me');

        $meResult->assertStatus(200);
    }

    public function testSelfServicePasswordChangeMissingCurrentPassword(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->patch('api/v1/auth/password', [
                'password' => 'newpassword123',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('current_password', $json['errors']);
    }

    public function testSelfServicePasswordChangeMissingNewPassword(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->patch('api/v1/auth/password', [
                'current_password' => 'password123',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('password', $json['errors']);
    }

    public function testSelfServicePasswordChangeNewPasswordTooShort(): void
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => 'activeuser',
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        $token = $loginJson['access_token'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->patch('api/v1/auth/password', [
                'current_password' => 'password123',
                'password'         => 'short',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('password', $json['errors']);
    }

    public function testSelfServicePasswordChangeWithoutAuth(): void
    {
        $result = $this->withBodyFormat('json')
            ->patch('api/v1/auth/password', [
                'current_password' => 'password123',
                'password'         => 'newpassword123',
            ]);

        $result->assertStatus(401);
    }
}
