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

class ItemUnitsTest extends CIUnitTestCase
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
            ['name' => 'pack'],
        ]);
    }

    protected function seedItems(): void
    {
        $categoryModel = new ItemCategoryModel();
        $itemUnitModel = new ItemUnitModel();
        $db            = Database::connect();

        $kering = $categoryModel->where('name', 'KERING')->first();
        $gramId = $itemUnitModel->getIdByName('gram');
        $kgId   = $itemUnitModel->getIdByName('kg');

        $db->table('items')->insert([
            'item_category_id'     => $kering['id'],
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

    public function testListItemUnitsWithoutAuth(): void
    {
        $this->get('api/v1/item-units')->assertStatus(401);
    }

    public function testListItemUnitsAsDapurIsForbidden(): void
    {
        $token = $this->login('dapur');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-units')
            ->assertStatus(403);
    }

    public function testListItemUnitsSupportsSearchSortAndDateRange(): void
    {
        $db = Database::connect();

        $db->table('item_units')->where('name', 'gram')->update(['created_at' => '2026-04-01 10:00:00']);
        $db->table('item_units')->where('name', 'kg')->update(['created_at' => '2026-04-10 10:00:00']);
        $db->table('item_units')->where('name', 'liter')->update(['created_at' => '2026-04-20 10:00:00']);

        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-units?q=g&sortBy=id&sortDir=DESC&created_at_from=2026-04-05&created_at_to=2026-04-15');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(1, $json['data']);
        $this->assertSame('kg', $json['data'][0]['name']);
    }

    public function testListItemUnitsSupportsPaginateFalseForDropdowns(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-units?paginate=false&sortBy=id&sortDir=ASC');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(5, $json['data']);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame(5, $json['meta']['perPage']);
        $this->assertSame(5, $json['meta']['total']);
        $this->assertSame(1, $json['meta']['totalPages']);
        $this->assertFalse($json['meta']['paginated']);
        $this->assertNull($json['links']['next']);
        $this->assertNull($json['links']['previous']);
    }

    public function testListItemUnitsRejectsInvalidPaginateValue(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-units?paginate=invalid');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testAdminCanCreateItemUnit(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/item-units', ['name' => 'box']);

        $result->assertStatus(201);
        $result->assertJSONFragment(['message' => 'Item unit created successfully.']);
    }

    public function testGudangCannotCreateItemUnit(): void
    {
        $token = $this->login('gudang');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/item-units', ['name' => 'box'])
            ->assertStatus(403);
    }

    public function testAdminCannotCreateDuplicateItemUnitName(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/item-units', ['name' => 'GRAM']);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testAdminCannotRecreateDeletedItemUnitAndMustRestoreIt(): void
    {
        $token         = $this->login('admin');
        $itemUnitModel = new ItemUnitModel();
        $packId        = $itemUnitModel->getIdByName('pack');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/item-units/' . $packId)
            ->assertStatus(200);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/item-units', ['name' => 'PACK']);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertSame('The name belongs to a deleted item unit. Restore it instead.', $json['errors']['name']);
        $this->assertSame((string) $packId, $json['errors']['restore_id']);
    }

    public function testAdminCanRestoreDeletedItemUnit(): void
    {
        $token         = $this->login('admin');
        $itemUnitModel = new ItemUnitModel();
        $packId        = $itemUnitModel->getIdByName('pack');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/item-units/' . $packId)
            ->assertStatus(200);

        $restoreResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patch('api/v1/item-units/' . $packId . '/restore');

        $restoreResult->assertStatus(200);
        $restoreResult->assertJSONFragment(['message' => 'Item unit restored successfully.']);

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-units/' . $packId);

        $showResult->assertStatus(200);
        $showJson = json_decode($showResult->getJSON(), true);
        $this->assertSame('pack', $showJson['data']['name']);
    }

    public function testAdminCannotDeleteItemUnitUsedByActiveItems(): void
    {
        $token         = $this->login('admin');
        $itemUnitModel = new ItemUnitModel();
        $gramId        = $itemUnitModel->getIdByName('gram');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/item-units/' . $gramId);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testAdminCanDeleteUnusedItemUnit(): void
    {
        $token         = $this->login('admin');
        $itemUnitModel = new ItemUnitModel();
        $packId        = $itemUnitModel->getIdByName('pack');

        $deleteResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/item-units/' . $packId);

        $deleteResult->assertStatus(200);
        $deleteResult->assertJSONFragment(['message' => 'Item unit deleted successfully.']);

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-units/' . $packId);

        $showResult->assertStatus(404);
    }
}
