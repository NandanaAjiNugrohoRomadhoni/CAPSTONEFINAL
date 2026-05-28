<?php

namespace App\Services;

use App\Models\ItemModel;
use App\Models\StockTransactionDetailModel;
use App\Models\StockTransactionModel;
use App\Models\StockOpnameDetailModel;
use App\Models\StockOpnameModel;
use App\Services\NotificationService;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class StockOpnameService
{
    private const ZERO_DELTA_ERROR_MESSAGE = 'Zero-delta opname lines are not allowed. expected_current_qty must be different from actual_qty for every detail.';
    private const LEGACY_POST_CONFLICT_ERROR_MESSAGE = 'Insufficient stock to post stock opname variance.';

    private const ALLOWED_CREATE_FIELDS = [
        'opname_date',
        'notes',
        'details',
    ];

    private const ALLOWED_DETAIL_FIELDS = [
        'item_id',
        'counted_qty',
    ];

    protected StockOpnameModel $stockOpnameModel;
    protected StockOpnameDetailModel $stockOpnameDetailModel;
    protected StockTransactionModel $stockTransactionModel;
    protected StockTransactionDetailModel $stockTransactionDetailModel;
    protected ItemModel $itemModel;
    protected StockTransactionService $stockTransactionService;
    protected AuditService $auditService;
    protected NotificationService $notificationService;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->stockOpnameModel       = new StockOpnameModel();
        $this->stockOpnameDetailModel = new StockOpnameDetailModel();
        $this->stockTransactionModel  = new StockTransactionModel();
        $this->stockTransactionDetailModel = new StockTransactionDetailModel();
        $this->itemModel              = new ItemModel();
        $this->stockTransactionService = new StockTransactionService();
        $this->auditService           = new AuditService();
        $this->notificationService    = new NotificationService();
        $this->db                     = Database::connect();
    }

    public function createDraft(array $data, int $userId, ?string $ipAddress = null): array
    {
        $validationResult = $this->validateDraftPayload($data);
        if (! $validationResult['success']) {
            return $validationResult;
        }

        $normalizedDetails = $validationResult['normalized_details'];
        $notes             = $validationResult['notes'];
        $opnameDate        = $validationResult['opname_date'];

        $this->db->transStart();

        $stockOpnameData = [
            'opname_date' => $opnameDate,
            'state'       => StockOpnameModel::STATE_DRAFT,
            'notes'       => $notes,
            'created_by'  => $userId,
        ];

        $stockOpnameId = $this->stockOpnameModel->insert($stockOpnameData, true);
        if ($stockOpnameId === false) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to create stock opname draft.',
                'errors'  => $this->stockOpnameModel->errors(),
            ];
        }

        foreach ($normalizedDetails as $detail) {
            $detailData = [
                'stock_opname_id' => (int) $stockOpnameId,
                'item_id'         => $detail['item_id'],
                'system_qty'      => number_format((float) $detail['system_qty'], 2, '.', ''),
                'counted_qty'     => number_format((float) $detail['counted_qty'], 2, '.', ''),
                'variance_qty'    => number_format((float) $detail['variance_qty'], 2, '.', ''),
            ];

            if ($this->stockOpnameDetailModel->insert($detailData) === false) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to create stock opname details.',
                    'errors'  => $this->stockOpnameDetailModel->errors(),
                ];
            }
        }

        $auditLogged = $this->auditService->log(
            $userId,
            'stock_opname_create_draft',
            'stock_opnames',
            (int) $stockOpnameId,
            'Stock opname draft created.',
            null,
            [
                'id'          => (int) $stockOpnameId,
                'opname_date' => $opnameDate,
                'state'       => StockOpnameModel::STATE_DRAFT,
                'notes'       => $notes,
                'details'     => $normalizedDetails,
            ],
            $ipAddress
        );

        if (! $auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to write audit log.',
                'errors'  => [],
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors'  => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Stock opname draft created successfully.',
            'data'    => [
                'id'    => (int) $stockOpnameId,
                'state' => StockOpnameModel::STATE_DRAFT,
            ],
        ];
    }

    public function updateDraft(int $id, array $data, int $userId, ?string $ipAddress = null): array
    {
        $stockOpname = $this->stockOpnameModel->findById($id);
        if ($stockOpname === null) {
            return [
                'success' => false,
                'message' => 'Stock opname not found.',
                'errors'  => [],
                'status'  => 404,
            ];
        }

        if (! in_array($stockOpname['state'], [StockOpnameModel::STATE_DRAFT, StockOpnameModel::STATE_REJECTED], true)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'state' => sprintf('Invalid state transition from %s to UPDATE.', $stockOpname['state']),
                ],
                'status'  => 400,
            ];
        }

        $validationResult = $this->validateDraftPayload($data);
        if (! $validationResult['success']) {
            $validationResult['status'] = 400;

            return $validationResult;
        }

        $normalizedDetails = $validationResult['normalized_details'];
        $notes             = $validationResult['notes'];
        $opnameDate        = $validationResult['opname_date'];
        $existingDetails   = $this->stockOpnameDetailModel->getDetailsByStockOpnameId($id);
        $oldValues         = [
            'header'  => $stockOpname,
            'details' => $existingDetails,
        ];

        $this->db->transStart();

        $updated = $this->stockOpnameModel->update($id, [
            'opname_date' => $opnameDate,
            'notes'       => $notes,
        ]);

        if (! $updated) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to update stock opname.',
                'errors'  => $this->stockOpnameModel->errors(),
                'status'  => 400,
            ];
        }

        if ($this->stockOpnameDetailModel->where('stock_opname_id', $id)->delete() === false) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to replace stock opname details.',
                'errors'  => $this->stockOpnameDetailModel->errors(),
                'status'  => 400,
            ];
        }

        foreach ($normalizedDetails as $detail) {
            $detailData = [
                'stock_opname_id' => $id,
                'item_id'         => $detail['item_id'],
                'system_qty'      => number_format((float) $detail['system_qty'], 2, '.', ''),
                'counted_qty'     => number_format((float) $detail['counted_qty'], 2, '.', ''),
                'variance_qty'    => number_format((float) $detail['variance_qty'], 2, '.', ''),
            ];

            if ($this->stockOpnameDetailModel->insert($detailData) === false) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to replace stock opname details.',
                    'errors'  => $this->stockOpnameDetailModel->errors(),
                    'status'  => 400,
                ];
            }
        }

        $updatedHeader = $this->stockOpnameModel->findById($id);
        $auditLogged   = $this->auditService->log(
            $userId,
            'stock_opname_update',
            'stock_opnames',
            $id,
            'Stock opname updated.',
            $oldValues,
            [
                'header'  => $updatedHeader,
                'details' => $normalizedDetails,
            ],
            $ipAddress
        );

        if (! $auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to write audit log.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        return [
            'success' => true,
            'message' => 'Stock opname updated successfully.',
            'data'    => [
                'id'    => $id,
                'state' => $updatedHeader['state'] ?? $stockOpname['state'],
            ],
        ];
    }

    public function submit(int $id, int $userId, ?string $ipAddress = null): array
    {
        $stockOpname = $this->stockOpnameModel->findById($id);
        if ($stockOpname === null) {
            return [
                'success' => false,
                'message' => 'Stock opname not found.',
                'errors'  => [],
                'status'  => 404,
            ];
        }

        if ($stockOpname['state'] !== StockOpnameModel::STATE_DRAFT) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'state' => sprintf('Invalid state transition from %s to SUBMITTED.', $stockOpname['state']),
                ],
                'status'  => 400,
            ];
        }

        $oldValues = $stockOpname;

        $updated = $this->stockOpnameModel->update($id, [
            'state'        => StockOpnameModel::STATE_SUBMITTED,
            'submitted_by' => $userId,
            'submitted_at' => date('Y-m-d H:i:s'),
            'approved_by'  => null,
            'approved_at'  => null,
            'rejected_by'  => null,
            'rejected_at'  => null,
            'rejection_reason' => null,
            'posted_by'    => null,
            'posted_at'    => null,
        ]);

        if (! $updated) {
            return [
                'success' => false,
                'message' => 'Failed to submit stock opname.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        $newValues = $this->stockOpnameModel->find($id);
        $this->auditService->log(
            $userId,
            'stock_opname_submit',
            'stock_opnames',
            $id,
            'Stock opname submitted.',
            $oldValues,
            $newValues,
            $ipAddress
        );

        $details = $this->stockOpnameDetailModel->getDetailsByStockOpnameId($id);
        $itemIds = array_unique(array_column($details, 'item_id'));
        $itemNames = $itemIds !== [] ? $this->itemModel->whereIn('id', $itemIds)->findColumn('name') : [];
        if ($itemNames === null) {
            $itemNames = [];
        }
        $itemNameStr = implode(', ', $itemNames);
        
        $this->notificationService->sendToRole(
            'Admin',
            'Pengajuan Penyesuaian Stok',
            "Pengajuan penyesuaian stok untuk bahan {$itemNameStr} telah diajukan oleh Petugas Gudang. Silakan lakukan verifikasi.",
            'STOCK_OPNAME',
            $id
        );

        return [
            'success' => true,
            'message' => 'Stock opname submitted successfully.',
            'data'    => [
                'id'    => (int) $id,
                'state' => StockOpnameModel::STATE_SUBMITTED,
            ],
        ];
    }

    public function approve(int $id, int $userId, ?string $ipAddress = null): array
    {
        $stockOpname = $this->stockOpnameModel->findById($id);
        if ($stockOpname === null) {
            return [
                'success' => false,
                'message' => 'Stock opname not found.',
                'errors'  => [],
                'status'  => 404,
            ];
        }

        if ($stockOpname['state'] !== StockOpnameModel::STATE_SUBMITTED) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'state' => sprintf('Invalid state transition from %s to APPROVED.', $stockOpname['state']),
                ],
                'status'  => 400,
            ];
        }

        $oldValues = $stockOpname;

        $updated = $this->stockOpnameModel->update($id, [
            'state'           => StockOpnameModel::STATE_APPROVED,
            'approved_by'     => $userId,
            'approved_at'     => date('Y-m-d H:i:s'),
            'rejected_by'     => null,
            'rejected_at'     => null,
            'rejection_reason' => null,
        ]);

        if (! $updated) {
            return [
                'success' => false,
                'message' => 'Failed to approve stock opname.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        $newValues = $this->stockOpnameModel->find($id);
        $this->auditService->log(
            $userId,
            'stock_opname_approve',
            'stock_opnames',
            $id,
            'Stock opname approved.',
            $oldValues,
            $newValues,
            $ipAddress
        );

        $details = $this->stockOpnameDetailModel->getDetailsByStockOpnameId($id);
        $itemIds = array_unique(array_column($details, 'item_id'));
        $itemNames = $itemIds !== [] ? $this->itemModel->whereIn('id', $itemIds)->findColumn('name') : [];
        if ($itemNames === null) {
            $itemNames = [];
        }
        $itemNameStr = implode(', ', $itemNames);
        
        $this->notificationService->sendToUser(
            (int) $stockOpname['submitted_by'],
            'Pengajuan Penyesuaian Stok Disetujui',
            "Penyesuaian stok untuk bahan {$itemNameStr} telah disetujui oleh Admin.",
            'STOCK_OPNAME',
            $id
        );

        return [
            'success' => true,
            'message' => 'Stock opname approved successfully.',
            'data'    => [
                'id'    => (int) $id,
                'state' => StockOpnameModel::STATE_APPROVED,
            ],
        ];
    }

    public function reject(int $id, array $data, int $userId, ?string $ipAddress = null): array
    {
        $stockOpname = $this->stockOpnameModel->findById($id);
        if ($stockOpname === null) {
            return [
                'success' => false,
                'message' => 'Stock opname not found.',
                'errors'  => [],
                'status'  => 404,
            ];
        }

        if ($stockOpname['state'] !== StockOpnameModel::STATE_SUBMITTED) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'state' => sprintf('Invalid state transition from %s to REJECTED.', $stockOpname['state']),
                ],
                'status'  => 400,
            ];
        }

        if (! isset($data['reason']) || trim((string) $data['reason']) === '') {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'reason' => 'The reason field is required.',
                ],
                'status'  => 400,
            ];
        }

        $reason = trim((string) $data['reason']);
        if (mb_strlen($reason) > 255) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'reason' => 'The reason field must not exceed 255 characters.',
                ],
                'status'  => 400,
            ];
        }

        $oldValues = $stockOpname;

        $updated = $this->stockOpnameModel->update($id, [
            'state'            => StockOpnameModel::STATE_REJECTED,
            'rejected_by'      => $userId,
            'rejected_at'      => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason,
            'approved_by'      => null,
            'approved_at'      => null,
        ]);

        if (! $updated) {
            return [
                'success' => false,
                'message' => 'Failed to reject stock opname.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        $newValues = $this->stockOpnameModel->find($id);
        $this->auditService->log(
            $userId,
            'stock_opname_reject',
            'stock_opnames',
            $id,
            'Stock opname rejected.',
            $oldValues,
            $newValues,
            $ipAddress
        );

        $details = $this->stockOpnameDetailModel->getDetailsByStockOpnameId($id);
        $itemIds = array_unique(array_column($details, 'item_id'));
        $itemNames = $itemIds !== [] ? $this->itemModel->whereIn('id', $itemIds)->findColumn('name') : [];
        if ($itemNames === null) {
            $itemNames = [];
        }
        $itemNameStr = implode(', ', $itemNames);
        
        $this->notificationService->sendToUser(
            (int) $stockOpname['submitted_by'],
            'Pengajuan Penyesuaian Stok Ditolak',
            "Penyesuaian stok untuk bahan {$itemNameStr} ditolak. Silakan periksa kembali data yang diajukan.",
            'STOCK_OPNAME',
            $id
        );

        return [
            'success' => true,
            'message' => 'Stock opname rejected successfully.',
            'data'    => [
                'id'    => (int) $id,
                'state' => StockOpnameModel::STATE_REJECTED,
            ],
        ];
    }

    public function post(int $id, int $userId, ?string $ipAddress = null): array
    {
        $stockOpname = $this->stockOpnameModel->findById($id);
        if ($stockOpname === null) {
            return [
                'success' => false,
                'message' => 'Stock opname not found.',
                'errors'  => [],
                'status'  => 404,
            ];
        }

        if ($stockOpname['state'] !== StockOpnameModel::STATE_APPROVED) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'state' => sprintf('Invalid state transition from %s to POSTED.', $stockOpname['state']),
                ],
                'status'  => 400,
            ];
        }

        $details = $this->stockOpnameDetailModel->getDetailsByStockOpnameId($id);
        if ($details === []) {
            return [
                'success' => false,
                'message' => 'System error: stock opname has no details.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        $postingPayload = [];
        foreach ($details as $detail) {
            $itemId       = (int) $detail['item_id'];
            $expectedQty  = round((float) $detail['system_qty'], 2);
            $actualQty    = round((float) $detail['counted_qty'], 2);
            $signedDelta  = round($actualQty - $expectedQty, 2);

            if (abs($signedDelta) < 0.005) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'details' => self::ZERO_DELTA_ERROR_MESSAGE,
                    ],
                    'status'  => 400,
                ];
            }

            $postingPayload[] = [
                'item_id'              => $itemId,
                'expected_current_qty' => $expectedQty,
                'actual_qty'           => $actualQty,
            ];
        }

        $this->db->transStart();

        $coreResult = $this->stockTransactionService->createOpnameAdjustmentEntries(
            $postingPayload,
            $stockOpname['opname_date'],
            $userId,
            $id,
            $ipAddress,
        );

        if (! $coreResult['success']) {
            $this->db->transRollback();

            $coreStatus = (int) ($coreResult['status'] ?? 400);
            $coreErrors = $coreResult['errors'] ?? [];

            if ($coreStatus === 409 && $this->hasOpnameExpectedQtyConflict($coreErrors)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'details' => self::LEGACY_POST_CONFLICT_ERROR_MESSAGE,
                    ],
                    'status'  => 400,
                ];
            }

            return [
                'success' => false,
                'message' => $coreResult['message'],
                'errors'  => $coreErrors,
                'status'  => $coreStatus,
            ];
        }

        $oldValues = $stockOpname;
        $updated   = $this->stockOpnameModel->update($id, [
            'state'     => StockOpnameModel::STATE_POSTED,
            'posted_by' => $userId,
            'posted_at' => date('Y-m-d H:i:s'),
        ]);

        if (! $updated) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to finalize stock opname.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        $newValues = $this->stockOpnameModel->find($id);
        $auditLogged = $this->auditService->log(
            $userId,
            'stock_opname_post',
            'stock_opnames',
            $id,
            'Stock opname posted.',
            $oldValues,
            $newValues,
            $ipAddress
        );

        if (! $auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to write audit log.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors'  => [],
                'status'  => 400,
            ];
        }

        $this->stockTransactionService->flushQueuedMinStockNotifications();

        return [
            'success' => true,
            'message' => 'Stock opname posted successfully.',
            'data'    => [
                'id'    => (int) $id,
                'state' => StockOpnameModel::STATE_POSTED,
            ],
        ];
    }

    public function findByIdWithDetails(int $id): ?array
    {
        $header = $this->stockOpnameModel->findById($id);
        if ($header === null) {
            return null;
        }

        $details = $this->stockOpnameDetailModel->getDetailsByStockOpnameId($id);

        return [
            'header'  => $header,
            'details' => $details,
        ];
    }

    private function hasOpnameExpectedQtyConflict(array $errors): bool
    {
        foreach (array_keys($errors) as $errorKey) {
            if (preg_match('/^details\.\d+\.expected_current_qty$/', (string) $errorKey) === 1) {
                return true;
            }
        }

        return false;
    }

    private function validateDraftPayload(array $data): array
    {
        $unknownTopLevelFields = array_diff(array_keys($data), self::ALLOWED_CREATE_FIELDS);
        if ($unknownTopLevelFields !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'fields' => 'Unknown field(s): ' . implode(', ', $unknownTopLevelFields),
                ],
            ];
        }

        if (! isset($data['opname_date']) || strtotime((string) $data['opname_date']) === false) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'opname_date' => 'The opname_date field is required and must be a valid date.',
                ],
            ];
        }

        $notes = null;
        if (array_key_exists('notes', $data) && $data['notes'] !== null) {
            $notes = trim((string) $data['notes']);
            if ($notes !== '' && mb_strlen($notes) > 1000) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'notes' => 'The notes field must not exceed 1000 characters.',
                    ],
                ];
            }

            if ($notes === '') {
                $notes = null;
            }
        }

        if (! isset($data['details']) || ! is_array($data['details']) || $data['details'] === []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'details' => 'The details field is required and must be a non-empty array.',
                ],
            ];
        }

        $normalizedDetails = [];
        $itemIds           = [];

        foreach ($data['details'] as $index => $detail) {
            if (! is_array($detail)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        "details.{$index}" => 'Each detail entry must be an object.',
                    ],
                ];
            }

            $unknownDetailFields = array_diff(array_keys($detail), self::ALLOWED_DETAIL_FIELDS);
            if ($unknownDetailFields !== []) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        "details.{$index}" => 'Unknown field(s): ' . implode(', ', $unknownDetailFields),
                    ],
                ];
            }

            if (! isset($detail['item_id']) || ! is_numeric($detail['item_id'])) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        "details.{$index}.item_id" => 'The item_id field is required and must be numeric.',
                    ],
                ];
            }

            if (! array_key_exists('counted_qty', $detail) || ! is_numeric($detail['counted_qty']) || (float) $detail['counted_qty'] < 0) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        "details.{$index}.counted_qty" => 'The counted_qty field is required and must be a non-negative number.',
                    ],
                ];
            }

            $itemId = (int) $detail['item_id'];
            if (in_array($itemId, $itemIds, true)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        "details.{$index}.item_id" => 'Duplicate item_id found in details.',
                    ],
                ];
            }

            $item = $this->itemModel->find($itemId);
            if ($item === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        "details.{$index}.item_id" => 'The selected item is invalid.',
                    ],
                ];
            }

            $itemIds[] = $itemId;

            $systemQty   = round((float) $item['qty'], 2);
            $countedQty  = round((float) $detail['counted_qty'], 2);
            $varianceQty = round($countedQty - $systemQty, 2);

            $normalizedDetails[] = [
                'item_id'      => $itemId,
                'system_qty'   => $systemQty,
                'counted_qty'  => $countedQty,
                'variance_qty' => $varianceQty,
            ];
        }

        return [
            'success'            => true,
            'opname_date'        => (string) $data['opname_date'],
            'notes'              => $notes,
            'normalized_details' => $normalizedDetails,
        ];
    }
}
