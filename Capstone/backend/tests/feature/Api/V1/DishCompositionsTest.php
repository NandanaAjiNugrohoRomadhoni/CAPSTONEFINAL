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

class DishCompositionsTest extends CIUnitTestCase
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

    protected function seedItemCategories(): void
    {
        $categoryModel = new ItemCategoryModel();
        $categoryModel->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
        ]);
    }

    protected function seedItemUnits(): void
    {
        $itemUnitModel = new ItemUnitModel();
        $itemUnitModel->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
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

        $db->table('items')->insertBatch([
            [
                'item_category_id'     => $category['id'],
                'name'                 => 'Beras',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 1000,
            ],
            [
                'item_category_id'     => $category['id'],
                'name'                 => 'Garam',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => false,
                'qty'                  => 500,
            ],
        ]);
    }

    protected function seedDishes(): void
    {
        $db = Database::connect();

        $db->table('dishes')->insertBatch([
            ['name' => 'Nasi Tim'],
            ['name' => 'Bubur Ayam'],
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

    public function testDishCompositionHappyPathCreateListShowUpdate(): void
    {
        $token = $this->login('dapur');

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dish-compositions', [
                'dish_id'         => 1,
                'item_id'         => 1,
                'qty_per_patient' => '125.50',
            ]);

        $createResult->assertStatus(201);
        $createResult->assertJSONFragment(['message' => 'Dish composition created successfully.']);

        $createdJson = json_decode($createResult->getJSON(), true);
        $compositionId = $createdJson['data']['id'];
        $this->assertSame('125.50', $createdJson['data']['qty_per_patient']);

        $listResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/dish-compositions?dish_id=1&sortBy=id&sortDir=ASC');

        $listResult->assertStatus(200);
        $listJson = json_decode($listResult->getJSON(), true);
        $this->assertCount(1, $listJson['data']);
        $this->assertSame('Nasi Tim', $listJson['data'][0]['dish']['name']);
        $this->assertSame('Beras', $listJson['data'][0]['item']['name']);

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/dish-compositions/' . $compositionId);

        $showResult->assertStatus(200);
        $showJson = json_decode($showResult->getJSON(), true);
        $this->assertSame('125.50', $showJson['data']['qty_per_patient']);

        $updateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/dish-compositions/' . $compositionId, [
                'qty_per_patient' => '140.75',
            ]);

        $updateResult->assertStatus(200);
        $updateResult->assertJSONFragment(['message' => 'Dish composition updated successfully.']);

        $updatedJson = json_decode($updateResult->getJSON(), true);
        $this->assertSame('140.75', $updatedJson['data']['qty_per_patient']);
    }

    public function testGudangHasReadOnlyAccessForDishCompositions(): void
    {
        $adminToken = $this->login('admin');
        $gudangToken = $this->login('gudang');

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/dish-compositions', [
                'dish_id'         => 1,
                'item_id'         => 1,
                'qty_per_patient' => '100.00',
            ])
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/dish-compositions')
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/dish-compositions', [
                'dish_id'         => 1,
                'item_id'         => 1,
                'qty_per_patient' => '100.00',
            ])
            ->assertStatus(403);
    }

    public function testCreateDishCompositionRejectsDuplicateDishItemPair(): void
    {
        $token = $this->login('admin');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dish-compositions', [
                'dish_id'         => 1,
                'item_id'         => 1,
                'qty_per_patient' => '100.00',
            ])
            ->assertStatus(201);

        $duplicateResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dish-compositions', [
                'dish_id'         => 1,
                'item_id'         => 1,
                'qty_per_patient' => '120.00',
            ]);

        $duplicateResult->assertStatus(400);
        $duplicateJson = json_decode($duplicateResult->getJSON(), true);
        $this->assertSame('Validation failed.', $duplicateJson['message']);
        $this->assertArrayHasKey('dish_id,item_id', $duplicateJson['errors']);
    }

    public function testCreateDishCompositionRejectsInactiveItem(): void
    {
        $token = $this->login('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dish-compositions', [
                'dish_id'         => 1,
                'item_id'         => 2,
                'qty_per_patient' => '50.00',
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('The selected item is inactive.', $json['errors']['item_id']);
    }

    public function testCreateDishCompositionRejectsInvalidDishReference(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dish-compositions', [
                'dish_id'         => 999,
                'item_id'         => 1,
                'qty_per_patient' => '50.00',
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('The selected dish is invalid.', $json['errors']['dish_id']);
    }

    public function testCreateDishCompositionRejectsInvalidItemReference(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/dish-compositions', [
                'dish_id'         => 1,
                'item_id'         => 999,
                'qty_per_patient' => '50.00',
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('The selected item is invalid.', $json['errors']['item_id']);
    }
}
