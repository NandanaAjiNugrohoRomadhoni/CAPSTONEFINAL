<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class SpkStockInPrefillTest extends CIUnitTestCase
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

    public function testPrefillReturnsEditableDraftAndPersistsOnlyAfterExplicitCreate(): void
    {
        $db = Database::connect();
        $dapurToken = $this->login('dapur');
        $adminToken = $this->login('admin');

        $this->createDailyPatient($dapurToken, '2026-03-01', 100);

        $generated = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);
        $generated->assertStatus(201);

        $generatedJson = json_decode($generated->getJSON(), true);
        $spkId = (int) $generatedJson['data']['id'];

        $transactionCountBefore = $db->table('stock_transactions')->countAllResults();
        $detailCountBefore = $db->table('stock_transaction_details')->countAllResults();

        $prefill = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->get('api/v1/spk/stock-in-prefill/' . $spkId);
        $prefill->assertStatus(200);

        $prefillJson = json_decode($prefill->getJSON(), true);
        $this->assertIsArray($prefillJson);
        $this->assertArrayHasKey('data', $prefillJson);
        $this->assertSame('IN', $prefillJson['data']['type_name']);
        $this->assertSame($spkId, (int) $prefillJson['data']['spk_id']);
        $this->assertSame('2026-03-01', $prefillJson['data']['transaction_date']);
        $this->assertArrayHasKey('details', $prefillJson['data']);
        $this->assertNotEmpty($prefillJson['data']['details']);

        $firstDetail = $prefillJson['data']['details'][0];
        $this->assertArrayHasKey('item_id', $firstDetail);
        $this->assertArrayHasKey('qty', $firstDetail);
        $this->assertGreaterThan(0, (float) $firstDetail['qty']);

        $this->assertSame($transactionCountBefore, $db->table('stock_transactions')->countAllResults());
        $this->assertSame($detailCountBefore, $db->table('stock_transaction_details')->countAllResults());

        $editedDetails = $prefillJson['data']['details'];
        $editedDetails[0]['qty'] = (float) $editedDetails[0]['qty'] + 25.5;

        if (count($editedDetails) > 1) {
            array_pop($editedDetails);
        }

        $newItem = $db->table('items')->where('name', 'Extra Basah')->get()->getRowArray();
        $this->assertNotNull($newItem);

        $editedDetails[] = [
            'item_id' => (int) $newItem['id'],
            'qty'     => 10,
        ];

        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => $prefillJson['data']['type_name'],
                'transaction_date' => $prefillJson['data']['transaction_date'],
                'spk_id'           => $prefillJson['data']['spk_id'],
                'details'          => $editedDetails,
            ]);

        $create->assertStatus(201);
        $create->assertJSONFragment(['message' => 'Stock transaction created successfully.']);

        $createJson = json_decode($create->getJSON(), true);
        $transactionId = (int) $createJson['data']['id'];

        $this->assertSame($transactionCountBefore + 1, $db->table('stock_transactions')->countAllResults());

        $transaction = $db->table('stock_transactions')->where('id', $transactionId)->get()->getRowArray();
        $this->assertNotNull($transaction);
        $this->assertSame($spkId, (int) $transaction['spk_id']);

        $type = $db->table('transaction_types')->where('id', (int) $transaction['type_id'])->get()->getRowArray();
        $this->assertNotNull($type);
        $this->assertSame('IN', $type['name']);

        $approved = $db->table('approval_statuses')->where('name', 'APPROVED')->get()->getRowArray();
        $this->assertNotNull($approved);
        $this->assertSame((int) $approved['id'], (int) $transaction['approval_status_id']);
        $this->assertSame(0, (int) $transaction['is_revision']);

        $savedDetails = $db->table('stock_transaction_details')
            ->where('transaction_id', $transactionId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(count($editedDetails), $savedDetails);
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

        $db->table('items')->insert([
            'item_category_id'      => $basahCategoryId,
            'name'                  => 'Extra Basah',
            'unit_base'             => 'gram',
            'unit_convert'          => 'kg',
            'item_unit_base_id'     => $gramUnit,
            'item_unit_convert_id'  => $kgUnit,
            'conversion_base'       => 1000,
            'is_active'             => true,
            'qty'                   => 50,
        ]);

        $db->table('dishes')->insert([
            'id'   => 1,
            'name' => 'Sup Ayam',
        ]);

        $db->table('dish_compositions')->insert([
            'dish_id'          => 1,
            'item_id'          => $itemId,
            'qty_per_patient'  => 2.00,
        ]);

        $db->table('menu_dishes')->insertBatch([
            ['menu_id' => 1, 'meal_time_id' => 2, 'dish_id' => 1],
            ['menu_id' => 2, 'meal_time_id' => 2, 'dish_id' => 1],
            ['menu_id' => 11, 'meal_time_id' => 2, 'dish_id' => 1],
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

    private function createDailyPatient(string $token, string $serviceDate, int $totalPatients): void
    {
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/daily-patients', [
                'service_date'   => $serviceDate,
                'total_patients' => $totalPatients,
            ])
            ->assertStatus(201);
    }
}
