<?php

namespace App\Services;

use App\Models\SpkCalculationModel;
use App\Models\SpkRecommendationModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use InvalidArgumentException;

class SpkPersistenceService
{
    protected SpkCalculationModel $spkCalculationModel;
    protected SpkRecommendationModel $spkRecommendationModel;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->spkCalculationModel   = new SpkCalculationModel();
        $this->spkRecommendationModel = new SpkRecommendationModel();
        $this->db                    = Database::connect();
    }

    /**
     * @param array<string, mixed> $headerData
     * @param array<int, array<string, mixed>> $recommendations
     */
    public function createVersionedSpk(array $headerData, array $recommendations): array
    {
        $scopeKey = $this->buildScopeKey($headerData);
        $nextVersion = $this->spkCalculationModel->getNextVersionForScope($scopeKey);

        $this->db->transStart();

        $this->spkCalculationModel
            ->where('scope_key', $scopeKey)
            ->where('is_latest', true)
            ->set(['is_latest' => false])
            ->update();

        $spkId = $this->spkCalculationModel->insert([
            'spk_type'          => $headerData['spk_type'],
            'calculation_scope' => $headerData['calculation_scope'],
            'scope_key'         => $scopeKey,
            'version'           => $nextVersion,
            'is_latest'         => true,
            'calculation_date'  => $headerData['calculation_date'],
            'target_date_start' => $headerData['target_date_start'],
            'target_date_end'   => $headerData['target_date_end'],
            'target_month'      => $headerData['target_month'] ?? null,
            'daily_patient_id'  => $headerData['daily_patient_id'] ?? null,
            'user_id'           => (int) $headerData['user_id'],
            'category_id'       => (int) $headerData['category_id'],
            'estimated_patients' => (int) $headerData['estimated_patients'],
            'is_finish'         => (bool) ($headerData['is_finish'] ?? false),
        ], true);

        if ($spkId === false) {
            $this->db->transRollback();

            return [
                'success' => false,
                'message' => 'Failed to create SPK calculation header.',
                'errors'  => $this->spkCalculationModel->errors(),
            ];
        }

        foreach ($recommendations as $index => $recommendation) {
            $systemRecommendedQty = (float) $recommendation['system_recommended_qty'];
            $hasFinal = array_key_exists('recommended_qty', $recommendation);
            $finalRecommendedQty = $hasFinal
                ? (float) $recommendation['recommended_qty']
                : $systemRecommendedQty;

            $isOverridden = array_key_exists('is_overridden', $recommendation)
                ? (bool) $recommendation['is_overridden']
                : abs($finalRecommendedQty - $systemRecommendedQty) > 0.00001;

            if ($isOverridden && ! array_key_exists('override_reason', $recommendation)) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        "recommendations.{$index}.override_reason" => 'override_reason is required when recommendation is overridden.',
                    ],
                ];
            }

            $inserted = $this->spkRecommendationModel->insert([
                'spk_id'                  => (int) $spkId,
                'item_id'                 => (int) $recommendation['item_id'],
                'target_date'             => $recommendation['target_date'] ?? null,
                'current_stock_qty'       => (float) $recommendation['current_stock_qty'],
                'required_qty'            => (float) $recommendation['required_qty'],
                'system_recommended_qty'  => $systemRecommendedQty,
                'recommended_qty'         => $finalRecommendedQty,
                'is_overridden'           => $isOverridden,
                'override_reason'         => $isOverridden ? (string) $recommendation['override_reason'] : null,
                'overridden_by'           => $isOverridden ? (int) ($recommendation['overridden_by'] ?? $headerData['user_id']) : null,
                'overridden_at'           => $isOverridden ? ($recommendation['overridden_at'] ?? date('Y-m-d H:i:s')) : null,
            ]);

            if ($inserted === false) {
                $this->db->transRollback();

                return [
                    'success' => false,
                    'message' => 'Failed to create SPK recommendation snapshot.',
                    'errors'  => $this->spkRecommendationModel->errors(),
                ];
            }
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'message' => 'Failed to persist SPK history version.',
                'errors'  => [],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'id'      => (int) $spkId,
                'version' => $nextVersion,
                'scope_key' => $scopeKey,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $headerData
     */
    private function buildScopeKey(array $headerData): string
    {
        $spkType = (string) ($headerData['spk_type'] ?? '');
        $scope = (string) ($headerData['calculation_scope'] ?? '');

        if ($spkType === '' || $scope === '') {
            throw new InvalidArgumentException('spk_type and calculation_scope are required.');
        }

        if ($scope === SpkCalculationModel::SCOPE_COMBINED_WINDOW) {
            return implode('|', [
                $spkType,
                $scope,
                (string) $headerData['target_date_start'],
                (string) $headerData['target_date_end'],
                (string) $headerData['category_id'],
            ]);
        }

        if ($scope === SpkCalculationModel::SCOPE_MONTHLY) {
            return implode('|', [
                $spkType,
                $scope,
                (string) $headerData['target_month'],
                (string) $headerData['category_id'],
            ]);
        }

        throw new InvalidArgumentException('Unsupported calculation_scope for SPK persistence.');
    }
}
