<?php

namespace Tests\Unit;

use App\Services\SpkReportCompatibilityService;
use CodeIgniter\Test\CIUnitTestCase;

class SpkReportCompatibilityServiceTest extends CIUnitTestCase
{
    public function testProjectForSrsMapsRichRecommendationToSrsQtyContract(): void
    {
        $service = new SpkReportCompatibilityService();

        $projection = $service->projectForSrs([
            'id' => 91,
            'calculation_date' => '2026-04-20',
            'target_date_start' => '2026-04-20',
            'target_date_end' => '2026-04-21',
            'daily_patient_id' => 7,
            'user_id' => 3,
            'category_id' => 2,
            'estimated_patients' => 100,
            'is_finish' => true,
        ], [
            [
                'id' => 501,
                'spk_id' => 91,
                'item_id' => 12,
                'recommended_qty' => 25.75,
            ],
        ]);

        $this->assertSame(91, $projection['spk_calculation']['id']);
        $this->assertSame('2026-04-20', $projection['spk_calculation']['calculation_date']);
        $this->assertSame(1, $projection['spk_calculation']['is_finish']);
        $this->assertSame(501, $projection['spk_recommendations'][0]['id']);
        $this->assertSame(91, $projection['spk_recommendations'][0]['spk_id']);
        $this->assertSame(12, $projection['spk_recommendations'][0]['item_id']);
        $this->assertSame(25.75, $projection['spk_recommendations'][0]['qty']);
        $this->assertSame('srs-compat-v1', $projection['meta']['projection_version']);
    }

    public function testValidateProjectionContractSucceedsForExpectedSchema(): void
    {
        $service = new SpkReportCompatibilityService();

        $projection = $service->projectForSrs([
            'id' => 10,
            'calculation_date' => '2026-04-10',
            'target_date_start' => '2026-04-10',
            'target_date_end' => '2026-04-11',
            'daily_patient_id' => null,
            'user_id' => 1,
            'category_id' => 1,
            'estimated_patients' => 0,
            'is_finish' => false,
        ], [
            [
                'id' => 1,
                'spk_id' => 10,
                'item_id' => 20,
                'recommended_qty' => 7,
            ],
        ]);

        $validation = $service->validateProjectionContract($projection);

        $this->assertTrue($validation['success']);
        $this->assertSame([], $validation['errors']);
    }

    public function testValidateProjectionContractDetectsMissingAndUnexpectedFields(): void
    {
        $service = new SpkReportCompatibilityService();

        $invalidProjection = [
            'spk_calculation' => [
                'id' => 1,
                'calculation_date' => '2026-04-10',
                'target_date_start' => '2026-04-10',
                'target_date_end' => '2026-04-11',
                'daily_patient_id' => null,
                'user_id' => 1,
                'category_id' => 1,
                'estimated_patients' => 0,
                // is_finish intentionally missing
                'scope_key' => 'unexpected-rich-field',
            ],
            'spk_recommendations' => [
                [
                    'id' => 10,
                    'spk_id' => 1,
                    // item_id intentionally missing
                    'qty' => 2.5,
                    'system_recommended_qty' => 2.5,
                ],
            ],
        ];

        $validation = $service->validateProjectionContract($invalidProjection);

        $this->assertFalse($validation['success']);
        $this->assertSame(['is_finish'], $validation['errors']['spk_calculation']['missing']);
        $this->assertSame(['scope_key'], $validation['errors']['spk_calculation']['unexpected']);
        $this->assertSame(['item_id'], $validation['errors']['spk_recommendations'][0]['missing']);
        $this->assertSame(['system_recommended_qty'], $validation['errors']['spk_recommendations'][0]['unexpected']);
    }
}
