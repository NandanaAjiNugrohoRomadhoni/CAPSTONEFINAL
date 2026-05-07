<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Services\SpkStockPostingService;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class SpkBasahTest extends CIUnitTestCase
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

    public function testGenerateIncludesRequestedDateAndNextDateWhenStillSameMonth(): void
    {
        $token = $this->login('dapur');

        $this->createDailyPatient($token, '2026-03-01', 100);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);

        $response->assertStatus(201);
        $response->assertJSONFragment(['message' => 'SPK basah generated successfully.']);

        $json = json_decode($response->getJSON(), true);
        $this->assertSame(['2026-03-01', '2026-03-02'], $json['data']['target_dates']);
        $this->assertSame(105, $json['data']['estimated_patients']);

        $db = Database::connect();
        $spk = $db->table('spk_calculations')->where('id', (int) $json['data']['id'])->get()->getRowArray();
        $this->assertNotNull($spk);
        $this->assertSame('2026-03-01', $spk['target_date_start']);
        $this->assertSame('2026-03-02', $spk['target_date_end']);
        $this->assertSame('basah', $spk['spk_type']);

        $details = $db->table('spk_recommendations')
            ->where('spk_id', (int) $json['data']['id'])
            ->orderBy('target_date', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(2, $details);
        $this->assertSame('2026-03-01', $details[0]['target_date']);
        $this->assertSame('2026-03-02', $details[1]['target_date']);
        $this->assertSame('210.00', number_format((float) $details[0]['required_qty'], 2, '.', ''));
        $this->assertSame('210.00', number_format((float) $details[1]['required_qty'], 2, '.', ''));
        $this->assertSame('100.00', number_format((float) $details[0]['current_stock_qty'], 2, '.', ''));
        $this->assertSame('110.00', number_format((float) $details[0]['system_recommended_qty'], 2, '.', ''));
        $this->assertSame('210.00', number_format((float) $details[1]['system_recommended_qty'], 2, '.', ''));
    }

    public function testGenerateOnMonthEndIncludesOnlyRequestedDate(): void
    {
        $token = $this->login('dapur');

        $this->createDailyPatient($token, '2026-03-31', 80);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-31',
            ]);

        $response->assertStatus(201);
        $json = json_decode($response->getJSON(), true);

        $this->assertSame(['2026-03-31'], $json['data']['target_dates']);
        $this->assertSame(84, $json['data']['estimated_patients']);

        $db = Database::connect();
        $details = $db->table('spk_recommendations')
            ->where('spk_id', (int) $json['data']['id'])
            ->get()
            ->getResultArray();

        $this->assertCount(1, $details);
        $this->assertSame('2026-03-31', $details[0]['target_date']);
    }

    public function testGenerateFailsAtomicallyWhenDailyPatientMissingAndCreatesNoRows(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $beforeHeaderCount = $db->table('spk_calculations')->countAllResults();
        $beforeDetailCount = $db->table('spk_recommendations')->countAllResults();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);

        $response->assertStatus(400);
        $response->assertJSONFragment(['message' => 'Validation failed.']);

        $afterHeaderCount = $db->table('spk_calculations')->countAllResults();
        $afterDetailCount = $db->table('spk_recommendations')->countAllResults();
        $this->assertSame($beforeHeaderCount, $afterHeaderCount);
        $this->assertSame($beforeDetailCount, $afterDetailCount);
    }

    public function testGenerateFailsAtomicallyWhenRecipeMappingMissingAndCreatesNoRows(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $this->createDailyPatient($token, '2026-03-01', 100);
        $db->table('dish_compositions')->where('dish_id', 1)->delete();

        $beforeHeaderCount = $db->table('spk_calculations')->countAllResults();
        $beforeDetailCount = $db->table('spk_recommendations')->countAllResults();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);

        $response->assertStatus(400);
        $response->assertJSONFragment(['message' => 'Validation failed.']);

        $afterHeaderCount = $db->table('spk_calculations')->countAllResults();
        $afterDetailCount = $db->table('spk_recommendations')->countAllResults();
        $this->assertSame($beforeHeaderCount, $afterHeaderCount);
        $this->assertSame($beforeDetailCount, $afterDetailCount);
    }

    public function testGenerateDoesNotCreateStockTransactions(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $this->createDailyPatient($token, '2026-03-01', 100);
        $beforeCount = $db->table('stock_transactions')->countAllResults();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/basah/generate', [
                'service_date' => '2026-03-01',
            ]);

        $response->assertStatus(201);
        $afterCount = $db->table('stock_transactions')->countAllResults();
        $this->assertSame($beforeCount, $afterCount);
    }

    public function testMenuCalendarProjectionMatchesCanonicalResolverShape(): void
    {
        $token = $this->login('admin');
        $date  = '2026-03-12';

        $canonicalResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/menu-calendar?date=' . $date);
        $canonicalResult->assertStatus(200);
        $canonicalJson = json_decode($canonicalResult->getJSON(), true);

        $projectionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/spk/basah/menu-calendar?date=' . $date);
        $projectionResult->assertStatus(200);
        $projectionJson = json_decode($projectionResult->getJSON(), true);

        $this->assertSame($canonicalJson['data'], $projectionJson['data']);
        $this->assertSame($canonicalJson['meta'] ?? null, $projectionJson['meta'] ?? null);
    }

    public function testMenuCalendarProjectionMatchesCanonicalResolverForMonthAndRangeModes(): void
    {
        $token = $this->login('admin');

        if (! function_exists('cal_days_in_month')) {
            $this->markTestSkipped('calendar extension is unavailable in this runtime.');
        }

        $month = '2026-03';
        $canonicalMonthResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/menu-calendar?month=' . $month);
        $canonicalMonthResult->assertStatus(200);
        $canonicalMonthJson = json_decode($canonicalMonthResult->getJSON(), true);

        $projectionMonthResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/spk/basah/menu-calendar?month=' . $month);
        $projectionMonthResult->assertStatus(200);
        $projectionMonthJson = json_decode($projectionMonthResult->getJSON(), true);

        $this->assertSame($canonicalMonthJson['data'], $projectionMonthJson['data']);
        $this->assertSame($canonicalMonthJson['meta'] ?? null, $projectionMonthJson['meta'] ?? null);

        $rangeQuery = 'start_date=2026-03-12&end_date=2026-03-15';
        $canonicalRangeResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/menu-calendar?' . $rangeQuery);
        $canonicalRangeResult->assertStatus(200);
        $canonicalRangeJson = json_decode($canonicalRangeResult->getJSON(), true);

        $projectionRangeResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/spk/basah/menu-calendar?' . $rangeQuery);
        $projectionRangeResult->assertStatus(200);
        $projectionRangeJson = json_decode($projectionRangeResult->getJSON(), true);

        $this->assertSame($canonicalRangeJson['data'], $projectionRangeJson['data']);
        $this->assertSame($canonicalRangeJson['meta'] ?? null, $projectionRangeJson['meta'] ?? null);
    }

    public function testMenuCalendarProjectionRejectsConflictingResolverModesLikeCanonicalEndpoint(): void
    {
        $token = $this->login('admin');

        $canonicalResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/menu-calendar?date=2026-03-12&month=2026-03');
        $canonicalResult->assertStatus(400);
        $canonicalJson = json_decode($canonicalResult->getJSON(), true);

        $projectionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/spk/basah/menu-calendar?date=2026-03-12&month=2026-03');
        $projectionResult->assertStatus(400);
        $projectionJson = json_decode($projectionResult->getJSON(), true);

        $this->assertSame($canonicalJson['message'] ?? null, $projectionJson['message'] ?? null);
        $this->assertSame($canonicalJson['errors']['query'] ?? null, $projectionJson['errors']['query'] ?? null);
    }

    public function testPostStockCreatesOutTransactionAndFinalizesSpk(): void
    {
        $db = Database::connect();

        $basahCategoryId = (new ItemCategoryModel())->getIdByName(ItemCategoryModel::NAME_BASAH);
        $this->assertNotNull($basahCategoryId);

        $spkInsert = $db->table('spk_calculations')->insert([
            'spk_type' => 'basah',
            'calculation_scope' => 'combined_window',
            'scope_key' => 'basah|combined_window|2026-03-01|2026-03-02|' . $basahCategoryId,
            'version' => 1,
            'is_latest' => true,
            'calculation_date' => '2026-03-01',
            'target_date_start' => '2026-03-01',
            'target_date_end' => '2026-03-02',
            'target_month' => null,
            'daily_patient_id' => null,
            'user_id' => 2,
            'category_id' => (int) $basahCategoryId,
            'estimated_patients' => 100,
            'is_finish' => false,
        ]);
        $this->assertTrue($spkInsert);
        $spkId = (int) $db->insertID();

        $recommendationInsert = $db->table('spk_recommendations')->insert([
            'spk_id' => $spkId,
            'item_id' => 1,
            'target_date' => '2026-03-01',
            'current_stock_qty' => 100,
            'required_qty' => 210,
            'system_recommended_qty' => 110,
            'recommended_qty' => 110,
            'is_overridden' => false,
            'override_reason' => null,
            'overridden_by' => null,
            'overridden_at' => null,
        ]);
        $this->assertTrue($recommendationInsert);

        $beforeTxCount = $db->table('stock_transactions')->countAllResults();

        $service = new SpkStockPostingService();
        $result = $service->post($spkId, 'basah', 1, '127.0.0.1');

        $this->assertTrue($result['success']);

        $afterTxCount = $db->table('stock_transactions')->countAllResults();
        $this->assertSame($beforeTxCount + 1, $afterTxCount);

        $spk = $db->table('spk_calculations')->where('id', $spkId)->get()->getRowArray();
        $this->assertNotNull($spk);
        $this->assertSame(1, (int) $spk['is_finish']);

        $postedTx = $db->table('stock_transactions')
            ->where('spk_id', $spkId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        $this->assertNotNull($postedTx);
    }

    public function testPostStockRejectsAlreadyPostedSpk(): void
    {
        $db = Database::connect();

        $basahCategoryId = (new ItemCategoryModel())->getIdByName(ItemCategoryModel::NAME_BASAH);
        $this->assertNotNull($basahCategoryId);

        $spkInsert = $db->table('spk_calculations')->insert([
            'spk_type' => 'basah',
            'calculation_scope' => 'combined_window',
            'scope_key' => 'basah|combined_window|2026-03-03|2026-03-04|' . $basahCategoryId,
            'version' => 1,
            'is_latest' => true,
            'calculation_date' => '2026-03-03',
            'target_date_start' => '2026-03-03',
            'target_date_end' => '2026-03-04',
            'target_month' => null,
            'daily_patient_id' => null,
            'user_id' => 2,
            'category_id' => (int) $basahCategoryId,
            'estimated_patients' => 100,
            'is_finish' => true,
        ]);
        $this->assertTrue($spkInsert);
        $spkId = (int) $db->insertID();

        $service = new SpkStockPostingService();
        $result = $service->post($spkId, 'basah', 1, '127.0.0.1');

        $this->assertFalse($result['success']);
        $this->assertSame(400, (int) $result['status_code']);
        $this->assertSame('Validation failed.', $result['message']);
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
