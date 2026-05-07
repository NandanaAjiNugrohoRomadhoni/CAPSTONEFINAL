<?php

namespace Tests\Feature\Api\V1;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Shield\Entities\User;
use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use Config\Database;

class LookupsTest extends CIUnitTestCase
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
        $this->seedLookupData();
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
        $roleModel = new RoleModel();
        $userProvider = new AppUserProvider();

        $adminRole = $roleModel->findByName('admin');
        $dapurRole = $roleModel->findByName('dapur');
        $gudangRole = $roleModel->findByName('gudang');

        $adminUser = new User([
            'role_id'   => $adminRole['id'],
            'name'      => 'Admin User',
            'username'  => 'admin',
            'email'     => 'admin@example.com',
            'is_active' => true,
            'active'    => true,
        ]);
        $adminUser->fill(['password' => 'password123']);
        $userProvider->insert($adminUser, true);

        $dapurUser = new User([
            'role_id'   => $dapurRole['id'],
            'name'      => 'Dapur User',
            'username'  => 'dapur',
            'email'     => 'dapur@example.com',
            'is_active' => true,
            'active'    => true,
        ]);
        $dapurUser->fill(['password' => 'password123']);
        $userProvider->insert($dapurUser, true);

        $gudangUser = new User([
            'role_id'   => $gudangRole['id'],
            'name'      => 'Gudang User',
            'username'  => 'gudang',
            'email'     => 'gudang@example.com',
            'is_active' => true,
            'active'    => true,
        ]);
        $gudangUser->fill(['password' => 'password123']);
        $userProvider->insert($gudangUser, true);
    }

    protected function seedLookupData(): void
    {
        $this->db->table('item_categories')->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);

        $this->db->table('transaction_types')->insertBatch([
            ['name' => 'IN'],
            ['name' => 'OUT'],
            ['name' => 'RETURN_IN'],
        ]);

        $this->db->table('approval_statuses')->insertBatch([
            ['name' => 'APPROVED'],
            ['name' => 'PENDING'],
            ['name' => 'REJECTED'],
        ]);
    }

    protected function seedItemUnits(): void
    {
        $this->db->table('item_units')->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
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
            'name'                 => 'Seeded Lookup Item',
            'unit_base'            => 'gram',
            'unit_convert'         => 'kg',
            'item_unit_base_id'    => $gramId,
            'item_unit_convert_id' => $kgId,
            'conversion_base'      => 1000,
            'is_active'            => true,
            'qty'                  => 100,
        ]);
    }

    protected function getToken(string $username): string
    {
        $loginResult = $this->withBodyFormat('json')
            ->post('api/v1/auth/login', [
                'username' => $username,
                'password' => 'password123',
            ]);

        $loginJson = json_decode($loginResult->getJSON(), true);
        return $loginJson['access_token'];
    }

    // Item Categories Tests

    public function testItemCategoriesWithoutAuth(): void
    {
        $result = $this->get('api/v1/item-categories');
        $result->assertStatus(401);
    }

    public function testItemCategoriesWithDapurRole(): void
    {
        $token = $this->getToken('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-categories');

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Insufficient permissions.']);
    }

    public function testItemCategoriesWithAdminRole(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-categories');

        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertCount(3, $json['data']);
        $this->assertSame(3, $json['meta']['total']);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame('BASAH', $json['data'][0]['name']);
        $this->assertSame('KERING', $json['data'][1]['name']);
        $this->assertSame('PENGEMAS', $json['data'][2]['name']);
    }

    public function testItemCategoriesWithGudangRole(): void
    {
        $token = $this->getToken('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-categories');

        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertCount(3, $json['data']);
    }

    public function testItemCategoriesSupportSearchSortAndDateRange(): void
    {
        $db = Database::connect();

        $db->table('item_categories')->where('name', 'BASAH')->update(['created_at' => '2026-04-01 10:00:00']);
        $db->table('item_categories')->where('name', 'KERING')->update(['created_at' => '2026-04-15 10:00:00']);
        $db->table('item_categories')->where('name', 'PENGEMAS')->update(['created_at' => '2026-04-20 10:00:00']);

        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-categories?q=KER&sortBy=id&sortDir=DESC&created_at_from=2026-04-10&created_at_to=2026-04-18');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(1, $json['data']);
        $this->assertSame('KERING', $json['data'][0]['name']);
    }

    public function testItemCategoriesSupportPaginateFalseForDropdowns(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-categories?paginate=false&sortBy=id&sortDir=ASC');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(3, $json['data']);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame(3, $json['meta']['perPage']);
        $this->assertSame(3, $json['meta']['total']);
        $this->assertSame(1, $json['meta']['totalPages']);
        $this->assertFalse($json['meta']['paginated']);
        $this->assertNull($json['links']['next']);
        $this->assertNull($json['links']['previous']);
    }

    public function testItemCategoriesRejectInvalidPaginateValue(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-categories?paginate=maybe');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testItemCategoriesRejectUnsupportedQueryParameter(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-categories?unknown=value');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testAdminCanCreateAndDeleteUnusedItemCategory(): void
    {
        $token = $this->getToken('admin');

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/item-categories', ['name' => 'MINUMAN']);

        $createResult->assertStatus(201);
        $createJson = json_decode($createResult->getJSON(), true);

        $deleteResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/item-categories/' . $createJson['data']['id']);

        $deleteResult->assertStatus(200);
        $deleteResult->assertJSONFragment(['message' => 'Item category deleted successfully.']);
    }

    public function testAdminCannotDeleteItemCategoryUsedByActiveItems(): void
    {
        $token         = $this->getToken('admin');
        $categoryModel = new ItemCategoryModel();
        $kering        = $categoryModel->where('name', 'KERING')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/item-categories/' . $kering['id']);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testAdminCannotRecreateDeletedItemCategoryAndMustRestoreIt(): void
    {
        $token = $this->getToken('admin');

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/item-categories', ['name' => 'MINUMAN']);

        $createResult->assertStatus(201);
        $createdJson = json_decode($createResult->getJSON(), true);
        $categoryId  = $createdJson['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/item-categories/' . $categoryId)
            ->assertStatus(200);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/item-categories', ['name' => '  minuman  ']);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertSame('The name belongs to a deleted item category. Restore it instead.', $json['errors']['name']);
        $this->assertSame((string) $categoryId, $json['errors']['restore_id']);
    }

    public function testAdminCanRestoreDeletedItemCategory(): void
    {
        $token = $this->getToken('admin');

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/item-categories', ['name' => 'MINUMAN']);

        $createResult->assertStatus(201);
        $createdJson = json_decode($createResult->getJSON(), true);
        $categoryId  = $createdJson['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->delete('api/v1/item-categories/' . $categoryId)
            ->assertStatus(200);

        $restoreResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->patch('api/v1/item-categories/' . $categoryId . '/restore');

        $restoreResult->assertStatus(200);
        $restoreResult->assertJSONFragment(['message' => 'Item category restored successfully.']);

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/item-categories/' . $categoryId);

        $showResult->assertStatus(200);
        $showJson = json_decode($showResult->getJSON(), true);
        $this->assertSame('MINUMAN', $showJson['data']['name']);
    }

    // Transaction Types Tests

    public function testTransactionTypesWithoutAuth(): void
    {
        $result = $this->get('api/v1/transaction-types');
        $result->assertStatus(401);
    }

    public function testTransactionTypesWithDapurRole(): void
    {
        $token = $this->getToken('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/transaction-types');

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Insufficient permissions.']);
    }

    public function testTransactionTypesWithAdminRole(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/transaction-types');

        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertCount(3, $json['data']);
        $this->assertSame(3, $json['meta']['total']);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame('IN', $json['data'][0]['name']);
        $this->assertSame('OUT', $json['data'][1]['name']);
        $this->assertSame('RETURN_IN', $json['data'][2]['name']);
    }

    public function testTransactionTypesWithGudangRole(): void
    {
        $token = $this->getToken('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/transaction-types');

        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertCount(3, $json['data']);
    }

    public function testTransactionTypesSupportPaginateFalse(): void
    {
        $token = $this->getToken('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/transaction-types?paginate=false');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(3, $json['data']);
        $this->assertFalse($json['meta']['paginated']);
        $this->assertSame(3, $json['meta']['total']);
    }

    public function testRolesSupportSearchAndSorting(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/roles?q=gu&sortBy=id&sortDir=DESC');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(1, $json['data']);
        $this->assertSame('gudang', $json['data'][0]['name']);
    }

    public function testRolesSupportPaginateFalse(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/roles?paginate=false&sortBy=id&sortDir=ASC');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(3, $json['data']);
        $this->assertFalse($json['meta']['paginated']);
        $this->assertSame('admin', $json['data'][0]['name']);
    }

    // Approval Statuses Tests

    public function testApprovalStatusesWithoutAuth(): void
    {
        $result = $this->get('api/v1/approval-statuses');
        $result->assertStatus(401);
    }

    public function testApprovalStatusesWithDapurRole(): void
    {
        $token = $this->getToken('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/approval-statuses');

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Insufficient permissions.']);
    }

    public function testApprovalStatusesWithAdminRole(): void
    {
        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/approval-statuses');

        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertCount(3, $json['data']);
        $this->assertSame(3, $json['meta']['total']);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame('APPROVED', $json['data'][0]['name']);
        $this->assertSame('PENDING', $json['data'][1]['name']);
        $this->assertSame('REJECTED', $json['data'][2]['name']);
    }

    public function testApprovalStatusesWithGudangRole(): void
    {
        $token = $this->getToken('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/approval-statuses');

        $result->assertStatus(200);
        
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertCount(3, $json['data']);
    }

    public function testApprovalStatusesSupportPaginateFalse(): void
    {
        $token = $this->getToken('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/approval-statuses?paginate=false');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(3, $json['data']);
        $this->assertFalse($json['meta']['paginated']);
        $this->assertSame(3, $json['meta']['total']);
    }

    public function testMealTimesWithoutAuth(): void
    {
        $result = $this->get('api/v1/meal-times');
        $result->assertStatus(401);
    }

    public function testMealTimesWithDapurRole(): void
    {
        $token = $this->getToken('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/meal-times');

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Insufficient permissions.']);
    }

    public function testMealTimesWithAdminRole(): void
    {
        $this->db->table('meal_times')->insertBatch([
            ['name' => 'Pagi'],
            ['name' => 'Siang'],
            ['name' => 'Sore'],
        ]);

        $token = $this->getToken('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/meal-times?sortBy=id&sortDir=ASC');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertCount(3, $json['data']);
        $this->assertSame('Pagi', $json['data'][0]['name']);
        $this->assertSame('Siang', $json['data'][1]['name']);
        $this->assertSame('Sore', $json['data'][2]['name']);
    }

    public function testMealTimesWithGudangRoleAndPaginateFalse(): void
    {
        $this->db->table('meal_times')->insertBatch([
            ['name' => 'Pagi'],
            ['name' => 'Siang'],
            ['name' => 'Sore'],
        ]);

        $token = $this->getToken('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/meal-times?paginate=false&sortBy=id&sortDir=ASC');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertCount(3, $json['data']);
        $this->assertFalse($json['meta']['paginated']);
        $this->assertSame(3, $json['meta']['total']);
    }
}
