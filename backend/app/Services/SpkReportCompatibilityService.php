<?php

namespace App\Services;

class SpkReportCompatibilityService
{
    /**
     * SRS-facing SPK recommendation projection contract.
     *
     * @var array<int, string>
     */
    public const SRS_RECOMMENDATION_KEYS = [
        'id',
        'spk_id',
        'item_id',
        'qty',
    ];

    /**
     * @var array<int, string>
     */
    public const SRS_CALCULATION_KEYS = [
        'id',
        'calculation_date',
        'target_date_start',
        'target_date_end',
        'daily_patient_id',
        'user_id',
        'category_id',
        'estimated_patients',
        'is_finish',
    ];

    /**
     * @param array<string, mixed> $headerRow
     * @param array<int, array<string, mixed>> $recommendationRows
     */
    public function projectForSrs(array $headerRow, array $recommendationRows): array
    {
        $projectedRecommendations = [];
        $totalProjectedQty = 0.0;

        foreach ($recommendationRows as $row) {
            $projected = [
                'id' => (int) ($row['id'] ?? 0),
                'spk_id' => (int) ($row['spk_id'] ?? 0),
                'item_id' => (int) ($row['item_id'] ?? 0),
                'qty' => (float) ($row['recommended_qty'] ?? 0.0),
            ];

            $projectedRecommendations[] = $projected;
            $totalProjectedQty += $projected['qty'];
        }

        return [
            'spk_calculation' => [
                'id' => (int) ($headerRow['id'] ?? 0),
                'calculation_date' => $headerRow['calculation_date'] ?? null,
                'target_date_start' => $headerRow['target_date_start'] ?? null,
                'target_date_end' => $headerRow['target_date_end'] ?? null,
                'daily_patient_id' => isset($headerRow['daily_patient_id']) && $headerRow['daily_patient_id'] !== null
                    ? (int) $headerRow['daily_patient_id']
                    : null,
                'user_id' => isset($headerRow['user_id']) ? (int) $headerRow['user_id'] : null,
                'category_id' => isset($headerRow['category_id']) ? (int) $headerRow['category_id'] : null,
                'estimated_patients' => isset($headerRow['estimated_patients']) ? (int) $headerRow['estimated_patients'] : null,
                'is_finish' => isset($headerRow['is_finish']) ? (int) ((bool) $headerRow['is_finish']) : null,
            ],
            'spk_recommendations' => $projectedRecommendations,
            'meta' => [
                'projection_version' => 'srs-compat-v1',
                'source_schema' => 'spk-persistence-rich',
                'recommendation_total_qty' => round($totalProjectedQty, 2),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $projection
     * @return array<string, mixed>
     */
    public function validateProjectionContract(array $projection): array
    {
        $errors = [];

        $calculation = is_array($projection['spk_calculation'] ?? null)
            ? $projection['spk_calculation']
            : [];

        $calculationDiff = $this->diffKeys($calculation, self::SRS_CALCULATION_KEYS);
        if ($calculationDiff['missing'] !== [] || $calculationDiff['unexpected'] !== []) {
            $errors['spk_calculation'] = $calculationDiff;
        }

        $recommendations = is_array($projection['spk_recommendations'] ?? null)
            ? $projection['spk_recommendations']
            : [];

        foreach ($recommendations as $index => $recommendation) {
            if (! is_array($recommendation)) {
                $errors['spk_recommendations'][$index] = [
                    'missing' => self::SRS_RECOMMENDATION_KEYS,
                    'unexpected' => [],
                ];

                continue;
            }

            $recommendationDiff = $this->diffKeys($recommendation, self::SRS_RECOMMENDATION_KEYS);
            if ($recommendationDiff['missing'] !== [] || $recommendationDiff['unexpected'] !== []) {
                $errors['spk_recommendations'][$index] = $recommendationDiff;
            }
        }

        return [
            'success' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $actual
     * @param array<int, string> $expectedKeys
     * @return array<string, array<int, string>>
     */
    private function diffKeys(array $actual, array $expectedKeys): array
    {
        $actualKeys = array_map('strval', array_keys($actual));
        sort($actualKeys);

        $expected = $expectedKeys;
        sort($expected);

        $missing = array_values(array_diff($expected, $actualKeys));
        $unexpected = array_values(array_diff($actualKeys, $expected));

        return [
            'missing' => $missing,
            'unexpected' => $unexpected,
        ];
    }
}
