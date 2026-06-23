<?php

namespace App\Services;

use App\Enums\AuditActionType;

use App\Models\ApprovalStatusModel;
use App\Models\ItemModel;
use App\Models\StockTransactionDetailModel;
use App\Models\StockTransactionModel;
use App\Models\TransactionTypeModel;
use App\Services\NotificationService;
use CodeIgniter\Database\BaseConnection;
use App\Services\StockSnapshotService;
use Config\Database;

class StockTransactionService
{
    public const FORBIDDEN_FIELDS = [
        'user_id',
        'approved_by',
        'approval_status_id',
        'is_revision',
        'parent_transaction_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    private const ALLOWED_TOP_LEVEL_FIELDS = [
        'type_id',
        'type_name',
        'transaction_date',
        'spk_id',
        'details',
    ];

    private const ALLOWED_DETAIL_FIELDS = [
        'item_id',
        'qty',
        'input_unit',
    ];

    private const ALLOWED_REVISION_TOP_LEVEL_FIELDS = [
        'transaction_date',
        'spk_id',
        'details',
        'reason',
    ];

    private const ALLOWED_DIRECT_CORRECTION_FIELDS = [
        'transaction_date',
        'item_id',
        'expected_current_qty',
        'target_qty',
        'reason',
    ];

    private const SUPPORTED_TRANSACTION_TYPES = [
        TransactionTypeModel::NAME_IN,
        TransactionTypeModel::NAME_OUT,
        TransactionTypeModel::NAME_RETURN_IN,
        TransactionTypeModel::NAME_OPNAME_ADJUSTMENT,
    ];

    protected StockTransactionModel $transactionModel;
    protected StockTransactionDetailModel $detailModel;
    protected TransactionTypeModel $typeModel;
    protected ItemModel $itemModel;
    protected ApprovalStatusModel $approvalStatusModel;
    protected AuditService $auditService;
    protected NotificationService $notificationService;
    protected BaseConnection $db;
    /**
     * @var array<int, array{item_id:int,item_name:string}>
     */
    private array $queuedMinStockNotifications = [];

    public function __construct()
    {
        $this->transactionModel = new StockTransactionModel();
        $this->detailModel = new StockTransactionDetailModel();
        $this->typeModel = new TransactionTypeModel();
        $this->itemModel = new ItemModel();
        $this->approvalStatusModel = new ApprovalStatusModel();
        $this->auditService = new AuditService();
        $this->notificationService = new NotificationService();
        $this->db = Database::connect();
    }

    public function createTransaction(array $data, int $userId, ?string $ipAddress = null): array
    {
        $this->resetQueuedMinStockNotifications();

        $approvedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        if ($approvedStatusId === null) {
            return [
                'success' => false,
                'message' => 'System error: APPROVED approval status not found.',
                'errors' => [],
            ];
        }

        $forbiddenErrors = $this->collectForbiddenFieldErrors($data);
        if ($forbiddenErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $forbiddenErrors,
            ];
        }

