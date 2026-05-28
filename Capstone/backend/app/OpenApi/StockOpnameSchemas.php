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
 */
final class StockOpnameSchemas
{
}
