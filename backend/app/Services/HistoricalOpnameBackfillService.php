<?php

namespace App\Services;

use App\Models\ApprovalStatusModel;
use App\Models\StockTransactionModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class HistoricalOpnameBackfillService
{
    private const LEGACY_SOURCE_TABLE = 'stock_opname_details';

    protected BaseConnection $db;
    protected StockTransactionModel $stockTransactionModel;
    protected TransactionTypeModel $transactionTypeModel;
    protected ApprovalStatusModel $approvalStatusModel;

    public function __construct()
    {
        $this->db                          = Database::connect();
        $this->stockTransactionModel       = new StockTransactionModel();
        $this->transactionTypeModel        = new TransactionTypeModel();
        $this->approvalStatusModel         = new ApprovalStatusModel();
    }

    /**
     * @return array{success:bool,message:string,data?:array<string,mixed>,errors?:array<string,mixed>}
     */
    public function backfill(?string $fromDate = null, ?string $toDate = null): array
    {
        $fromDateObject = null;
        if ($fromDate !== null) {
            $parsedFromDate = $this->parseDateFromCommandOption($fromDate);
            if ($parsedFromDate === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'from' => 'The from option must be a valid date (YYYY-MM-DD).',
                    ],
                ];
            }

            $fromDateObject = $parsedFromDate;
        }

        $toDateObject = null;
        if ($toDate !== null) {
            $parsedToDate = $this->parseDateFromCommandOption($toDate);
            if ($parsedToDate === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'to' => 'The to option must be a valid date (YYYY-MM-DD).',
                    ],
                ];
            }

            $toDateObject = $parsedToDate;
        }

        if ($fromDateObject !== null && $toDateObject !== null && $fromDateObject->getTimestamp() > $toDateObject->getTimestamp()) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'range' => 'The from option must be less than or equal to to option.',
                ],
            ];
        }

        $normalizedFromDate = $fromDateObject?->format('Y-m-d');
        $normalizedToDate   = $toDateObject?->format('Y-m-d');

        $requiredIds = $this->resolveRequiredTypeAndStatusIds();
        if (! $requiredIds['success']) {
            return $requiredIds;
        }

        $builder = $this->db->table('stock_opname_details sod');
        $builder
            ->select('sod.id AS legacy_detail_id')
            ->select('sod.stock_opname_id')
            ->select('sod.item_id')
            ->select('sod.variance_qty')
            ->select('so.opname_date')
            ->select('so.created_by')
            ->join('stock_opnames so', 'so.id = sod.stock_opname_id', 'inner')
            ->where('so.deleted_at', null)
            ->where('so.state', 'POSTED')
            ->where('sod.variance_qty !=', 0)
            ->orderBy('so.opname_date', 'ASC')
            ->orderBy('sod.id', 'ASC');

        if ($normalizedFromDate !== null) {
            $builder->where('so.opname_date >=', $normalizedFromDate);
        }

        if ($normalizedToDate !== null) {
            $builder->where('so.opname_date <=', $normalizedToDate);
        }

        $sourceRows = $builder->get()->getResultArray();

        $seenLegacyDetailIds = [];
        foreach ($sourceRows as $row) {
            $legacyDetailId = (int) $row['legacy_detail_id'];
            $seenLegacyDetailIds[$legacyDetailId] = true;
        }

        $createdCount      = 0;
        $skippedCount      = 0;
        $createdDetailIds  = [];
        $legacyDetailCount = count($sourceRows);

        $this->db->transStart();

        foreach ($sourceRows as $row) {
            $legacyDetailId = (int) $row['legacy_detail_id'];

            $deltaQty = round((float) $row['variance_qty'], 2);
            $absDelta = round(abs($deltaQty), 2);
            if ($absDelta < 0.005) {
                $skippedCount++;
                continue;
            }

            if ($this->hasBackfilledTransaction($legacyDetailId)) {
                $skippedCount++;
                continue;
            }

            $reason = sprintf(
                'Historical stock opname backfill #%d detail #%d item #%d',
                (int) $row['stock_opname_id'],
                $legacyDetailId,
                (int) $row['item_id'],
            );

            $transactionPayload = [
                'type_id'                  => (int) $requiredIds['data']['type_id'],
                'transaction_date'         => (string) $row['opname_date'],
                'is_revision'              => false,
                'parent_transaction_id'    => null,
                'approval_status_id'       => (int) $requiredIds['data']['approval_status_id'],
                'approved_by'              => null,
                'user_id'                  => (int) $row['created_by'],
                'spk_id'                   => null,
                'reason'                   => $reason,
                'legacy_source_table'      => self::LEGACY_SOURCE_TABLE,
                'legacy_source_id'         => (int) $row['stock_opname_id'],
                'legacy_source_detail_id'  => $legacyDetailId,
            ];

            $transactionId = $this->stockTransactionModel->insert($transactionPayload, true);

            if ($transactionId === false) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to create stock transaction backfill row.',
                    'errors'  => [
                        'model'   => $this->stockTransactionModel->errors(),
                        'db'      => $this->db->error(),
                        'payload' => $transactionPayload,
                    ],
                ];
            }

            $detailInserted = $this->db->table('stock_transaction_details')->insert([
                'transaction_id' => (int) $transactionId,
                'item_id'        => (int) $row['item_id'],
                'qty'            => number_format($absDelta, 2, '.', ''),
                'input_qty'      => number_format($absDelta, 2, '.', ''),
                'input_unit'     => 'base',
            ]);

            if ($detailInserted === false) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to create stock transaction detail backfill row.',
                    'errors'  => [
                        'db' => $this->db->error(),
                    ],
                ];
            }

            $createdCount++;
            $createdDetailIds[] = $legacyDetailId;
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            $dbError = $this->db->error();

            return [
                'success' => false,
                'message' => 'Transaction failed.',
                'errors'  => [
                    'database' => $dbError,
                ],
            ];
        }

        return [
            'success' => true,
            'message' => 'Historical stock opname backfill completed.',
            'data'    => [
                'legacy_source_table'     => self::LEGACY_SOURCE_TABLE,
                'from'                    => $normalizedFromDate,
                'to'                      => $normalizedToDate,
                'legacy_rows_considered'  => $legacyDetailCount,
                'created_rows'            => $createdCount,
                'skipped_rows'            => $skippedCount,
                'created_detail_ids'      => $createdDetailIds,
                'seen_detail_ids'         => array_keys($seenLegacyDetailIds),
            ],
        ];
    }

    private function hasBackfilledTransaction(int $legacyDetailId): bool
    {
        return $this->db->table('stock_transactions')
            ->where('legacy_source_table', self::LEGACY_SOURCE_TABLE)
            ->where('legacy_source_detail_id', $legacyDetailId)
            ->countAllResults() > 0;
    }

    private function parseDateFromCommandOption(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false) {
            return null;
        }

        if (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return null;
        }

        if ($date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    /**
     * @return array{success:bool,message:string,data?:array<string,int>,errors?:array<string,mixed>}
     */
    private function resolveRequiredTypeAndStatusIds(): array
    {
        $opnameAdjustmentTypeId = $this->transactionTypeModel->getIdByName(TransactionTypeModel::NAME_OPNAME_ADJUSTMENT);
        if ($opnameAdjustmentTypeId === null) {
            return [
                'success' => false,
                'message' => 'System error: transaction type not found.',
                'errors'  => [
                    'type' => TransactionTypeModel::NAME_OPNAME_ADJUSTMENT,
                ],
            ];
        }

        $approvedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        if ($approvedStatusId === null) {
            return [
                'success' => false,
                'message' => 'System error: APPROVED approval status not found.',
                'errors'  => [
                    'approval_status' => ApprovalStatusModel::NAME_APPROVED,
                ],
            ];
        }

        return [
            'success' => true,
            'message' => 'Resolved required type and status.',
            'data'    => [
                'type_id'            => $opnameAdjustmentTypeId,
                'approval_status_id' => $approvedStatusId,
            ],
        ];
    }

}
