<?php

namespace App\Services;

use App\Models\SpkCalculationModel;
use App\Models\SpkRecommendationModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class SpkOverrideService
{
    private const ALLOWED_FIELDS = [
        'recommendation_id',
        'recommended_qty',
        'reason',
    ];

    protected SpkCalculationModel $spkCalculationModel;
    protected SpkRecommendationModel $spkRecommendationModel;
    protected AuditService $auditService;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->spkCalculationModel = new SpkCalculationModel();
        $this->spkRecommendationModel = new SpkRecommendationModel();
        $this->auditService = new AuditService();
        $this->db = Database::connect();
    }

    public function overrideItem(
        int $spkId,
        string $spkType,
        array $payload,
        int $actorId,
        ?string $ipAddress = null
    ): array {
        $unknownFields = array_diff(array_keys($payload), self::ALLOWED_FIELDS);
        if ($unknownFields !== []) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'fields' => 'Unknown field(s): ' . implode(', ', $unknownFields),
                ],
            ];
        }

        if (! isset($payload['recommendation_id']) || ! is_numeric($payload['recommendation_id']) || (int) $payload['recommendation_id'] <= 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'recommendation_id' => 'The recommendation_id field is required and must be a positive integer.',
                ],
            ];
        }

        if (! array_key_exists('recommended_qty', $payload) || ! is_numeric($payload['recommended_qty']) || (float) $payload['recommended_qty'] < 0) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'recommended_qty' => 'The recommended_qty field is required and must be a non-negative number.',
                ],
            ];
        }

        if (! isset($payload['reason']) || trim((string) $payload['reason']) === '') {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Validation failed.',
                'errors' => [
                    'reason' => 'The reason field is required.',
                ],
            ];
        }

        $reason = trim((string) $payload['reason']);
        $recommendationId = (int) $payload['recommendation_id'];
        $finalRecommendedQty = round((float) $payload['recommended_qty'], 2);

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
                'status_code' => 403,
                'message' => 'SPK is already finalized. Overrides are not allowed.',
                'errors' => [],
            ];
        }

        $recommendation = $this->spkRecommendationModel
            ->where('id', $recommendationId)
            ->where('spk_id', $spkId)
            ->first();

        if ($recommendation === null) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => 'SPK recommendation item not found.',
                'errors' => [],
            ];
        }

        $this->db->transStart();

        $oldValues = [
            'recommended_qty' => (float) $recommendation['recommended_qty'],
            'is_overridden' => (bool) $recommendation['is_overridden'],
            'override_reason' => $recommendation['override_reason'],
            'overridden_by' => $recommendation['overridden_by'] !== null ? (int) $recommendation['overridden_by'] : null,
            'overridden_at' => $recommendation['overridden_at'],
        ];

        $overrideAt = date('Y-m-d H:i:s');
        $updated = $this->spkRecommendationModel->update($recommendationId, [
            'recommended_qty' => $finalRecommendedQty,
            'is_overridden' => true,
            'override_reason' => $reason,
            'overridden_by' => $actorId,
            'overridden_at' => $overrideAt,
        ]);

        if (! $updated) {
            $this->db->transRollback();

            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Failed to update SPK recommendation item.',
                'errors' => $this->spkRecommendationModel->errors(),
            ];
        }

        $newValues = [
            'recommended_qty' => $finalRecommendedQty,
            'is_overridden' => true,
            'override_reason' => $reason,
            'overridden_by' => $actorId,
            'overridden_at' => $overrideAt,
            'system_recommended_qty' => (float) $recommendation['system_recommended_qty'],
        ];

        $auditLogged = $this->auditService->log(
            $actorId,
            'spk_recommendation_override',
            'spk_recommendations',
            $recommendationId,
            'SPK recommendation item overridden before finalization.',
            $oldValues,
            $newValues,
            $ipAddress
        );

        if (! $auditLogged) {
            $this->db->transRollback();

            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Failed to write audit log.',
                'errors' => [],
            ];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return [
                'success' => false,
                'status_code' => 400,
                'message' => 'Failed to persist SPK recommendation override.',
                'errors' => [],
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'SPK recommendation item overridden successfully.',
            'data' => [
                'spk_id' => $spkId,
                'recommendation_id' => $recommendationId,
                'system_recommended_qty' => (float) $recommendation['system_recommended_qty'],
                'recommended_qty' => $finalRecommendedQty,
                'override' => [
                    'is_overridden' => true,
                    'reason' => $reason,
                    'overridden_by' => $actorId,
                    'overridden_at' => $overrideAt,
                ],
            ],
        ];
    }
}
