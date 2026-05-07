<?php

namespace Tests\Unit;

use App\Models\ApprovalStatusModel;
use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class HistoricalOpnameBackfillCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $DBGroup     = 'tests';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedUsers();
        $this->seedItemCategories();
        $this->seedItemUnits();
        $this->seedItems();
        $this->seedTransactionTypes();
        $this->seedApprovalStatuses();
        $this->seedHistoricalPostedOpnameRows();
    }

    public function testBackfillCommandCreatesLedgerRowsAndIsIdempotent(): void
    {
        $db = Database::connect();

        $before = $db->table('stock_transactions')
            ->where('legacy_source_table', 'stock_opname_details')
            ->countAllResults();
        $this->assertSame(0, $before);

        command('opname:backfill-historical-ledger --from 2026-01-01 --to 2026-01-31');

        $sourceDetails = $db->table('stock_opname_details sod')
            ->select('sod.id AS detail_id, sod.stock_opname_id, sod.item_id, sod.variance_qty')
            ->join('stock_opnames so', 'so.id = sod.stock_opname_id', 'inner')
            ->where('so.state', 'POSTED')
            ->where('so.opname_date >=', '2026-01-01')
            ->where('so.opname_date <=', '2026-01-31')
            ->where('sod.variance_qty !=', 0)
            ->orderBy('sod.id', 'ASC')
            ->get()
            ->getResultArray();

        $expectedDetailIds = array_values(array_map(
            static fn (array $row): int => (int) $row['detail_id'],
            $sourceDetails,
        ));
        $expectedRowCount = count($expectedDetailIds);

        $rowsAfterFirst = $db->table('stock_transactions st')
            ->select('st.id, st.type_id, st.approval_status_id, st.reason, st.legacy_source_table, st.legacy_source_id, st.legacy_source_detail_id')
            ->where('st.legacy_source_table', 'stock_opname_details')
            ->whereIn('st.legacy_source_detail_id', $expectedDetailIds)
            ->orderBy('st.legacy_source_detail_id', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount($expectedRowCount, $rowsAfterFirst);
        $typeId = (new TransactionTypeModel())->getIdByName(TransactionTypeModel::NAME_OPNAME_ADJUSTMENT);
        $statusId = (new ApprovalStatusModel())->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $this->assertNotNull($typeId);
        $this->assertNotNull($statusId);

        foreach ($rowsAfterFirst as $row) {
            $this->assertSame((int) $typeId, (int) $row['type_id']);
            $this->assertSame((int) $statusId, (int) $row['approval_status_id']);
            $this->assertSame('stock_opname_details', (string) $row['legacy_source_table']);
            $this->assertContains((int) $row['legacy_source_detail_id'], $expectedDetailIds);
            $this->assertStringContainsString('Historical stock opname backfill', (string) $row['reason']);
            $this->assertMatchesRegularExpression('/detail\s+#\d+\s+item\s+#\d+/', (string) $row['reason']);
        }

        $txByMarker = [];
        foreach ($rowsAfterFirst as $row) {
            $markerKey = sprintf(
                '%s:%d:%d',
                (string) $row['legacy_source_table'],
                (int) $row['legacy_source_id'],
                (int) $row['legacy_source_detail_id'],
            );
            $txByMarker[$markerKey] = (int) $row['id'];
        }

        foreach ($sourceDetails as $sourceDetail) {
            $markerKey = sprintf(
                '%s:%d:%d',
                'stock_opname_details',
                (int) $sourceDetail['stock_opname_id'],
                (int) $sourceDetail['detail_id'],
            );

            $this->assertArrayHasKey($markerKey, $txByMarker);

            $detailRow = $db->table('stock_transaction_details')
                ->where('transaction_id', $txByMarker[$markerKey])
                ->where('item_id', (int) $sourceDetail['item_id'])
                ->get()
                ->getRowArray();

            $this->assertNotNull($detailRow);
            $this->assertSame(
                number_format(abs((float) $sourceDetail['variance_qty']), 2, '.', ''),
                number_format((float) $detailRow['qty'], 2, '.', ''),
            );
        }

        command('opname:backfill-historical-ledger --from 2026-01-01 --to 2026-01-31');

        $afterSecond = $db->table('stock_transactions')
            ->where('legacy_source_table', 'stock_opname_details')
            ->whereIn('legacy_source_detail_id', $expectedDetailIds)
            ->countAllResults();
        $this->assertSame($expectedRowCount, $afterSecond, 'Second command run must create zero additional rows.');

        $service = new \App\Services\HistoricalOpnameBackfillService();
        $thirdRun = $service->backfill('2026-01-01', '2026-01-31');
        $this->assertTrue($thirdRun['success']);
        $this->assertSame(0, (int) $thirdRun['data']['created_rows']);
        $this->assertSame($expectedRowCount, (int) $thirdRun['data']['skipped_rows']);
    }

    public function testBackfillServiceRejectsNonStrictDatesAndChronologicalRange(): void
    {
        $service = new \App\Services\HistoricalOpnameBackfillService();

        $invalidFrom = $service->backfill('2026-1-01', '2026-01-31');
        $this->assertFalse($invalidFrom['success']);
        $this->assertSame('The from option must be a valid date (YYYY-MM-DD).', $invalidFrom['errors']['from']);

        $invalidTo = $service->backfill('2026-01-01', '2026-01-32');
        $this->assertFalse($invalidTo['success']);
        $this->assertSame('The to option must be a valid date (YYYY-MM-DD).', $invalidTo['errors']['to']);

        $invalidRange = $service->backfill('2026-01-31', '2026-01-01');
        $this->assertFalse($invalidRange['success']);
        $this->assertSame('The from option must be less than or equal to to option.', $invalidRange['errors']['range']);
    }

    public function testBackfillDoesNotRecreateMarkerWhenSoftDeleted(): void
    {
        $db = Database::connect();
        $expectedRowCount = $this->countBackfillableDetails('2026-01-01', '2026-01-31');

        $service = new \App\Services\HistoricalOpnameBackfillService();
        $firstRun = $service->backfill('2026-01-01', '2026-01-31');
        $this->assertTrue($firstRun['success']);
        $this->assertSame($expectedRowCount, (int) $firstRun['data']['created_rows']);

        $firstCreatedRow = $db->table('stock_transactions')
            ->select('id')
            ->where('legacy_source_table', 'stock_opname_details')
            ->orderBy('legacy_source_detail_id', 'ASC')
            ->get()
            ->getRowArray();
        $this->assertNotNull($firstCreatedRow);

        $deletedAt = date('Y-m-d H:i:s');
        $db->table('stock_transactions')
            ->where('id', (int) $firstCreatedRow['id'])
            ->update(['deleted_at' => $deletedAt]);

        $secondRun = $service->backfill('2026-01-01', '2026-01-31');
        $this->assertTrue($secondRun['success']);
        $this->assertSame(0, (int) $secondRun['data']['created_rows']);
        $this->assertSame($expectedRowCount, (int) $secondRun['data']['skipped_rows']);

        $allMarkerRows = $db->table('stock_transactions')
            ->where('legacy_source_table', 'stock_opname_details')
            ->countAllResults();
        $this->assertSame($expectedRowCount, $allMarkerRows);

        $activeMarkerRows = $db->table('stock_transactions')
            ->where('legacy_source_table', 'stock_opname_details')
            ->where('deleted_at', null)
            ->countAllResults();
        $this->assertSame($expectedRowCount - 1, $activeMarkerRows);
    }

    private function seedRoles(): void
    {
        $roleModel = new RoleModel();
        $roleModel->insertBatch([
            ['name' => 'admin'],
            ['name' => 'gudang'],
            ['name' => 'dapur'],
        ]);
    }

    private function seedUsers(): void
    {
        $roleModel    = new RoleModel();
        $userProvider = new AppUserProvider();

        $users = [
            ['role' => 'admin', 'name' => 'Admin User', 'username' => 'admin', 'email' => 'admin@example.com'],
            ['role' => 'gudang', 'name' => 'Gudang User', 'username' => 'gudang', 'email' => 'gudang@example.com'],
        ];

        foreach ($users as $userData) {
            $role = $roleModel->findByName($userData['role']);
            $this->assertNotNull($role);

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

    private function seedItemCategories(): void
    {
        $categoryModel = new ItemCategoryModel();
        $categoryModel->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
        ]);
    }

    private function seedItemUnits(): void
    {
        $unitModel = new ItemUnitModel();
        $unitModel->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
        ]);
    }

    private function seedItems(): void
    {
        $db = Database::connect();
        $categoryModel = new ItemCategoryModel();
        $unitModel = new ItemUnitModel();

        $kering = $categoryModel->where('name', 'KERING')->first();
        $basah  = $categoryModel->where('name', 'BASAH')->first();
        $gramId = $unitModel->getIdByName('gram');
        $kgId   = $unitModel->getIdByName('kg');

        $db->table('items')->insertBatch([
            [
                'item_category_id'     => $kering['id'],
                'name'                 => 'Beras',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 1000,
            ],
            [
                'item_category_id'     => $basah['id'],
                'name'                 => 'Ayam',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 800,
            ],
        ]);
    }

    private function seedTransactionTypes(): void
    {
        $typeModel = new TransactionTypeModel();
        $typeModel->insertBatch([
            ['name' => TransactionTypeModel::NAME_IN],
            ['name' => TransactionTypeModel::NAME_OUT],
            ['name' => TransactionTypeModel::NAME_RETURN_IN],
            ['name' => TransactionTypeModel::NAME_OPNAME_ADJUSTMENT],
        ]);
    }

    private function seedApprovalStatuses(): void
    {
        $statusModel = new ApprovalStatusModel();
        $statusModel->insertBatch([
            ['name' => ApprovalStatusModel::NAME_APPROVED],
            ['name' => ApprovalStatusModel::NAME_PENDING],
            ['name' => ApprovalStatusModel::NAME_REJECTED],
        ]);
    }

    private function seedHistoricalPostedOpnameRows(): void
    {
        $db = Database::connect();

        $gudangUser = $db->table('users')->where('username', 'gudang')->get()->getRowArray();
        $this->assertNotNull($gudangUser);

        $db->table('stock_opnames')->insert([
            'opname_date'   => '2026-01-15',
            'state'         => 'POSTED',
            'notes'         => 'Historical period sample A',
            'created_by'    => (int) $gudangUser['id'],
            'submitted_by'  => (int) $gudangUser['id'],
            'submitted_at'  => '2026-01-15 09:00:00',
            'approved_by'   => (int) $gudangUser['id'],
            'approved_at'   => '2026-01-15 09:30:00',
            'posted_by'     => (int) $gudangUser['id'],
            'posted_at'     => '2026-01-15 10:00:00',
            'created_at'    => '2026-01-15 08:00:00',
            'updated_at'    => '2026-01-15 10:00:00',
        ]);
        $firstOpnameId = (int) $db->insertID();

        $db->table('stock_opname_details')->insert([
            'stock_opname_id' => $firstOpnameId,
            'item_id'         => 1,
            'system_qty'      => '1000.00',
            'counted_qty'     => '950.00',
            'variance_qty'    => '-50.00',
        ]);

        $db->table('stock_opname_details')->insert([
            'stock_opname_id' => $firstOpnameId,
            'item_id'         => 2,
            'system_qty'      => '800.00',
            'counted_qty'     => '860.00',
            'variance_qty'    => '60.00',
        ]);

        $db->table('stock_opnames')->insert([
            'opname_date'   => '2026-01-20',
            'state'         => 'DRAFT',
            'notes'         => 'Must be ignored by backfill',
            'created_by'    => (int) $gudangUser['id'],
            'created_at'    => '2026-01-20 08:00:00',
            'updated_at'    => '2026-01-20 08:00:00',
        ]);
        $draftOpnameId = (int) $db->insertID();

        $db->table('stock_opname_details')->insert([
            'stock_opname_id' => $draftOpnameId,
            'item_id'         => 1,
            'system_qty'      => '950.00',
            'counted_qty'     => '900.00',
            'variance_qty'    => '-50.00',
        ]);
    }

    private function countBackfillableDetails(string $fromDate, string $toDate): int
    {
        $db = Database::connect();

        return $db->table('stock_opname_details sod')
            ->join('stock_opnames so', 'so.id = sod.stock_opname_id', 'inner')
            ->where('so.state', 'POSTED')
            ->where('so.opname_date >=', $fromDate)
            ->where('so.opname_date <=', $toDate)
            ->where('sod.variance_qty !=', 0)
            ->countAllResults();
    }
}
