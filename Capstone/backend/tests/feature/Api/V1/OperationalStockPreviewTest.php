<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class OperationalStockPreviewTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedUsers();
        $this->seedOperationalBaseline();
    }

    public function testOperationalPreviewReturnsSameDayDraftAndDoesNotMutateStockOrSpkHistory(): void
    {
        $token = $this->login('dapur');
        $db    = Database::connect();

        $item = $db->table('items')->where('name', 'Ayam Basah')->get()->getRowArray();
        $this->assertNotNull($item);

        $itemId = (int) $item['id'];
        $stockBefore = (float) $item['qty'];
        $stockTxnBefore = $db->table('stock_transactions')->countAllResults();
        $spkHistoryBefore = $db->table('spk_calculations')->countAllResults();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/operational-stock-preview', [
                'service_date'   => '2026-03-01',
                'meal_time'      => 'SIANG',
                'total_patients' => 100,
            ]);

        $response->assertStatus(200);

        $json = json_decode($response->getJSON(), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertSame('2026-03-01', $json['data']['service_date']);
        $this->assertSame('SIANG', $json['data']['meal_time']);
        $this->assertSame(100, (int) $json['data']['total_patients']);
        $this->assertArrayNotHasKey('adjusted_patients', $json['data']);

        $this->assertArrayHasKey('menu', $json['data']);
        $this->assertSame(1, (int) $json['data']['menu']['id']);

        $this->assertArrayHasKey('items', $json['data']);
        $this->assertCount(1, $json['data']['items']);
        $previewItem = $json['data']['items'][0];

        $this->assertSame($itemId, (int) $previewItem['item_id']);
        $this->assertSame('Ayam Basah', $previewItem['item_name']);
        $this->assertSame(100.0, (float) $previewItem['current_stock_qty']);
        $this->assertSame(200.0, (float) $previewItem['required_qty']);
        $this->assertSame(200.0, (float) $previewItem['projected_stock_out_qty']);
        $this->assertSame(0.0, (float) $previewItem['projected_remaining_stock_qty']);
        $this->assertSame(100.0, (float) $previewItem['projected_shortage_qty']);

        $this->assertArrayHasKey('summary', $json['data']);
        $this->assertSame(1, (int) $json['data']['summary']['total_items']);
        $this->assertSame(200.0, (float) $json['data']['summary']['total_required_qty']);
        $this->assertSame(200.0, (float) $json['data']['summary']['total_projected_stock_out_qty']);
        $this->assertSame(100.0, (float) $json['data']['summary']['total_projected_shortage_qty']);

        $itemAfterPreview = $db->table('items')->where('id', $itemId)->get()->getRowArray();
        $this->assertNotNull($itemAfterPreview);
        $this->assertSame($stockBefore, (float) $itemAfterPreview['qty']);
        $this->assertSame($stockTxnBefore, $db->table('stock_transactions')->countAllResults());
        $this->assertSame($spkHistoryBefore, $db->table('spk_calculations')->countAllResults());

        $outType = $db->table('transaction_types')->where('name', 'OUT')->get()->getRowArray();
        $this->assertNotNull($outType);

        $stockPost = $this->withHeaders(['Authorization' => 'Bearer ' . $this->login('admin')])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => (int) $outType['id'],
                'transaction_date' => '2026-03-01',
                'details'          => [
                    ['item_id' => $itemId, 'qty' => 50],
                ],
            ]);

        $stockPost->assertStatus(201);

        $itemAfterStockPost = $db->table('items')->where('id', $itemId)->get()->getRowArray();
        $this->assertNotNull($itemAfterStockPost);
        $this->assertSame($stockBefore - 50.0, (float) $itemAfterStockPost['qty']);
        $this->assertSame($stockTxnBefore + 1, $db->table('stock_transactions')->countAllResults());
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

    protected function seedOperationalBaseline(): void
    {
        $db = Database::connect();

        $db->table('meal_times')->insertBatch([
            ['id' => 1, 'name' => 'Pagi'],
            ['id' => 2, 'name' => 'Siang'],
            ['id' => 3, 'name' => 'Sore'],
        ]);

        $db->table('menus')->insertBatch([
            ['id' => 1, 'name' => 'Paket 1'],
            ['id' => 2, 'name' => 'Paket 2'],
            ['id' => 3, 'name' => 'Paket 3'],
            ['id' => 4, 'name' => 'Paket 4'],
            ['id' => 5, 'name' => 'Paket 5'],
            ['id' => 6, 'name' => 'Paket 6'],
            ['id' => 7, 'name' => 'Paket 7'],
            ['id' => 8, 'name' => 'Paket 8'],
            ['id' => 9, 'name' => 'Paket 9'],
            ['id' => 10, 'name' => 'Paket 10'],
            ['id' => 11, 'name' => 'Paket 11'],
        ]);

        $db->table('item_categories')->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);

        $db->table('transaction_types')->insertBatch([
            ['name' => 'IN'],
            ['name' => 'OUT'],
            ['name' => 'RETURN_IN'],
        ]);

        $db->table('approval_statuses')->insertBatch([
            ['name' => 'APPROVED'],
            ['name' => 'PENDING'],
            ['name' => 'REJECTED'],
        ]);

        $basahCategoryId = (int) $db->table('item_categories')->where('name', 'BASAH')->get()->getRowArray()['id'];

        $db->table('item_units')->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
        ]);
        $gramUnit = (int) $db->table('item_units')->where('name', 'gram')->get()->getRowArray()['id'];
        $kgUnit   = (int) $db->table('item_units')->where('name', 'kg')->get()->getRowArray()['id'];

        $db->table('items')->insert([
            'item_category_id'      => $basahCategoryId,
            'name'                  => 'Ayam Basah',
            'unit_base'             => 'gram',
            'unit_convert'          => 'kg',
            'item_unit_base_id'     => $gramUnit,
            'item_unit_convert_id'  => $kgUnit,
            'conversion_base'       => 1000,
            'is_active'             => true,
            'qty'                   => 100,
        ]);
        $itemId = (int) $db->insertID();

        $db->table('dishes')->insert([
            'id'   => 1,
            'name' => 'Sup Ayam',
        ]);

        $db->table('dish_compositions')->insert([
            'dish_id'          => 1,
            'item_id'          => $itemId,
            'qty_per_patient'  => 2.00,
        ]);

        $db->table('menu_dishes')->insert([
            'menu_id'      => 1,
            'meal_time_id' => 2,
            'dish_id'      => 1,
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
}
