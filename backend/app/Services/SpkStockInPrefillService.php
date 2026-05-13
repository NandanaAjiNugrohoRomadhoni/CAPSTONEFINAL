<?php

namespace App\Services;

use App\Models\SpkCalculationModel;
use App\Models\SpkRecommendationModel;

class SpkStockInPrefillService
{
    protected SpkCalculationModel $spkCalculationModel;
    protected SpkRecommendationModel $spkRecommendationModel;

    public function __construct()
    {
        $this->spkCalculationModel     = new SpkCalculationModel();
        $this->spkRecommendationModel  = new SpkRecommendationModel();
    }

    public function buildDraftFromSpk(int $spkId): array
    {
        if ($spkId <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'spk_id' => 'The spk_id field must be a positive integer.',
                ],
            ];
        }

        $spk = $this->spkCalculationModel->find($spkId);
        if ($spk === null) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => 'SPK not found.',
                'errors' => [],
            ];
        }

        $recommendations = $this->spkRecommendationModel
            ->where('spk_id', $spkId)
            ->orderBy('target_date', 'ASC')
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

        $detailsByItem = [];
        foreach ($recommendations as $recommendation) {
            $itemId = (int) $recommendation['item_id'];
            $recommendedQty = (float) $recommendation['recommended_qty'];

            if (! isset($detailsByItem[$itemId])) {
                $detailsByItem[$itemId] = 0.0;
            }

            $detailsByItem[$itemId] += $recommendedQty;
        }

        ksort($detailsByItem);

        $details = [];
        foreach ($detailsByItem as $itemId => $totalQty) {
            if ($totalQty <= 0.0) {
                continue;
            }

            $details[] = [
                'item_id' => (int) $itemId,
                'qty'     => round((float) $totalQty, 2),
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'data' => [
                'type_name'        => 'IN',
                'transaction_date' => (string) $spk['calculation_date'],
                'spk_id'           => (int) $spkId,
                'details'          => $details,
            ],
        ];
    }
}
