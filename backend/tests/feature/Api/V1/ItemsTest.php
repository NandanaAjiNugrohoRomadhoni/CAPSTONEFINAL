<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Database\Exceptions\DataException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class ItemsTest extends CIUnitTestCase
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
                'qty'               => 1500,
            ],
            [
                'item_category_id'  => $basah['id'],
                'name'              => 'Ayam',
                'unit_base'         => 'gram',
                'unit_convert'      => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'   => 1000,
                'is_active'         => false,
                'qty'               => 2500,
            ],
        ]);
    }

    public function testItemModelDirectQtyUpdateDoesNotChangeStoredQty(): void
    {
        $itemModel = new ItemModel();

        $before = $itemModel->find(1);
        $this->assertNotNull($before);

        try {
            $itemModel->update(1, ['qty' => 9999]);
            $this->fail('Expected DataException when attempting direct qty update via ItemModel.');
        } catch (DataException $exception) {
            $this->assertSame('There is no data to update.', $exception->getMessage());
        }


        $after = $itemModel->find(1);
        $this->assertNotNull($after);
        $this->assertSame((string) $before['qty'], (string) $after['qty']);
    }

    public function testItemModelDirectQtyInsertUsesDatabaseDefaultQty(): void
    {
        $itemModel     = new ItemModel();
        $categoryModel = new ItemCategoryModel();
        $category      = $categoryModel->where('name', 'KERING')->first();

        $createdId = $itemModel->insert([
            'item_category_id' => $category['id'],
            'name'             => 'Direct Model Insert Item',
            'unit_base'        => 'gram',
            'unit_convert'     => 'kg',
            'conversion_base'  => 1000,
            'is_active'        => true,
            'qty'              => 9999,
        ], true);

        $this->assertIsNumeric($createdId);

        $created = $itemModel->find((int) $createdId);
        $this->assertNotNull($created);
        $this->assertSame('0.00', number_format((float) $created['qty'], 2, '.', ''));
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

    public function testListItemsWithoutAuth(): void
    {
        $this->get('api/v1/items')->assertStatus(401);
    }

    public function testListItemsAsDapurIsForbidden(): void
    {
        $token = $this->login('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items');

        $result->assertStatus(403);
    }

    public function testListItemsAsGudangReturnsPaginatedEnvelope(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame(10, $json['meta']['perPage']);
        $this->assertSame('Ayam', $json['data'][0]['name']);
        $this->assertSame('Beras', $json['data'][1]['name']);
    }

    public function testListItemsRejectsUnknownQueryParameter(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items?unknown=value');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testListItemsRejectsInvalidQueryValue(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items?page=0&is_active=maybe');

        $result->assertStatus(400);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('page', $json['errors']);
        $this->assertArrayHasKey('is_active', $json['errors']);
    }

    public function testListItemsSupportsFiltering(): void
    {
        $token         = $this->login('admin');
        $categoryModel = new ItemCategoryModel();
        $kering        = $categoryModel->where('name', 'KERING')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items?item_category_id=' . $kering['id'] . '&is_active=1&q=Ber');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(1, $json['data']);
        $this->assertSame('Beras', $json['data'][0]['name']);
    }

    public function testShowItemAsGudang(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items/1');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertSame('Beras', $json['data']['name']);
        $this->assertArrayHasKey('category', $json['data']);
        $this->assertArrayHasKey('qty', $json['data']);
        $this->assertArrayHasKey('item_unit_base_id', $json['data']);
        $this->assertArrayHasKey('item_unit_convert_id', $json['data']);
        $this->assertArrayHasKey('item_unit_base', $json['data']);
        $this->assertArrayHasKey('item_unit_convert', $json['data']);
        $this->assertSame('gram', $json['data']['item_unit_base']['name']);
        $this->assertSame('kg', $json['data']['item_unit_convert']['name']);
    }

    public function testShowMissingItemReturnsNotFound(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items/9999');

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Item not found.']);
    }

    public function testCreateItemAsGudang(): void
    {
        $token         = $this->login('gudang');
        $categoryModel = new ItemCategoryModel();
        $category      = $categoryModel->where('name', 'PENGEMAS')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'             => 'Minyak',
                'item_category_id' => $category['id'],
                'unit_base'        => 'ml',
                'unit_convert'     => 'liter',
                'conversion_base'  => 1000,
                'is_active'        => true,
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['message' => 'Item created successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Minyak', $json['data']['name']);
        $this->assertSame('0.00', $json['data']['qty']);
        $this->assertArrayHasKey('item_unit_base_id', $json['data']);
        $this->assertArrayHasKey('item_unit_convert_id', $json['data']);
        $this->assertArrayHasKey('item_unit_base', $json['data']);
        $this->assertArrayHasKey('item_unit_convert', $json['data']);
        $this->assertSame('ml', $json['data']['item_unit_base']['name']);
        $this->assertSame('liter', $json['data']['item_unit_convert']['name']);
    }

    public function testCreateItemRejectsQtyField(): void
    {
        $token         = $this->login('admin');
        $categoryModel = new ItemCategoryModel();
        $category      = $categoryModel->where('name', 'PENGEMAS')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'             => 'Gula',
                'item_category_id' => $category['id'],
                'unit_base'        => 'gram',
                'unit_convert'     => 'kg',
                'conversion_base'  => 1000,
                'qty'              => 500,
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateItemRejectsDuplicateName(): void
    {
        $token         = $this->login('admin');
        $categoryModel = new ItemCategoryModel();
        $category      = $categoryModel->where('name', 'KERING')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'             => 'Beras',
                'item_category_id' => $category['id'],
                'unit_base'        => 'gram',
                'unit_convert'     => 'kg',
                'conversion_base'  => 1000,
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testUpdateItemAsGudang(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/items/1', [
                'name'      => 'Beras Premium',
                'is_active' => false,
            ]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Item updated successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Beras Premium', $json['data']['name']);
        $this->assertFalse($json['data']['is_active']);
    }

    public function testUpdateItemRejectsQtyField(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/items/1', [
                'qty' => 99,
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testDeleteItemAsGudangIsForbidden(): void
    {
        $token = $this->login('gudang');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/items/1')
            ->assertStatus(403);
    }

    public function testDeleteItemAsAdminSoftDeletesItem(): void
    {
        $token = $this->login('admin');

        $deleteResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/items/1');

        $deleteResult->assertStatus(200);
        $deleteResult->assertJSONFragment(['message' => 'Item deleted successfully.']);

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items/1');

        $showResult->assertStatus(404);
    }

    public function testCannotReuseDeletedItemName(): void
    {
        $token = $this->login('admin');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/items/1')
            ->assertStatus(200);

        $categoryModel = new ItemCategoryModel();
        $category      = $categoryModel->where('name', 'KERING')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'             => 'Beras',
                'item_category_id' => $category['id'],
                'unit_base'        => 'gram',
                'unit_convert'     => 'kg',
                'conversion_base'  => 1000,
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('restore_id', $json['errors']);
        $this->assertSame('1', $json['errors']['restore_id']);
    }

    public function testAdminCanRestoreDeletedItem(): void
    {
        $token = $this->login('admin');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/items/1')
            ->assertStatus(200);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->call('PATCH', 'api/v1/items/1/restore');

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Item restored successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertSame('Beras', $json['data']['name']);

        // Verify it appears in list again
        $list = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/items/1');
        $list->assertStatus(200);
    }

    public function testRestoreAlreadyActiveItemReturns200(): void
    {
        $token = $this->login('admin');

        // Item 1 (Beras) is not deleted — restoring it is idempotent
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->call('PATCH', 'api/v1/items/1/restore');

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Item restored successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Beras', $json['data']['name']);
    }

    public function testRestoreItemFailsIfCategoryWasSoftDeleted(): void
    {
        $token = $this->login('admin');
        $itemModel = new ItemModel();
        $categoryModel = new ItemCategoryModel();

        $item = $itemModel->find(1);
        $this->assertNotNull($item);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/items/1')
            ->assertStatus(200);

        $categoryModel->delete((int) $item['item_category_id']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->call('PATCH', 'api/v1/items/1/restore');

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('item_category_id', $json['errors']);
    }

    public function testRestoreItemFailsIfBaseUnitWasSoftDeleted(): void
    {
        $token = $this->login('admin');
        $itemModel = new ItemModel();
        $itemUnitModel = new ItemUnitModel();

        $item = $itemModel->find(1);
        $this->assertNotNull($item);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/items/1')
            ->assertStatus(200);

        $itemUnitModel->delete((int) $item['item_unit_base_id']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->call('PATCH', 'api/v1/items/1/restore');

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('unit_base', $json['errors']);
    }

    public function testRestoreItemFailsIfConvertUnitWasSoftDeleted(): void
    {
        $token = $this->login('admin');
        $itemModel = new ItemModel();
        $itemUnitModel = new ItemUnitModel();

        $item = $itemModel->find(1);
        $this->assertNotNull($item);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/items/1')
            ->assertStatus(200);

        $itemUnitModel->delete((int) $item['item_unit_convert_id']);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->call('PATCH', 'api/v1/items/1/restore');

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('unit_convert', $json['errors']);
    }

    public function testRestoreNonExistentItemReturns404(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->call('PATCH', 'api/v1/items/9999/restore');

        $result->assertStatus(404);
    }

    public function testGudangCannotRestoreItem(): void
    {
        $token = $this->login('admin');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/items/1')
            ->assertStatus(200);

        $gudangToken = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->call('PATCH', 'api/v1/items/1/restore');

        $result->assertStatus(403);
    }

    // Dual lookup tests: item_category_name support
    public function testCreateItemWithItemCategoryNameSucceeds(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'               => 'Minyak Goreng',
                'item_category_name' => 'PENGEMAS',
                'unit_base'          => 'ml',
                'unit_convert'       => 'liter',
                'conversion_base'    => 1000,
                'is_active'          => true,
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['message' => 'Item created successfully.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Minyak Goreng', $json['data']['name']);
        $this->assertSame('PENGEMAS', $json['data']['category']['name']);
    }

    public function testCreateItemWithTrimmedItemCategoryNameSucceeds(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'               => 'Telur',
                'item_category_name' => '  BASAH  ',
                'unit_base'          => 'butir',
                'unit_convert'       => 'pack',
                'conversion_base'    => 10,
            ]);

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('BASAH', $json['data']['category']['name']);
    }

    public function testCreateItemWithCaseInsensitiveItemCategoryNameSucceeds(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'               => 'Gula',
                'item_category_name' => 'kering',
                'unit_base'          => 'gram',
                'unit_convert'       => 'kg',
                'conversion_base'    => 1000,
            ]);

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('KERING', $json['data']['category']['name']);
    }

    public function testCreateItemWithBothItemCategoryIdAndItemCategoryNameFails(): void
    {
        $token         = $this->login('admin');
        $categoryModel = new ItemCategoryModel();
        $category      = $categoryModel->where('name', 'KERING')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'               => 'Conflict Item',
                'item_category_id'   => $category['id'],
                'item_category_name' => 'BASAH',
                'unit_base'          => 'gram',
                'unit_convert'       => 'kg',
                'conversion_base'    => 1000,
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('item_category_id', $json['errors']);
        $this->assertArrayHasKey('item_category_name', $json['errors']);
    }

    public function testCreateItemWithInvalidItemCategoryNameFails(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'               => 'Invalid Category Item',
                'item_category_name' => 'NONEXISTENT',
                'unit_base'          => 'gram',
                'unit_convert'       => 'kg',
                'conversion_base'    => 1000,
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('item_category_name', $json['errors']);
    }

    public function testUpdateItemWithItemCategoryNameSucceeds(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/items/1', [
                'item_category_name' => 'BASAH',
            ]);

        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('BASAH', $json['data']['category']['name']);
    }

    public function testUpdateItemWithBothItemCategoryIdAndItemCategoryNameFails(): void
    {
        $token         = $this->login('gudang');
        $categoryModel = new ItemCategoryModel();
        $category      = $categoryModel->where('name', 'BASAH')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/items/1', [
                'item_category_id'   => $category['id'],
                'item_category_name' => 'KERING',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('item_category_id', $json['errors']);
        $this->assertArrayHasKey('item_category_name', $json['errors']);
    }

    public function testUpdateItemWithInvalidItemCategoryNameFails(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->put('api/v1/items/1', [
                'item_category_name' => 'INVALIDCATEGORY',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('item_category_name', $json['errors']);
    }
}