        $unknownTopLevelFields = array_diff(array_keys($data), self::ALLOWED_TOP_LEVEL_FIELDS);
        if ($unknownTopLevelFields !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'fields' => 'Unknown field(s): ' . implode(', ', $unknownTopLevelFields),
                ],
            ];
        }

        // Check for conflicting type_id and type_name
        if (isset($data['type_id']) && isset($data['type_name'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'type_id' => 'Cannot specify both type_id and type_name.',
                    'type_name' => 'Cannot specify both type_id and type_name.',
                ],
            ];
        }

        // Require at least one type field
        if (!isset($data['type_id']) && !isset($data['type_name'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['type_id' => 'Either type_id or type_name is required.'],
            ];
        }

        // Resolve type_name to type_id if provided
        if (isset($data['type_name']) && !isset($data['type_id'])) {
            $typeId = $this->typeModel->getIdByName($data['type_name']);
            if ($typeId === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['type_name' => 'The selected transaction type is invalid.'],
                ];
            }
            $data['type_id'] = $typeId;
        }

        if (!is_numeric($data['type_id'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['type_id' => 'The type_id field must be numeric.'],
            ];
        }

        if (!isset($data['transaction_date'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['transaction_date' => 'The transaction_date field is required.'],
            ];
        }

        if (strtotime((string) $data['transaction_date']) === false) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['transaction_date' => 'The transaction_date field must be a valid date.'],
            ];
        }

        if (array_key_exists('spk_id', $data) && $data['spk_id'] !== null && (!is_numeric($data['spk_id']) || (int) $data['spk_id'] <= 0)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['spk_id' => 'The spk_id field must be a positive integer when provided.'],
            ];
        }

        $type = $this->typeModel->find((int) $data['type_id']);
        if ($type === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['type_id' => 'The selected transaction type is invalid.'],
            ];
        }

        if (!in_array($type['name'], self::SUPPORTED_TRANSACTION_TYPES, true)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['type_id' => sprintf('Unsupported transaction type: %s', $type['name'])],
            ];
        }

        if (!isset($data['details']) || !is_array($data['details'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['details' => 'The details field is required and must be an array.'],
            ];
        }

        if ($data['details'] === []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['details' => 'The details field cannot be empty.'],
            ];
        }

        $itemIds = [];
        foreach ($data['details'] as $index => $detail) {
            if (!is_array($detail)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}" => 'Each detail entry must be an object.'],
                ];
            }

            $unknownDetailFields = array_diff(array_keys($detail), self::ALLOWED_DETAIL_FIELDS);
            if ($unknownDetailFields !== []) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}" => 'Unknown field(s): ' . implode(', ', $unknownDetailFields)],
                ];
            }

            if (!isset($detail['item_id']) || !is_numeric($detail['item_id'])) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.item_id" => 'The item_id field is required and must be numeric.'],
                ];
            }

            if (!isset($detail['qty']) || !is_numeric($detail['qty']) || (float) $detail['qty'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.qty" => 'The qty field is required and must be a positive number.'],
                ];
            }

            if (isset($detail['input_unit']) && !in_array($detail['input_unit'], ['base', 'convert'], true)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.input_unit" => 'The input_unit field must be "base" or "convert".'],
                ];
            }

            $itemId = (int) $detail['item_id'];
            if (in_array($itemId, $itemIds, true)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.item_id" => 'Duplicate item_id found in details.'],
                ];
            }

            $itemIds[] = $itemId;

            $item = $this->itemModel->find($itemId);
            if ($item === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.item_id" => 'The selected item is invalid.'],
                ];
            }
        }

        if ($type['name'] === TransactionTypeModel::NAME_OUT) {
            foreach ($data['details'] as $index => $detail) {
                $item = $this->itemModel->find((int) $detail['item_id']);
                $currentQty = (float) $item['qty'];
                $inputUnit = $detail['input_unit'] ?? 'base';
                $requestedQty = (float) $detail['qty'];
                $normalizedQty = $inputUnit === 'convert'
                    ? $requestedQty * (float) $item['conversion_base']
                    : $requestedQty;

                if ($currentQty < $normalizedQty) {
                    return [
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => [
                            "details.{$index}.qty" => sprintf(
                                'Insufficient stock. Available: %s, Requested: %s',
                                number_format($currentQty, 2, '.', ''),
                                number_format($normalizedQty, 2, '.', '')
                            ),
                        ],
                    ];
                }
            }
        }

        // Auto-trigger opening stock snapshot for the transaction's month (idempotent, failure-safe)
        (new StockSnapshotService())->ensureOpeningSnapshot(substr((string) $data['transaction_date'], 0, 7));

        $this->db->transStart();

        $transactionData = [
            'type_id' => (int) $data['type_id'],
            'transaction_date' => $data['transaction_date'],
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => null,
            'user_id' => $userId,
            'spk_id' => isset($data['spk_id']) && is_numeric($data['spk_id']) ? (int) $data['spk_id'] : null,
        ];

        $transactionId = $this->transactionModel->insert($transactionData, true);

        if ($transactionId === false) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to create stock transaction.',
                'errors' => $this->transactionModel->errors(),
            ];
        }

        foreach ($data['details'] as $detail) {
            $inputUnit = $detail['input_unit'] ?? 'base';
            $inputQty = (float) $detail['qty'];
            $item = $this->itemModel->find((int) $detail['item_id']);
            $normalizedQty = $inputUnit === 'convert'
                ? $inputQty * (float) $item['conversion_base']
                : $inputQty;

            $detailData = [
                'transaction_id' => $transactionId,
                'item_id' => (int) $detail['item_id'],
                'qty' => $normalizedQty,
                'input_qty' => $inputQty,
                'input_unit' => $inputUnit,
            ];

            if ($this->detailModel->insert($detailData) === false) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to create transaction details.',
                    'errors' => $this->detailModel->errors(),
                ];
            }

            $changeQty = $normalizedQty;
            $itemId = (int) $detail['item_id'];
            $escapedQty = $this->db->escape(number_format($changeQty, 2, '.', ''));

            // Atomic qty update using DB-side arithmetic with conditional update for OUT
            if (in_array($type['name'], [TransactionTypeModel::NAME_IN, TransactionTypeModel::NAME_RETURN_IN, TransactionTypeModel::NAME_OPNAME_ADJUSTMENT], true)) {
                // IN/RETURN_IN/OPNAME_ADJUSTMENT: unconditional increment
                $builder = $this->db->table('items');
                $builder->where('id', $itemId);
                $builder->set('qty', "qty + {$escapedQty}", false);
                $builder->set('updated_at', date('Y-m-d H:i:s'));

                if (!$builder->update()) {
                    $this->db->transRollback();

                    return [
                        'success' => false,
                        'message' => 'Failed to update item quantity.',
                        'errors' => [],
                    ];
                }
            } else {
                // OUT: conditional decrement (qty >= changeQty)
                $builder = $this->db->table('items');
                $builder->where('id', $itemId);
                $builder->where("qty >= {$escapedQty}", null, false);
                $builder->set('qty', "qty - {$escapedQty}", false);
                $builder->set('updated_at', date('Y-m-d H:i:s'));

                if (!$builder->update()) {
                    $this->db->transRollback();

                    return [
                        'success' => false,
                        'message' => 'Failed to update item quantity.',
                        'errors' => [],
                    ];
                }

                $affectedRows = $this->db->affectedRows();

                if ($affectedRows === 0) {
                    $this->db->transRollback();

                    return [
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors' => [
                            'details' => 'Insufficient stock. Stock may have changed since validation.',
                        ],
                    ];
                }

                $this->queueMinStockNotificationIfNeeded($itemId);
            }
        }

        $auditLogged = $this->auditService->log(
            $userId,
            AuditActionType::Create,
            'stock_transactions',
            (int) $transactionId,
            'Stock transaction created.',
            null,
            $transactionData,
            $ipAddress
        );

        if (!$auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to write audit log.',
                'errors' => [],
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors' => [],
            ];
        }

        $this->flushQueuedMinStockNotifications();

        return [
            'success' => true,
            'message' => 'Stock transaction created successfully.',
            'data' => [
                'id' => (int) $transactionId,
                'approval_status_id' => $approvedStatusId,
                'is_revision' => false,
            ],
        ];
    }

    private function collectForbiddenFieldErrors(array $data): array
    {
        $errors = [];

        foreach (self::FORBIDDEN_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $errors[$field] = sprintf('The %s field cannot be set directly.', $field);
            }
        }

        return $errors;
    }

    private function applySignedItemDelta(int $itemId, float $signedDelta, string $insufficientStockMessage): array
    {
        if (abs($signedDelta) < 0.005) {
            return [
                'success' => true,
            ];
        }

        $escapedQty = $this->db->escape(number_format(abs($signedDelta), 2, '.', ''));
        $builder = $this->db->table('items');
        $builder->where('id', $itemId);
        $builder->set('updated_at', date('Y-m-d H:i:s'));

        if ($signedDelta > 0) {
            $builder->set('qty', "qty + {$escapedQty}", false);

            if (!$builder->update()) {
                return [
                    'success' => false,
                    'message' => 'Failed to update item quantity.',
                    'errors' => [],
                ];
            }

            return [
                'success' => true,
            ];
        }

        $builder->where("qty >= {$escapedQty}", null, false);
        $builder->set('qty', "qty - {$escapedQty}", false);

        if (!$builder->update()) {
            return [
                'success' => false,
                'message' => 'Failed to update item quantity.',
                'errors' => [],
            ];
        }

        if ($this->db->affectedRows() === 0) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'details' => $insufficientStockMessage,
                ],
            ];
        }

        $this->queueMinStockNotificationIfNeeded($itemId);

        return [
            'success' => true,
        ];
    }

    public function createDirectCorrection(array $data, int $userId, ?string $ipAddress = null): array
    {
        $this->resetQueuedMinStockNotifications();

        $approvedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        if ($approvedStatusId === null) {
            return [
                'success' => false,
                'message' => 'System error: APPROVED approval status not found.',
                'errors' => [],
            ];
        }

        $forbiddenErrors = $this->collectForbiddenFieldErrors($data);
        if ($forbiddenErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $forbiddenErrors,
            ];
        }

        $unknownTopLevelFields = array_diff(array_keys($data), self::ALLOWED_DIRECT_CORRECTION_FIELDS);
        if ($unknownTopLevelFields !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'fields' => 'Unknown field(s): ' . implode(', ', $unknownTopLevelFields),
                ],
            ];
        }

        if (!isset($data['transaction_date'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['transaction_date' => 'The transaction_date field is required.'],
            ];
        }

        if (strtotime((string) $data['transaction_date']) === false) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['transaction_date' => 'The transaction_date field must be a valid date.'],
            ];
        }

        if (!isset($data['item_id']) || !is_numeric($data['item_id'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['item_id' => 'The item_id field is required and must be numeric.'],
            ];
        }

        $itemId = (int) $data['item_id'];
        $item = $this->itemModel->find($itemId);
        if ($item === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['item_id' => 'The selected item is invalid.'],
            ];
        }

        if (!array_key_exists('expected_current_qty', $data) || !is_numeric($data['expected_current_qty']) || (float) $data['expected_current_qty'] < 0) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['expected_current_qty' => 'The expected_current_qty field is required and must be a non-negative number.'],
            ];
        }

        if (!array_key_exists('target_qty', $data) || !is_numeric($data['target_qty']) || (float) $data['target_qty'] < 0) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['target_qty' => 'The target_qty field is required and must be a non-negative number.'],
            ];
        }

        if (!isset($data['reason']) || trim((string) $data['reason']) === '') {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['reason' => 'The reason field is required.'],
            ];
        }

        $reason = trim((string) $data['reason']);
        if (mb_strlen($reason) > 255) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['reason' => 'The reason field must not exceed 255 characters.'],
            ];
        }

        $expectedCurrentQty = round((float) $data['expected_current_qty'], 2);
        $targetQty = round((float) $data['target_qty'], 2);

        if (abs($expectedCurrentQty - $targetQty) < 0.005) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['target_qty' => 'The target_qty field must be different from expected_current_qty.'],
            ];
        }

        $deltaQty = round($targetQty - $expectedCurrentQty, 2);
        $typeName = $deltaQty > 0 ? TransactionTypeModel::NAME_IN : TransactionTypeModel::NAME_OUT;
        $typeId = $this->typeModel->getIdByName($typeName);
        if ($typeId === null) {
            return [
                'success' => false,
                'message' => 'System error: transaction type not found.',
                'errors' => [],
            ];
        }

        // Auto-trigger opening stock snapshot for the transaction's month (idempotent, failure-safe)
        (new StockSnapshotService())->ensureOpeningSnapshot(substr((string) $data['transaction_date'], 0, 7));

        $this->db->transStart();

        $escapedExpectedQty = $this->db->escape(number_format($expectedCurrentQty, 2, '.', ''));
        $itemBuilder = $this->db->table('items');
        $itemBuilder->where('id', $itemId);
        $itemBuilder->where("qty = {$escapedExpectedQty}", null, false);
        $itemBuilder->set('qty', number_format($targetQty, 2, '.', ''));
        $itemBuilder->set('updated_at', date('Y-m-d H:i:s'));

        if (!$itemBuilder->update()) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to update item quantity.',
                'errors' => [],
            ];
        }

        if ($this->db->affectedRows() === 0) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'expected_current_qty' => 'Current stock no longer matches expected_current_qty. Reload the item and retry the correction.',
                ],
            ];
        }

        $this->queueMinStockNotificationIfNeeded($itemId);

        $transactionData = [
            'type_id' => $typeId,
            'transaction_date' => $data['transaction_date'],
            'is_revision' => false,
            'parent_transaction_id' => null,
            'approval_status_id' => $approvedStatusId,
            'approved_by' => null,
            'user_id' => $userId,
            'spk_id' => null,
            'reason' => $reason,
        ];

        $transactionId = $this->transactionModel->insert($transactionData, true);
        if ($transactionId === false) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to create stock transaction.',
                'errors' => $this->transactionModel->errors(),
            ];
        }

        $detailData = [
            'transaction_id' => $transactionId,
            'item_id' => $itemId,
            'qty' => abs($deltaQty),
            'input_qty' => abs($deltaQty),
            'input_unit' => 'base',
        ];

        if ($this->detailModel->insert($detailData) === false) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to create transaction details.',
                'errors' => $this->detailModel->errors(),
            ];
        }

        $auditLogged = $this->auditService->log(
            $userId,
            AuditActionType::Create,
            'stock_transactions',
            (int) $transactionId,
            'Direct stock correction created.',
            [
                'item_id' => $itemId,
                'expected_current_qty' => number_format($expectedCurrentQty, 2, '.', ''),
            ],
            array_merge($transactionData, [
                'item_id' => $itemId,
                'target_qty' => number_format($targetQty, 2, '.', ''),
                'delta_qty' => number_format($deltaQty, 2, '.', ''),
                'detail_qty' => number_format(abs($deltaQty), 2, '.', ''),
                'input_unit' => 'base',
            ]),
            $ipAddress
        );

        if (!$auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to write audit log.',
                'errors' => [],
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors' => [],
            ];
        }

        $this->flushQueuedMinStockNotifications();

        return [
            'success' => true,
            'message' => 'Direct stock correction created successfully.',
            'data' => [
                'id' => (int) $transactionId,
                'approval_status_id' => $approvedStatusId,
                'is_revision' => false,
            ],
        ];
    }

    /**
     * Persist opname-like item corrections inside the caller transaction.
     *
     * Caller must own DB transaction boundary to keep stock-opname state changes
     * and stock-transaction artifacts atomic as one unit. Caller must flush queued
     * MIN_STOCK notifications only after successful outer commit.
     *
     * @param list<array{item_id:int,expected_current_qty:float,actual_qty:float}> $details
     */
    public function createOpnameAdjustmentEntries(
        array $details,
        string $transactionDate,
        int $userId,
        ?int $sourceOpnameId = null,
        ?string $ipAddress = null,
    ): array {
        $this->resetQueuedMinStockNotifications();

        if ($details === []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'details' => 'The details field cannot be empty.',
                ],
                'status' => 400,
            ];
        }

        $approvedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        if ($approvedStatusId === null) {
            return [
                'success' => false,
                'message' => 'System error: APPROVED approval status not found.',
                'errors' => [],
                'status' => 400,
            ];
        }

        $opnameAdjustmentTypeId = $this->typeModel->getIdByName(TransactionTypeModel::NAME_OPNAME_ADJUSTMENT);
        if ($opnameAdjustmentTypeId === null) {
            return [
                'success' => false,
                'message' => 'System error: transaction type not found.',
                'errors' => [],
                'status' => 400,
            ];
        }

        $createdTransactionIds = [];

        foreach ($details as $index => $detail) {
            $itemId = (int) ($detail['item_id'] ?? 0);
            $expectedQty = round((float) ($detail['expected_current_qty'] ?? 0), 2);
            $actualQty = round((float) ($detail['actual_qty'] ?? 0), 2);
            $signedDelta = round($actualQty - $expectedQty, 2);

            if ($itemId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        "details.{$index}.item_id" => 'The item_id field is required and must be numeric.',
                    ],
                    'status' => 400,
                ];
            }

            if ($actualQty < 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        "details.{$index}.actual_qty" => 'The actual_qty field must be a non-negative number.',
                    ],
                    'status' => 400,
                ];
            }

            if (abs($signedDelta) < 0.005) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        'details' => 'Zero-delta opname lines are not allowed. expected_current_qty must be different from actual_qty for every detail.',
                    ],
                    'status' => 400,
                ];
            }

            $item = $this->itemModel->find($itemId);
            if ($item === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        "details.{$index}.item_id" => 'The selected item is invalid.',
                    ],
                    'status' => 400,
                ];
            }

            $escapedExpectedQty = $this->db->escape(number_format($expectedQty, 2, '.', ''));
            $itemBuilder = $this->db->table('items');
            $itemBuilder->where('id', $itemId);
            $itemBuilder->where("qty = {$escapedExpectedQty}", null, false);
            $itemBuilder->set('qty', number_format($actualQty, 2, '.', ''));
            $itemBuilder->set('updated_at', date('Y-m-d H:i:s'));

            if (!$itemBuilder->update()) {
                return [
                    'success' => false,
                    'message' => 'Failed to update item quantity.',
                    'errors' => [],
                    'status' => 400,
                ];
            }

            if ($this->db->affectedRows() === 0) {
                return [
                    'success' => false,
                    'message' => 'Stock conflict detected.',
                    'errors' => [
                        "details.{$index}.expected_current_qty" => 'Current stock no longer matches expected_current_qty. Reload the item and retry posting.',
                    ],
                    'status' => 409,
                ];
            }

            $this->queueMinStockNotificationIfNeeded($itemId);

            $reason = $sourceOpnameId !== null
                ? sprintf('Stock opname #%d posting for item #%d', $sourceOpnameId, $itemId)
                : sprintf('Stock opname posting for item #%d', $itemId);

            $transactionData = [
                'type_id' => $opnameAdjustmentTypeId,
                'transaction_date' => $transactionDate,
                'is_revision' => false,
                'parent_transaction_id' => null,
                'approval_status_id' => $approvedStatusId,
                'approved_by' => null,
                'user_id' => $userId,
                'spk_id' => null,
                'reason' => $reason,
            ];

            $transactionId = $this->transactionModel->insert($transactionData, true);
            if ($transactionId === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to create stock transaction.',
                    'errors' => $this->transactionModel->errors(),
                    'status' => 400,
                ];
            }

            $detailData = [
                'transaction_id' => (int) $transactionId,
                'item_id' => $itemId,
                'qty' => number_format(abs($signedDelta), 2, '.', ''),
                'input_qty' => number_format(abs($signedDelta), 2, '.', ''),
                'input_unit' => 'base',
            ];

            if ($this->detailModel->insert($detailData) === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to create transaction details.',
                    'errors' => $this->detailModel->errors(),
                    'status' => 400,
                ];
            }

            $auditLogged = $this->auditService->log(
                $userId,
                AuditActionType::Create,
                'stock_transactions',
                (int) $transactionId,
                'Stock transaction created from stock opname posting.',
                null,
                array_merge($transactionData, [
                    'item_id' => $itemId,
                    'expected_current_qty' => number_format($expectedQty, 2, '.', ''),
                    'actual_qty' => number_format($actualQty, 2, '.', ''),
                    'delta_qty' => number_format($signedDelta, 2, '.', ''),
                    'detail_qty' => number_format(abs($signedDelta), 2, '.', ''),
                ]),
                $ipAddress,
            );

            if (!$auditLogged) {
                return [
                    'success' => false,
                    'message' => 'Failed to write audit log.',
                    'errors' => [],
                    'status' => 400,
                ];
            }

            $createdTransactionIds[] = (int) $transactionId;
        }

        return [
            'success' => true,
            'message' => 'Stock opname posting transactions created successfully.',
            'data' => [
                'transaction_ids' => $createdTransactionIds,
            ],
        ];
    }

    public function submitRevision(int $parentTransactionId, array $data, int $userId, ?string $ipAddress = null): array
    {
        $parent = $this->transactionModel->findById($parentTransactionId);
        if ($parent === null) {
            return [
                'success' => false,
                'message' => 'Parent transaction not found.',
                'errors' => [],
            ];
        }

        if ((bool) $parent['is_revision']) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['id' => 'Revision transactions cannot be revised again.'],
            ];
        }

        // Check forbidden fields
        $forbiddenErrors = $this->collectForbiddenFieldErrors($data);
        if ($forbiddenErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $forbiddenErrors,
            ];
        }

        // Check unknown top-level fields
        $unknownTopLevelFields = array_diff(array_keys($data), self::ALLOWED_REVISION_TOP_LEVEL_FIELDS);
        if ($unknownTopLevelFields !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'fields' => 'Unknown field(s): ' . implode(', ', $unknownTopLevelFields),
                ],
            ];
        }

        if (array_key_exists('reason', $data)) {
            $reason = trim((string) $data['reason']);

            if ($reason === '') {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['reason' => 'The reason field must not be empty when provided.'],
                ];
            }

            if (mb_strlen($reason) > 255) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['reason' => 'The reason field must not exceed 255 characters.'],
                ];
            }
        }

        // Validate transaction_date
        if (!isset($data['transaction_date'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['transaction_date' => 'The transaction_date field is required.'],
            ];
        }

        if (strtotime((string) $data['transaction_date']) === false) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['transaction_date' => 'The transaction_date field must be a valid date.'],
            ];
        }

        // Validate spk_id
        if (array_key_exists('spk_id', $data) && $data['spk_id'] !== null && (!is_numeric($data['spk_id']) || (int) $data['spk_id'] <= 0)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['spk_id' => 'The spk_id field must be a positive integer when provided.'],
            ];
        }

        // Validate details
        if (!isset($data['details']) || !is_array($data['details'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['details' => 'The details field is required and must be an array.'],
            ];
        }

        if ($data['details'] === []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['details' => 'The details field cannot be empty.'],
            ];
        }

        // Validate each detail entry
        $itemIds = [];
        foreach ($data['details'] as $index => $detail) {
            if (!is_array($detail)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}" => 'Each detail entry must be an object.'],
                ];
            }

            $unknownDetailFields = array_diff(array_keys($detail), self::ALLOWED_DETAIL_FIELDS);
            if ($unknownDetailFields !== []) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}" => 'Unknown field(s): ' . implode(', ', $unknownDetailFields)],
                ];
            }

            if (!isset($detail['item_id']) || !is_numeric($detail['item_id'])) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.item_id" => 'The item_id field is required and must be numeric.'],
                ];
            }

            if (!isset($detail['qty']) || !is_numeric($detail['qty']) || (float) $detail['qty'] <= 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.qty" => 'The qty field is required and must be a positive number.'],
                ];
            }

            if (isset($detail['input_unit']) && !in_array($detail['input_unit'], ['base', 'convert'], true)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.input_unit" => 'The input_unit field must be "base" or "convert".'],
                ];
            }

            $itemId = (int) $detail['item_id'];
            if (in_array($itemId, $itemIds, true)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.item_id" => 'Duplicate item_id found in details.'],
                ];
            }

            $itemIds[] = $itemId;

            $item = $this->itemModel->find($itemId);
            if ($item === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ["details.{$index}.item_id" => 'The selected item is invalid.'],
                ];
            }
        }

        // Get PENDING status ID
        $pendingStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_PENDING);
        if ($pendingStatusId === null) {
            return [
                'success' => false,
                'message' => 'System error: PENDING approval status not found.',
                'errors' => [],
            ];
        }

        // Start transaction
        $this->db->transStart();

        // Lock lineage root row and re-check pending sibling under lock.
        $lockedParent = $this->db->query(
            'SELECT id FROM ' . $this->stockTransactionsTable() . ' WHERE id = ? AND deleted_at IS NULL' . $this->forUpdateClause(),
            [$parentTransactionId]
        )->getRowArray();

        if ($lockedParent === null) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Parent transaction not found.',
                'errors' => [],
            ];
        }

        $existingPendingRevision = $this->transactionModel->findPendingRevisionByParentId($parentTransactionId, $pendingStatusId);

        $revisionData = [
            'type_id' => $parent['type_id'],
            'transaction_date' => $data['transaction_date'],
            'reason' => array_key_exists('reason', $data) ? trim((string) $data['reason']) : null,
            'is_revision' => true,
            'parent_transaction_id' => $parentTransactionId,
            'approval_status_id' => $pendingStatusId,
            'approved_by' => null,
            'user_id' => $userId,
            'spk_id' => isset($data['spk_id']) && is_numeric($data['spk_id']) ? (int) $data['spk_id'] : null,
        ];

        $isResubmission = $existingPendingRevision !== null;

        $existingPendingDetails = [];

        if ($isResubmission) {
            $revisionId = (int) $existingPendingRevision['id'];
            $existingPendingDetails = $this->detailModel->getDetailsByTransactionId($revisionId);

            $updated = $this->transactionModel->update($revisionId, $revisionData);
            if (!$updated) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to update revision transaction.',
                    'errors' => $this->transactionModel->errors(),
                ];
            }

            if ($this->detailModel->where('transaction_id', $revisionId)->delete() === false) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to replace revision details.',
                    'errors' => $this->detailModel->errors(),
                ];
            }
        } else {
            $revisionId = $this->transactionModel->insert($revisionData, true);

            if ($revisionId === false) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to create revision transaction.',
                    'errors' => $this->transactionModel->errors(),
                ];
            }
        }

        $detailWriteResult = $this->replaceRevisionDetails((int) $revisionId, $data['details']);
        if (!$detailWriteResult['success']) {
            $this->db->transRollback();

            return $detailWriteResult;
        }

        // Write audit log
        $auditOldValues = null;
        if ($isResubmission) {
            $auditOldValues = [
                'revision_header' => $existingPendingRevision,
                'revision_details' => $existingPendingDetails,
            ];
        } else {
            $auditOldValues = [
                'parent_header' => $parent,
                'parent_details' => $this->detailModel->getDetailsByTransactionId($parentTransactionId),
            ];
        }

        $auditNewValues = $isResubmission
            ? [
                'revision_header' => array_merge($revisionData, ['id' => (int) $revisionId]),
                'revision_details' => $detailWriteResult['details'],
            ]
            : [
                'revision_header' => array_merge($revisionData, ['id' => (int) $revisionId]),
                'revision_details' => $detailWriteResult['details'],
            ];

        $auditLogged = $this->auditService->log(
            $userId,
            AuditActionType::Submit,
            'stock_transactions',
            (int) $revisionId,
            $isResubmission ? 'Stock transaction pending revision updated.' : 'Stock transaction revision submitted.',
            $auditOldValues,
            $auditNewValues,
            $ipAddress
        );

        if (!$auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to write audit log.',
                'errors' => [],
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors' => [],
            ];
        }

        if (!$isResubmission) {
            $this->notificationService->sendToRole(
                'Admin',
                'Pengajuan Revisi Transaksi Stok',
                'Revisi transaksi stok telah diajukan. Silakan lakukan verifikasi.',
                'STOCK_REVISION',
                $revisionId
            );
        }

        return [
            'success' => true,
            'message' => 'Revision submitted successfully.',
            'data' => [
                'id' => (int) $revisionId,
                'approval_status_id' => $pendingStatusId,
                'is_revision' => true,
                'parent_transaction_id' => $parentTransactionId,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $details
     * @return array{success:bool,message?:string,errors?:array<string, mixed>,details?:array<int, array<string, int|float|string>>}
     */
    private function replaceRevisionDetails(int $revisionId, array $details): array
    {
        $writtenDetails = [];

        foreach ($details as $detail) {
            $inputUnit = $detail['input_unit'] ?? 'base';
            $inputQty = (float) $detail['qty'];
            $item = $this->itemModel->find((int) $detail['item_id']);
            $normalizedQty = $inputUnit === 'convert'
                ? $inputQty * (float) $item['conversion_base']
                : $inputQty;

            $detailData = [
                'transaction_id' => $revisionId,
                'item_id' => (int) $detail['item_id'],
                'qty' => $normalizedQty,
                'input_qty' => $inputQty,
                'input_unit' => $inputUnit,
            ];

            if ($this->detailModel->insert($detailData) === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to replace revision details.',
                    'errors' => $this->detailModel->errors(),
                ];
            }

            $writtenDetails[] = $detailData;
        }

        return [
            'success' => true,
            'details' => $writtenDetails,
        ];
    }

    public function approveRevision(int $revisionId, int $approverId, ?string $ipAddress = null): array
    {
        $this->resetQueuedMinStockNotifications();

        $revision = $this->transactionModel->findRevisionById($revisionId);
        if ($revision === null) {
            $transaction = $this->transactionModel->findById($revisionId);
            if ($transaction === null) {
                return [
                    'success' => false,
                    'message' => 'Revision not found.',
                    'errors' => [],
                ];
            }

            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['id' => 'Transaction is not a revision.'],
            ];
        }

        $pendingStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_PENDING);
        $approvedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $rejectedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_REJECTED);

        if ($pendingStatusId === null || $approvedStatusId === null || $rejectedStatusId === null) {
            return [
                'success' => false,
                'message' => 'System error: approval statuses not found.',
                'errors' => [],
            ];
        }

        if ((int) $revision['approval_status_id'] !== $pendingStatusId) {
            if ((int) $revision['approval_status_id'] === $approvedStatusId) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['id' => 'Revision already approved.'],
                ];
            }

            if ((int) $revision['approval_status_id'] === $rejectedStatusId) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['id' => 'Revision already rejected.'],
                ];
            }

            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['id' => 'Revision has an invalid approval state.'],
            ];
        }

        $parentTransactionId = (int) ($revision['parent_transaction_id'] ?? 0);
        if ($parentTransactionId <= 0) {
            return [
                'success' => false,
                'message' => 'System error: parent transaction not found.',
                'errors' => [],
            ];
        }

        $parent = $this->transactionModel->findById($parentTransactionId);
        if ($parent === null) {
            return [
                'success' => false,
                'message' => 'System error: parent transaction not found.',
                'errors' => [],
            ];
        }

        // Load detail rows
        $details = $this->detailModel->getDetailsByTransactionId($revisionId);
        if ($details === []) {
            return [
                'success' => false,
                'message' => 'System error: revision has no details.',
                'errors' => [],
            ];
        }

        // Get transaction type to determine qty mutation direction
        $type = $this->typeModel->find((int) $revision['type_id']);
        if ($type === null) {
            return [
                'success' => false,
                'message' => 'System error: transaction type not found.',
                'errors' => [],
            ];
        }

        // Start transaction
        $this->db->transStart();

        // Lock revision row and ensure it is still pending (stale-state guard).
        $lockedRevision = $this->db->query(
            'SELECT id, approval_status_id FROM ' . $this->stockTransactionsTable() . ' WHERE id = ? AND is_revision = 1 AND deleted_at IS NULL' . $this->forUpdateClause(),
            [$revisionId]
        )->getRowArray();

        if ($lockedRevision === null) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Revision not found.',
                'errors' => [],
            ];
        }

        if ((int) $lockedRevision['approval_status_id'] !== $pendingStatusId) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['id' => 'Revision has an invalid approval state.'],
            ];
        }

        // Lock lineage root for consistent baseline resolution.
        $lockedParent = $this->db->query(
            'SELECT id FROM ' . $this->stockTransactionsTable() . ' WHERE id = ? AND deleted_at IS NULL' . $this->forUpdateClause(),
            [$parentTransactionId]
        )->getRowArray();

        if ($lockedParent === null) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'System error: parent transaction not found.',
                'errors' => [],
            ];
        }

        $baselineTransaction = $this->transactionModel->findLatestApprovedRevisionByParentId($parentTransactionId, $approvedStatusId, $revisionId);
        $baselineTransactionId = $baselineTransaction !== null
            ? (int) $baselineTransaction['id']
            : $parentTransactionId;

        $baselineDetails = $this->detailModel->getDetailsByTransactionId($baselineTransactionId);
        if ($baselineDetails === []) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'System error: baseline transaction has no details.',
                'errors' => [],
            ];
        }

        $baselineMap = [];
        foreach ($baselineDetails as $detail) {
            $baselineMap[(int) $detail['item_id']] = (float) $detail['qty'];
        }

        $revisionMap = [];
        foreach ($details as $detail) {
            $revisionMap[(int) $detail['item_id']] = (float) $detail['qty'];
        }

        $allItemIds = array_values(array_unique(array_merge(array_keys($baselineMap), array_keys($revisionMap))));
        $direction = in_array($type['name'], [
            TransactionTypeModel::NAME_IN,
            TransactionTypeModel::NAME_RETURN_IN,
            TransactionTypeModel::NAME_OPNAME_ADJUSTMENT,
        ], true) ? 1 : -1;

        foreach ($allItemIds as $itemId) {
            $baselineQty = $baselineMap[$itemId] ?? 0.0;
            $revisionQty = $revisionMap[$itemId] ?? 0.0;
            $signedDelta = $direction * ($revisionQty - $baselineQty);

            $mutationResult = $this->applySignedItemDelta(
                (int) $itemId,
                (float) $signedDelta,
                'Insufficient stock. Stock may have changed since revision submission.'
            );

            if (!$mutationResult['success']) {
                $this->db->transRollback();

                return $mutationResult;
            }
        }

        // Update revision row
        $oldValues = $revision;
        $updated = $this->transactionModel->update($revisionId, [
            'approval_status_id' => $approvedStatusId,
            'approved_by' => $approverId,
        ]);

        if (!$updated) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to update revision status.',
                'errors' => [],
            ];
        }

        // Write audit log
        $newValues = $this->transactionModel->find($revisionId);

        $auditLogged = $this->auditService->log(
            $approverId,
            AuditActionType::Approval,
            'stock_transactions',
            $revisionId,
            'Stock transaction revision approved.',
            $oldValues,
            $newValues,
            $ipAddress
        );

        if (!$auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to write audit log.',
                'errors' => [],
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors' => [],
            ];
        }

        $this->flushQueuedMinStockNotifications();

        $this->notificationService->sendToUser(
            (int) $revision['user_id'],
            'Revisi Transaksi Disetujui',
            'Revisi transaksi stok Anda telah disetujui.',
            'STOCK_REVISION',
            $revisionId
        );

        return [
            'success' => true,
            'message' => 'Revision approved successfully.',
            'data' => [
                'id' => $revisionId,
                'approval_status_id' => $approvedStatusId,
                'approved_by' => $approverId,
            ],
        ];
    }

    public function rejectRevision(int $revisionId, array $data, int $approverId, ?string $ipAddress = null): array
    {
        // Ensure revision exists
        $revision = $this->transactionModel->findRevisionById($revisionId);
        if ($revision === null) {
            $transaction = $this->transactionModel->findById($revisionId);
            if ($transaction === null) {
                return [
                    'success' => false,
                    'message' => 'Revision not found.',
                    'errors' => [],
                ];
            }

            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['id' => 'Transaction is not a revision.'],
            ];
        }

        $rejectionReason = null;
        if (array_key_exists('reason', $data)) {
            $rejectionReason = trim((string) $data['reason']);

            if ($rejectionReason === '') {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['reason' => 'The reason field is required.'],
                ];
            }

            if (mb_strlen($rejectionReason) > 255) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['reason' => 'The reason field must not exceed 255 characters.'],
                ];
            }
        }

        // Get status IDs
        $pendingStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_PENDING);
        $approvedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $rejectedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_REJECTED);

        if ($pendingStatusId === null || $approvedStatusId === null || $rejectedStatusId === null) {
            return [
                'success' => false,
                'message' => 'System error: approval statuses not found.',
                'errors' => [],
            ];
        }

        if ((int) $revision['approval_status_id'] !== $pendingStatusId) {
            if ((int) $revision['approval_status_id'] === $approvedStatusId) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['id' => 'Revision already approved.'],
                ];
            }

            if ((int) $revision['approval_status_id'] === $rejectedStatusId) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => ['id' => 'Revision already rejected.'],
                ];
            }

            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['id' => 'Revision has an invalid approval state.'],
            ];
        }

        // Start transaction
        $this->db->transStart();

        // Lock revision row and ensure it is still pending.
        $lockedRevision = $this->db->query(
            'SELECT id, approval_status_id FROM ' . $this->stockTransactionsTable() . ' WHERE id = ? AND is_revision = 1 AND deleted_at IS NULL' . $this->forUpdateClause(),
            [$revisionId]
        )->getRowArray();

        if ($lockedRevision === null) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Revision not found.',
                'errors' => [],
            ];
        }

        if ((int) $lockedRevision['approval_status_id'] !== $pendingStatusId) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => ['id' => 'Revision has an invalid approval state.'],
            ];
        }

        // Update revision row (no qty mutation)
        $oldValues = $revision;
        $updated = $this->transactionModel->update($revisionId, [
            'approval_status_id' => $rejectedStatusId,
            'approved_by' => $approverId,
            'rejection_reason' => $rejectionReason,
        ]);

        if (!$updated) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to update revision status.',
                'errors' => [],
            ];
        }

        // Write audit log
        $newValues = $this->transactionModel->find($revisionId);

        $auditLogged = $this->auditService->log(
            $approverId,
            AuditActionType::Rejection,
            'stock_transactions',
            $revisionId,
            'Stock transaction revision rejected.',
            $oldValues,
            $newValues,
            $ipAddress
        );

        if (!$auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to write audit log.',
                'errors' => [],
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors' => [],
            ];
        }

        $this->notificationService->sendToUser(
            (int) $revision['user_id'],
            'Revisi Transaksi Ditolak',
            'Revisi transaksi stok Anda ditolak.',
            'STOCK_REVISION',
            $revisionId
        );

        return [
            'success' => true,
            'message' => 'Revision rejected successfully.',
            'data' => [
                'id' => $revisionId,
                'approval_status_id' => $rejectedStatusId,
                'approved_by' => $approverId,
            ],
        ];
    }

    private function forUpdateClause(): string
    {
        return $this->db->getPlatform() === 'SQLite3' ? '' : ' FOR UPDATE';
    }

    private function stockTransactionsTable(): string
    {
        return $this->db->prefixTable('stock_transactions');
    }

    public function flushQueuedMinStockNotifications(): void
    {
        foreach ($this->queuedMinStockNotifications as $payload) {
            $this->notificationService->sendToRole(
                'Admin',
                'Stok Minimum',
                "Stok bahan {$payload['item_name']} telah mencapai batas minimum. Segera lakukan pengadaan",
                'MIN_STOCK',
                $payload['item_id']
            );
        }

        $this->resetQueuedMinStockNotifications();
    }

    public function setAuditServiceForTesting(AuditService $auditService): void
    {
        $this->auditService = $auditService;
    }

    private function queueMinStockNotificationIfNeeded(int $itemId): void
    {
        $itemAfter = $this->itemModel->find($itemId);
        if ($itemAfter === null) {
            return;
        }

        if ((float) $itemAfter['qty'] > (float) ($itemAfter['min_stock'] ?? 0)) {
            return;
        }

        if (isset($this->queuedMinStockNotifications[$itemId])) {
            return;
        }

        $this->queuedMinStockNotifications[$itemId] = [
            'item_id' => $itemId,
            'item_name' => (string) ($itemAfter['name'] ?? 'Barang'),
        ];
    }

    private function resetQueuedMinStockNotifications(): void
    {
        $this->queuedMinStockNotifications = [];
    }
}
