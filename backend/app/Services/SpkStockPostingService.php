<?php

namespace App\Services;

use App\Models\SpkCalculationModel;
use App\Models\SpkRecommendationModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class SpkStockPostingService
{
    protected SpkCalculationModel $spkCalculationModel;
    protected SpkRecommendationModel $spkRecommendationModel;
    protected StockTransactionService $stockTransactionService;
    protected TransactionTypeModel $transactionTypeModel;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->spkCalculationModel   = new SpkCalculationModel();
        $this->spkRecommendationModel = new SpkRecommendationModel();
        $this->stockTransactionService = new StockTransactionService();
        $this->transactionTypeModel  = new TransactionTypeModel();
        $this->db                    = Database::connect();
    }

    public function post(int $spkId, string $spkType, int $actorId, ?string $ipAddress = null): array
    {
        if ($spkId <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'id' => 'The SPK ID must be a positive integer.',
                ],
            ];
        }

        $spk = $this->spkCalculationModel
            ->where('id', $spkId)
            ->where('spk_type', $spkType)
            ->first();

        if ($spk === null) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => 'SPK history not found.',
                'errors' => [],
            ];
        }

        if ((bool) $spk['is_finish']) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'state' => 'SPK has already been posted to stock.',
                ],
            ];
        }

        $recommendations = $this->spkRecommendationModel
            ->where('spk_id', $spkId)
            ->orderBy('item_id', 'ASC')
            ->findAll();

        if ($recommendations === []) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'spk_id' => 'The selected SPK has no recommendation rows.',
                ],
            ];
        }

        $aggregated = [];
        foreach ($recommendations as $row) {
            $itemId = (int) $row['item_id'];
            $qty    = round((float) $row['recommended_qty'], 2);

            if ($qty <= 0) {
                continue;
            }

            $aggregated[$itemId] = ($aggregated[$itemId] ?? 0.0) + $qty;
        }

        if ($aggregated === []) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'spk_id' => 'No positive recommendation quantities available to post.',
                ],
            ];
        }

        $typeId = $this->transactionTypeModel->getIdByName(TransactionTypeModel::NAME_IN);
        if ($typeId === null) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'System configuration error.',
                'errors' => [
                    'lookup' => 'Required transaction type is missing.',
                ],
            ];
        }

        $payload = [
            'type_id' => $typeId,
            'transaction_date' => (string) $spk['calculation_date'],
            'spk_id' => $spkId,
            'details' => array_map(
                static fn (int $itemId, float $qty): array => [
                    'item_id' => $itemId,
                    'qty' => round($qty, 2),
                    'input_unit' => 'base',
                ],
                array_keys($aggregated),
                array_values($aggregated)
            ),
        ];

        $this->db->transStart();

        $transactionResult = $this->stockTransactionService->createTransaction($payload, $actorId, $ipAddress);
        if (! $transactionResult['success']) {
            $this->db->transRollback();

            return [
                'success' => false,
                'status_code' => 400,
                'message' => $transactionResult['message'],
                'errors' => $transactionResult['errors'] ?? [],
            ];
        }

        $updated = $this->spkCalculationModel->update($spkId, ['is_finish' => true]);
        if (! $updated) {
            $this->db->transRollback();

            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Failed to finalize SPK posting state.',
                'errors' => $this->spkCalculationModel->errors(),
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Failed to post SPK into stock transaction.',
                'errors' => [],
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'id' => $spkId,
                'version' => (int) $spk['version'],
                'is_finish' => true,
                'posted_transaction_id' => (int) ($transactionResult['data']['id'] ?? 0),
            ],
        ];
    }
}
