<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class SpkBasahReadTest extends CIUnitTestCase
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

    public function testHistoryReturnsBasahRowsInStableOrderWithMetadata(): void
    {
        $token = $this->login('dapur');
        $readToken = $this->login('gudang');

        $this->createDailyPatient($token, '2026-03-01', 100);
        $this->createDailyPatient($token, '2026-03-31', 120);

        $first = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);
        $first->assertStatus(201);

        $second = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-31',
            ]);
        $second->assertStatus(201);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $readToken])
            ->get('api/v1/spk/basah/history');

        $response->assertStatus(200);
        $json = json_decode($response->getJSON(), true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertGreaterThanOrEqual(2, count($json['data']));
        $this->assertSame(count($json['data']), $json['meta']['total']);

        $firstRow = $json['data'][0];
        $secondRow = $json['data'][1];

        $this->assertSame('2026-03-31', $firstRow['calculation_date']);
        $this->assertSame('2026-03-01', $secondRow['calculation_date']);
        $this->assertSame('combined_window', $firstRow['calculation_scope']);
        $this->assertArrayHasKey('user', $firstRow);
        $this->assertSame('dapur', $firstRow['user']['username']);
        $this->assertArrayHasKey('category', $firstRow);
        $this->assertSame('BASAH', $firstRow['category']['name']);
    }

    public function testShowReturnsPersistedDetailFieldsWithoutRecomputeAndKeepsStockUntouched(): void
    {
        $token = $this->login('dapur');
        $readToken = $this->login('gudang');
        $db = Database::connect();

        $this->createDailyPatient($token, '2026-03-01', 100);

        $generated = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);
        $generated->assertStatus(201);
        $payload = json_decode($generated->getJSON(), true);
        $spkId = (int) $payload['data']['id'];

        $targetRecommendation = $db->table('spk_recommendations')
            ->where('spk_id', $spkId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();
        $this->assertNotNull($targetRecommendation);

        $db->table('spk_recommendations')
            ->where('id', (int) $targetRecommendation['id'])
            ->update([
                'recommended_qty' => 123.45,
                'is_overridden'   => 1,
                'override_reason' => 'Manual buffer',
            ]);

        $itemRow = $db->table('items')->where('id', (int) $targetRecommendation['item_id'])->get()->getRowArray();
        $this->assertNotNull($itemRow);
        $stockBefore = (float) $itemRow['qty'];
        $stockTxnBefore = $db->table('stock_transactions')->countAllResults();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $readToken])
            ->get('api/v1/spk/basah/history/' . $spkId);
        $response->assertStatus(200);
        $json = json_decode($response->getJSON(), true);

        $this->assertSame($spkId, $json['data']['id']);
        $this->assertArrayHasKey('items', $json['data']);
        $this->assertNotEmpty($json['data']['items']);

        $item = $json['data']['items'][0];
        $this->assertArrayHasKey('current_stock_qty', $item);
        $this->assertArrayHasKey('required_qty', $item);
        $this->assertArrayHasKey('system_recommended_qty', $item);
        $this->assertArrayHasKey('final_recommended_qty', $item);
        $this->assertTrue($item['override']['is_overridden']);
        $this->assertSame('Manual buffer', $item['override']['reason']);
        $this->assertSame(123.45, (float) $item['final_recommended_qty']);

        $this->assertArrayHasKey('print_ready', $json['data']);
        $this->assertSame($spkId, $json['data']['print_ready']['spk_id']);
        $this->assertArrayHasKey('recommendations', $json['data']['print_ready']);
        $this->assertNotEmpty($json['data']['print_ready']['recommendations']);

        $itemAfter = $db->table('items')->where('id', (int) $targetRecommendation['item_id'])->get()->getRowArray();
        $this->assertNotNull($itemAfter);
        $this->assertSame($stockBefore, (float) $itemAfter['qty']);
        $this->assertSame($stockTxnBefore, $db->table('stock_transactions')->countAllResults());
    }

    public function testHistoryPreservesDistinctRowsAcrossRegenerationForSameScope(): void
    {
        $token = $this->login('dapur');
        $readToken = $this->login('gudang');

        $this->createDailyPatient($token, '2026-03-01', 100);

        $first = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);
        $first->assertStatus(201);
        $firstJson = json_decode($first->getJSON(), true);

        $second = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);
        $second->assertStatus(201);
        $secondJson = json_decode($second->getJSON(), true);

        $this->assertNotSame((int) $firstJson['data']['id'], (int) $secondJson['data']['id']);
        $this->assertSame((string) $firstJson['data']['scope_key'], (string) $secondJson['data']['scope_key']);
        $this->assertSame(1, (int) $firstJson['data']['version']);
        $this->assertSame(2, (int) $secondJson['data']['version']);

        $history = $this->withHeaders(['Authorization' => 'Bearer ' . $readToken])
            ->get('api/v1/spk/basah/history');
        $history->assertStatus(200);
        $historyJson = json_decode($history->getJSON(), true);

        $scopeKey = (string) $firstJson['data']['scope_key'];
        $matching = array_values(array_filter($historyJson['data'], static function (array $row) use ($scopeKey): bool {
            return (string) $row['scope_key'] === $scopeKey;
        }));

        $this->assertCount(2, $matching);
        $this->assertSame(2, (int) $matching[0]['version']);
        $this->assertTrue((bool) $matching[0]['is_latest']);
        $this->assertSame(1, (int) $matching[1]['version']);
        $this->assertFalse((bool) $matching[1]['is_latest']);

        $showOld = $this->withHeaders(['Authorization' => 'Bearer ' . $readToken])
            ->get('api/v1/spk/basah/history/' . (int) $firstJson['data']['id']);
        $showOld->assertStatus(200);
        $showOldJson = json_decode($showOld->getJSON(), true);
        $this->assertSame(1, (int) $showOldJson['data']['version']);
        $this->assertFalse((bool) $showOldJson['data']['is_latest']);

        $showNew = $this->withHeaders(['Authorization' => 'Bearer ' . $readToken])
            ->get('api/v1/spk/basah/history/' . (int) $secondJson['data']['id']);
        $showNew->assertStatus(200);
        $showNewJson = json_decode($showNew->getJSON(), true);
        $this->assertSame(2, (int) $showNewJson['data']['version']);
        $this->assertTrue((bool) $showNewJson['data']['is_latest']);
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

        $basahCategoryId = (int) $db->table('item_categories')->where('name', 'BASAH')->get()->getRowArray()['id'];

        $db->table('item_units')->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
        ]);
        $gramUnit = (int) $db->table('item_units')->where('name', 'gram')->get()->getRowArray()['id'];
        $kgUnit   = (int) $db->table('item_units')->where('name', 'kg')->get()->getRowArray()['id'];

        $itemBuilder = $db->table('items');
        $itemBuilder->insert([
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
