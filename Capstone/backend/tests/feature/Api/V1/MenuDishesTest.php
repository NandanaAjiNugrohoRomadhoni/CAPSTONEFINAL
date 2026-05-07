<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class MenuDishesTest extends CIUnitTestCase
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
            ['name' => 'Sup Sayur'],
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
        $this->assertIsArray($json);
        $this->assertArrayHasKey('access_token', $json);

        return $json['access_token'];
    }

    protected function assignSlot(string $token, int $menuId, int $mealTimeId, int $dishId): array
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/menu-dishes', [
                'menu_id'      => $menuId,
                'meal_time_id' => $mealTimeId,
                'dish_id'      => $dishId,
            ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('id', $json['data']);

        return $json['data'];
    }

    public function testSlotAssignmentHappyPathForPagiSiangSore(): void
    {
        $writeToken = $this->login('dapur');
        $readToken = $this->login('gudang');

        $this->assignSlot($writeToken, 1, 1, 1);
        $this->assignSlot($writeToken, 1, 2, 2);
        $this->assignSlot($writeToken, 1, 3, 3);

        $listResult = $this->withHeaders(['Authorization' => 'Bearer ' . $readToken])
            ->get('api/v1/menu-dishes');

        $listResult->assertStatus(200);

        $json = json_decode($listResult->getJSON(), true);
        $this->assertCount(3, $json['data']);
        $this->assertSame('Pagi', $json['data'][0]['meal_time']['name']);
        $this->assertSame('Siang', $json['data'][1]['meal_time']['name']);
        $this->assertSame('Sore', $json['data'][2]['meal_time']['name']);
    }

    public function testDuplicateSlotRejectedForSameMenuAndMealTime(): void
    {
        $token = $this->login('admin');

        $this->assignSlot($token, 2, 1, 1);

        $duplicateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/menu-dishes', [
                'menu_id'      => 2,
                'meal_time_id' => 1,
                'dish_id'      => 2,
            ]);

        $duplicateResult->assertStatus(400);
        $json = json_decode($duplicateResult->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertArrayHasKey('menu_id,meal_time_id', $json['errors']);
    }

    public function testUpdateExistingSlotSuccess(): void
    {
        $token = $this->login('admin');
        $slot = $this->assignSlot($token, 3, 1, 1);

        $updateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/menu-dishes/' . $slot['id'], [
                'dish_id' => 2,
            ]);

        $updateResult->assertStatus(200);
        $json = json_decode($updateResult->getJSON(), true);
        $this->assertSame('Menu slot updated successfully.', $json['message']);
        $this->assertSame($slot['id'], $json['data']['id']);
        $this->assertSame(2, $json['data']['dish_id']);
    }

    public function testUpdateNonExistentSlotReturns404(): void
    {
        $token = $this->login('admin');

        $updateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/menu-dishes/9999', [
                'dish_id' => 2,
            ]);

        $updateResult->assertStatus(404);
        $json = json_decode($updateResult->getJSON(), true);
        $this->assertSame('Menu slot not found.', $json['message']);
    }

    public function testUpdateCollisionReturnsDuplicateKeyError(): void
    {
        $token = $this->login('admin');
        $slot = $this->assignSlot($token, 4, 1, 1);
        $this->assignSlot($token, 4, 2, 2);

        $updateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/menu-dishes/' . $slot['id'], [
                'meal_time_id' => 2,
            ]);

        $updateResult->assertStatus(400);
        $json = json_decode($updateResult->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertArrayHasKey('menu_id,meal_time_id', $json['errors']);
        $this->assertSame(
            'The menu_id and meal_time_id combination has already been taken.',
            $json['errors']['menu_id,meal_time_id'],
        );
    }

    public function testDeleteExistingSlotReturns200(): void
    {
        $token = $this->login('admin');
        $slot = $this->assignSlot($token, 5, 1, 1);

        $deleteResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/menu-dishes/' . $slot['id']);

        $deleteResult->assertStatus(200);
        $json = json_decode($deleteResult->getJSON(), true);
        $this->assertSame('Menu slot deleted successfully.', $json['message']);
    }

    public function testDeleteNonExistentSlotReturns404(): void
    {
        $token = $this->login('admin');

        $deleteResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/menu-dishes/9999');

        $deleteResult->assertStatus(404);
        $json = json_decode($deleteResult->getJSON(), true);
        $this->assertSame('Menu slot not found.', $json['message']);
    }

    public function testGudangForbiddenForUpdateAndDelete(): void
    {
        $token = $this->login('gudang');
        $slot = $this->assignSlot($this->login('admin'), 6, 1, 1);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/menu-dishes/' . $slot['id'], [
                'dish_id' => 2,
            ])
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/menu-dishes/' . $slot['id'])
            ->assertStatus(403);
    }
}
