<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="SpkKeringPengemasHistoryEntry",
 *     type="object",
 *     required={"id","version","scope_key","is_latest","calculation_scope","calculation_date","target_date_start","target_date_end","target_month","estimated_patients","is_finish","created_at","user","category"},
 *     @OA\Property(property="id", type="integer", example=44),
 *     @OA\Property(property="version", type="integer", example=3),
 *     @OA\Property(property="scope_key", type="string", example="kering_pengemas|monthly|2026-04|2"),
 *     @OA\Property(property="is_latest", type="boolean", example=true),
 *     @OA\Property(property="calculation_scope", type="string", example="monthly"),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-04-01"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-04-01"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-04-30"),
 *     @OA\Property(property="target_month", type="string", example="2026-04"),
 *     @OA\Property(property="estimated_patients", type="integer", example=78),
 *     @OA\Property(property="is_finish", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-04-01 08:00:00"),
 *     @OA\Property(property="user", ref="#/components/schemas/SpkHistoryUserSummary"),
 *     @OA\Property(property="category", ref="#/components/schemas/SpkHistoryCategorySummary")
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasHistoryMeta",
 *     type="object",
 *     required={"total"},
 *     @OA\Property(property="total", type="integer", example=2)
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasHistoryCollectionResponse",
 *     type="object",
 *     required={"data","meta"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SpkKeringPengemasHistoryEntry")),
 *     @OA\Property(property="meta", ref="#/components/schemas/SpkKeringPengemasHistoryMeta")
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasRecommendationItem",
 *     type="object",
 *     required={"id","item_id","item_name","item_unit_base","item_unit_convert","target_date","current_stock_qty","required_qty","system_recommended_qty","final_recommended_qty","override"},
 *     @OA\Property(property="id", type="integer", example=44),
 *     @OA\Property(property="item_id", type="integer", example=1),
 *     @OA\Property(property="item_name", type="string", nullable=true, example="Ayam Kering"),
 *     @OA\Property(property="item_unit_base", type="string", nullable=true, example="gram"),
 *     @OA\Property(property="item_unit_convert", type="string", nullable=true, example="kg"),
 *     @OA\Property(property="target_date", type="string", nullable=true, example=null),
 *     @OA\Property(property="current_stock_qty", type="number", format="float", example=100),
 *     @OA\Property(property="required_qty", type="number", format="float", example=210),
 *     @OA\Property(property="system_recommended_qty", type="number", format="float", example=110),
 *     @OA\Property(property="final_recommended_qty", type="number", format="float", example=123.45),
 *     @OA\Property(property="override", ref="#/components/schemas/SpkRecommendationOverrideState")
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasPrintReady",
 *     type="object",
 *     required={"spk_id","spk_type","version","calculation_date","target_date_start","target_date_end","target_month","estimated_patients","category_name","generated_by","recommendations"},
 *     @OA\Property(property="spk_id", type="integer", example=44),
 *     @OA\Property(property="spk_type", type="string", example="kering_pengemas"),
 *     @OA\Property(property="version", type="integer", example=3),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-04-01"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-04-01"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-04-30"),
 *     @OA\Property(property="target_month", type="string", example="2026-04"),
 *     @OA\Property(property="estimated_patients", type="integer", example=78),
 *     @OA\Property(property="category_name", type="string", nullable=true, example="KERING_PENGEMAS"),
 *     @OA\Property(property="generated_by", type="string", nullable=true, example="Dapur User"),
 *     @OA\Property(property="recommendations", type="array", @OA\Items(ref="#/components/schemas/SpkKeringPengemasRecommendationItem"))
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasHistoryDetail",
 *     type="object",
 *     required={"id","version","scope_key","is_latest","spk_type","calculation_scope","calculation_date","target_date_start","target_date_end","target_month","estimated_patients","is_finish","created_at","updated_at","user","category","items","print_ready"},
 *     @OA\Property(property="id", type="integer", example=44),
 *     @OA\Property(property="version", type="integer", example=3),
 *     @OA\Property(property="scope_key", type="string", example="kering_pengemas|monthly|2026-04|2"),
 *     @OA\Property(property="is_latest", type="boolean", example=true),
 *     @OA\Property(property="spk_type", type="string", example="kering_pengemas"),
 *     @OA\Property(property="calculation_scope", type="string", example="monthly"),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-04-01"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-04-01"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-04-30"),
 *     @OA\Property(property="target_month", type="string", example="2026-04"),
 *     @OA\Property(property="estimated_patients", type="integer", example=78),
 *     @OA\Property(property="is_finish", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-04-01 08:00:00"),
 *     @OA\Property(property="updated_at", type="string", nullable=true, example="2026-04-01 08:00:00"),
 *     @OA\Property(property="user", ref="#/components/schemas/SpkHistoryUserSummary"),
 *     @OA\Property(property="category", ref="#/components/schemas/SpkHistoryCategorySummary"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/SpkKeringPengemasRecommendationItem")),
 *     @OA\Property(property="print_ready", ref="#/components/schemas/SpkKeringPengemasPrintReady")
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasShowResponse",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/SpkKeringPengemasHistoryDetail")
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasPostStockResult",
 *     type="object",
 *     required={"id","version","is_finish","posted_transaction_id"},
 *     @OA\Property(property="id", type="integer", example=44),
 *     @OA\Property(property="version", type="integer", example=3),
 *     @OA\Property(property="is_finish", type="boolean", example=true),
 *     @OA\Property(property="posted_transaction_id", type="integer", example=88)
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasPostStockResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="SPK posted to stock transaction successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/SpkKeringPengemasPostStockResult")
 * )
 */
final class SpkKeringPengemasSchemas
{
}
