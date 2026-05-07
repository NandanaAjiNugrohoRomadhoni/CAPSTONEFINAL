<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use App\Services\SpkStockPostingService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class SpkKeringPengemasTest extends CIUnitTestCase
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

    public function testGenerateUsesPreviousMonthPostedUsageAndPersistsSnapshotFields(): void
    {
        $token = $this->login('dapur');
        $db = Database::connect();

        $postedStatusId = (int) $db->table('approval_statuses')->where('name', 'APPROVED')->get()->getRowArray()['id'];
        $pendingStatusId = (int) $db->table('approval_statuses')->where('name', 'PENDING')->get()->getRowArray()['id'];
        $outTypeId = (int) $db->table('transaction_types')->where('name', 'OUT')->get()->getRowArray()['id'];

        $itemHighUsage = $db->table('items')->where('name', 'Beras Kering')->get()->getRowArray();
        $itemLowUsage  = $db->table('items')->where('name', 'Plastik Pengemas')->get()->getRowArray();
        $this->assertNotNull($itemHighUsage);
        $this->assertNotNull($itemLowUsage);

        $db->table('stock_transactions')->insert([
            'type_id'            => $outTypeId,
            'transaction_date'   => '2026-03-05',
            'is_revision'        => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $postedStatusId,
            'approved_by'        => null,
            'user_id'            => 1,
            'spk_id'             => null,
        ]);
        $tx1 = (int) $db->insertID();
        $db->table('stock_transaction_details')->insert([
            'transaction_id' => $tx1,
            'item_id'        => (int) $itemHighUsage['id'],
            'qty'            => 500,
            'input_qty'      => 500,
            'input_unit'     => 'base',
        ]);

        $db->table('stock_transactions')->insert([
            'type_id'            => $outTypeId,
            'transaction_date'   => '2026-03-10',
            'is_revision'        => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $postedStatusId,
            'approved_by'        => null,
            'user_id'            => 1,
            'spk_id'             => null,
        ]);
        $tx2 = (int) $db->insertID();
        $db->table('stock_transaction_details')->insert([
            'transaction_id' => $tx2,
            'item_id'        => (int) $itemLowUsage['id'],
            'qty'            => 100,
            'input_qty'      => 100,
            'input_unit'     => 'base',
        ]);

        $db->table('stock_transactions')->insert([
            'type_id'            => $outTypeId,
            'transaction_date'   => '2026-03-12',
            'is_revision'        => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $pendingStatusId,
            'approved_by'        => null,
            'user_id'            => 1,
            'spk_id'             => null,
        ]);
        $ignoredPendingTx = (int) $db->insertID();
        $db->table('stock_transaction_details')->insert([
            'transaction_id' => $ignoredPendingTx,
            'item_id'        => (int) $itemHighUsage['id'],
            'qty'            => 999,
            'input_qty'      => 999,
            'input_unit'     => 'base',
        ]);

        $db->table('stock_transactions')->insert([
            'type_id'            => $outTypeId,
            'transaction_date'   => '2026-04-02',
            'is_revision'        => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $postedStatusId,
            'approved_by'        => null,
            'user_id'            => 1,
            'spk_id'             => null,
        ]);
        $ignoredCurrentMonthTx = (int) $db->insertID();
        $db->table('stock_transaction_details')->insert([
            'transaction_id' => $ignoredCurrentMonthTx,
            'item_id'        => (int) $itemLowUsage['id'],
            'qty'            => 777,
            'input_qty'      => 777,
            'input_unit'     => 'base',
        ]);

        $stockTxCountBefore = $db->table('stock_transactions')->countAllResults();

        $generate = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/spk/kering-pengemas/generate', [
                'target_month' => '2026-04',
            ]);

        $generate->assertStatus(201);
        $generate->assertJSONFragment(['message' => 'SPK kering/pengemas generated successfully.']);

        $json = json_decode($generate->getJSON(), true);
        $spkId = (int) $json['data']['id'];

        $header = $db->table('spk_calculations')->where('id', $spkId)->get()->getRowArray();
        $this->assertNotNull($header);
        $this->assertSame('kering_pengemas', $header['spk_type']);
        $this->assertSame('monthly', $header['calculation_scope']);
        $this->assertSame('2026-04', $header['target_month']);

        $details = $db->table('spk_recommendations')
            ->where('spk_id', $spkId)
            ->orderBy('item_id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(2, $details);

        $highUsageDetail = null;
        $lowUsageDetail = null;
        foreach ($details as $detail) {
            if ((int) $detail['item_id'] === (int) $itemHighUsage['id']) {
                $highUsageDetail = $detail;
            }

            if ((int) $detail['item_id'] === (int) $itemLowUsage['id']) {
                $lowUsageDetail = $detail;
            }
        }

        $this->assertNotNull($highUsageDetail);
        $this->assertNotNull($lowUsageDetail);

        $this->assertSame('80.00', number_format((float) $highUsageDetail['current_stock_qty'], 2, '.', ''));
        $this->assertSame('550.00', number_format((float) $highUsageDetail['required_qty'], 2, '.', ''));
        $this->assertSame('470.00', number_format((float) $highUsageDetail['system_recommended_qty'], 2, '.', ''));
        $this->assertSame('470.00', number_format((float) $highUsageDetail['recommended_qty'], 2, '.', ''));

        $this->assertSame('200.00', number_format((float) $lowUsageDetail['current_stock_qty'], 2, '.', ''));
        $this->assertSame('110.00', number_format((float) $lowUsageDetail['required_qty'], 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $lowUsageDetail['system_recommended_qty'], 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $lowUsageDetail['recommended_qty'], 2, '.', ''));

        $stockTxCountAfter = $db->table('stock_transactions')->countAllResults();
        $this->assertSame($stockTxCountBefore, $stockTxCountAfter);

        $readToken = $this->login('gudang');

        $show = $this->withHeaders(['Authorization' => 'Bearer ' . $readToken])
            ->get('api/v1/spk/kering-pengemas/history/' . $spkId);
        $show->assertStatus(200);

        $showJson = json_decode($show->getJSON(), true);
        $this->assertSame($spkId, $showJson['data']['id']);
        $this->assertArrayHasKey('items', $showJson['data']);
        $this->assertNotEmpty($showJson['data']['items']);
        $this->assertArrayHasKey('current_stock_qty', $showJson['data']['items'][0]);
        $this->assertArrayHasKey('required_qty', $showJson['data']['items'][0]);
        $this->assertArrayHasKey('system_recommended_qty', $showJson['data']['items'][0]);
        $this->assertArrayHasKey('final_recommended_qty', $showJson['data']['items'][0]);
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
            ->get('api/v1/spk/kering-pengemas/menu-calendar?date=' . $date);
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
            ->get('api/v1/spk/kering-pengemas/menu-calendar?month=' . $month);
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
            ->get('api/v1/spk/kering-pengemas/menu-calendar?' . $rangeQuery);
        $projectionRangeResult->assertStatus(200);
        $projectionRangeJson = json_decode($projectionRangeResult->getJSON(), true);

        $this->assertSame($canonicalRangeJson['data'], $projectionRangeJson['data']);
        $this->assertSame($canonicalRangeJson['meta'] ?? null, $projectionRangeJson['meta'] ?? null);
    }

    public function testMenuCalendarProjectionRejectsMalformedDateLikeCanonicalEndpoint(): void
    {
        $token = $this->login('admin');

        $canonicalResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/menu-calendar?date=2026/03/12');
        $canonicalResult->assertStatus(400);
        $canonicalJson = json_decode($canonicalResult->getJSON(), true);

        $projectionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/spk/kering-pengemas/menu-calendar?date=2026/03/12');
        $projectionResult->assertStatus(400);
        $projectionJson = json_decode($projectionResult->getJSON(), true);

        $this->assertSame($canonicalJson['message'] ?? null, $projectionJson['message'] ?? null);
        $this->assertSame($canonicalJson['errors']['date'] ?? null, $projectionJson['errors']['date'] ?? null);
    }

    public function testPostStockCreatesOutTransactionAndFinalizesSpkKeringPengemas(): void
    {
        $db = Database::connect();

        $spkInsert = $db->table('spk_calculations')->insert([
            'spk_type' => 'kering_pengemas',
            'calculation_scope' => 'monthly',
            'scope_key' => 'kering_pengemas|monthly|2026-04|2',
            'version' => 1,
            'is_latest' => true,
            'calculation_date' => '2026-04-01',
            'target_date_start' => '2026-04-01',
            'target_date_end' => '2026-04-30',
            'target_month' => '2026-04',
            'daily_patient_id' => null,
            'user_id' => 2,
            'category_id' => 2,
            'estimated_patients' => 0,
            'is_finish' => false,
        ]);
        $this->assertTrue($spkInsert);
        $spkId = (int) $db->insertID();

        $recommendationInsert = $db->table('spk_recommendations')->insert([
            'spk_id' => $spkId,
            'item_id' => 1,
            'target_date' => null,
            'current_stock_qty' => 80,
            'required_qty' => 550,
            'system_recommended_qty' => 470,
            'recommended_qty' => 470,
            'is_overridden' => false,
            'override_reason' => null,
            'overridden_by' => null,
            'overridden_at' => null,
        ]);
        $this->assertTrue($recommendationInsert);

        $beforeTxCount = $db->table('stock_transactions')->countAllResults();

        $service = new SpkStockPostingService();
        $result = $service->post($spkId, 'kering_pengemas', 1, '127.0.0.1');

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

        $db->table('item_units')->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
            ['name' => 'pack'],
        ]);

        $keringCategoryId = (int) $db->table('item_categories')->where('name', 'KERING')->get()->getRowArray()['id'];
        $pengemasCategoryId = (int) $db->table('item_categories')->where('name', 'PENGEMAS')->get()->getRowArray()['id'];

        $gramUnitId = (int) $db->table('item_units')->where('name', 'gram')->get()->getRowArray()['id'];
        $kgUnitId = (int) $db->table('item_units')->where('name', 'kg')->get()->getRowArray()['id'];
        $packUnitId = (int) $db->table('item_units')->where('name', 'pack')->get()->getRowArray()['id'];

        $db->table('items')->insertBatch([
            [
                'item_category_id'     => $keringCategoryId,
                'name'                 => 'Beras Kering',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramUnitId,
                'item_unit_convert_id' => $kgUnitId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 80,
            ],
            [
                'item_category_id'     => $pengemasCategoryId,
                'name'                 => 'Plastik Pengemas',
                'unit_base'            => 'pack',
                'unit_convert'         => 'pack',
                'item_unit_base_id'    => $packUnitId,
                'item_unit_convert_id' => $packUnitId,
                'conversion_base'      => 1,
                'is_active'            => true,
                'qty'                  => 200,
            ],
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
