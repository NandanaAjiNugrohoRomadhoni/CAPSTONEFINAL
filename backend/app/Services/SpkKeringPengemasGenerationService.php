<?php

namespace App\Services;

use App\Models\ApprovalStatusModel;
use App\Models\ItemCategoryModel;
use App\Models\SpkCalculationModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;

class SpkKeringPengemasGenerationService
{
    protected ItemCategoryModel $itemCategoryModel;
    protected ApprovalStatusModel $approvalStatusModel;
    protected TransactionTypeModel $transactionTypeModel;
    protected SpkPersistenceService $spkPersistenceService;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->itemCategoryModel     = new ItemCategoryModel();
        $this->approvalStatusModel   = new ApprovalStatusModel();
        $this->transactionTypeModel  = new TransactionTypeModel();
        $this->spkPersistenceService = new SpkPersistenceService();
        $this->db                    = Database::connect();
    }

    public function generate(array $data, int $userId): array
    {
        $validationResult = $this->validateGeneratePayload($data);
        if (! $validationResult['success']) {
            return $validationResult;
        }

        $targetMonth = $validationResult['target_month'];
        $monthStart  = $validationResult['target_date_start'];
        $monthEnd    = $validationResult['target_date_end'];
        $postedStatusId = $validationResult['posted_status_id'];
        $outTypeId      = $validationResult['out_type_id'];

        $categoryRows = [
            ItemCategoryModel::NAME_KERING => $this->itemCategoryModel->getIdByName(ItemCategoryModel::NAME_KERING),
            'PENGEMAS' => $this->itemCategoryModel->getIdByName('PENGEMAS'),
        ];

        if ($categoryRows[ItemCategoryModel::NAME_KERING] === null || $categoryRows['PENGEMAS'] === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'category' => 'KERING and PENGEMAS item categories must be configured.',
                ],
            ];
        }

        $recommendations = [];

        foreach ($categoryRows as $categoryId) {
            $recommendations = array_merge(
                $recommendations,
                $this->buildCategoryRecommendations(
                    (int) $categoryId,
                    $targetMonth,
                    $postedStatusId,
                    $outTypeId
                )
            );
        }

        $persisted = $this->spkPersistenceService->createVersionedSpk([
            'spk_type'            => SpkCalculationModel::TYPE_KERING_PENGEMAS,
            'calculation_scope'   => SpkCalculationModel::SCOPE_MONTHLY,
            'calculation_date'    => date('Y-m-d'),
            'target_date_start'   => $monthStart,
            'target_date_end'     => $monthEnd,
            'target_month'        => $targetMonth,
            'daily_patient_id'    => null,
            'user_id'             => $userId,
            'category_id'         => (int) $categoryRows[ItemCategoryModel::NAME_KERING],
            'estimated_patients'  => 0,
            'is_finish'           => false,
        ], $recommendations);

        if (! $persisted['success']) {
            return $persisted;
        }

        return [
            'success' => true,
            'data'    => [
                'id'           => (int) $persisted['data']['id'],
                'version'      => (int) $persisted['data']['version'],
                'scope_key'    => (string) $persisted['data']['scope_key'],
                'target_month' => $targetMonth,
            ],
        ];
    }

    private function validateGeneratePayload(array $data): array
    {
        $rules = [
            'target_month' => 'required|regex_match[/^\d{4}-\d{2}$/]',
        ];

        $validation = service('validation');
        $payload    = [
            'target_month' => (string) ($data['target_month'] ?? ''),
        ];

        if (! $validation->setRules($rules)->run($payload)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validation->getErrors(),
            ];
        }

        $targetMonth = $payload['target_month'];
        if (! $this->isValidTargetMonth($targetMonth)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'target_month' => 'The target_month field must be a valid month in Y-m format.',
                ],
            ];
        }

        $postedStatusId = $this->approvalStatusModel->getIdByName(ApprovalStatusModel::NAME_APPROVED);
        if ($postedStatusId === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'approval_status' => 'APPROVED approval status is not configured.',
                ],
            ];
        }

        $outTypeId = $this->transactionTypeModel->getIdByName(TransactionTypeModel::NAME_OUT);
        if ($outTypeId === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'transaction_type' => 'OUT transaction type is not configured.',
                ],
            ];
        }

        $monthStart = $targetMonth . '-01';
        $monthEnd   = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');

        return [
            'success'           => true,
            'target_month'      => $targetMonth,
            'target_date_start' => $monthStart,
            'target_date_end'   => $monthEnd,
            'posted_status_id'  => $postedStatusId,
            'out_type_id'       => $outTypeId,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryRecommendations(int $categoryId, string $targetMonth, int $postedStatusId, int $outTypeId): array
    {
        $targetMonthDate = new DateTimeImmutable($targetMonth . '-01');
        $previousMonthStart = $targetMonthDate->modify('-1 month')->format('Y-m-01');
        $previousMonthEnd   = $targetMonthDate->modify('-1 month')->modify('last day of this month')->format('Y-m-d');

        $transactionRows = $this->db
            ->table('stock_transactions')
            ->select('id')
            ->where('approval_status_id', $postedStatusId)
            ->where('type_id', $outTypeId)
            ->where('is_revision', 0)
            ->where('deleted_at', null)
            ->where('transaction_date >=', $previousMonthStart)
            ->where('transaction_date <=', $previousMonthEnd)
            ->get()
            ->getResultArray();

        $transactionIds = array_map(static fn(array $row): int => (int) $row['id'], $transactionRows);

        $itemRows = $this->db
            ->table('items')
            ->select('id AS item_id, qty AS current_stock_qty')
            ->where('deleted_at', null)
            ->where('item_category_id', $categoryId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $recommendations = [];
        $usageByItem = [];

        if ($transactionIds !== []) {
            foreach ($transactionIds as $transactionId) {
                $detailRows = $this->db
                    ->table('stock_transaction_details')
                    ->select('item_id, qty')
                    ->where('transaction_id', (int) $transactionId)
                    ->get()
                    ->getResultArray();

                foreach ($detailRows as $detailRow) {
                    $itemId = (int) $detailRow['item_id'];
                    $usageByItem[$itemId] = ($usageByItem[$itemId] ?? 0.0) + (float) $detailRow['qty'];
                }
            }
        }

        foreach ($itemRows as $row) {
            $itemId = (int) $row['item_id'];
            $usage = $usageByItem[$itemId] ?? 0.0;
            $currentStock = (float) $row['current_stock_qty'];
            $grossNeed = round($usage * 1.10, 2);
            $systemRecommendation = max($grossNeed - $currentStock, 0.0);

            $recommendations[] = [
                'item_id'                => $itemId,
                'target_date'            => null,
                'current_stock_qty'      => $currentStock,
                'required_qty'           => $grossNeed,
                'system_recommended_qty' => $systemRecommendation,
                'recommended_qty'        => $systemRecommendation,
            ];
        }

        return $recommendations;
    }

    private function isValidTargetMonth(string $targetMonth): bool
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $targetMonth)) {
            return false;
        }

        [$year, $month] = array_map('intval', explode('-', $targetMonth));

        return checkdate($month, 1, $year);
    }
}
