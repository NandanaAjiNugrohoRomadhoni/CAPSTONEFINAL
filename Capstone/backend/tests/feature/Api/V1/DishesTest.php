<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class DishesTest extends CIUnitTestCase
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
        $this->seedMenus();
        $this->seedMealTimes();
        $this->seedItems();
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
        $roleModel   = new RoleModel();
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

    protected function seedDishes(): void
    {
        $db = Database::connect();

        $db->table('dishes')->insertBatch([
            ['name' => 'Bubur Ayam'],
            ['name' => 'Nasi Tim'],
            ['name' => 'Sup Sayur'],
        ]);
    }

    protected function seedItemCategories(): void
    {
        $categoryModel = new ItemCategoryModel();
        $categoryModel->insert(['name' => 'KERING']);
    }

    protected function seedItemUnits(): void
    {
        $itemUnitModel = new ItemUnitModel();
        $itemUnitModel->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
        ]);
    }

    protected function seedMenus(): void
    {
        $db = Database::connect();

        $rows = [];
        for ($id = 1; $id <= 3; $id++) {
            $rows[] = ['id' => $id, 'name' => 'Paket ' . $id];
        }

        $db->table('menus')->insertBatch($rows);
    }

    protected function seedMealTimes(): void
    {
        $db = Database::connect();

        $db->table('meal_times')->insertBatch([
            ['id' => 1, 'name' => 'Pagi'],
        ]);
    }

    protected function seedItems(): void
    {
        $db            = Database::connect();
        $categoryModel = new ItemCategoryModel();
        $itemUnitModel = new ItemUnitModel();

        $category = $categoryModel->where('name', 'KERING')->first();
        $gramId   = $itemUnitModel->getIdByName('gram');
        $kgId     = $itemUnitModel->getIdByName('kg');

        $db->table('items')->insert([
            'item_category_id'     => $category['id'],
            'name'                 => 'Beras',
            'unit_base'            => 'gram',
            'unit_convert'         => 'kg',
            'item_unit_base_id'    => $gramId,
            'item_unit_convert_id' => $kgId,
            'conversion_base'      => 1000,
            'is_active'            => true,
            'qty'                  => 100,
        ]);
    }

    protected function seedMenuDishesForDish(int $dishId): void
    {
        $db = Database::connect();

        $db->table('menu_dishes')->insertBatch([
            [
                'menu_id'      => 1,
                'meal_time_id' => 1,
                'dish_id'      => $dishId,
            ],
            [
                'menu_id'      => 2,
                'meal_time_id' => 1,
                'dish_id'      => $dishId,
            ],
        ]);
    }

    protected function seedDishCompositionForDish(int $dishId): void
    {
        $db = Database::connect();

        $db->table('dish_compositions')->insert([
            'dish_id'         => $dishId,
            'item_id'         => 1,
            'qty_per_patient' => '100.00',
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

    public function testListDishesWithoutAuth(): void
    {
        $this->get('api/v1/dishes')->assertStatus(401);
    }

    public function testListDishesAsGudangReturnsEnvelope(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/dishes?q=na&sortBy=name&sortDir=DESC&page=1&perPage=2');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame(2, $json['meta']['perPage']);
        $this->assertNotEmpty($json['data']);
        $this->assertContains('Nasi Tim', array_column($json['data'], 'name'));
        $this->assertIsBool($json['data'][0]['is_active']);
    }

    public function testDapurCanCreateDish(): void
    {
        $token = $this->login('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dishes', ['name' => 'Bubur Kacang Hijau']);

        $result->assertStatus(201);
        $result->assertJSONFragment(['message' => 'Dish created successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertIsBool($json['data']['is_active']);
        $this->assertTrue($json['data']['is_active']);
    }

    public function testGudangCannotCreateDish(): void
    {
        $token = $this->login('gudang');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dishes', ['name' => 'Bubur Kacang Hijau'])
            ->assertStatus(403);
    }

    public function testCreateDishRejectsDuplicateName(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dishes', ['name' => 'bubur ayam']);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testShowDishAsDapur(): void
    {
        $token = $this->login('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/dishes/1');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Bubur Ayam', $json['data']['name']);
        $this->assertIsBool($json['data']['is_active']);
        $this->assertTrue($json['data']['is_active']);
    }

    public function testUpdateDishAsAdmin(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/dishes/1', ['name' => 'Bubur Ayam Spesial']);

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Dish updated successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertIsBool($json['data']['is_active']);
        $this->assertTrue($json['data']['is_active']);
    }

    public function testGudangCannotUpdateDish(): void
    {
        $token = $this->login('gudang');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/dishes/1', ['name' => 'Bubur Ayam Spesial'])
            ->assertStatus(403);
    }

    public function testUpdateDishRejectsDuplicateName(): void
    {
        $token = $this->login('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/dishes/1', ['name' => 'Sup Sayur']);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertArrayHasKey('name', $json['errors']);
    }

    public function testDeactivateDishRemovesMenuDishesButKeepsCompositions(): void
    {
        $token = $this->login('admin');
        $db    = Database::connect();

        $this->seedMenuDishesForDish(1);
        $this->seedDishCompositionForDish(1);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patch('api/v1/dishes/1/deactivate');

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Dish deactivated successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertFalse($json['data']['is_active']);
        $this->assertSame(0, $db->table('menu_dishes')->where('dish_id', 1)->countAllResults());
        $this->assertSame(1, $db->table('dish_compositions')->where('dish_id', 1)->countAllResults());
    }

    public function testReactivateDishSetsActiveAndDoesNotRestoreMenuDishes(): void
    {
        $token = $this->login('admin');
        $db    = Database::connect();

        $this->seedMenuDishesForDish(1);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patch('api/v1/dishes/1/deactivate');

        $this->assertSame(0, $db->table('menu_dishes')->where('dish_id', 1)->countAllResults());

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patch('api/v1/dishes/1/reactivate');

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Dish reactivated successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['data']['is_active']);
        $this->assertSame(0, $db->table('menu_dishes')->where('dish_id', 1)->countAllResults());
    }

    public function testActiveDishDeleteIsRejected(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/dishes/1');

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertArrayHasKey('dish_id', $json['errors']);
    }

    public function testInactiveDishDeleteSucceedsAndCascadesCompositions(): void
    {
        $token = $this->login('admin');
        $db    = Database::connect();

        $this->seedMenuDishesForDish(1);
        $this->seedDishCompositionForDish(1);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patch('api/v1/dishes/1/deactivate');

        $this->assertSame(0, $db->table('menu_dishes')->where('dish_id', 1)->countAllResults());

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/dishes/1');

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Dish deleted successfully.']);

        $this->assertSame(0, $db->table('dishes')->where('id', 1)->countAllResults());
        $this->assertSame(0, $db->table('dish_compositions')->where('dish_id', 1)->countAllResults());
    }
}
