<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class MenusTest extends CIUnitTestCase
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
        $this->seedMealTimes();
        $this->seedMenus();
        $this->seedDishes();
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
            ['role' => 'dapur', 'name' => 'Dapur User', 'username' => 'dapur', 'email' => 'dapur@example.com'],
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

    protected function seedMealTimes(): void
    {
        $db = Database::connect();

        $db->table('meal_times')->insertBatch([
            ['id' => 1, 'name' => 'Pagi'],
            ['id' => 2, 'name' => 'Siang'],
            ['id' => 3, 'name' => 'Sore'],
        ]);
    }

    protected function seedMenus(): void
    {
        $db = Database::connect();

        $rows = [];
        for ($id = 1; $id <= 11; $id++) {
            $rows[] = ['id' => $id, 'name' => 'Paket ' . $id];
        }

        $db->table('menus')->insertBatch($rows);
    }

    protected function seedDishes(): void
    {
        $db = Database::connect();

        $db->table('dishes')->insertBatch([
            ['name' => 'Bubur Ayam'],
            ['name' => 'Nasi Tim'],
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

    public function testAdminCannotDeleteMenuPackageBecauseEndpointIsUnavailable(): void
    {
        $token = $this->login('admin');

        $db          = Database::connect();
        $initialRows = $db->table('menus')->countAllResults();
        $this->assertSame(11, $initialRows);

        try {
            $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->delete('api/v1/menus/11');
            $this->fail('Expected PageNotFoundException was not thrown.');
        } catch (PageNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertSame(11, $db->table('menus')->countAllResults());
    }

    public function testDapurCannotDeleteMenuPackageBecauseEndpointIsUnavailable(): void
    {
        $token = $this->login('dapur');

        $db          = Database::connect();
        $initialRows = $db->table('menus')->countAllResults();

        try {
            $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->delete('api/v1/menus/5');
            $this->fail('Expected PageNotFoundException was not thrown.');
        } catch (PageNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertSame($initialRows, $db->table('menus')->countAllResults());
    }
}
