<?php

namespace Tests\Feature\Api\V1;

use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use App\Models\StockOpnameModel;
use App\Models\StockTransactionModel;
use App\Models\StockTransactionDetailModel;
use App\Models\ApprovalStatusModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class StockOpnamesTest extends CIUnitTestCase
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
        $this->seedTransactionTypes();
        $this->seedApprovalStatuses();
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
                'item_category_id'     => $kering['id'],
                'name'                 => 'Beras',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 5000,
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
                'qty'                  => 3000,
            ],
        ]);
    }

    protected function seedTransactionTypes(): void
    {
        $typeModel = new TransactionTypeModel();
        $typeModel->insertBatch([
            ['name' => 'IN'],
            ['name' => 'OUT'],
            ['name' => 'RETURN_IN'],
            ['name' => TransactionTypeModel::NAME_OPNAME_ADJUSTMENT],
        ]);
    }

    protected function seedApprovalStatuses(): void
    {
        $statusModel = new ApprovalStatusModel();
        $statusModel->insertBatch([
            ['name' => 'APPROVED'],
            ['name' => 'PENDING'],
            ['name' => 'REJECTED'],
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

    public function testStockOpnameHappyPathLifecycle(): void
    {
        $gudangToken = $this->login('gudang');
        $adminToken  = $this->login('admin');

        $itemModel      = new ItemModel();
        $berasBeforeQty = (float) $itemModel->find(1)['qty'];
        $ayamBeforeQty  = (float) $itemModel->find(2)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-20',
                'notes'       => 'Cycle count June.',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => $berasBeforeQty - 100],
                    ['item_id' => 2, 'counted_qty' => $ayamBeforeQty + 50],
                ],
            ]);

        $createResult->assertStatus(201);
        $createJson = json_decode($createResult->getJSON(), true);
        $opnameId   = (int) $createJson['data']['id'];

        $submitResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/submit', []);
        $this->assertNotNull($submitResult);
        $submitResult->assertStatus(200);
        $submitJson = json_decode($submitResult->getJSON(), true);
        $this->assertSame(StockOpnameModel::STATE_SUBMITTED, $submitJson['data']['state']);

        $approveResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/approve', []);
        $this->assertNotNull($approveResult);
        $approveResult->assertStatus(200);
        $approveJson = json_decode($approveResult->getJSON(), true);
        $this->assertSame(StockOpnameModel::STATE_APPROVED, $approveJson['data']['state']);

        $postResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/post', []);

        $postResult->assertStatus(200);
        $postJson = json_decode($postResult->getJSON(), true);
        $this->assertSame(StockOpnameModel::STATE_POSTED, $postJson['data']['state']);

        $berasAfterQty = (float) $itemModel->find(1)['qty'];
        $ayamAfterQty  = (float) $itemModel->find(2)['qty'];

        $this->assertSame($berasBeforeQty - 100, $berasAfterQty);
        $this->assertSame($ayamBeforeQty + 50, $ayamAfterQty);

        $stockTransactionModel = new StockTransactionModel();
        $postedTransactions    = $stockTransactionModel
            ->like('reason', 'Stock opname #' . $opnameId . ' posting', 'both')
            ->findAll();

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/stock-opnames/' . $opnameId);
        $showResult->assertStatus(200);
        $showJson = json_decode($showResult->getJSON(), true);

        $expectedNonZeroDetails = array_values(array_filter(
            $showJson['data']['details'],
            static fn (array $detail): bool => abs((float) $detail['variance_qty']) >= 0.005,
        ));
        $this->assertCount(count($expectedNonZeroDetails), $postedTransactions);

        $this->assertSame(StockOpnameModel::STATE_POSTED, $showJson['data']['header']['state']);
        $this->assertArrayHasKey('created_by_name', $showJson['data']['header']);
        $this->assertArrayHasKey('submitted_by_name', $showJson['data']['header']);
        $this->assertArrayHasKey('approved_by_name', $showJson['data']['header']);
        $this->assertArrayHasKey('posted_by_name', $showJson['data']['header']);
        $this->assertSame('Gudang User', $showJson['data']['header']['created_by_name']);
        $this->assertSame('Gudang User', $showJson['data']['header']['submitted_by_name']);
        $this->assertSame('Admin User', $showJson['data']['header']['approved_by_name']);
        $this->assertSame('Admin User', $showJson['data']['header']['posted_by_name']);
        $this->assertGreaterThanOrEqual(2, count($showJson['data']['details']));
    }

    public function testStockOpnameInvalidTransitionApproveDraftRejected(): void
    {
        $gudangToken = $this->login('gudang');
        $adminToken  = $this->login('admin');

        $itemModel      = new ItemModel();
        $berasBeforeQty = (float) $itemModel->find(1)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-21',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => $berasBeforeQty - 25],
                ],
            ]);

        $createResult->assertStatus(201);
        $opnameId = (int) json_decode($createResult->getJSON(), true)['data']['id'];

        $approveResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/approve', []);

        $approveResult->assertStatus(400);
        $approveResult->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($approveResult->getJSON(), true);
        $this->assertArrayHasKey('state', $json['errors']);
        $this->assertStringContainsString('DRAFT', $json['errors']['state']);

        $stockOpnameModel = new StockOpnameModel();
        $opnameRow        = $stockOpnameModel->find($opnameId);
        $this->assertSame(StockOpnameModel::STATE_DRAFT, $opnameRow['state']);

        $berasAfterQty = (float) $itemModel->find(1)['qty'];
        $this->assertSame($berasBeforeQty, $berasAfterQty);
    }

    public function testGudangCannotApproveOrPostStockOpname(): void
    {
        $gudangToken = $this->login('gudang');

        $itemModel = new ItemModel();
        $beforeQty = (float) $itemModel->find(1)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-22',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => $beforeQty - 10],
                ],
            ]);

        $createResult->assertStatus(201);
        $opnameId = (int) json_decode($createResult->getJSON(), true)['data']['id'];

        $submitResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/submit', []);
        $submitResult->assertStatus(200);

        $approveResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/approve', []);
        $approveResult->assertStatus(403);

        $postResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/post', []);
        $postResult->assertStatus(403);

        $this->assertSame($beforeQty, (float) $itemModel->find(1)['qty']);
    }

    public function testGudangCanUpdateDraftAndRejectedStockOpnameButNotSubmitted(): void
    {
        $gudangToken = $this->login('gudang');
        $adminToken  = $this->login('admin');
        $itemModel   = new ItemModel();

        $draftCreate = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-26',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => (float) $itemModel->find(1)['qty'] - 10],
                ],
            ]);
        $draftCreate->assertStatus(201);
        $draftId = (int) json_decode($draftCreate->getJSON(), true)['data']['id'];

        $draftUpdate = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->put('api/v1/stock-opnames/' . $draftId, [
                'opname_date' => '2026-06-27',
                'notes'       => 'Updated draft count',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => (float) $itemModel->find(1)['qty'] - 5],
                    ['item_id' => 2, 'counted_qty' => (float) $itemModel->find(2)['qty'] + 15],
                ],
            ]);
        $draftUpdate->assertStatus(200);
        $draftUpdate->assertJSONFragment(['message' => 'Stock opname updated successfully.']);

        $draftShow = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/stock-opnames/' . $draftId);
        $draftShow->assertStatus(200);
        $draftShowJson = json_decode($draftShow->getJSON(), true);
        $this->assertSame('2026-06-27', $draftShowJson['data']['header']['opname_date']);
        $this->assertSame('Updated draft count', $draftShowJson['data']['header']['notes']);
        $this->assertCount(2, $draftShowJson['data']['details']);

        $rejectedCreate = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-28',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => (float) $itemModel->find(1)['qty'] - 20],
                ],
            ]);
        $rejectedCreate->assertStatus(201);
        $rejectedId = (int) json_decode($rejectedCreate->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $rejectedId . '/submit', [])
            ->assertStatus(200);
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $rejectedId . '/reject', ['reason' => 'Please recount item 1'])
            ->assertStatus(200);

        $rejectedUpdate = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->put('api/v1/stock-opnames/' . $rejectedId, [
                'opname_date' => '2026-06-29',
                'notes'       => 'Recount after rejection',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => (float) $itemModel->find(1)['qty'] - 7],
                ],
            ]);
        $rejectedUpdate->assertStatus(200);

        $rejectedShow = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->get('api/v1/stock-opnames/' . $rejectedId);
        $rejectedShow->assertStatus(200);
        $rejectedShowJson = json_decode($rejectedShow->getJSON(), true);
        $this->assertSame(StockOpnameModel::STATE_REJECTED, $rejectedShowJson['data']['header']['state']);
        $this->assertSame('2026-06-29', $rejectedShowJson['data']['header']['opname_date']);

        $submittedCreate = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-30',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => (float) $itemModel->find(1)['qty'] - 30],
                ],
            ]);
        $submittedCreate->assertStatus(201);
        $submittedId = (int) json_decode($submittedCreate->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $submittedId . '/submit', [])
            ->assertStatus(200);

        $submittedUpdate = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->put('api/v1/stock-opnames/' . $submittedId, [
                'opname_date' => '2026-07-01',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => (float) $itemModel->find(1)['qty'] - 11],
                ],
            ]);

        $submittedUpdate->assertStatus(400);
        $submittedJson = json_decode($submittedUpdate->getJSON(), true);
        $this->assertSame('Validation failed.', $submittedJson['message']);
        $this->assertStringContainsString('SUBMITTED', $submittedJson['errors']['state']);
    }

    public function testStockOpnamePostConvertsAbsoluteQtyToOpnameAdjustmentLedgerRows(): void
    {
        $gudangToken = $this->login('gudang');
        $adminToken  = $this->login('admin');

        $itemModel     = new ItemModel();
        $berasBefore   = (float) $itemModel->find(1)['qty'];
        $ayamBefore    = (float) $itemModel->find(2)['qty'];
        $berasActual   = $berasBefore + 120.25;
        $ayamActual    = $ayamBefore - 80.5;

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-23',
                'notes'       => 'Delta conversion contract test.',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => $berasActual],
                    ['item_id' => 2, 'counted_qty' => $ayamActual],
                ],
            ]);
        $createResult->assertStatus(201);
        $opnameId = (int) json_decode($createResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/submit', [])
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/approve', [])
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/post', [])
            ->assertStatus(200);

        $this->assertSame($berasActual, (float) $itemModel->find(1)['qty']);
        $this->assertSame($ayamActual, (float) $itemModel->find(2)['qty']);

        $transactionTypeModel   = new TransactionTypeModel();
        $opnameAdjustmentTypeId = (int) $transactionTypeModel->getIdByName(TransactionTypeModel::NAME_OPNAME_ADJUSTMENT);

        $stockTransactionModel = new StockTransactionModel();
        $postedTransactions    = $stockTransactionModel
            ->like('reason', 'Stock opname #' . $opnameId . ' posting', 'both')
            ->orderBy('id', 'ASC')
            ->findAll();

        $this->assertCount(2, $postedTransactions);

        $detailModel = new StockTransactionDetailModel();

        $seen120 = false;
        $seen80  = false;
        foreach ($postedTransactions as $transaction) {
            $details = $detailModel->getDetailsByTransactionId((int) $transaction['id']);
            $this->assertCount(1, $details);
            $this->assertSame($opnameAdjustmentTypeId, (int) $transaction['type_id']);

            $detailQty = number_format((float) $details[0]['qty'], 2, '.', '');

            if ($detailQty === '120.25') {
                $seen120 = true;
                $this->assertSame('120.25', $detailQty);
            }

            if ($detailQty === '80.50') {
                $seen80 = true;
                $this->assertSame('80.50', $detailQty);
            }
        }

        $this->assertTrue($seen120, 'Expected one posting transaction detail with 120.25 delta qty.');
        $this->assertTrue($seen80, 'Expected one posting transaction detail with 80.50 delta qty.');
    }

    public function testStockOpnamePostWithStaleExpectedQtyPreservesLegacyValidationEnvelopeAndRollsBackAllWrites(): void
    {
        $gudangToken = $this->login('gudang');
        $adminToken  = $this->login('admin');

        $itemModel   = new ItemModel();
        $item1Before = (float) $itemModel->find(1)['qty'];
        $item2Before = (float) $itemModel->find(2)['qty'];

        Database::connect()->table('items')->where('id', 1)->update([
            'min_stock'  => number_format($item1Before + 100, 2, '.', ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-25',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => $item1Before + 20],
                    ['item_id' => 2, 'counted_qty' => $item2Before - 10],
                ],
            ]);
        $createResult->assertStatus(201);
        $opnameId = (int) json_decode($createResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/submit', [])
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/approve', [])
            ->assertStatus(200);

        $notificationCountBefore = Database::connect()->table('notifications')->countAllResults();

        Database::connect()->table('items')->where('id', 2)->update([
            'qty'        => number_format($item2Before + 5, 2, '.', ''),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $postResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/post', []);

        $postResult->assertStatus(400);
        $postJson = json_decode($postResult->getJSON(), true);
        $this->assertSame('Validation failed.', $postJson['message']);
        $this->assertSame('Insufficient stock to post stock opname variance.', $postJson['errors']['details']);

        $this->assertSame($item1Before, (float) $itemModel->find(1)['qty']);
        $this->assertSame($item2Before + 5, (float) $itemModel->find(2)['qty']);

        $stockTransactionModel = new StockTransactionModel();
        $postedTransactions    = $stockTransactionModel
            ->like('reason', 'Stock opname #' . $opnameId . ' posting', 'both')
            ->findAll();
        $this->assertCount(0, $postedTransactions);

        $notificationCountAfter = Database::connect()->table('notifications')->countAllResults();
        $this->assertSame($notificationCountBefore, $notificationCountAfter);

        $opnameRow = (new StockOpnameModel())->find($opnameId);
        $this->assertSame(StockOpnameModel::STATE_APPROVED, $opnameRow['state']);
    }

    public function testStockOpnamePostRejectsZeroDeltaWithDeterministicMessage(): void
    {
        $gudangToken = $this->login('gudang');
        $adminToken  = $this->login('admin');

        $itemModel = new ItemModel();
        $current   = (float) $itemModel->find(1)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames', [
                'opname_date' => '2026-06-24',
                'details'     => [
                    ['item_id' => 1, 'counted_qty' => $current],
                ],
            ]);
        $createResult->assertStatus(201);
        $opnameId = (int) json_decode($createResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/submit', [])
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/approve', [])
            ->assertStatus(200);

        $postResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-opnames/' . $opnameId . '/post', []);

        $postResult->assertStatus(400);
        $postJson = json_decode($postResult->getJSON(), true);
        $this->assertSame('Validation failed.', $postJson['message']);
        $this->assertSame(
            'Zero-delta opname lines are not allowed. expected_current_qty must be different from actual_qty for every detail.',
            $postJson['errors']['details']
        );

        $this->assertSame($current, (float) $itemModel->find(1)['qty']);
    }
}
