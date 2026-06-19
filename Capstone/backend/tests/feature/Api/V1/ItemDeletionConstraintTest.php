<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\DishCompositionModel;
use App\Models\DishModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

class ItemDeletionConstraintTest extends CIUnitTestCase
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
    }

    protected function seedRoles(): void
    {
        (new RoleModel())->insertBatch([
            ['name' => 'admin'],
            ['name' => 'dapur'],
        ]);
    }

    protected function seedUsers(): void
    {
        $roleModel    = new RoleModel();
        $userProvider = new AppUserProvider();
        $adminRole    = $roleModel->findByName('admin');

        $user = new User([
            'role_id'   => $adminRole['id'],
            'name'      => 'Admin',
            'username'  => 'admin',
            'email'     => 'admin@example.com',
            'is_active' => true,
            'active'    => true,
        ]);
        $user->fill(['password' => 'password123']);
        $userProvider->insert($user, true);
    }

    protected function seedItemCategories(): void
    {
        (new ItemCategoryModel())->insert(['name' => 'BASAH']);
    }

    protected function seedItemUnits(): void
    {
        (new ItemUnitModel())->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
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

    public function testDeleteItemUsedInDishReturns409()
    {
        $itemModel = new ItemModel();
        $dishModel = new DishModel();
        $compositionModel = new DishCompositionModel();

        $itemId = $itemModel->insert([
            'item_category_id' => 1,
            'name' => 'Beras',
            'unit_base' => 'gram',
            'unit_convert' => 'kg',
            'item_unit_base_id' => 1,
            'item_unit_convert_id' => 2,
            'conversion_base' => 1000,
            'is_active' => true,
        ]);

        $dishId = $dishModel->insert([
            'name' => 'Nasi Putih',
            'is_active' => true,
        ]);

        $compositionModel->insert([
            'dish_id' => $dishId,
            'item_id' => $itemId,
            'qty_per_patient' => 100,
        ]);

        $token = $this->login('admin');
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                       ->delete("/api/v1/items/{$itemId}");

        $result->assertStatus(409);
        
        $body = json_decode($result->getJSON(), true);
        $this->assertEquals('Cannot delete item because it is used in one or more dishes.', $body['message']);
        
        $this->assertArrayHasKey('data', $body);
        $this->assertNotEmpty($body['data']);
        $this->assertEquals('Nasi Putih', $body['data'][0]['name']);
    }
}
