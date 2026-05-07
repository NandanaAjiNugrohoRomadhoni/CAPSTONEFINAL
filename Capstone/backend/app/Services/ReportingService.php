<?php

namespace App\Services;

use App\Models\ApprovalStatusModel;
use App\Models\SpkCalculationModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;

class ReportingService
{
    protected BaseConnection $db;
    protected TransactionTypeModel $transactionTypeModel;
    protected ApprovalStatusModel $approvalStatusModel;
    protected SpkReportCompatibilityService $spkReportCompatibilityService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->transactionTypeModel = new TransactionTypeModel();
        $this->approvalStatusModel = new ApprovalStatusModel();
        $this->spkReportCompatibilityService = new SpkReportCompatibilityService();
    }

    public function getStockReport(array $query): array
    {
        $validated = $this->validateReportQuery($query, ['category_id', 'item_id', 'is_active']);
        if (! $validated['success']) {
            return $validated;
        }

        $builder = $this->db
            ->table('items i')
            ->select('i.id, i.name, i.item_category_id, ic.name AS category_name, i.qty, i.unit_base, i.unit_convert, i.is_active, i.updated_at')
            ->join('item_categories ic', 'ic.id = i.item_category_id', 'inner')
            ->where('i.deleted_at', null)
            ->where('ic.deleted_at', null)
            ->where('i.updated_at >=', $validated['period_start'] . ' 00:00:00')
            ->where('i.updated_at <=', $validated['period_end'] . ' 23:59:59');

        if (isset($validated['filters']['category_id'])) {
            $builder->where('i.item_category_id', (int) $validated['filters']['category_id']);
        }

        if (isset($validated['filters']['item_id'])) {
            $builder->where('i.id', (int) $validated['filters']['item_id']);
        }

        if (isset($validated['filters']['is_active'])) {
            $builder->where('i.is_active', $validated['filters']['is_active'] ? 1 : 0);
        }

        $rows = $builder
            ->orderBy('i.id', 'ASC')
            ->get()
            ->getResultArray();

        $totalQty = 0.0;
        $activeItems = 0;
        foreach ($rows as $row) {
            $totalQty += (float) $row['qty'];
            if ((int) $row['is_active'] === 1) {
                $activeItems++;
            }
        }

        return [
            'success' => true,
            'data' => [
                'report_type' => 'stocks',
                'period' => [
                    'start' => $validated['period_start'],
                    'end' => $validated['period_end'],
                ],
                'filters' => $validated['filters'],
                'summary' => [
                    'total_items' => count($rows),
                    'active_items' => $activeItems,
                    'total_qty' => round($totalQty, 2),
                ],
                'rows' => array_map(static fn(array $row): array => [
                    'item_id' => (int) $row['id'],
                    'item_name' => $row['name'],
                    'category_id' => (int) $row['item_category_id'],
                    'category_name' => $row['category_name'],
                    'qty' => (float) $row['qty'],
                    'unit_base' => $row['unit_base'],
                    'unit_convert' => $row['unit_convert'],
                    'is_active' => (bool) $row['is_active'],
                    'updated_at' => $row['updated_at'],
                ], $rows),
            ],
        ];
    }

    public function getTransactionReport(array $query): array
    {
        $validated = $this->validateReportQuery($query, ['type_id', 'status_id', 'item_id']);
        if (! $validated['success']) {
            return $validated;
        }

        $builder = $this->db
            ->table('stock_transactions st')
            ->select('st.id AS transaction_id, st.transaction_date, st.type_id, tt.name AS type_name, st.approval_status_id, aps.name AS status_name, st.user_id, st.spk_id, st.reason, std.item_id, i.name AS item_name, std.qty')
            ->join('stock_transaction_details std', 'std.transaction_id = st.id', 'inner')
            ->join('transaction_types tt', 'tt.id = st.type_id', 'inner')
            ->join('approval_statuses aps', 'aps.id = st.approval_status_id', 'inner')
            ->join('items i', 'i.id = std.item_id', 'inner')
            ->where('st.deleted_at', null)
            ->where('st.transaction_date >=', $validated['period_start'])
            ->where('st.transaction_date <=', $validated['period_end']);

        if (isset($validated['filters']['type_id'])) {
            $builder->where('st.type_id', (int) $validated['filters']['type_id']);
        }

        if (isset($validated['filters']['status_id'])) {
            $builder->where('st.approval_status_id', (int) $validated['filters']['status_id']);
        }

        if (isset($validated['filters']['item_id'])) {
            $builder->where('std.item_id', (int) $validated['filters']['item_id']);
        }

        $rows = $builder
            ->orderBy('st.transaction_date', 'ASC')
            ->orderBy('st.id', 'ASC')
            ->orderBy('std.item_id', 'ASC')
            ->get()
            ->getResultArray();

        $rows = $this->applyTransactionCompatibilityAggregationRules($rows);

        $totalQty = 0.0;
        foreach ($rows as $row) {
            $totalQty += (float) $row['qty'];
        }

        return [
            'success' => true,
            'data' => [
                'report_type' => 'transactions',
                'period' => [
                    'start' => $validated['period_start'],
                    'end' => $validated['period_end'],
                ],
                'filters' => $validated['filters'],
                'summary' => [
                    'total_rows' => count($rows),
                    'total_qty' => round($totalQty, 2),
                ],
                'rows' => array_map(static fn(array $row): array => [
                    'transaction_id' => (int) $row['transaction_id'],
                    'transaction_date' => $row['transaction_date'],
                    'type_id' => (int) $row['type_id'],
                    'type_name' => $row['type_name'],
                    'status_id' => (int) $row['approval_status_id'],
                    'status_name' => $row['status_name'],
                    'user_id' => (int) $row['user_id'],
                    'spk_id' => $row['spk_id'] !== null ? (int) $row['spk_id'] : null,
                    'item_id' => (int) $row['item_id'],
                    'item_name' => $row['item_name'],
                    'qty' => (float) $row['qty'],
                ], $rows),
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function applyTransactionCompatibilityAggregationRules(array $rows): array
    {
        $legacySignatures = [];

        foreach ($rows as $row) {
            if (! in_array((string) ($row['type_name'] ?? ''), [TransactionTypeModel::NAME_IN, TransactionTypeModel::NAME_OUT], true)) {
                continue;
            }

            $legacySignatures[$this->buildTransactionCompatibilitySignature($row)] = true;
        }

        $filtered = [];

        foreach ($rows as $row) {
            $typeName = (string) ($row['type_name'] ?? '');
            $reason   = (string) ($row['reason'] ?? '');

            if (
                $typeName === TransactionTypeModel::NAME_OPNAME_ADJUSTMENT
                && $this->isOpnamePostingAdjustmentReason($reason)
                && isset($legacySignatures[$this->buildTransactionCompatibilitySignature($row)])
            ) {
                continue;
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildTransactionCompatibilitySignature(array $row): string
    {
        return implode('|', [
            (string) ($row['transaction_date'] ?? ''),
            (string) ((int) ($row['approval_status_id'] ?? 0)),
            (string) ((int) ($row['user_id'] ?? 0)),
            (string) ((int) ($row['item_id'] ?? 0)),
            number_format((float) ($row['qty'] ?? 0), 2, '.', ''),
        ]);
    }

    private function isOpnamePostingAdjustmentReason(string $reason): bool
    {
        $trimmed = trim($reason);

        return preg_match('/^Stock opname(?: #\d+)? posting for item #\d+$/', $trimmed) === 1;
    }

    public function getSpkHistoryReport(array $query): array
    {
        $validated = $this->validateReportQuery($query, ['spk_type', 'category_id']);
        if (! $validated['success']) {
            return $validated;
        }

        $builder = $this->db
            ->table('spk_calculations sc')
            ->select('sc.id, sc.spk_type, sc.version, sc.calculation_scope, sc.calculation_date, sc.target_date_start, sc.target_date_end, sc.target_month, sc.estimated_patients, sc.is_finish, sc.category_id, ic.name AS category_name, sc.user_id, u.name AS user_name, COUNT(sr.id) AS total_recommendations, COALESCE(SUM(sr.required_qty), 0) AS total_required_qty, COALESCE(SUM(sr.recommended_qty), 0) AS total_recommended_qty')
            ->join('item_categories ic', 'ic.id = sc.category_id', 'left')
            ->join('users u', 'u.id = sc.user_id', 'left')
            ->join('spk_recommendations sr', 'sr.spk_id = sc.id', 'left')
            ->where('sc.calculation_date >=', $validated['period_start'])
            ->where('sc.calculation_date <=', $validated['period_end']);

        if (isset($validated['filters']['spk_type'])) {
            $builder->where('sc.spk_type', (string) $validated['filters']['spk_type']);
        }

        if (isset($validated['filters']['category_id'])) {
            $builder->where('sc.category_id', (int) $validated['filters']['category_id']);
        }

        $rows = $builder
            ->groupBy('sc.id, sc.spk_type, sc.version, sc.calculation_scope, sc.calculation_date, sc.target_date_start, sc.target_date_end, sc.target_month, sc.estimated_patients, sc.is_finish, sc.category_id, ic.name, sc.user_id, u.name')
            ->orderBy('sc.calculation_date', 'ASC')
            ->orderBy('sc.id', 'ASC')
            ->get()
            ->getResultArray();

        $compatibilityRows = [];
        foreach ($rows as $headerRow) {
            $recommendationRows = $this->db
                ->table('spk_recommendations')
                ->select('id, spk_id, item_id, recommended_qty')
                ->where('spk_id', (int) $headerRow['id'])
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            $compatibilityRows[] = $this->spkReportCompatibilityService->projectForSrs($headerRow, $recommendationRows);
        }

        return [
            'success' => true,
            'data' => [
                'report_type' => 'spk-history',
                'period' => [
                    'start' => $validated['period_start'],
                    'end' => $validated['period_end'],
                ],
                'filters' => $validated['filters'],
                'summary' => [
                    'total_spk' => count($rows),
                ],
                'rows' => array_map(static fn(array $row): array => [
                    'spk_id' => (int) $row['id'],
                    'spk_type' => $row['spk_type'],
                    'version' => (int) $row['version'],
                    'calculation_scope' => $row['calculation_scope'],
                    'calculation_date' => $row['calculation_date'],
                    'target_date_start' => $row['target_date_start'],
                    'target_date_end' => $row['target_date_end'],
                    'target_month' => $row['target_month'],
                    'estimated_patients' => (int) $row['estimated_patients'],
                    'is_finish' => (bool) $row['is_finish'],
                    'category_id' => (int) $row['category_id'],
                    'category_name' => $row['category_name'],
                    'user_id' => (int) $row['user_id'],
                    'user_name' => $row['user_name'],
                    'total_recommendations' => (int) $row['total_recommendations'],
                    'total_required_qty' => (float) $row['total_required_qty'],
                    'total_recommended_qty' => (float) $row['total_recommended_qty'],
                ], $rows),
                'compatibility_projection' => [
                    'contract' => [
                        'spk_calculations' => SpkReportCompatibilityService::SRS_CALCULATION_KEYS,
                        'spk_recommendations' => SpkReportCompatibilityService::SRS_RECOMMENDATION_KEYS,
                    ],
                    'rows' => $compatibilityRows,
                ],
            ],
        ];
    }

    public function getEvaluationReport(array $query): array
    {
        $validated = $this->validateReportQuery($query, ['spk_type', 'category_id']);
        if (! $validated['success']) {
            return $validated;
        }

        $approvedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        $outTypeId = $this->transactionTypeModel->getIdByName(TransactionTypeModel::NAME_OUT);

        if ($approvedStatusId === null || $outTypeId === null) {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'lookup' => 'Required lookup data for evaluation report is not configured.',
                ],
            ];
        }

        $spkBuilder = $this->db
            ->table('spk_calculations sc')
            ->select('sc.id, sc.spk_type, sc.calculation_date, sc.category_id, COALESCE(SUM(sr.recommended_qty), 0) AS planned_qty')
            ->join('spk_recommendations sr', 'sr.spk_id = sc.id', 'left')
            ->where('sc.calculation_date >=', $validated['period_start'])
            ->where('sc.calculation_date <=', $validated['period_end']);

        if (isset($validated['filters']['spk_type'])) {
            $spkBuilder->where('sc.spk_type', (string) $validated['filters']['spk_type']);
        }

        if (isset($validated['filters']['category_id'])) {
            $spkBuilder->where('sc.category_id', (int) $validated['filters']['category_id']);
        }

        $spkRows = $spkBuilder
            ->groupBy('sc.id, sc.spk_type, sc.calculation_date, sc.category_id')
            ->orderBy('sc.calculation_date', 'ASC')
            ->orderBy('sc.id', 'ASC')
            ->get()
            ->getResultArray();

        $realizationMap = [];
        $realizationRows = $this->db
            ->table('stock_transactions st')
            ->select('st.spk_id, COALESCE(SUM(std.qty), 0) AS realization_qty')
            ->join('stock_transaction_details std', 'std.transaction_id = st.id', 'inner')
            ->where('st.deleted_at', null)
            ->where('st.type_id', $outTypeId)
            ->where('st.approval_status_id', $approvedStatusId)
            ->where('st.spk_id IS NOT NULL', null, false)
            ->where('st.transaction_date >=', $validated['period_start'])
            ->where('st.transaction_date <=', $validated['period_end'])
            ->groupBy('st.spk_id')
            ->get()
            ->getResultArray();

        foreach ($realizationRows as $row) {
            $realizationMap[(int) $row['spk_id']] = (float) $row['realization_qty'];
        }

        $rows = [];
        $totalPlanned = 0.0;
        $totalRealization = 0.0;

        foreach ($spkRows as $spkRow) {
            $spkId = (int) $spkRow['id'];
            $planned = (float) $spkRow['planned_qty'];
            $realization = $realizationMap[$spkId] ?? 0.0;
            $variance = $realization - $planned;

            $totalPlanned += $planned;
            $totalRealization += $realization;

            $rows[] = [
                'spk_id' => $spkId,
                'spk_type' => $spkRow['spk_type'],
                'calculation_date' => $spkRow['calculation_date'],
                'category_id' => (int) $spkRow['category_id'],
                'planned_qty' => round($planned, 2),
                'realization_qty' => round($realization, 2),
                'variance_qty' => round($variance, 2),
            ];
        }

        return [
            'success' => true,
            'data' => [
                'report_type' => 'evaluation',
                'period' => [
                    'start' => $validated['period_start'],
                    'end' => $validated['period_end'],
                ],
                'filters' => $validated['filters'],
                'summary' => [
                    'total_spk' => count($rows),
                    'planned_total_qty' => round($totalPlanned, 2),
                    'realization_total_qty' => round($totalRealization, 2),
                    'variance_total_qty' => round($totalRealization - $totalPlanned, 2),
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * @param array<int, string> $allowedFilterKeys
     */
    private function validateReportQuery(array $query, array $allowedFilterKeys): array
    {
        $allowedParams = array_merge(['period_start', 'period_end'], $allowedFilterKeys);
        $unknown = array_values(array_diff(array_keys($query), $allowedParams));
        if ($unknown !== []) {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'query' => 'Unsupported query parameter(s): ' . implode(', ', $unknown),
                ],
            ];
        }

        $errors = [];

        $periodStart = trim((string) ($query['period_start'] ?? ''));
        $periodEnd = trim((string) ($query['period_end'] ?? ''));

        if ($periodStart === '') {
            $errors['period_start'] = 'The period_start field is required.';
        }

        if ($periodEnd === '') {
            $errors['period_end'] = 'The period_end field is required.';
        }

        if ($periodStart !== '' && ! $this->isValidDate($periodStart)) {
            $errors['period_start'] = 'The period_start field must be a valid date in Y-m-d format.';
        }

        if ($periodEnd !== '' && ! $this->isValidDate($periodEnd)) {
            $errors['period_end'] = 'The period_end field must be a valid date in Y-m-d format.';
        }

        if (! isset($errors['period_start']) && ! isset($errors['period_end']) && $periodStart > $periodEnd) {
            $errors['period_start'] = 'The period_start field must be earlier than or equal to period_end.';
        }

        $filters = [];

        if (in_array('category_id', $allowedFilterKeys, true) && array_key_exists('category_id', $query)) {
            if (! ctype_digit((string) $query['category_id']) || (int) $query['category_id'] < 1) {
                $errors['category_id'] = 'The category_id field must be a positive integer.';
            } else {
                $filters['category_id'] = (int) $query['category_id'];
            }
        }

        if (in_array('item_id', $allowedFilterKeys, true) && array_key_exists('item_id', $query)) {
            if (! ctype_digit((string) $query['item_id']) || (int) $query['item_id'] < 1) {
                $errors['item_id'] = 'The item_id field must be a positive integer.';
            } else {
                $filters['item_id'] = (int) $query['item_id'];
            }
        }

        if (in_array('type_id', $allowedFilterKeys, true) && array_key_exists('type_id', $query)) {
            if (! ctype_digit((string) $query['type_id']) || (int) $query['type_id'] < 1) {
                $errors['type_id'] = 'The type_id field must be a positive integer.';
            } else {
                $filters['type_id'] = (int) $query['type_id'];
            }
        }

        if (in_array('status_id', $allowedFilterKeys, true) && array_key_exists('status_id', $query)) {
            if (! ctype_digit((string) $query['status_id']) || (int) $query['status_id'] < 1) {
                $errors['status_id'] = 'The status_id field must be a positive integer.';
            } else {
                $filters['status_id'] = (int) $query['status_id'];
            }
        }

        if (in_array('spk_type', $allowedFilterKeys, true) && array_key_exists('spk_type', $query)) {
            $spkType = trim((string) $query['spk_type']);
            if (! in_array($spkType, [SpkCalculationModel::TYPE_BASAH, SpkCalculationModel::TYPE_KERING_PENGEMAS], true)) {
                $errors['spk_type'] = sprintf(
                    'The spk_type field must be one of: %s, %s.',
                    SpkCalculationModel::TYPE_BASAH,
                    SpkCalculationModel::TYPE_KERING_PENGEMAS
                );
            } else {
                $filters['spk_type'] = $spkType;
            }
        }

        if (in_array('is_active', $allowedFilterKeys, true) && array_key_exists('is_active', $query)) {
            $normalized = strtolower(trim((string) $query['is_active']));
            if (! in_array($normalized, ['1', '0', 'true', 'false'], true)) {
                $errors['is_active'] = 'The is_active field must be one of: true, false, 1, 0.';
            } else {
                $filters['is_active'] = in_array($normalized, ['1', 'true'], true);
            }
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'status' => 400,
                'message' => 'Validation failed.',
                'errors' => $errors,
            ];
        }

        return [
            'success' => true,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'filters' => $filters,
        ];
    }

    private function isValidDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (! $parsed instanceof DateTimeImmutable) {
            return false;
        }

        return $parsed->format('Y-m-d') === $value;
    }
}
