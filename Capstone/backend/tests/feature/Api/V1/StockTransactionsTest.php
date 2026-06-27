<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApprovalStatusModel;
use App\Models\AppUserProvider;
use App\Models\AuditLogModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;
use App\Models\RoleModel;
use App\Models\StockTransactionDetailModel;
use App\Models\StockTransactionModel;
use App\Models\TransactionTypeModel;
use App\Services\StockSnapshotService;
use App\Services\AuditService;
use App\Services\StockTransactionService;
use App\Database\Migrations\AddMinStockToItems;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Database\Exceptions\DataException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class StockTransactionsTest extends CIUnitTestCase
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
        $this->seedTransactionTypes();
        $this->seedApprovalStatuses();
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
                'item_category_id'  => $kering['id'],
                'name'              => 'Beras',
                'unit_base'         => 'gram',
                'unit_convert'      => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'   => 1000,
                'is_active'         => true,
                'qty'               => 5000,
            ],
            [
                'item_category_id'  => $basah['id'],
                'name'              => 'Ayam',
                'unit_base'         => 'gram',
                'unit_convert'      => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'   => 1000,
                'is_active'         => true,
                'qty'               => 3000,
            ],
        ]);
    }

    public function testItemModelDirectQtyUpdateDoesNotChangeStoredQty(): void
    {
        $itemModel = new ItemModel();

        $before = $itemModel->find(1);
        $this->assertNotNull($before);

        try {
            $itemModel->update(1, ['qty' => 9999]);
            $this->fail('Expected DataException when attempting direct qty update via ItemModel.');
        } catch (DataException $exception) {
            $this->assertSame('There is no data to update.', $exception->getMessage());
        }


        $after = $itemModel->find(1);
        $this->assertNotNull($after);
        $this->assertSame((string) $before['qty'], (string) $after['qty']);
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

    public function testListTransactionsWithoutAuth(): void
    {
        $this->get('api/v1/stock-transactions')->assertStatus(401);
    }

    public function testCreateTransactionWithoutAuth(): void
    {
        $this->post('api/v1/stock-transactions')->assertStatus(401);
    }

    public function testListTransactionsAsDapurIsForbidden(): void
    {
        $token = $this->login('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions');

        $result->assertStatus(403);
    }

    public function testCreateTransactionAsDapurIsForbidden(): void
    {
        $token = $this->login('dapur');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 100],
                ],
            ]);

        $result->assertStatus(403);
    }

    public function testShowTransactionAsDapurIsForbidden(): void
    {
        $adminToken = $this->login('admin');
        $dapurToken = $this->login('dapur');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 50],
                ],
            ]);

        $json = json_decode($createResult->getJSON(), true);
        $id   = $json['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->get('api/v1/stock-transactions/' . $id);

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Insufficient permissions.']);
    }

    public function testDetailsTransactionAsDapurIsForbidden(): void
    {
        $adminToken = $this->login('admin');
        $dapurToken = $this->login('dapur');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 50],
                ],
            ]);

        $json = json_decode($createResult->getJSON(), true);
        $id   = $json['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $dapurToken])
            ->get('api/v1/stock-transactions/' . $id . '/details');

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Insufficient permissions.']);
    }

    public function testCreateValidInTransactionIncreasesQty(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel     = new ItemModel();
        $itemBefore    = $itemModel->find(1);
        $qtyBefore     = (float) $itemBefore['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 100.50],
                ],
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['message' => 'Stock transaction created successfully.']);

        $json = json_decode($result->getJSON(), true);
        $approvalStatusModel = new ApprovalStatusModel();
        $approvedStatusId    = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $this->assertSame($approvedStatusId, $json['data']['approval_status_id']);
        $this->assertFalse($json['data']['is_revision']);

        $itemAfter = $itemModel->find(1);
        $qtyAfter  = (float) $itemAfter['qty'];

        $this->assertSame($qtyBefore + 100.50, $qtyAfter);
    }

    public function testCreateValidOutTransactionDecreasesQty(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(1);
        $qtyBefore  = (float) $itemBefore['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $outType['id'],
                'transaction_date' => '2026-04-02',
                'details'          => [
                    ['item_id' => 1, 'qty' => 200],
                ],
            ]);

        $result->assertStatus(201);

        $itemAfter = $itemModel->find(1);
        $qtyAfter  = (float) $itemAfter['qty'];

        $this->assertSame($qtyBefore - 200, $qtyAfter);
    }

    public function testCreateValidReturnInTransactionIncreasesQty(): void
    {
        $token = $this->login('gudang');

        $typeModel  = new TransactionTypeModel();
        $returnType = $typeModel->where('name', 'RETURN_IN')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(2);
        $qtyBefore  = (float) $itemBefore['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $returnType['id'],
                'transaction_date' => '2026-04-03',
                'details'          => [
                    ['item_id' => 2, 'qty' => 50],
                ],
            ]);

        $result->assertStatus(201);

        $itemAfter = $itemModel->find(2);
        $qtyAfter  = (float) $itemAfter['qty'];

        $this->assertSame($qtyBefore + 50, $qtyAfter);
    }

    public function testCreateValidOpnameAdjustmentTransactionByTypeNameIncreasesQty(): void
    {
        $token = $this->login('gudang');

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(1);
        $qtyBefore  = (float) $itemBefore['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => TransactionTypeModel::NAME_OPNAME_ADJUSTMENT,
                'transaction_date' => '2026-04-03',
                'details'          => [
                    ['item_id' => 1, 'qty' => 40],
                ],
            ]);

        $result->assertStatus(201);

        $itemAfter = $itemModel->find(1);
        $qtyAfter  = (float) $itemAfter['qty'];

        $this->assertSame($qtyBefore + 40, $qtyAfter);
    }

    public function testCreateTransactionWithUnknownTypeNameReturnsValidationError(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'FOO_BAR',
                'transaction_date' => '2026-04-06',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('type_name', $json['errors']);
        $this->assertSame('The selected transaction type is invalid.', $json['errors']['type_name']);
    }

    public function testNormalTransactionTypesAreAutoApprovedAndNonRevision(): void
    {
        $token = $this->login('admin');

        $approvalStatusModel = new ApprovalStatusModel();
        $approvedStatusId    = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $typeModel           = new TransactionTypeModel();

        $cases = [
            ['type_name' => 'IN', 'item_id' => 1, 'qty' => 10, 'transaction_date' => '2026-10-01'],
            ['type_name' => 'OUT', 'item_id' => 1, 'qty' => 5, 'transaction_date' => '2026-10-02'],
            ['type_name' => 'RETURN_IN', 'item_id' => 2, 'qty' => 7, 'transaction_date' => '2026-10-03'],
        ];

        foreach ($cases as $case) {
            $type = $typeModel->where('name', $case['type_name'])->first();

            $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->withBodyFormat('json')
                ->post('api/v1/stock-transactions', [
                    'type_id'          => $type['id'],
                    'transaction_date' => $case['transaction_date'],
                    'details'          => [
                        ['item_id' => $case['item_id'], 'qty' => $case['qty']],
                    ],
                ]);

            $result->assertStatus(201);

            $json = json_decode($result->getJSON(), true);
            $this->assertSame($approvedStatusId, $json['data']['approval_status_id']);
            $this->assertFalse($json['data']['is_revision']);
        }
    }

    public function testCreateOutTransactionWithInsufficientStockReturnsError(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $outType['id'],
                'transaction_date' => '2026-04-04',
                'details'          => [
                    ['item_id' => 1, 'qty' => 99999],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionWithDuplicateItemIdReturnsError(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-05',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionWithNonexistentItemReturnsError(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-06',
                'details'          => [
                    ['item_id' => 9999, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionWithMissingTypeIdReturnsError(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'transaction_date' => '2026-04-07',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionWithMissingTransactionDateReturnsError(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id' => $inType['id'],
                'details' => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionWithMissingDetailsReturnsError(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-08',
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionWithEmptyDetailsReturnsError(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-09',
                'details'          => [],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsClientSuppliedUserId(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-10',
                'user_id'          => 999,
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsClientSuppliedApprovalStatusId(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'            => $inType['id'],
                'transaction_date'   => '2026-04-11',
                'approval_status_id' => 3,
                'details'            => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsClientSuppliedIsRevision(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-12',
                'is_revision'      => true,
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsClientSuppliedParentTransactionId(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'               => $inType['id'],
                'transaction_date'      => '2026-04-13',
                'parent_transaction_id' => 1,
                'details'               => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsClientSuppliedApprovedBy(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-14',
                'approved_by'      => 1,
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testSuccessfulCreateWritesAuditLog(): void
    {
        $token = $this->login('admin');
        // Pre-create opening snapshot so auto-trigger in createTransaction is a no-op
        // (otherwise ensureOpeningSnapshot logs an extra audit entry for 2026-04)
        (new StockSnapshotService())->takeOpeningSnapshot('2026-04');

        $typeModel = new TransactionTypeModel();

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $auditModel   = new AuditLogModel();
        $countBefore  = $auditModel->countAllResults();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-15',
                'details'          => [
                    ['item_id' => 1, 'qty' => 25],
                ],
            ]);

        $result->assertStatus(201);

        $countAfter = $auditModel->countAllResults();

        $this->assertSame($countBefore + 1, $countAfter);

        $latestAudit = $auditModel->orderBy('id', 'DESC')->first();
        $this->assertSame('create', $latestAudit['action_type']);
        $this->assertSame('stock_transactions', $latestAudit['table_name']);
    }

    public function testDirectCorrectionCreatesApprovedOutTransactionWithReason(): void
    {
        $token = $this->login('admin');

        $itemModel = new ItemModel();
        $before    = (float) $itemModel->find(1)['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/direct-corrections', [
                'transaction_date'      => '2026-04-15',
                'item_id'               => 1,
                'expected_current_qty'  => $before,
                'target_qty'            => $before - 25,
                'reason'                => 'Manual stock correction after recount.',
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['message' => 'Direct stock correction created successfully.']);

        $json = json_decode($result->getJSON(), true);

        $approvalStatusModel = new ApprovalStatusModel();
        $approvedStatusId    = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $this->assertSame($approvedStatusId, $json['data']['approval_status_id']);
        $this->assertFalse($json['data']['is_revision']);

        $transactionModel = new StockTransactionModel();
        $transaction      = $transactionModel->find($json['data']['id']);

        $typeModel = new TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();
        $this->assertSame($outType['id'], (int) $transaction['type_id']);
        $this->assertSame('Manual stock correction after recount.', $transaction['reason']);
        $this->assertNull($transaction['parent_transaction_id']);

        $detailModel = new StockTransactionDetailModel();
        $details     = $detailModel->getDetailsByTransactionId((int) $transaction['id']);
        $this->assertCount(1, $details);
        $this->assertSame('25.00', number_format((float) $details[0]['qty'], 2, '.', ''));
        $this->assertSame('25.00', number_format((float) $details[0]['input_qty'], 2, '.', ''));
        $this->assertSame('base', $details[0]['input_unit']);

        $after = (float) $itemModel->find(1)['qty'];
        $this->assertSame($before - 25, $after);
    }

    public function testDirectCorrectionCreatesApprovedInTransactionWithDerivedType(): void
    {
        $token = $this->login('admin');

        $itemModel = new ItemModel();
        $before    = (float) $itemModel->find(2)['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/direct-corrections', [
                'transaction_date'      => '2026-04-16',
                'item_id'               => 2,
                'expected_current_qty'  => $before,
                'target_qty'            => $before + 125.5,
                'reason'                => 'Manual correction after item receipt recount.',
            ]);

        $result->assertStatus(201);

        $transactionId      = json_decode($result->getJSON(), true)['data']['id'];
        $transactionModel   = new StockTransactionModel();
        $transaction        = $transactionModel->find($transactionId);
        $typeModel          = new TransactionTypeModel();
        $inType             = $typeModel->where('name', 'IN')->first();

        $this->assertSame($inType['id'], (int) $transaction['type_id']);
        $this->assertSame($before + 125.5, (float) $itemModel->find(2)['qty']);
    }

    public function testDirectCorrectionRequiresReason(): void
    {
        $token = $this->login('admin');

        $currentQty = (float) (new ItemModel())->find(1)['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/direct-corrections', [
                'transaction_date'      => '2026-04-17',
                'item_id'               => 1,
                'expected_current_qty'  => $currentQty,
                'target_qty'            => $currentQty - 10,
                'reason'                => '   ',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('reason', $json['errors']);
    }

    public function testDirectCorrectionRejectsNoOpTargetQty(): void
    {
        $token = $this->login('admin');

        $currentQty = (float) (new ItemModel())->find(1)['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/direct-corrections', [
                'transaction_date'      => '2026-04-18',
                'item_id'               => 1,
                'expected_current_qty'  => $currentQty,
                'target_qty'            => $currentQty,
                'reason'                => 'No-op correction should fail.',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('target_qty', $json['errors']);
    }

    public function testDirectCorrectionRejectsStaleExpectedCurrentQty(): void
    {
        $token = $this->login('admin');

        $itemModel = new ItemModel();
        $before    = (float) $itemModel->find(1)['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/direct-corrections', [
                'transaction_date'      => '2026-04-19',
                'item_id'               => 1,
                'expected_current_qty'  => $before - 1,
                'target_qty'            => $before - 10,
                'reason'                => 'Stale correction should be rejected.',
            ]);

        $result->assertStatus(400);
        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('expected_current_qty', $json['errors']);
        $this->assertSame($before, (float) $itemModel->find(1)['qty']);
    }

    public function testDirectCorrectionRejectsUnknownField(): void
    {
        $token = $this->login('admin');

        $currentQty = (float) (new ItemModel())->find(1)['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/direct-corrections', [
                'transaction_date'      => '2026-04-20',
                'item_id'               => 1,
                'expected_current_qty'  => $currentQty,
                'target_qty'            => $currentQty + 5,
                'reason'                => 'Correction with unsupported field.',
                'details'               => [
                    ['item_id' => 1, 'qty' => 5],
                ],
            ]);

        $result->assertStatus(400);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('fields', $json['errors']);
    }

    public function testDirectCorrectionWritesAuditLog(): void
    {
        $token = $this->login('admin');

        $itemModel = new ItemModel();
        $before    = (float) $itemModel->find(2)['qty'];

        // Pre-create opening snapshot so auto-trigger is a no-op
        (new StockSnapshotService())->takeOpeningSnapshot('2026-04');

        $auditModel  = new AuditLogModel();
        $countBefore = $auditModel->countAllResults();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/direct-corrections', [
                'transaction_date'      => '2026-04-21',
                'item_id'               => 2,
                'expected_current_qty'  => $before,
                'target_qty'            => $before - 20,
                'reason'                => 'Audit log verification.',
            ]);

        $result->assertStatus(201);

        $countAfter = $auditModel->countAllResults();
        $this->assertSame($countBefore + 1, $countAfter);

        $latestAudit = $auditModel->orderBy('id', 'DESC')->first();
        $this->assertSame('create', $latestAudit['action_type']);

        $newValues = json_decode((string) $latestAudit['new_values'], true);
        $this->assertIsArray($newValues);
        $this->assertSame('Audit log verification.', $newValues['reason']);
        $this->assertSame('20.00', $newValues['detail_qty']);
    }

    public function testListTransactionsReturnsDataMetaLinks(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-16',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('links', $json);
        $this->assertSame(1, $json['meta']['page']);
        $this->assertSame(10, $json['meta']['perPage']);
    }

    public function testListTransactionsRejectsUnknownQueryParam(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?unknown=value');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testShowExistingTransactionReturnsHeader(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-17',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $json = json_decode($createResult->getJSON(), true);
        $id   = $json['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions/' . $id);

        $result->assertStatus(200);

        $showJson = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $showJson);
        $this->assertSame($id, $showJson['data']['id']);
        $this->assertArrayHasKey('type_id', $showJson['data']);
        $this->assertArrayHasKey('transaction_date', $showJson['data']);
        $this->assertArrayHasKey('user_name', $showJson['data']);
        $this->assertArrayHasKey('approved_by_name', $showJson['data']);
        $this->assertArrayNotHasKey('details', $showJson['data']);
    }

    public function testListAndShowExposeUserActionHistoryFields(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-17',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $transactionId = (int) json_decode($createResult->getJSON(), true)['data']['id'];

        $listResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?sortBy=id&sortDir=DESC');
        $listResult->assertStatus(200);

        $listJson = json_decode($listResult->getJSON(), true);
        $targetRow = null;
        foreach ($listJson['data'] as $row) {
            if ((int) $row['id'] === $transactionId) {
                $targetRow = $row;
                break;
            }
        }

        $this->assertNotNull($targetRow);
        $this->assertArrayHasKey('user_name', $targetRow);
        $this->assertArrayHasKey('approved_by_name', $targetRow);
        $this->assertSame('Gudang User', $targetRow['user_name']);
        $this->assertNull($targetRow['approved_by_name']);

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions/' . $transactionId);
        $showResult->assertStatus(200);

        $showJson = json_decode($showResult->getJSON(), true);
        $this->assertArrayHasKey('user_name', $showJson['data']);
        $this->assertArrayHasKey('approved_by_name', $showJson['data']);
        $this->assertSame('Gudang User', $showJson['data']['user_name']);
        $this->assertNull($showJson['data']['approved_by_name']);
    }

    public function testShowMissingTransactionReturnsNotFound(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions/9999');

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Stock transaction not found.']);
    }

    public function testDetailsExistingTransactionReturnsLineItems(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-18',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                    ['item_id' => 2, 'qty' => 30],
                ],
            ]);

        $json = json_decode($createResult->getJSON(), true);
        $id   = $json['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions/' . $id . '/details');

        $result->assertStatus(200);

        $detailsJson = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $detailsJson);
        $this->assertCount(2, $detailsJson['data']);
        $this->assertArrayHasKey('item_id', $detailsJson['data'][0]);
        $this->assertArrayHasKey('item_name', $detailsJson['data'][0]);
        $this->assertArrayHasKey('item_category_id', $detailsJson['data'][0]);
        $this->assertArrayHasKey('item_category_name', $detailsJson['data'][0]);
        $this->assertArrayHasKey('satuan', $detailsJson['data'][0]);
        $this->assertArrayHasKey('qty', $detailsJson['data'][0]);
    }

    public function testAdminRevisionReviewListShowAndDetailsKeepFlatContracts(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-10-04',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-10-05',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $listResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/stock-transactions?sortBy=id&sortDir=DESC');

        $listResult->assertStatus(200);
        $listJson = json_decode($listResult->getJSON(), true);

        $parentRow   = null;
        $revisionRow = null;
        foreach ($listJson['data'] as $row) {
            if ((int) $row['id'] === (int) $parentId) {
                $parentRow = $row;
            }
            if ((int) $row['id'] === (int) $revisionId) {
                $revisionRow = $row;
            }
        }

        $this->assertNotNull($parentRow);
        $this->assertNotNull($revisionRow);
        $this->assertArrayHasKey('id', $revisionRow);
        $this->assertArrayHasKey('type_id', $revisionRow);
        $this->assertArrayHasKey('transaction_date', $revisionRow);
        $this->assertArrayHasKey('is_revision', $revisionRow);
        $this->assertArrayHasKey('parent_transaction_id', $revisionRow);
        $this->assertArrayHasKey('approval_status_id', $revisionRow);
        $this->assertArrayNotHasKey('details', $revisionRow);
        $this->assertTrue((bool) $revisionRow['is_revision']);
        $this->assertSame((int) $parentId, (int) $revisionRow['parent_transaction_id']);

        $showResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/stock-transactions/' . $revisionId);

        $showResult->assertStatus(200);
        $showJson = json_decode($showResult->getJSON(), true);
        $this->assertSame((int) $revisionId, (int) $showJson['data']['id']);
        $this->assertTrue((bool) $showJson['data']['is_revision']);
        $this->assertSame((int) $parentId, (int) $showJson['data']['parent_transaction_id']);
        $this->assertArrayNotHasKey('details', $showJson['data']);

        $detailsResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/stock-transactions/' . $revisionId . '/details');

        $detailsResult->assertStatus(200);
        $detailsJson = json_decode($detailsResult->getJSON(), true);
        $this->assertCount(1, $detailsJson['data']);
        $this->assertArrayHasKey('item_id', $detailsJson['data'][0]);
        $this->assertArrayHasKey('item_name', $detailsJson['data'][0]);
        $this->assertArrayHasKey('item_category_id', $detailsJson['data'][0]);
        $this->assertArrayHasKey('item_category_name', $detailsJson['data'][0]);
        $this->assertArrayHasKey('satuan', $detailsJson['data'][0]);
        $this->assertArrayHasKey('qty', $detailsJson['data'][0]);
        $this->assertArrayHasKey('input_qty', $detailsJson['data'][0]);
        $this->assertArrayHasKey('input_unit', $detailsJson['data'][0]);
        $this->assertArrayNotHasKey('type_id', $detailsJson['data'][0]);
        $this->assertArrayNotHasKey('is_revision', $detailsJson['data'][0]);
        $this->assertArrayNotHasKey('parent_transaction_id', $detailsJson['data'][0]);
    }

    public function testDetailsMissingTransactionReturnsNotFound(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions/9999/details');

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Stock transaction not found.']);
    }

    public function testItemMasterStillRejectsDirectQtyWrites(): void
    {
        $token = $this->login('admin');

        $categoryModel = new ItemCategoryModel();
        $category      = $categoryModel->where('name', 'KERING')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/items', [
                'name'             => 'Test Item',
                'item_category_id' => $category['id'],
                'unit_base'        => 'gram',
                'unit_convert'     => 'kg',
                'conversion_base'  => 1000,
                'qty'              => 500,
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsUnknownTopLevelField(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-20',
                'unknown_field'    => 'some value',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsUnknownDetailField(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-21',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10, 'extra_field' => 'not allowed'],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsNonObjectDetailEntry(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-21',
                'details'          => ['invalid-detail-entry'],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsInvalidTransactionDate(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => 'not-a-date',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionRejectsInvalidSpkId(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-22',
                'spk_id'           => -5,
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testCreateTransactionWithValidSpkIdPersistsCompatibilityLink(): void
    {
        $token = $this->login('admin');

        $categoryModel = new ItemCategoryModel();
        $kering = $categoryModel->where('name', 'KERING')->first();
        $this->assertNotNull($kering);

        $spkId = Database::connect()->table('spk_calculations')->insert([
            'spk_type'          => 'basah',
            'calculation_scope' => 'combined_window',
            'scope_key'         => 'task-9-stock-transaction-spk-compat',
            'version'           => 1,
            'is_latest'         => true,
            'calculation_date'  => '2026-04-22',
            'target_date_start' => '2026-04-22',
            'target_date_end'   => '2026-04-23',
            'target_month'      => null,
            'daily_patient_id'  => null,
            'user_id'           => 1,
            'category_id'       => (int) $kering['id'],
            'estimated_patients' => 100,
            'is_finish'         => false,
        ], true);

        $this->assertNotFalse($spkId);

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-22',
                'spk_id'           => (int) $spkId,
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(201);
        $json = json_decode($result->getJSON(), true);

        $this->assertSame('Stock transaction created successfully.', $json['message']);

        $transactionModel = new StockTransactionModel();
        $savedTransaction = $transactionModel->find((int) $json['data']['id']);
        $this->assertNotNull($savedTransaction);
        $this->assertSame((int) $spkId, (int) $savedTransaction['spk_id']);
    }

    public function testCreateTransactionKeepsHistoricalBackfillMarkersNullByDefault(): void
    {
        $token = $this->login('admin');

        $db = Database::connect();
        $columns = $db->getFieldNames('stock_transactions');
        $this->assertContains('legacy_source_table', $columns);
        $this->assertContains('legacy_source_id', $columns);
        $this->assertContains('legacy_source_detail_id', $columns);

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-23',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $result->assertStatus(201);
        $transactionId = (int) json_decode($result->getJSON(), true)['data']['id'];

        $saved = $db->table('stock_transactions')
            ->where('id', $transactionId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($saved);
        $this->assertNull($saved['legacy_source_table']);
        $this->assertNull($saved['legacy_source_id']);
        $this->assertNull($saved['legacy_source_detail_id']);
    }

    public function testDirectCorrectionRejectsGudangRoleWithForbiddenResponse(): void
    {
        $token = $this->login('gudang');
        $currentQty = (float) (new ItemModel())->find(1)['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/direct-corrections', [
                'transaction_date'      => '2026-04-23',
                'item_id'               => 1,
                'expected_current_qty'  => $currentQty,
                'target_qty'            => $currentQty - 5,
                'reason'                => 'RBAC check.',
            ]);

        $result->assertStatus(403);
    }

    public function testListTransactionsRejectsInvalidPage(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?page=0');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testListTransactionsRejectsInvalidPerPage(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?perPage=200');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testListTransactionsReturnsNewestFirst(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create three transactions with different dates
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-03',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-02',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('2026-04-03', $json['data'][0]['transaction_date']);
        $this->assertSame('2026-04-02', $json['data'][1]['transaction_date']);
        $this->assertSame('2026-04-01', $json['data'][2]['transaction_date']);
    }

    public function testMultipleTransactionsMutateQtyCorrectly(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();
        $outType   = $typeModel->where('name', 'OUT')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(1);
        $qtyStart   = (float) $itemBefore['qty'];

        // IN +100
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-23',
                'details'          => [
                    ['item_id' => 1, 'qty' => 100],
                ],
            ])->assertStatus(201);

        $item1 = $itemModel->find(1);
        $this->assertSame($qtyStart + 100, (float) $item1['qty']);

        // OUT -50
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $outType['id'],
                'transaction_date' => '2026-04-24',
                'details'          => [
                    ['item_id' => 1, 'qty' => 50],
                ],
            ])->assertStatus(201);

        $item2 = $itemModel->find(1);
        $this->assertSame($qtyStart + 100 - 50, (float) $item2['qty']);

        // IN +25
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-25',
                'details'          => [
                    ['item_id' => 1, 'qty' => 25],
                ],
            ])->assertStatus(201);

        $itemFinal = $itemModel->find(1);
        $this->assertSame($qtyStart + 100 - 50 + 25, (float) $itemFinal['qty']);
    }

    public function testFailedOutTransactionDoesNotMutateQty(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(1);
        $qtyBefore  = (float) $itemBefore['qty'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $outType['id'],
                'transaction_date' => '2026-04-25',
                'details'          => [
                    ['item_id' => 1, 'qty' => $qtyBefore + 1],
                ],
            ]);

        $result->assertStatus(400);

        $itemAfter = $itemModel->find(1);
        $this->assertSame($qtyBefore, (float) $itemAfter['qty']);
    }

    public function testCreateTransactionRollbackDoesNotPersistMinStockNotification(): void
    {
        $db = Database::connect();

        $itemModel = new ItemModel();
        $item      = $itemModel->find(1);
        $this->assertNotNull($item);

        $initialQty = (float) $item['qty'];

        $db->table('items')->where('id', 1)->update([
            'min_stock'   => number_format($initialQty - 100, 2, '.', ''),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $typeModel = new TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();
        $this->assertNotNull($outType);

        $notificationCountBefore = $db->table('notifications')->countAllResults();

        $service = new StockTransactionService();
        $failingAuditService = new class () extends AuditService {
            public function log(
                ?int $userId,
                \App\Enums\AuditActionType $actionType,
                string $tableName,
                int $recordId,
                ?string $message = null,
                ?array $oldValues = null,
                ?array $newValues = null,
                ?string $ipAddress = null
            ): bool {
                return false;
            }
        };

        $service->setAuditServiceForTesting($failingAuditService);

        $result = $service->createTransaction([
            'type_id'          => (int) $outType['id'],
            'transaction_date' => '2026-07-01',
            'details'          => [
                ['item_id' => 1, 'qty' => 200],
            ],
        ], 1);

        $this->assertFalse($result['success']);
        $this->assertSame('Failed to write audit log.', $result['message']);

        $this->assertSame($initialQty, (float) $itemModel->find(1)['qty']);

        $notificationCountAfter = $db->table('notifications')->countAllResults();
        $this->assertSame($notificationCountBefore, $notificationCountAfter);
    }

    public function testAddMinStockMigrationDownIsSafeOnSqlite(): void
    {
        $db      = Database::connect();
        $columns = $db->getFieldNames('items');
        $this->assertContains('min_stock', $columns);

        $migration = new AddMinStockToItems();
        $migration->down();

        $columnsAfterDown = $db->getFieldNames('items');
        $this->assertContains('min_stock', $columnsAfterDown);
    }

    public function testCreateTransactionRejectsUnsupportedTransactionTypeName(): void
    {
        $token = $this->login('admin');

        // Create a transaction type that exists in DB but is not in SUPPORTED_TRANSACTION_TYPES
        $typeModel = new TransactionTypeModel();
        $typeModel->insert(['name' => 'UNSUPPORTED_TYPE']);
        $unsupportedType = $typeModel->where('name', 'UNSUPPORTED_TYPE')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $unsupportedType['id'],
                'transaction_date' => '2026-04-26',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    // Wave 2: Submit revision tests
    public function testSubmitRevisionWithoutTokenReturnsUnauthorized(): void
    {
        $this->post('api/v1/stock-transactions/1/submit-revision')->assertStatus(401);
    }

    public function testSubmitRevisionAsDapurReturnsForbidden(): void
    {
        $token = $this->login('dapur');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/1/submit-revision', [
                'transaction_date' => '2026-04-27',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(403);
    }

    public function testSubmitRevisionForMissingParentReturnsNotFound(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/9999/submit-revision', [
                'transaction_date' => '2026-04-28',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Parent transaction not found.']);
    }

    public function testSubmitRevisionRejectsForbiddenFields(): void
    {
        $adminToken = $this->login('admin');

        // Create parent transaction
        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-04-29',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json      = json_decode($createResult->getJSON(), true);
        $parentId  = $json['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-04-30',
                'user_id'          => 999,
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testSubmitRevisionRejectsUnknownTopLevelField(): void
    {
        $gudangToken = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-02',
                'unknown_field'    => 'value',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testSubmitRevisionRejectsUnknownDetailField(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-03',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-04',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15, 'extra' => 'field'],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testSubmitRevisionRejectsEmptyDetails(): void
    {
        $gudangToken = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-05',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-06',
                'details'          => [],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testValidSubmitRevisionCreatesRevisionRecord(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent transaction
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-07',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-08',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['message' => 'Revision submitted successfully.']);

        $revisionJson = json_decode($result->getJSON(), true);
        $this->assertTrue($revisionJson['data']['is_revision']);
        $this->assertSame($parentId, $revisionJson['data']['parent_transaction_id']);

        $approvalStatusModel = new ApprovalStatusModel();
        $pendingStatusId     = $approvalStatusModel->getIdByName('PENDING');
        $this->assertSame($pendingStatusId, $revisionJson['data']['approval_status_id']);
    }

    public function testValidSubmitRevisionDoesNotMutateQty(): void
    {
        $gudangToken = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(1);
        $qtyBefore  = (float) $itemBefore['qty'];

        // Create parent transaction (this will mutate qty)
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-09',
                'details'          => [
                    ['item_id' => 1, 'qty' => 100],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $itemAfterParent = $itemModel->find(1);
        $qtyAfterParent  = (float) $itemAfterParent['qty'];
        $this->assertSame($qtyBefore + 100, $qtyAfterParent);

        // Submit revision (this should NOT mutate qty)
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-10',
                'details'          => [
                    ['item_id' => 1, 'qty' => 200],
                ],
            ]);

        $result->assertStatus(201);

        $itemAfterRevision = $itemModel->find(1);
        $qtyAfterRevision  = (float) $itemAfterRevision['qty'];
        $this->assertSame($qtyAfterParent, $qtyAfterRevision);
    }

    public function testValidSubmitRevisionWritesAuditLog(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-11',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $auditModel  = new AuditLogModel();
        $countBefore = $auditModel->countAllResults();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-12',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $result->assertStatus(201);

        $countAfter = $auditModel->countAllResults();
        $this->assertSame($countBefore + 1, $countAfter);

        $latestAudit = $auditModel->orderBy('id', 'DESC')->first();
        $this->assertSame('submit', $latestAudit['action_type']);

        $newValues = json_decode((string) $latestAudit['new_values'], true);
        $this->assertIsArray($newValues);
        $this->assertIsArray($newValues['revision_header'] ?? null);
        $this->assertTrue($newValues['revision_header']['is_revision']);
        $this->assertSame($parentId, $newValues['revision_header']['parent_transaction_id']);
    }

    public function testSubmitRevisionRejectsRevisionAsParent(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-12',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-13',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/submit-revision', [
                'transaction_date' => '2026-05-14',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Revision transactions cannot be revised again.', $json['errors']['id']);
    }

    // Wave 3: Approve revision tests
    public function testApproveRevisionAsGudangReturnsForbidden(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/1/approve', []);

        $result->assertStatus(403);
    }

    public function testApproveRevisionForMissingRevisionReturnsNotFound(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/9999/approve', []);

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Revision not found.']);
    }

    public function testApproveNonRevisionTransactionReturnsError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create normal transaction
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-13',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json = json_decode($createResult->getJSON(), true);
        $id   = $json['data']['id'];

        // Try to approve it
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $id . '/approve', []);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testApproveAlreadyApprovedRevisionReturnsError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-14',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-15',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Approve once
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        // Try to approve again
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', []);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testApproveAlreadyRejectedRevisionReturnsError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-16',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-17',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Reject it first (we'll need reject endpoint for this, so this test will initially fail)
        // For now, manually update status
        $db = \Config\Database::connect();
        $approvalStatusModel = new ApprovalStatusModel();
        $rejectedStatusId    = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_REJECTED);
        $db->table('stock_transactions')->where('id', $revisionId)->update(['approval_status_id' => $rejectedStatusId]);

        // Try to approve rejected revision
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', []);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testValidApproveRevisionReturnsSuccess(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-18',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-19',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Approve
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', []);

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Revision approved successfully.']);

        $approveJson = json_decode($result->getJSON(), true);

        $approvalStatusModel = new ApprovalStatusModel();
        $approvedStatusId    = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $this->assertSame($approvedStatusId, $approveJson['data']['approval_status_id']);
        $this->assertIsInt($approveJson['data']['approved_by']);
    }

    public function testApproveRevisionMutatesQtyForInType(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(1);
        $qtyBefore  = (float) $itemBefore['qty'];

        // Create parent (will mutate qty +50)
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-20',
                'details'          => [
                    ['item_id' => 1, 'qty' => 50],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $itemAfterParent = $itemModel->find(1);
        $qtyAfterParent  = (float) $itemAfterParent['qty'];
        $this->assertSame($qtyBefore + 50, $qtyAfterParent);

        // Submit revision (+75, does NOT mutate yet)
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-21',
                'details'          => [
                    ['item_id' => 1, 'qty' => 75],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        $itemAfterRevisionSubmit = $itemModel->find(1);
        $qtyAfterRevisionSubmit  = (float) $itemAfterRevisionSubmit['qty'];
        $this->assertSame($qtyAfterParent, $qtyAfterRevisionSubmit);

        // Approve revision (should apply delta +25)
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        $itemAfterApprove = $itemModel->find(1);
        $qtyAfterApprove  = (float) $itemAfterApprove['qty'];
        $this->assertSame($qtyAfterParent + 25, $qtyAfterApprove);
    }

    public function testApproveRevisionMutatesQtyForOutType(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(1);
        $qtyBefore  = (float) $itemBefore['qty'];

        // Create parent OUT (-30)
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $outType['id'],
                'transaction_date' => '2026-05-22',
                'details'          => [
                    ['item_id' => 1, 'qty' => 30],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $itemAfterParent = $itemModel->find(1);
        $qtyAfterParent  = (float) $itemAfterParent['qty'];
        $this->assertSame($qtyBefore - 30, $qtyAfterParent);

        // Submit revision (-40, does NOT mutate yet)
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-23',
                'details'          => [
                    ['item_id' => 1, 'qty' => 40],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Approve revision (should apply delta -10)
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        $itemAfterApprove = $itemModel->find(1);
        $qtyAfterApprove  = (float) $itemAfterApprove['qty'];
        $this->assertSame($qtyAfterParent - 10, $qtyAfterApprove);
    }

    public function testApproveRevisionMutatesQtyForReturnInType(): void
    {
        $adminToken = $this->login('admin');

        $typeModel  = new TransactionTypeModel();
        $returnType = $typeModel->where('name', 'RETURN_IN')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(2);
        $qtyBefore  = (float) $itemBefore['qty'];

        // Create parent RETURN_IN (+25)
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $returnType['id'],
                'transaction_date' => '2026-05-24',
                'details'          => [
                    ['item_id' => 2, 'qty' => 25],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $itemAfterParent = $itemModel->find(2);
        $qtyAfterParent  = (float) $itemAfterParent['qty'];
        $this->assertSame($qtyBefore + 25, $qtyAfterParent);

        // Submit revision (+35)
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-25',
                'details'          => [
                    ['item_id' => 2, 'qty' => 35],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Approve revision
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        $itemAfterApprove = $itemModel->find(2);
        $qtyAfterApprove  = (float) $itemAfterApprove['qty'];
        $this->assertSame($qtyAfterParent + 10, $qtyAfterApprove);
    }

    public function testSubmitRevisionReusesExistingPendingSiblingInSameLineage(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel = new ItemModel();
        $qtyBefore = (float) $itemModel->find(1)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-25',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionOneResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-26',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);
        $revisionOneResult->assertStatus(201);
        $revisionOneId = json_decode($revisionOneResult->getJSON(), true)['data']['id'];

        $revisionTwoResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-27',
                'details'          => [
                    ['item_id' => 2, 'qty' => 15],
                ],
            ]);
        $revisionTwoResult->assertStatus(201);

        $json = json_decode($revisionTwoResult->getJSON(), true);
        $this->assertSame('Revision submitted successfully.', $json['message']);
        $this->assertSame($revisionOneId, $json['data']['id']);

        $qtyAfterSecondSubmitAttempt = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyBefore + 10, $qtyAfterSecondSubmitAttempt);
        $this->assertSame(3000.0, (float) $itemModel->find(2)['qty']);

        $transactionModel    = new \App\Models\StockTransactionModel();
        $revisionOneAfterTry = $transactionModel->find($revisionOneId);
        $approvalStatusModel = new ApprovalStatusModel();
        $pendingStatusId     = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_PENDING);
        $this->assertSame($pendingStatusId, (int) $revisionOneAfterTry['approval_status_id']);
        $this->assertSame('2026-05-27', $revisionOneAfterTry['transaction_date']);

        $pendingRevisions = $transactionModel
            ->where('parent_transaction_id', $parentId)
            ->where('is_revision', true)
            ->where('approval_status_id', $pendingStatusId)
            ->findAll();
        $this->assertCount(1, $pendingRevisions);

        $detailModel = new StockTransactionDetailModel();
        $revisionDetails = $detailModel->getDetailsByTransactionId($revisionOneId);
        $this->assertCount(1, $revisionDetails);
        $this->assertSame(2, (int) $revisionDetails[0]['item_id']);
        $this->assertSame(15.0, (float) $revisionDetails[0]['qty']);
    }

    public function testSubmitRevisionAllowsNewSiblingAfterPreviousRejected(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel = new ItemModel();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-25',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];
        $qtyAfterParent = (float) $itemModel->find(1)['qty'];

        $revisionOneResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-26',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);
        $revisionOneResult->assertStatus(201);
        $revisionOneId = json_decode($revisionOneResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionOneId . '/reject', [])
            ->assertStatus(200);

        $revisionTwoResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-27',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);
        $revisionTwoResult->assertStatus(201);

        $qtyAfterSecondSubmit = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyAfterParent, $qtyAfterSecondSubmit);
    }

    public function testSubmitRevisionReplacesPendingRevisionSpkAndDetails(): void
    {
        $gudangToken = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-25',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionOneResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-26',
                'spk_id'           => 99,
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                    ['item_id' => 2, 'qty' => 3, 'input_unit' => 'convert'],
                ],
            ]);
        $revisionOneResult->assertStatus(201);
        $revisionId = json_decode($revisionOneResult->getJSON(), true)['data']['id'];

        $revisionTwoResult = $this->withHeaders(['Authorization' => 'Bearer ' . $gudangToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-27',
                'spk_id'           => null,
                'details'          => [
                    ['item_id' => 2, 'qty' => 500, 'input_unit' => 'base'],
                ],
            ]);
        $revisionTwoResult->assertStatus(201);

        $revisionTwoJson = json_decode($revisionTwoResult->getJSON(), true);
        $this->assertSame($revisionId, $revisionTwoJson['data']['id']);

        $transactionModel = new StockTransactionModel();
        $revision         = $transactionModel->find($revisionId);
        $this->assertSame('2026-05-27', $revision['transaction_date']);
        $this->assertNull($revision['spk_id']);

        $detailModel = new StockTransactionDetailModel();
        $details     = $detailModel->getDetailsByTransactionId($revisionId);
        $this->assertCount(1, $details);
        $this->assertSame(2, (int) $details[0]['item_id']);
        $this->assertSame(500.0, (float) $details[0]['qty']);
        $this->assertSame(500.0, (float) $details[0]['input_qty']);
        $this->assertSame('base', $details[0]['input_unit']);
    }

    public function testSubmitRevisionAllowsNewSiblingAfterPreviousApproved(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-25',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionOneResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-26',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);
        $revisionOneResult->assertStatus(201);
        $revisionOneId = json_decode($revisionOneResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionOneId . '/approve', [])
            ->assertStatus(200);

        $revisionTwoResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-27',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);
        $revisionTwoResult->assertStatus(201);
    }

    public function testApproveRevisionUsesLatestApprovedSiblingAsBaseline(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();
        $itemModel = new ItemModel();

        $qtyBefore = (float) $itemModel->find(1)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-25',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];
        $qtyAfterParent = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyBefore + 10, $qtyAfterParent);

        $revisionAResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-26',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);
        $revisionAId = json_decode($revisionAResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionAId . '/approve', [])
            ->assertStatus(200);

        $qtyAfterApproveA = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyBefore + 12, $qtyAfterApproveA);

        $revisionBResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-27',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);
        $revisionBId = json_decode($revisionBResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionBId . '/approve', [])
            ->assertStatus(200);

        $qtyAfterApproveB = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyBefore + 15, $qtyAfterApproveB);
    }

    public function testApproveRevisionWithZeroDeltaAgainstLatestApprovedBaselineLeavesQtyUnchanged(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();
        $itemModel = new ItemModel();

        $qtyBefore = (float) $itemModel->find(1)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-25',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionAResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-26',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);
        $revisionAId = json_decode($revisionAResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionAId . '/approve', [])
            ->assertStatus(200);

        $qtyAfterApproveA = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyBefore + 12, $qtyAfterApproveA);

        $revisionBResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-27',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);
        $revisionBId = json_decode($revisionBResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionBId . '/approve', [])
            ->assertStatus(200);

        $qtyAfterApproveB = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyAfterApproveA, $qtyAfterApproveB);
    }

    public function testApproveRevisionAppliesDeltaForAddedItem(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel  = new ItemModel();
        $item1Before = (float) $itemModel->find(1)['qty'];
        $item2Before = (float) $itemModel->find(2)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-28',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $qtyAfterParentItem1 = (float) $itemModel->find(1)['qty'];
        $qtyAfterParentItem2 = (float) $itemModel->find(2)['qty'];
        $this->assertSame($item1Before + 10, $qtyAfterParentItem1);
        $this->assertSame($item2Before, $qtyAfterParentItem2);

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-29',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                    ['item_id' => 2, 'qty' => 15],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        $item1AfterApprove = (float) $itemModel->find(1)['qty'];
        $item2AfterApprove = (float) $itemModel->find(2)['qty'];

        $this->assertSame($qtyAfterParentItem1, $item1AfterApprove);
        $this->assertSame($item2Before + 15, $item2AfterApprove);
    }

    public function testApproveRevisionReversesRemovedItemEffect(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();

        $itemModel   = new ItemModel();
        $item1Before = (float) $itemModel->find(1)['qty'];
        $item2Before = (float) $itemModel->find(2)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $outType['id'],
                'transaction_date' => '2026-05-30',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                    ['item_id' => 2, 'qty' => 20],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $qtyAfterParentItem1 = (float) $itemModel->find(1)['qty'];
        $qtyAfterParentItem2 = (float) $itemModel->find(2)['qty'];
        $this->assertSame($item1Before - 10, $qtyAfterParentItem1);
        $this->assertSame($item2Before - 20, $qtyAfterParentItem2);

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-31',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        $item1AfterApprove = (float) $itemModel->find(1)['qty'];
        $item2AfterApprove = (float) $itemModel->find(2)['qty'];

        $this->assertSame($qtyAfterParentItem1, $item1AfterApprove);
        $this->assertSame($item2Before, $item2AfterApprove);
    }

    public function testApproveRevisionWithZeroDeltaLeavesQtyUnchanged(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel = new ItemModel();
        $qtyBefore = (float) $itemModel->find(1)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $qtyAfterParent = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyBefore + 12, $qtyAfterParent);

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-02',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        $qtyAfterApprove = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyAfterParent, $qtyAfterApprove);
    }

    public function testApproveRevisionUsesPositiveDeltaForOpnameAdjustmentType(): void
    {
        $adminToken = $this->login('admin');

        $typeModel            = new TransactionTypeModel();
        $opnameAdjustmentType = $typeModel->where('name', TransactionTypeModel::NAME_OPNAME_ADJUSTMENT)->first();

        $itemModel = new ItemModel();
        $qtyBefore = (float) $itemModel->find(1)['qty'];

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $opnameAdjustmentType['id'],
                'transaction_date' => '2026-06-02',
                'details'          => [
                    ['item_id' => 1, 'qty' => 12],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $qtyAfterParent = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyBefore + 12, $qtyAfterParent);

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-03',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        $qtyAfterApprove = (float) $itemModel->find(1)['qty'];
        $this->assertSame($qtyBefore + 20, $qtyAfterApprove);
    }

    public function testApproveRevisionRollsBackAllItemQtyChangesWhenLaterDeltaFails(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel = new ItemModel();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-03',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                    ['item_id' => 2, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $qtyAfterParentItem1 = (float) $itemModel->find(1)['qty'];

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-04',
                'details'          => [
                    ['item_id' => 1, 'qty' => 5],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $db = Database::connect();
        $db->table('items')->where('id', 2)->update(['qty' => 5]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', []);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $item1AfterFailedApprove = (float) $itemModel->find(1)['qty'];
        $item2AfterFailedApprove = (float) $itemModel->find(2)['qty'];
        $this->assertSame($qtyAfterParentItem1, $item1AfterFailedApprove);
        $this->assertSame(5.0, $item2AfterFailedApprove);

        $transactionModel  = new \App\Models\StockTransactionModel();
        $revisionAfterFail = $transactionModel->find($revisionId);
        $approvalStatusModel = new ApprovalStatusModel();
        $pendingStatusId     = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_PENDING);
        $this->assertSame($pendingStatusId, (int) $revisionAfterFail['approval_status_id']);
    }

    public function testApproveRevisionWritesAuditLog(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-26',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-27',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        $auditModel  = new AuditLogModel();
        $countBefore = $auditModel->countAllResults();

        // Approve
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        $countAfter = $auditModel->countAllResults();
        $this->assertSame($countBefore + 1, $countAfter);

        $latestAudit = $auditModel->orderBy('id', 'DESC')->first();
        $this->assertSame('approval', $latestAudit['action_type']);

        $oldValues = json_decode((string) $latestAudit['old_values'], true);
        $newValues = json_decode((string) $latestAudit['new_values'], true);
        $this->assertIsArray($oldValues);
        $this->assertIsArray($newValues);
        $this->assertSame($oldValues['id'], $newValues['id']);
        $this->assertNotSame($oldValues['approval_status_id'], $newValues['approval_status_id']);
        $this->assertNull($oldValues['approved_by']);
        $this->assertNotNull($newValues['approved_by']);
    }

    public function testApproveRevisionWithInvalidStatusReturnsExplicitError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-27',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-28',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $approvalStatusModel = new ApprovalStatusModel();
        $unexpectedStatusId  = $approvalStatusModel->insert(['name' => 'ARCHIVED'], true);

        $db = \Config\Database::connect();
        $db->table('stock_transactions')->where('id', $revisionId)->update(['approval_status_id' => $unexpectedStatusId]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', []);

        $result->assertStatus(400);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Revision has an invalid approval state.', $json['errors']['id']);
    }

    public function testApproveRevisionWithInsufficientStockLeavesQtyAndStatusUnchanged(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();

        $itemModel = new ItemModel();

        // Create parent OUT (-10)
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $outType['id'],
                'transaction_date' => '2026-05-28',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $itemAfterParent = $itemModel->find(1);
        $qtyAfterParent  = (float) $itemAfterParent['qty'];

        // Submit revision requesting more additional OUT stock than is currently available.
        // Parent OUT qty=10, so any revision qty above 5000 would require more than the
        // remaining 4990 units to be decremented during approval.
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-05-29',
                'details'          => [
                    ['item_id' => 1, 'qty' => $qtyAfterParent + 11],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Try to approve (should fail)
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', []);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        // Qty should be unchanged
        $itemAfterFailedApprove = $itemModel->find(1);
        $qtyAfterFailedApprove  = (float) $itemAfterFailedApprove['qty'];
        $this->assertSame($qtyAfterParent, $qtyAfterFailedApprove);

        // Revision should still be PENDING
        $transactionModel  = new \App\Models\StockTransactionModel();
        $revisionAfterFail = $transactionModel->find($revisionId);
        $approvalStatusModel = new ApprovalStatusModel();
        $pendingStatusId     = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_PENDING);
        $this->assertSame($pendingStatusId, (int) $revisionAfterFail['approval_status_id']);
    }

    // Wave 4: Reject revision tests
    public function testRejectRevisionAsGudangReturnsForbidden(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/1/reject', []);

        $result->assertStatus(403);
    }

    public function testRejectRevisionForMissingRevisionReturnsNotFound(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/9999/reject', []);

        $result->assertStatus(404);
        $result->assertJSONFragment(['message' => 'Revision not found.']);
    }

    public function testRejectNonRevisionTransactionReturnsError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create normal transaction
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-30',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json = json_decode($createResult->getJSON(), true);
        $id   = $json['data']['id'];

        // Try to reject it
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $id . '/reject', []);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testRejectAlreadyApprovedRevisionReturnsError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-05-31',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Approve it
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/approve', [])
            ->assertStatus(200);

        // Try to reject approved revision
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', []);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testRejectAlreadyRejectedRevisionReturnsError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-02',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-03',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Reject once
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', [])
            ->assertStatus(200);

        // Try to reject again
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', []);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testValidRejectRevisionReturnsSuccess(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-04',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-05',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        // Reject
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', []);

        $result->assertStatus(200);
        $result->assertJSONFragment(['message' => 'Revision rejected successfully.']);

        $rejectJson = json_decode($result->getJSON(), true);

        $approvalStatusModel = new ApprovalStatusModel();
        $rejectedStatusId    = $approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_REJECTED);
        $this->assertSame($rejectedStatusId, $rejectJson['data']['approval_status_id']);
        $this->assertIsInt($rejectJson['data']['approved_by']);

        $transactionModel = new StockTransactionModel();
        $rejectedRevision = $transactionModel->find($revisionId);
        $this->assertNull($rejectedRevision['rejection_reason']);
    }

    public function testRejectRevisionWithReasonPersistsRejectionReasonWithoutReusingReason(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-05',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-06',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];
        $reason     = 'Please fix the revised quantity before resubmitting.';

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', [
                'reason' => $reason,
            ])
            ->assertStatus(200);

        $transactionModel = new StockTransactionModel();
        $rejectedRevision = $transactionModel->find($revisionId);

        $this->assertSame($reason, $rejectedRevision['rejection_reason']);
        $this->assertNull($rejectedRevision['reason']);
    }

    public function testRejectRevisionWithBlankReasonReturnsValidationError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-06',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-07',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', [
                'reason' => '   ',
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('The reason field is required.', $json['errors']['reason']);

        $transactionModel = new StockTransactionModel();
        $rejectedRevision = $transactionModel->find($revisionId);
        $this->assertNull($rejectedRevision['rejection_reason']);
    }

    public function testRejectRevisionWithTooLongReasonReturnsValidationError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-07',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-08',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', [
                'reason' => str_repeat('a', 256),
            ]);

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('The reason field must not exceed 255 characters.', $json['errors']['reason']);

        $transactionModel = new StockTransactionModel();
        $rejectedRevision = $transactionModel->find($revisionId);
        $this->assertNull($rejectedRevision['rejection_reason']);
    }

    public function testRejectRevisionDoesNotMutateQty(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $itemModel  = new ItemModel();
        $itemBefore = $itemModel->find(1);
        $qtyBefore  = (float) $itemBefore['qty'];

        // Create parent (will mutate qty +30)
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-06',
                'details'          => [
                    ['item_id' => 1, 'qty' => 30],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        $itemAfterParent = $itemModel->find(1);
        $qtyAfterParent  = (float) $itemAfterParent['qty'];
        $this->assertSame($qtyBefore + 30, $qtyAfterParent);

        // Submit revision (+50, does NOT mutate yet)
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-07',
                'details'          => [
                    ['item_id' => 1, 'qty' => 50],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        $itemAfterRevisionSubmit = $itemModel->find(1);
        $qtyAfterRevisionSubmit  = (float) $itemAfterRevisionSubmit['qty'];
        $this->assertSame($qtyAfterParent, $qtyAfterRevisionSubmit);

        // Reject revision (should NOT mutate)
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', [])
            ->assertStatus(200);

        $itemAfterReject = $itemModel->find(1);
        $qtyAfterReject  = (float) $itemAfterReject['qty'];
        $this->assertSame($qtyAfterParent, $qtyAfterReject);
    }

    public function testRejectRevisionWritesAuditLog(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Create parent
        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-08',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $json     = json_decode($createResult->getJSON(), true);
        $parentId = $json['data']['id'];

        // Submit revision
        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-09',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionJson = json_decode($revisionResult->getJSON(), true);
        $revisionId   = $revisionJson['data']['id'];

        $auditModel  = new AuditLogModel();
        $countBefore = $auditModel->countAllResults();

        // Reject
        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', [])
            ->assertStatus(200);

        $countAfter = $auditModel->countAllResults();
        $this->assertSame($countBefore + 1, $countAfter);

        $latestAudit = $auditModel->orderBy('id', 'DESC')->first();
        $this->assertSame('rejection', $latestAudit['action_type']);

        $oldValues = json_decode((string) $latestAudit['old_values'], true);
        $newValues = json_decode((string) $latestAudit['new_values'], true);
        $this->assertIsArray($oldValues);
        $this->assertIsArray($newValues);
        $this->assertSame($oldValues['id'], $newValues['id']);
        $this->assertNotSame($oldValues['approval_status_id'], $newValues['approval_status_id']);
        $this->assertNull($oldValues['approved_by']);
        $this->assertNotNull($newValues['approved_by']);
    }

    public function testRejectRevisionWithInvalidStatusReturnsExplicitError(): void
    {
        $adminToken = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $createResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-06-10',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $parentId = json_decode($createResult->getJSON(), true)['data']['id'];

        $revisionResult = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $parentId . '/submit-revision', [
                'transaction_date' => '2026-06-11',
                'details'          => [
                    ['item_id' => 1, 'qty' => 15],
                ],
            ]);

        $revisionId = json_decode($revisionResult->getJSON(), true)['data']['id'];

        $approvalStatusModel = new ApprovalStatusModel();
        $unexpectedStatusId  = $approvalStatusModel->insert(['name' => 'ARCHIVED'], true);

        $db = \Config\Database::connect();
        $db->table('stock_transactions')->where('id', $revisionId)->update(['approval_status_id' => $unexpectedStatusId]);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions/' . $revisionId . '/reject', []);

        $result->assertStatus(400);

        $json = json_decode($result->getJSON(), true);
        $this->assertSame('Revision has an invalid approval state.', $json['errors']['id']);
    }

    // Dual lookup tests: type_name support

    public function testCreateTransactionWithTypeNameSucceeds(): void
    {
        $token = $this->login('gudang');

        $typeModel = new \App\Models\TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'IN',
                'transaction_date' => '2026-07-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 100],
                ],
            ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('id', $json['data']);

        // Verify the transaction was created with the correct type
        $transactionModel = new \App\Models\StockTransactionModel();
        $transaction      = $transactionModel->find($json['data']['id']);
        $this->assertSame($inType['id'], $transaction['type_id']);
    }

    public function testCreateTransactionWithTrimmedTypeNameSucceeds(): void
    {
        $token = $this->login('gudang');

        $typeModel = new \App\Models\TransactionTypeModel();
        $outType   = $typeModel->where('name', 'OUT')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => '  OUT  ',
                'transaction_date' => '2026-07-02',
                'details'          => [
                    ['item_id' => 1, 'qty' => 50],
                ],
            ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('id', $json['data']);

        // Verify the transaction was created with the correct type
        $transactionModel = new \App\Models\StockTransactionModel();
        $transaction      = $transactionModel->find($json['data']['id']);
        $this->assertSame($outType['id'], $transaction['type_id']);
    }

    public function testCreateTransactionWithCaseInsensitiveTypeNameSucceeds(): void
    {
        $token = $this->login('gudang');

        $typeModel  = new \App\Models\TransactionTypeModel();
        $returnType = $typeModel->where('name', 'RETURN_IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'return_in',
                'transaction_date' => '2026-07-03',
                'details'          => [
                    ['item_id' => 1, 'qty' => 20],
                ],
            ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('id', $json['data']);

        // Verify the transaction was created with the correct type
        $transactionModel = new \App\Models\StockTransactionModel();
        $transaction      = $transactionModel->find($json['data']['id']);
        $this->assertSame($returnType['id'], $transaction['type_id']);
    }

    public function testCreateTransactionWithBothTypeIdAndTypeNameFails(): void
    {
        $token = $this->login('gudang');

        $typeModel = new \App\Models\TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'type_name'        => 'IN',
                'transaction_date' => '2026-07-04',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertStringContainsString('type_id', $json['errors']['type_id']);
        $this->assertStringContainsString('type_name', $json['errors']['type_id']);
    }

    public function testCreateTransactionWithInvalidTypeNameFails(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'INVALID_TYPE',
                'transaction_date' => '2026-07-05',
                'details'          => [
                    ['item_id' => 1, 'qty' => 10],
                ],
            ]);

        $result->assertStatus(400);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('type_name', $json['errors']);
    }

    // -------------------------------------------------------------------------
    // Unit-conversion tests
    // -------------------------------------------------------------------------

    /**
     * Test 1 — Legacy create with qty only (no input_unit).
     * Backward-compatible: stored qty == request qty, input_unit defaults to "base".
     */
    public function testCreateTransactionLegacyQtyOnlyStoresCorrectly(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'IN',
                'transaction_date' => '2026-08-01',
                'details'          => [
                    ['item_id' => 1, 'qty' => 500],
                ],
            ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $transactionId = $json['data']['id'];

        $db     = Database::connect();
        $detail = $db->table('stock_transaction_details')
            ->where('transaction_id', $transactionId)
            ->get()->getRowArray();

        $this->assertNotNull($detail);
        // stored qty must equal the request qty (base default)
        $this->assertEquals(500.0, (float) $detail['qty']);
        $this->assertEquals(500.0, (float) $detail['input_qty']);
        $this->assertSame('base', $detail['input_unit']);
    }

    /**
     * Test 2 — Create with input_unit=convert.
     * Stored qty must be request qty * conversion_base (1000).
     */
    public function testCreateTransactionWithInputUnitConvertNormalizesQty(): void
    {
        $token = $this->login('gudang');

        // Send 2 kg → should store 2000 grams
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'IN',
                'transaction_date' => '2026-08-02',
                'details'          => [
                    ['item_id' => 1, 'qty' => 2, 'input_unit' => 'convert'],
                ],
            ]);

        $result->assertStatus(201);

        $json = json_decode($result->getJSON(), true);
        $transactionId = $json['data']['id'];

        $db     = Database::connect();
        $detail = $db->table('stock_transaction_details')
            ->where('transaction_id', $transactionId)
            ->get()->getRowArray();

        $this->assertNotNull($detail);
        $this->assertEquals(2000.0, (float) $detail['qty']);
        $this->assertEquals(2.0, (float) $detail['input_qty']);
        $this->assertSame('convert', $detail['input_unit']);
    }

    /**
     * Test 3 — Detail responses include input_qty, input_unit, and normalized qty.
     */
    public function testTransactionDetailsResponseIncludesInputFields(): void
    {
        $token = $this->login('gudang');

        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'IN',
                'transaction_date' => '2026-08-03',
                'details'          => [
                    ['item_id' => 1, 'qty' => 3, 'input_unit' => 'convert'],
                ],
            ]);

        $create->assertStatus(201);
        $transactionId = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get("api/v1/stock-transactions/{$transactionId}/details");

        $result->assertStatus(200);

        $json    = json_decode($result->getJSON(), true);
        $details = $json['data'] ?? [];
        $this->assertNotEmpty($details);

        $first = $details[0];
        $this->assertArrayHasKey('item_name', $first);
        $this->assertArrayHasKey('item_category_id', $first);
        $this->assertArrayHasKey('item_category_name', $first);
        $this->assertArrayHasKey('satuan', $first);
        $this->assertArrayHasKey('qty', $first);
        $this->assertArrayHasKey('input_qty', $first);
        $this->assertArrayHasKey('input_unit', $first);

        $this->assertSame('Beras', $first['item_name']);
        $this->assertNotNull($first['item_category_id']);
        $this->assertSame('KERING', $first['item_category_name']);
        $this->assertSame('gram', $first['satuan']);
        $this->assertEquals(3000.0, (float) $first['qty']);
        $this->assertEquals(3.0, (float) $first['input_qty']);
        $this->assertSame('convert', $first['input_unit']);
    }

    /**
     * Test 4 — Revision submit with input_unit=convert stores normalized qty.
     */
    public function testSubmitRevisionWithInputUnitConvertNormalizesQty(): void
    {
        $token = $this->login('gudang');

        // Create a parent transaction first (IN, approved)
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'IN',
                'transaction_date' => '2026-08-04',
                'details'          => [
                    ['item_id' => 2, 'qty' => 1000],
                ],
            ]);

        $create->assertStatus(201);
        $parentId = json_decode($create->getJSON(), true)['data']['id'];

        // Submit revision with input_unit=convert (1 kg = 1000 g)
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post("api/v1/stock-transactions/{$parentId}/submit-revision", [
                'transaction_date' => '2026-08-05',
                'details'          => [
                    ['item_id' => 2, 'qty' => 1, 'input_unit' => 'convert'],
                ],
            ]);

        $result->assertStatus(201);

        $revisionId = json_decode($result->getJSON(), true)['data']['id'];

        $db     = Database::connect();
        $detail = $db->table('stock_transaction_details')
            ->where('transaction_id', $revisionId)
            ->get()->getRowArray();

        $this->assertNotNull($detail);
        $this->assertEquals(1000.0, (float) $detail['qty']);
        $this->assertEquals(1.0, (float) $detail['input_qty']);
        $this->assertSame('convert', $detail['input_unit']);
    }

    public function testSubmitRevisionLegacyQtyOnlyDefaultsInputUnitToBase(): void
    {
        $token = $this->login('gudang');

        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'IN',
                'transaction_date' => '2026-08-04',
                'details'          => [
                    ['item_id' => 2, 'qty' => 1000],
                ],
            ]);

        $create->assertStatus(201);
        $parentId = json_decode($create->getJSON(), true)['data']['id'];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post("api/v1/stock-transactions/{$parentId}/submit-revision", [
                'transaction_date' => '2026-08-05',
                'details'          => [
                    ['item_id' => 2, 'qty' => 750],
                ],
            ]);

        $result->assertStatus(201);

        $revisionId = json_decode($result->getJSON(), true)['data']['id'];

        $db     = Database::connect();
        $detail = $db->table('stock_transaction_details')
            ->where('transaction_id', $revisionId)
            ->get()->getRowArray();

        $this->assertNotNull($detail);
        $this->assertEquals(750.0, (float) $detail['qty']);
        $this->assertEquals(750.0, (float) $detail['input_qty']);
        $this->assertSame('base', $detail['input_unit']);
    }

    /**
     * Test 5 — Approve revision mutates stock using normalized qty (not input_qty).
     */
    public function testApproveRevisionUsesNormalizedQtyForStockMutation(): void
    {
        $token      = $this->login('gudang');
        $adminToken = $this->login('admin');
        $itemModel  = new ItemModel();

        // Beras starts at 5000 g
        $before = (float) $itemModel->find(1)['qty'];

        // Create a parent IN transaction
        $create = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'IN',
                'transaction_date' => '2026-08-06',
                'details'          => [
                    ['item_id' => 1, 'qty' => 100],
                ],
            ]);

        $create->assertStatus(201);
        $parentId = json_decode($create->getJSON(), true)['data']['id'];

        // Submit revision with input_unit=convert (4 kg → 4000 g)
        $revision = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post("api/v1/stock-transactions/{$parentId}/submit-revision", [
                'transaction_date' => '2026-08-07',
                'details'          => [
                    ['item_id' => 1, 'qty' => 4, 'input_unit' => 'convert'],
                ],
            ]);

        $revision->assertStatus(201);
        $revisionId = json_decode($revision->getJSON(), true)['data']['id'];

        // Approve the revision
        $approve = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->withBodyFormat('json')
            ->post("api/v1/stock-transactions/{$revisionId}/approve");

        $approve->assertStatus(200);

        // Stock should reflect the normalized revision delta: parent +100 g already applied,
        // then approving 4 kg (4000 g) replaces that parent qty, so the net change is +3900 g.
        $after = (float) $itemModel->find(1)['qty'];
        // before + 100 (create) + 3900 (approve revision delta)
        $this->assertEquals($before + 4000.0, $after);
    }

    /**
     * Test 6 — Invalid input_unit value is rejected with 400.
     */
    public function testCreateTransactionWithInvalidInputUnitFails(): void
    {
        $token = $this->login('gudang');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'        => 'IN',
                'transaction_date' => '2026-08-08',
                'details'          => [
                    ['item_id' => 1, 'qty' => 5, 'input_unit' => 'kilos'],
                ],
            ]);

        $result->assertStatus(400);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('errors', $json);
        $this->assertArrayHasKey('details.0.input_unit', $json['errors']);
    }

    // -------------------------------------------------------------------------
    // List filter param tests
    // -------------------------------------------------------------------------

    public function testListTransactionsFiltersByTypeId(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();
        $outType   = $typeModel->where('name', 'OUT')->first();

        // Create an IN and an OUT transaction
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-09-01',
                'details'          => [['item_id' => 1, 'qty' => 10]],
            ])->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $outType['id'],
                'transaction_date' => '2026-09-02',
                'details'          => [['item_id' => 1, 'qty' => 5]],
            ])->assertStatus(201);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?type_id=' . $inType['id']);

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        foreach ($json['data'] as $row) {
            $this->assertSame($inType['id'], (int) $row['type_id']);
        }
    }

    public function testListTransactionsFiltersByTransactionDateRange(): void
    {
        $token = $this->login('gudang');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        // Three transactions: before, within, after the range
        foreach (['2026-09-10', '2026-09-15', '2026-09-20'] as $date) {
            $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->withBodyFormat('json')
                ->post('api/v1/stock-transactions', [
                    'type_id'          => $inType['id'],
                    'transaction_date' => $date,
                    'details'          => [['item_id' => 1, 'qty' => 5]],
                ])->assertStatus(201);
        }

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?transaction_date_from=2026-09-12&transaction_date_to=2026-09-18');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertCount(1, $json['data']);
        $this->assertSame('2026-09-15', $json['data'][0]['transaction_date']);
    }

    public function testListTransactionsSearchBySpkId(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-09-25',
                'spk_id'           => 12345,
                'details'          => [['item_id' => 1, 'qty' => 10]],
            ])->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-09-26',
                'spk_id'           => 99999,
                'details'          => [['item_id' => 1, 'qty' => 10]],
            ])->assertStatus(201);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?q=12345');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertCount(1, $json['data']);
        $this->assertSame(12345, (int) $json['data'][0]['spk_id']);
    }
    public function testListTransactionsFiltersBySpkId(): void
    {
        $token = $this->login('admin');

        $typeModel = new TransactionTypeModel();
        $inType    = $typeModel->where('name', 'IN')->first();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-09-27',
                'spk_id'           => 12345,
                'details'          => [['item_id' => 1, 'qty' => 10]],
            ])->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_id'          => $inType['id'],
                'transaction_date' => '2026-09-28',
                'spk_id'           => 99999,
                'details'          => [['item_id' => 1, 'qty' => 10]],
            ])->assertStatus(201);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?spk_id=12345');

        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $json);
        $this->assertCount(1, $json['data']);
        $this->assertSame(12345, (int) $json['data'][0]['spk_id']);
    }

    public function testListTransactionsRejectsInvalidSortBy(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?sortBy=invalid_column');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    public function testListTransactionsRejectsInvalidSortDir(): void
    {
        $token = $this->login('admin');

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions?sortDir=SIDEWAYS');

        $result->assertStatus(400);
        $result->assertJSONFragment(['message' => 'Validation failed.']);
    }

    // Regression: stock transactions must not be deletable via any route
    public function testDeleteStockTransactionAsAdminReturns404(): void
    {
        $token = $this->login('admin');

        // Create a transaction first
        $transactionId = $this->createTransaction('admin');

        // DELETE route does not exist — router should return 404
        try {
            $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->delete('api/v1/stock-transactions/' . $transactionId);
            $this->fail('Expected missing DELETE route to throw PageNotFoundException.');
        } catch (PageNotFoundException $exception) {
            $this->assertStringContainsString('DELETE: api/v1/stock-transactions/' . $transactionId, $exception->getMessage());
        }

        // Verify the transaction still exists (show returns 200 or the transaction data)
        $show = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get('api/v1/stock-transactions/' . $transactionId);

        $show->assertStatus(200);
    }

    public function testDeleteStockTransactionAsGudangReturns404(): void
    {
        $token = $this->login('gudang');

        $transactionId = $this->createTransaction('gudang');

        try {
            $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->delete('api/v1/stock-transactions/' . $transactionId);
            $this->fail('Expected missing DELETE route to throw PageNotFoundException.');
        } catch (PageNotFoundException $exception) {
            $this->assertStringContainsString('DELETE: api/v1/stock-transactions/' . $transactionId, $exception->getMessage());
        }

        // Verify the transaction still exists via admin
        $adminToken = $this->login('admin');
        $show = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->get('api/v1/stock-transactions/' . $transactionId);

        $show->assertStatus(200);
    }

    /**
     * Helper: create a minimal stock transaction and return its ID.
     */
    private function createTransaction(string $username): int
    {
        $token = $this->login($username);

        $itemModel = new ItemModel();
        $items     = $itemModel->findAll();
        $item      = $items[0];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'  => 'IN',
                'transaction_date' => '2026-04-30',
                'details'    => [
                    [
                        'item_id'  => $item['id'],
                        'qty'      => 10,
                    ],
                ],
            ]);

        $json = json_decode($result->getJSON(), true);
        return (int) $json['data']['id'];
    }

    public function testCreateTransactionTriggersSnapshot(): void
    {
        $token = $this->login('admin');
        $db = Database::connect();
        
        // Clean any existing snapshots for current month
        $currentMonth = date('Y-m') . '-01';
        $db->table('monthly_stock_snapshots')->where('period_month', $currentMonth)->delete();

        $itemModel = new ItemModel();
        $items = $itemModel->findAll();
        $item = $items[0];

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->withBodyFormat('json')
            ->post('api/v1/stock-transactions', [
                'type_name'  => 'IN',
                'transaction_date' => date('Y-m-d'),
                'details'    => [
                    [
                        'item_id'  => $item['id'],
                        'qty'      => 10,
                    ],
                ],
            ]);

        $result->assertStatus(201);

        // Verify snapshot was created
        $count = $db->table('monthly_stock_snapshots')
            ->where('period_month', $currentMonth)
            ->where('item_id', $item['id'])
            ->countAllResults();

        $this->assertSame(1, $count);
    }

    public function testSnapshotFailureDoesNotBlockTransaction(): void
    {
        $token = $this->login('admin');
        $db = Database::connect();
        $prefix = $db->getPrefix();
        $tableName = $prefix . 'monthly_stock_snapshots';
        $tempTableName = $prefix . 'monthly_stock_snapshots_temp';

        // Rename table to simulate DB error
        $db->query("ALTER TABLE {$tableName} RENAME TO {$tempTableName}");

        try {
            $itemModel = new ItemModel();
            $items = $itemModel->findAll();
            $item = $items[0];

            $result = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                ->withBodyFormat('json')
                ->post('api/v1/stock-transactions', [
                    'type_name'  => 'IN',
                    'transaction_date' => date('Y-m-d'),
                    'details'    => [
                        [
                            'item_id'  => $item['id'],
                            'qty'      => 10,
                        ],
                    ],
                ]);

            $result->assertStatus(201);
        } finally {
            // Restore table name
            $db->query("ALTER TABLE {$tempTableName} RENAME TO {$tableName}");
        }
    }
}

