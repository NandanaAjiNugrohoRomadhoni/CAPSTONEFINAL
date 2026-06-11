<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\ApprovalStatusModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use App\Models\SpkCalculationModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class ReportsTest extends CIUnitTestCase
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
        $this->seedLookups();
        $this->seedItems();
        $this->seedSpkData();
        $this->seedTransactions();
    }

    protected function seedRoles(): void
    {
        (new RoleModel())->insertBatch([
            ['name' => 'admin'],
            ['name' => 'dapur'],
            ['name' => 'gudang'],
        ]);
    }

    protected function seedUsers(): void
    {
        $roleModel = new RoleModel();
        $userProvider = new AppUserProvider();

        foreach ([
            ['role' => 'admin', 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com'],
            ['role' => 'gudang', 'name' => 'Gudang User', 'username' => 'gudang', 'email' => 'gudang@example.com'],
            ['role' => 'dapur', 'name' => 'Dapur User', 'username' => 'dapur', 'email' => 'dapur@example.com'],
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

    protected function seedLookups(): void
    {
        (new ItemCategoryModel())->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);

        (new ItemUnitModel())->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
        ]);

        (new TransactionTypeModel())->insertBatch([
            ['name' => 'IN'],
            ['name' => 'OUT'],
            ['name' => 'RETURN_IN'],
            ['name' => 'OPNAME_ADJUSTMENT'],
        ]);

        (new ApprovalStatusModel())->insertBatch([
            ['name' => 'APPROVED'],
            ['name' => 'PENDING'],
            ['name' => 'REJECTED'],
        ]);
    }

    protected function seedItems(): void
    {
        $db = Database::connect();
        $categoryModel = new ItemCategoryModel();
        $itemUnitModel = new ItemUnitModel();

        $basahId = $categoryModel->getIdByName('BASAH');
        $keringId = $categoryModel->getIdByName('KERING');
        $gramId = $itemUnitModel->getIdByName('gram');
        $kgId = $itemUnitModel->getIdByName('kg');

        $db->table('items')->insertBatch([
            [
                'item_category_id'     => $keringId,
                'name'                 => 'Beras',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 1000,
                'updated_at'           => '2026-04-15 10:00:00',
                'created_at'           => '2026-04-10 10:00:00',
            ],
            [
                'item_category_id'     => $basahId,
                'name'                 => 'Ayam',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 500,
                'updated_at'           => '2026-04-25 10:00:00',
                'created_at'           => '2026-04-10 10:00:00',
            ],
        ]);
    }

    protected function seedSpkData(): void
    {
        $spkModel = new SpkCalculationModel();
        $categoryModel = new ItemCategoryModel();

        $basahId = $categoryModel->getIdByName('BASAH');
        $keringId = $categoryModel->getIdByName('KERING');

        $spkOne = $spkModel->insert([
            'spk_type' => SpkCalculationModel::TYPE_BASAH,
            'calculation_scope' => SpkCalculationModel::SCOPE_COMBINED_WINDOW,
            'scope_key' => 'basah|combined_window|2026-04-12|2026-04-13|' . $basahId,
            'version' => 1,
            'is_latest' => true,
            'calculation_date' => '2026-04-12',
            'target_date_start' => '2026-04-12',
            'target_date_end' => '2026-04-13',
            'target_month' => null,
            'daily_patient_id' => null,
            'user_id' => 1,
            'category_id' => $basahId,
            'estimated_patients' => 100,
            'is_finish' => false,
        ], true);

        $spkTwo = $spkModel->insert([
            'spk_type' => SpkCalculationModel::TYPE_KERING_PENGEMAS,
            'calculation_scope' => SpkCalculationModel::SCOPE_MONTHLY,
            'scope_key' => 'kering_pengemas|monthly|2026-04|' . $keringId,
            'version' => 1,
            'is_latest' => true,
            'calculation_date' => '2026-04-18',
            'target_date_start' => '2026-04-01',
            'target_date_end' => '2026-04-30',
            'target_month' => '2026-04',
            'daily_patient_id' => null,
            'user_id' => 1,
            'category_id' => $keringId,
            'estimated_patients' => 0,
            'is_finish' => false,
        ], true);

        $spkThree = $spkModel->insert([
            'spk_type' => SpkCalculationModel::TYPE_BASAH,
            'calculation_scope' => SpkCalculationModel::SCOPE_COMBINED_WINDOW,
            'scope_key' => 'basah|combined_window|2026-05-02|2026-05-03|' . $basahId,
            'version' => 1,
            'is_latest' => true,
            'calculation_date' => '2026-05-02',
            'target_date_start' => '2026-05-02',
            'target_date_end' => '2026-05-03',
            'target_month' => null,
            'daily_patient_id' => null,
            'user_id' => 1,
            'category_id' => $basahId,
            'estimated_patients' => 80,
            'is_finish' => false,
        ], true);

        $db = Database::connect();
        $db->table('spk_recommendations')->insertBatch([
            [
                'spk_id' => $spkOne,
                'item_id' => 1,
                'target_date' => '2026-04-12',
                'current_stock_qty' => 1000,
                'required_qty' => 140,
                'system_recommended_qty' => 100,
                'recommended_qty' => 100,
                'is_overridden' => false,
            ],
            [
                'spk_id' => $spkTwo,
                'item_id' => 1,
                'target_date' => null,
                'current_stock_qty' => 1000,
                'required_qty' => 340,
                'system_recommended_qty' => 300,
                'recommended_qty' => 300,
                'is_overridden' => false,
            ],
            [
                'spk_id' => $spkThree,
                'item_id' => 1,
                'target_date' => '2026-05-02',
                'current_stock_qty' => 1000,
                'required_qty' => 90,
                'system_recommended_qty' => 80,
                'recommended_qty' => 80,
                'is_overridden' => false,
            ],
        ]);
    }

    protected function seedTransactions(): void
    {
        $db = Database::connect();
        $typeModel = new TransactionTypeModel();
        $statusModel = new ApprovalStatusModel();

        $outTypeId = $typeModel->getIdByName('OUT');
        $inTypeId = $typeModel->getIdByName('IN');
        $opnameAdjustmentTypeId = $typeModel->getIdByName('OPNAME_ADJUSTMENT');
        $approvedId = $statusModel->getIdByName('APPROVED');
        $pendingId = $statusModel->getIdByName('PENDING');

        $db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-04-12',
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => 1,
        ]);
        $txOne = (int) $db->insertID();

        $db->table('stock_transactions')->insert([
            'type_id' => $inTypeId,
            'transaction_date' => '2026-04-13',
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => null,
        ]);
        $txTwo = (int) $db->insertID();

        $db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-04-19',
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => 2,
        ]);
        $txThree = (int) $db->insertID();

        $db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-04-25',
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => 1,
        ]);
        $txFour = (int) $db->insertID();

        $db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-04-18',
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $pendingId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => 2,
        ]);
        $txFive = (int) $db->insertID();

        $db->table('stock_transactions')->insert([
            'type_id' => $opnameAdjustmentTypeId,
            'transaction_date' => '2026-04-14',
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => null,
            'reason' => 'Manual stock correction outside opname posting',
        ]);
        $txSix = (int) $db->insertID();

        $db->table('stock_transactions')->insert([
            'type_id' => $outTypeId,
            'transaction_date' => '2026-04-16',
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => null,
            'reason' => 'Legacy opname posting line',
        ]);
        $txSeven = (int) $db->insertID();

        $db->table('stock_transactions')->insert([
            'type_id' => $opnameAdjustmentTypeId,
            'transaction_date' => '2026-04-16',
            'is_revision' => 0,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedId,
            'approved_by' => null,
            'user_id' => 1,
            'spk_id' => null,
            'reason' => 'Stock opname #99 posting for item #1',
        ]);
        $txEight = (int) $db->insertID();

        $db->table('stock_transaction_details')->insertBatch([
            ['transaction_id' => $txOne, 'item_id' => 1, 'qty' => 120, 'input_qty' => 120, 'input_unit' => 'base'],
            ['transaction_id' => $txTwo, 'item_id' => 1, 'qty' => 80, 'input_qty' => 80, 'input_unit' => 'base'],
            ['transaction_id' => $txThree, 'item_id' => 1, 'qty' => 260, 'input_qty' => 260, 'input_unit' => 'base'],
            ['transaction_id' => $txFour, 'item_id' => 1, 'qty' => 999, 'input_qty' => 999, 'input_unit' => 'base'],
            ['transaction_id' => $txFive, 'item_id' => 1, 'qty' => 500, 'input_qty' => 500, 'input_unit' => 'base'],
            ['transaction_id' => $txSix, 'item_id' => 1, 'qty' => 30, 'input_qty' => 30, 'input_unit' => 'base'],
            ['transaction_id' => $txSeven, 'item_id' => 1, 'qty' => 40, 'input_qty' => 40, 'input_unit' => 'base'],
            ['transaction_id' => $txEight, 'item_id' => 1, 'qty' => 40, 'input_qty' => 40, 'input_unit' => 'base'],
        ]);
    }

    protected function insertItem(string $categoryName, string $name, float $qty = 0, string $updatedAt = '2026-04-10 10:00:00'): int
    {
        $db = Database::connect();
        $categoryModel = new ItemCategoryModel();
        $itemUnitModel = new ItemUnitModel();

        $db->table('items')->insert([
            'item_category_id'     => $categoryModel->getIdByName($categoryName),
            'name'                 => $name,
            'unit_base'            => 'gram',
            'unit_convert'         => 'kg',
            'item_unit_base_id'    => $itemUnitModel->getIdByName('gram'),
            'item_unit_convert_id' => $itemUnitModel->getIdByName('kg'),
            'conversion_base'      => 1000,
            'is_active'            => true,
            'qty'                  => $qty,
            'updated_at'           => $updatedAt,
            'created_at'           => '2026-04-10 10:00:00',
        ]);

        return (int) $db->insertID();
    }

    protected function insertMonthlySnapshot(string $periodMonth, int $itemId, float $openingQty): void
    {
        Database::connect()->table('monthly_stock_snapshots')->insert([
            'period_month' => $periodMonth,
            'item_id'      => $itemId,
            'opening_qty'  => $openingQty,
        ]);
    }

    protected function insertStockTransactionRow(string $typeName, string $transactionDate, int $itemId, float $qty, ?string $reason = null, ?int $approvalStatusId = null): int
    {
        $db = Database::connect();
        $typeModel = new TransactionTypeModel();
        $statusModel = new ApprovalStatusModel();

        $db->table('stock_transactions')->insert([
            'type_id'             => $typeModel->getIdByName($typeName),
            'transaction_date'    => $transactionDate,
            'is_revision'         => 0,
            'parent_transaction_id' => null,
            'approval_status_id'  => $approvalStatusId ?? $statusModel->getIdByName('APPROVED'),
            'approved_by'         => null,
            'user_id'             => 1,
            'spk_id'              => null,
            'reason'              => $reason,
        ]);
        $transactionId = (int) $db->insertID();

        $db->table('stock_transaction_details')->insert([
            'transaction_id' => $transactionId,
            'item_id'        => $itemId,
            'qty'            => $qty,
            'input_qty'      => $qty,
            'input_unit'     => 'base',
        ]);

        return $transactionId;
    }

    protected function login(string $username): string
    {
        $result = $this->withBodyFormat('json')->post('api/v1/auth/login', [
            'username' => $username,
            'password' => 'password123',
        ]);

        $json = json_decode($result->getJSON(), true);

        return $json['access_token'];
    }

    public function testStockReportReturnsDeterministicTotalsForSeededPeriod(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/stocks?period_start=2026-04-10&period_end=2026-04-20');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('stocks', $json['data']['report_type']);
        $this->assertSame(1, $json['data']['summary']['total_items']);
        $this->assertSame(1, $json['data']['summary']['active_items']);
        $this->assertEquals(1000.0, $json['data']['summary']['total_qty']);
        $this->assertCount(1, $json['data']['rows']);
        $this->assertSame('Beras', $json['data']['rows'][0]['item_name']);
    }

    public function testTransactionReportReturnsDeterministicTotalsForSeededPeriod(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/transactions?period_start=2026-04-10&period_end=2026-04-20');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('transactions', $json['data']['report_type']);
        $this->assertSame(6, $json['data']['summary']['total_rows']);
        $this->assertEquals(1030.0, $json['data']['summary']['total_qty']);

        $rows = $json['data']['rows'];
        $opnameRows = array_values(array_filter($rows, static fn(array $row): bool => $row['type_name'] === 'OPNAME_ADJUSTMENT'));
        $this->assertCount(1, $opnameRows);
        $this->assertEquals(30.0, $opnameRows[0]['qty']);
        $this->assertSame('2026-04-14', $opnameRows[0]['transaction_date']);
    }

    public function testTransactionReportDeduplicatesOpnamePostingAdjustmentsWhenLegacyOverlapExists(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/transactions?period_start=2026-04-16&period_end=2026-04-16');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('transactions', $json['data']['report_type']);
        $this->assertSame(1, $json['data']['summary']['total_rows']);
        $this->assertEquals(40.0, $json['data']['summary']['total_qty']);

        $row = $json['data']['rows'][0];
        $this->assertSame('OUT', $row['type_name']);
        $this->assertEquals(40.0, $row['qty']);
        $this->assertSame('2026-04-16', $row['transaction_date']);
    }

    public function testSpkHistoryReportReturnsDeterministicTotalsForSeededPeriod(): void
    {
        $token = $this->login('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/spk-history?period_start=2026-04-10&period_end=2026-04-20');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('spk-history', $json['data']['report_type']);
        $this->assertSame(2, $json['data']['summary']['total_spk']);
        $this->assertCount(2, $json['data']['rows']);
        $this->assertEquals(100.0, $json['data']['rows'][0]['total_recommended_qty']);
        $this->assertEquals(300.0, $json['data']['rows'][1]['total_recommended_qty']);

        $this->assertArrayHasKey('compatibility_projection', $json['data']);
        $this->assertSame(
            ['id', 'calculation_date', 'target_date_start', 'target_date_end', 'daily_patient_id', 'user_id', 'category_id', 'estimated_patients', 'is_finish'],
            $json['data']['compatibility_projection']['contract']['spk_calculations']
        );
        $this->assertSame(
            ['id', 'spk_id', 'item_id', 'qty'],
            $json['data']['compatibility_projection']['contract']['spk_recommendations']
        );

        $projectionRows = $json['data']['compatibility_projection']['rows'];
        $this->assertCount(2, $projectionRows);
        $this->assertSame(1, $projectionRows[0]['spk_calculation']['id']);
        $this->assertCount(1, $projectionRows[0]['spk_recommendations']);
        $this->assertSame(100.0, (float) $projectionRows[0]['spk_recommendations'][0]['qty']);
        $this->assertSame('srs-compat-v1', $projectionRows[0]['meta']['projection_version']);
    }

    public function testEvaluationReportComputesPlanRealizationVarianceDeterministically(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/evaluation?period_start=2026-04-10&period_end=2026-04-20');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('evaluation', $json['data']['report_type']);
        $this->assertSame(2, $json['data']['summary']['total_spk']);
        $this->assertEquals(400.0, $json['data']['summary']['planned_total_qty']);
        $this->assertEquals(380.0, $json['data']['summary']['realization_total_qty']);
        $this->assertEquals(-20.0, $json['data']['summary']['variance_total_qty']);

        $this->assertEquals(100.0, $json['data']['rows'][0]['planned_qty']);
        $this->assertEquals(120.0, $json['data']['rows'][0]['realization_qty']);
        $this->assertEquals(20.0, $json['data']['rows'][0]['variance_qty']);
        $this->assertEquals(300.0, $json['data']['rows'][1]['planned_qty']);
        $this->assertEquals(260.0, $json['data']['rows'][1]['realization_qty']);
        $this->assertEquals(-40.0, $json['data']['rows'][1]['variance_qty']);
    }

    public function testReportValidationRejectsMalformedPeriod(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/stocks?period_start=2026-13-99&period_end=2026-04-20');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('period_start', $json['errors']);
    }

    public function testSpkHistoryReportRequiresAuthentication(): void
    {
        $result = $this->get('api/v1/reports/spk-history?period_start=2026-04-10&period_end=2026-04-20');

        $result->assertStatus(401);
    }

    public function testReportValidationRejectsForbiddenQueryShapeForCompatibilityCoverage(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/spk-history?period_start=2026-04-10&period_end=2026-04-20&unexpected=value');

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertArrayHasKey('query', $json['errors']);
    }

    public function testMonthlyStockExportRequiresPeriodStart(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/monthly-stock-export?period_end=2026-04-30');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('period_start', $json['errors']);
    }

    public function testMonthlyStockExportRequiresPeriodEnd(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/monthly-stock-export?period_start=2026-04-01');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('period_end', $json['errors']);
    }

    public function testMonthlyStockExportRejectsUnsupportedQueryParameters(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/monthly-stock-export?period_start=2026-04-01&period_end=2026-04-30&unexpected=value');

        $result->assertStatus(400);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Validation failed.', $json['message']);
        $this->assertArrayHasKey('query', $json['errors']);
    }

    public function testMonthlyStockExportReturnsDailyRowsUsingTransactionDateAndItemLevelCategoryFilters(): void
    {
        $token = $this->login('gudang');
        $categoryModel = new ItemCategoryModel();
        $approvedId = (new ApprovalStatusModel())->getIdByName('APPROVED');

        $pengemasItemId = $this->insertItem('PENGEMAS', 'Plastik Vakum', 250);

        $this->insertMonthlySnapshot('2026-04-01', 1, 1000);
        $this->insertMonthlySnapshot('2026-04-01', 2, 500);
        $this->insertMonthlySnapshot('2026-04-01', $pengemasItemId, 250);

        $this->insertStockTransactionRow('RETURN_IN', '2026-04-11', 1, 15);
        $this->insertStockTransactionRow('IN', '2026-04-12', 2, 25);
        $this->insertStockTransactionRow('OUT', '2026-04-12', 2, 10);
        $this->insertStockTransactionRow('IN', '2026-04-12', $pengemasItemId, 40);
        $this->insertStockTransactionRow('OUT', '2026-04-13', $pengemasItemId, 5);
        $this->insertStockTransactionRow('OUT', '2026-04-14', 2, 99, null, $approvedId);
        $this->insertStockTransactionRow('OPNAME_ADJUSTMENT', '2026-04-15', 2, 30, 'Manual stock correction outside opname posting', $approvedId);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/monthly-stock-export?period_start=2026-04-01&period_end=2026-04-30');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('monthly-stock-export', $json['data']['report_type']);
        $this->assertSame('1-30', $json['data']['periode']);
        $this->assertSame(3, $json['data']['summary']['total_items']);
        $this->assertSame(30, $json['data']['summary']['total_days']);

        $rows = $json['data']['rows'];
        $this->assertCount(3, $rows);
        $this->assertSame(['KERING', 'BASAH', 'PENGEMAS'], array_column($rows, 'category_name'));

        $keringRow = $rows[0];
        $this->assertSame(1, $keringRow['item_id']);
        $this->assertSame('Beras', $keringRow['nama_bahan_makanan']);
        $this->assertSame('KERING', $keringRow['category_name']);
        $this->assertSame('gram', $keringRow['satuan']);
        $this->assertEquals(1000.0, $keringRow['stok_awal']);
        $this->assertSame([
            ['tanggal' => 11, 'masuk' => 15, 'keluar' => 0, 'sisa' => 1015],
            ['tanggal' => 12, 'masuk' => 0, 'keluar' => 120, 'sisa' => 895],
            ['tanggal' => 13, 'masuk' => 80, 'keluar' => 0, 'sisa' => 975],
            ['tanggal' => 19, 'masuk' => 0, 'keluar' => 260, 'sisa' => 715],
            ['tanggal' => 25, 'masuk' => 0, 'keluar' => 999, 'sisa' => -284],
        ], $keringRow['harian']);

        $basahRow = $rows[1];
        $this->assertSame(2, $basahRow['item_id']);
        $this->assertSame('Ayam', $basahRow['nama_bahan_makanan']);
        $this->assertSame('BASAH', $basahRow['category_name']);
        $this->assertEquals(500.0, $basahRow['stok_awal']);
        $this->assertSame([
            ['tanggal' => 12, 'masuk' => 25, 'keluar' => 10, 'sisa' => 515],
            ['tanggal' => 14, 'masuk' => 0, 'keluar' => 99, 'sisa' => 416],
        ], $basahRow['harian']);

        $pengemasRow = $rows[2];
        $this->assertSame($pengemasItemId, $pengemasRow['item_id']);
        $this->assertSame('PENGEMAS', $pengemasRow['category_name']);
        $this->assertEquals(250.0, $pengemasRow['stok_awal']);
        $this->assertSame([
            ['tanggal' => 12, 'masuk' => 40, 'keluar' => 0, 'sisa' => 290],
            ['tanggal' => 13, 'masuk' => 0, 'keluar' => 5, 'sisa' => 285],
        ], $pengemasRow['harian']);

        $categoryId = $categoryModel->getIdByName('PENGEMAS');
        $filteredResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/monthly-stock-export?period_start=2026-04-01&period_end=2026-04-30&category_id=' . $categoryId);

        $filteredResult->assertStatus(200);
        $filteredJson = json_decode($filteredResult->getJSON(), true);
        $this->assertSame(['category_id' => $categoryId], $filteredJson['data']['filters']);
        $this->assertCount(1, $filteredJson['data']['rows']);
        $this->assertSame('PENGEMAS', $filteredJson['data']['rows'][0]['category_name']);
    }

    public function testMonthlyStockExportFiltersByItemIdAndReturnsNullOpeningStockWhenSnapshotMissing(): void
    {
        $token = $this->login('admin');

        $this->insertStockTransactionRow('IN', '2026-04-20', 2, 50);
        $this->insertStockTransactionRow('OUT', '2026-04-21', 2, 20);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/reports/monthly-stock-export?period_start=2026-04-01&period_end=2026-04-30&item_id=2');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame(['item_id' => 2], $json['data']['filters']);
        $this->assertSame(1, $json['data']['summary']['total_items']);
        $this->assertCount(1, $json['data']['rows']);
        $this->assertSame(2, $json['data']['rows'][0]['item_id']);
        $this->assertNull($json['data']['rows'][0]['stok_awal']);
        $this->assertSame([
            ['tanggal' => 20, 'masuk' => 50, 'keluar' => 0, 'sisa' => null],
            ['tanggal' => 21, 'masuk' => 0, 'keluar' => 20, 'sisa' => null],
        ], $json['data']['rows'][0]['harian']);
    }
}
