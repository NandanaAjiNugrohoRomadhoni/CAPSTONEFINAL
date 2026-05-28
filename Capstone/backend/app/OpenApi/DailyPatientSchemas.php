<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="DailyPatient",
 *     type="object",
 *     required={"id","service_date","total_patients","created_at","updated_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="service_date", type="string", example="2026-05-01"),
 *     @OA\Property(property="total_patients", type="integer", minimum=0, example=120),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Morning shift"),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-05-01 06:00:00"),
 *     @OA\Property(property="updated_at", type="string", nullable=true, example="2026-05-01 06:00:00")
 * )
 * @OA\Schema(
 *     schema="DailyPatientResource",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/DailyPatient")
 * )
 * @OA\Schema(
 *     schema="DailyPatientCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/LookupCollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DailyPatient")))
 *     }
 * )
 * @OA\Schema(
 *     schema="DailyPatientCreateRequest",
 *     type="object",
 *     required={"service_date","total_patients"},
 *     @OA\Property(property="service_date", type="string", example="2026-05-01", description="Service date in Y-m-d format."),
 *     @OA\Property(property="total_patients", type="integer", minimum=0, example=120),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Morning shift")
 * )
 * @OA\Schema(
 *     schema="DailyPatientUpdateRequest",
 *     type="object",
 *     @OA\Property(property="service_date", type="string", example="2026-05-02", description="Updated service date in Y-m-d format. Must stay unique."),
 *     @OA\Property(property="total_patients", type="integer", minimum=0, example=140),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Adjusted after final census")
 * )
 * @OA\Schema(
 *     schema="DailyPatientMutationResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Daily patient created successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/DailyPatient")
 * )
 */
final class DailyPatientSchemas
{
}
