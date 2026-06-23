<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="StockOpnameDetailInput",
 *     type="object",
 *     required={"item_id","counted_qty"},
 *     @OA\Property(property="item_id", type="integer", minimum=1, example=1),
 *     @OA\Property(property="counted_qty", type="number", format="float", minimum=0, example=98.5)
 * )
 * @OA\Schema(
 *     schema="StockOpnameMutationRequest",
 *     type="object",
 *     required={"opname_date","details"},
 *     @OA\Property(property="opname_date", type="string", example="2026-06-20"),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Cycle count June."),
 *     @OA\Property(property="details", type="array", @OA\Items(ref="#/components/schemas/StockOpnameDetailInput"))
 * )
 * @OA\Schema(
 *     schema="StockOpnameActionResult",
 *     type="object",
 *     required={"id","state"},
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="state", type="string", enum={"DRAFT","SUBMITTED","APPROVED","REJECTED","POSTED"}, example="REJECTED")
 * )
 * @OA\Schema(
 *     schema="StockOpnameActionResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="Stock opname updated successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/StockOpnameActionResult")
 * )
 * @OA\Schema(
 *     schema="StockOpnameHeader",
 *     type="object",
 *     required={"id","opname_date","state","created_by","created_at","updated_at"},
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="opname_date", type="string", example="2026-06-20"),
 *     @OA\Property(property="state", type="string", enum={"DRAFT","SUBMITTED","APPROVED","REJECTED","POSTED"}, example="SUBMITTED"),
 *     @OA\Property(property="created_by", type="integer", example=2),
 *     @OA\Property(property="submitted_by", type="integer", nullable=true, example=2),
 *     @OA\Property(property="approved_by", type="integer", nullable=true, example=1),
 *     @OA\Property(property="rejected_by", type="integer", nullable=true, example=null),
 *     @OA\Property(property="posted_by", type="integer", nullable=true, example=null),
 *     @OA\Property(property="submitted_at", type="string", nullable=true, example="2026-06-20 10:00:00"),
 *     @OA\Property(property="approved_at", type="string", nullable=true, example="2026-06-20 14:00:00"),
 *     @OA\Property(property="rejected_at", type="string", nullable=true, example=null),
 *     @OA\Property(property="posted_at", type="string", nullable=true, example=null),
 *     @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
 *     @OA\Property(property="notes", type="string", nullable=true, example="Cycle count June."),
 *     @OA\Property(property="created_by_name", type="string", nullable=true, example="Gudang User"),
 *     @OA\Property(property="submitted_by_name", type="string", nullable=true, example="Gudang User"),
 *     @OA\Property(property="approved_by_name", type="string", nullable=true, example="Admin User"),
 *     @OA\Property(property="rejected_by_name", type="string", nullable=true, example=null),
 *     @OA\Property(property="posted_by_name", type="string", nullable=true, example=null),
 *     @OA\Property(property="created_at", type="string", example="2026-06-20 08:00:00"),
 *     @OA\Property(property="updated_at", type="string", example="2026-06-20 14:00:00")
 * )
 * @OA\Schema(
 *     schema="StockOpnameCollectionResponse",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/CollectionEnvelope"),
 *         @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/StockOpnameHeader")))
 *     }
 * )
 */
final class StockOpnameSchemas
{
}
