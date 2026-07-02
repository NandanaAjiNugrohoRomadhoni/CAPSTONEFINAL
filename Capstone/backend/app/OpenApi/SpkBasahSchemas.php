<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="SpkRecommendationOverrideRequest",
 *     type="object",
 *     required={"recommendation_id","recommended_qty","reason"},
 *     @OA\Property(property="recommendation_id", type="integer", minimum=1, example=44),
 *     @OA\Property(property="recommended_qty", type="number", format="float", minimum=0, example=77.25),
 *     @OA\Property(property="reason", type="string", example="Manual safety buffer before finalize.")
 * )
 * @OA\Schema(
 *     schema="MenuCalendarProjectionEntry",
 *     type="object",
 *     required={"date","day_of_month","menu_id","menu_name"},
 *     @OA\Property(property="date", type="string", example="2026-03-12"),
 *     @OA\Property(property="day_of_month", type="integer", example=12),
 *     @OA\Property(property="menu_id", type="integer", example=2),
 *     @OA\Property(property="menu_name", type="string", example="Paket 2")
 * )
 * @OA\Schema(
 *     schema="MenuCalendarProjectionDateResponse",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/MenuCalendarProjectionEntry")
 * )
 * @OA\Schema(
 *     schema="MenuCalendarProjectionMonthMeta",
 *     type="object",
 *     required={"month","total"},
 *     @OA\Property(property="month", type="string", example="2026-03"),
 *     @OA\Property(property="total", type="integer", example=31)
 * )
 * @OA\Schema(
 *     schema="MenuCalendarProjectionMonthResponse",
 *     type="object",
 *     required={"data","meta"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MenuCalendarProjectionEntry")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MenuCalendarProjectionMonthMeta")
 * )
 * @OA\Schema(
 *     schema="MenuCalendarProjectionRangeMeta",
 *     type="object",
 *     required={"start_date","end_date","total"},
 *     @OA\Property(property="start_date", type="string", example="2026-03-12"),
 *     @OA\Property(property="end_date", type="string", example="2026-03-15"),
 *     @OA\Property(property="total", type="integer", example=4)
 * )
 * @OA\Schema(
 *     schema="MenuCalendarProjectionRangeResponse",
 *     type="object",
 *     required={"data","meta"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MenuCalendarProjectionEntry")),
 *     @OA\Property(property="meta", ref="#/components/schemas/MenuCalendarProjectionRangeMeta")
 * )
 * @OA\Schema(
 *     schema="MenuCalendarProjectionResponse",
 *     type="object",
 *     description="Conservative shared menu-calendar wrapper used by the SPK basah projection endpoint. data may be a single entry or an array depending on resolver mode; meta is present for month and range modes.",
 *     @OA\Property(property="data", description="Single projection entry for date mode, or an array of projection entries for month and range modes."),
 *     @OA\Property(property="meta", type="object", nullable=true, additionalProperties=true)
 * )
 * @OA\Schema(
 *     schema="OperationalStockPreviewMenu",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Paket 1")
 * )
 * @OA\Schema(
 *     schema="OperationalStockPreviewItem",
 *     type="object",
 *     required={"item_id","item_name","current_stock_qty","required_qty","projected_stock_out_qty","projected_remaining_stock_qty","projected_shortage_qty"},
 *     @OA\Property(property="item_id", type="integer", example=1),
 *     @OA\Property(property="item_name", type="string", example="Ayam Basah"),
 *     @OA\Property(property="item_unit_base", type="string", nullable=true, example="gram"),
 *     @OA\Property(property="item_unit_convert", type="string", nullable=true, example="kg"),
 *     @OA\Property(property="current_stock_qty", type="number", format="float", example=100),
 *     @OA\Property(property="required_qty", type="number", format="float", example=200),
 *     @OA\Property(property="projected_stock_out_qty", type="number", format="float", example=200),
 *     @OA\Property(property="projected_remaining_stock_qty", type="number", format="float", example=0),
 *     @OA\Property(property="projected_shortage_qty", type="number", format="float", example=100)
 * )
 * @OA\Schema(
 *     schema="OperationalStockPreviewSummary",
 *     type="object",
 *     required={"total_items","total_required_qty","total_projected_stock_out_qty","total_projected_shortage_qty"},
 *     @OA\Property(property="total_items", type="integer", example=1),
 *     @OA\Property(property="total_required_qty", type="number", format="float", example=200),
 *     @OA\Property(property="total_projected_stock_out_qty", type="number", format="float", example=200),
 *     @OA\Property(property="total_projected_shortage_qty", type="number", format="float", example=100)
 * )
 * @OA\Schema(
 *     schema="OperationalStockPreviewData",
 *     type="object",
 *     required={"service_date","meal_time","total_patients","menu","items","summary"},
 *     @OA\Property(property="service_date", type="string", example="2026-03-01"),
 *     @OA\Property(property="meal_time", type="string", example="SIANG"),
 *     @OA\Property(property="total_patients", type="integer", example=100),
 *     @OA\Property(property="menu", ref="#/components/schemas/OperationalStockPreviewMenu"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/OperationalStockPreviewItem")),
 *     @OA\Property(property="summary", ref="#/components/schemas/OperationalStockPreviewSummary")
 * )
 * @OA\Schema(
 *     schema="OperationalStockPreviewResponse",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/OperationalStockPreviewData")
 * )
 * @OA\Schema(
 *     schema="OperationalStockPreviewRequest",
 *     type="object",
 *     required={"service_date","meal_time","total_patients"},
 *     @OA\Property(property="service_date", type="string", example="2026-03-01"),
 *     @OA\Property(property="meal_time", type="string", example="SIANG"),
 *     @OA\Property(property="total_patients", type="integer", minimum=0, example=100)
 * )
 * @OA\Schema(
 *     schema="SpkHistoryUserSummary",
 *     type="object",
 *     required={"id","name","username"},
 *     @OA\Property(property="id", type="integer", nullable=true, example=2),
 *     @OA\Property(property="name", type="string", nullable=true, example="Dapur User"),
 *     @OA\Property(property="username", type="string", nullable=true, example="dapur")
 * )
 * @OA\Schema(
 *     schema="SpkHistoryCategorySummary",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="name", type="string", nullable=true, example="BASAH")
 * )
 * @OA\Schema(
 *     schema="SpkRecommendationOverrideState",
 *     type="object",
 *     required={"is_overridden","reason","overridden_by","overridden_at"},
 *     @OA\Property(property="is_overridden", type="boolean", example=true),
 *     @OA\Property(property="reason", type="string", nullable=true, example="Manual safety buffer before finalize."),
 *     @OA\Property(property="overridden_by", type="integer", nullable=true, example=2),
 *     @OA\Property(property="overridden_at", type="string", nullable=true, example="2026-03-01 09:30:00")
 * )
 * @OA\Schema(
 *     schema="SpkBasahHistoryEntry",
 *     type="object",
 *     required={"id","version","scope_key","is_latest","calculation_scope","calculation_date","target_date_start","target_date_end","target_month","estimated_patients","is_finish","created_at","user","category"},
 *     @OA\Property(property="id", type="integer", example=31),
 *     @OA\Property(property="version", type="integer", example=2),
 *     @OA\Property(property="scope_key", type="string", example="basah|combined_window|2026-03-01|1"),
 *     @OA\Property(property="is_latest", type="boolean", example=true),
 *     @OA\Property(property="calculation_scope", type="string", example="combined_window"),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-03-01"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-03-02"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-03-03"),
 *     @OA\Property(property="target_month", type="string", nullable=true, example=null),
 *     @OA\Property(property="estimated_patients", type="integer", example=105),
 *     @OA\Property(property="is_finish", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-03-01 08:00:00"),
 *     @OA\Property(property="user", ref="#/components/schemas/SpkHistoryUserSummary"),
 *     @OA\Property(property="category", ref="#/components/schemas/SpkHistoryCategorySummary")
 * )
 * @OA\Schema(
 *     schema="SpkBasahHistoryMeta",
 *     type="object",
 *     required={"total"},
 *     @OA\Property(property="total", type="integer", example=2)
 * )
 * @OA\Schema(
 *     schema="SpkBasahHistoryCollectionResponse",
 *     type="object",
 *     required={"data","meta"},
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/SpkBasahHistoryEntry")),
 *     @OA\Property(property="meta", ref="#/components/schemas/SpkBasahHistoryMeta")
 * )
 * @OA\Schema(
 *     schema="SpkBasahRecommendationItem",
 *     type="object",
 *     required={"id","item_id","item_name","item_unit_base","item_unit_convert","target_date","current_stock_qty","required_qty","system_recommended_qty","final_recommended_qty","override"},
 *     @OA\Property(property="id", type="integer", example=44),
 *     @OA\Property(property="item_id", type="integer", example=1),
 *     @OA\Property(property="item_name", type="string", nullable=true, example="Ayam Basah"),
 *     @OA\Property(property="item_unit_base", type="string", nullable=true, example="gram"),
 *     @OA\Property(property="item_unit_convert", type="string", nullable=true, example="kg"),
 *     @OA\Property(property="target_date", type="string", nullable=true, example="2026-03-02"),
 *     @OA\Property(property="current_stock_qty", type="number", format="float", example=100),
 *     @OA\Property(property="required_qty", type="number", format="float", example=210),
 *     @OA\Property(property="system_recommended_qty", type="number", format="float", example=110),
 *     @OA\Property(property="final_recommended_qty", type="number", format="float", example=123.45),
 *     @OA\Property(property="override", ref="#/components/schemas/SpkRecommendationOverrideState")
 * )
 * @OA\Schema(
 *     schema="SpkBasahPrintReady",
 *     type="object",
 *     required={"spk_id","spk_type","version","calculation_date","target_date_start","target_date_end","target_dates","estimated_patients","category_name","generated_by","recommendations"},
 *     @OA\Property(property="spk_id", type="integer", example=31),
 *     @OA\Property(property="spk_type", type="string", example="basah"),
 *     @OA\Property(property="version", type="integer", example=2),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-03-01"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-03-02"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-03-03"),
 *     @OA\Property(property="target_dates", type="array", @OA\Items(type="string", example="2026-03-02")),
 *     @OA\Property(property="estimated_patients", type="integer", example=105),
 *     @OA\Property(property="category_name", type="string", nullable=true, example="BASAH"),
 *     @OA\Property(property="generated_by", type="string", nullable=true, example="Dapur User"),
 *     @OA\Property(property="recommendations", type="array", @OA\Items(ref="#/components/schemas/SpkBasahRecommendationItem"))
 * )
 * @OA\Schema(
 *     schema="SpkBasahHistoryDetail",
 *     type="object",
 *     required={"id","version","scope_key","is_latest","spk_type","calculation_scope","calculation_date","target_date_start","target_date_end","target_month","estimated_patients","is_finish","created_at","updated_at","user","category","items","print_ready"},
 *     @OA\Property(property="id", type="integer", example=31),
 *     @OA\Property(property="version", type="integer", example=2),
 *     @OA\Property(property="scope_key", type="string", example="basah|combined_window|2026-03-01|1"),
 *     @OA\Property(property="is_latest", type="boolean", example=true),
 *     @OA\Property(property="spk_type", type="string", example="basah"),
 *     @OA\Property(property="calculation_scope", type="string", example="combined_window"),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-03-01"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-03-02"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-03-03"),
 *     @OA\Property(property="target_month", type="string", nullable=true, example=null),
 *     @OA\Property(property="estimated_patients", type="integer", example=105),
 *     @OA\Property(property="is_finish", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-03-01 08:00:00"),
 *     @OA\Property(property="updated_at", type="string", nullable=true, example="2026-03-01 08:00:00"),
 *     @OA\Property(property="user", ref="#/components/schemas/SpkHistoryUserSummary"),
 *     @OA\Property(property="category", ref="#/components/schemas/SpkHistoryCategorySummary"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/SpkBasahRecommendationItem")),
 *     @OA\Property(property="print_ready", ref="#/components/schemas/SpkBasahPrintReady")
 * )
 * @OA\Schema(
 *     schema="SpkBasahShowResponse",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/SpkBasahHistoryDetail")
 * )
 * @OA\Schema(
 *     schema="SpkBasahGenerateResult",
 *     type="object",
 *     required={"id","version","scope_key","target_dates","estimated_patients"},
 *     @OA\Property(property="id", type="integer", example=31),
 *     @OA\Property(property="version", type="integer", example=2),
 *     @OA\Property(property="scope_key", type="string", example="basah|combined_window|2026-03-01|1"),
 *     @OA\Property(property="target_dates", type="array", @OA\Items(type="string", example="2026-03-02")),
 *     @OA\Property(property="estimated_patients", type="integer", example=105)
 * )
 * @OA\Schema(
 *     schema="SpkBasahGenerateResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="SPK basah generated successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/SpkBasahGenerateResult")
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasGenerateResult",
 *     type="object",
 *     required={"id","version","scope_key","target_month"},
 *     @OA\Property(property="id", type="integer", example=44),
 *     @OA\Property(property="version", type="integer", example=3),
 *     @OA\Property(property="scope_key", type="string", example="kering_pengemas|monthly|2026-04|2"),
 *     @OA\Property(property="target_month", type="string", example="2026-04")
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasGenerateResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="SPK kering/pengemas generated successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/SpkKeringPengemasGenerateResult")
 * )
 * @OA\Schema(
 *     schema="SpkGenerateConflictData",
 *     type="object",
 *     required={"spk_id","version","scope_key","is_latest","is_finish","regenerate_allowed"},
 *     @OA\Property(property="spk_id", type="integer", example=31),
 *     @OA\Property(property="version", type="integer", example=2),
 *     @OA\Property(property="scope_key", type="string", example="basah|combined_window|2026-03-01|1"),
 *     @OA\Property(property="is_latest", type="boolean", example=true),
 *     @OA\Property(property="is_finish", type="boolean", example=false),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-03-01"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-03-02"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-03-03"),
 *     @OA\Property(property="target_month", type="string", nullable=true, example=null),
 *     @OA\Property(property="regenerate_allowed", type="boolean", example=true)
 * )
 * @OA\Schema(
 *     schema="SpkGenerateConflictResponse",
 *     type="object",
 *     required={"message","errors","conflict"},
 *     @OA\Property(property="message", type="string", example="SPK generation conflict."),
 *     @OA\Property(property="errors", ref="#/components/schemas/ValidationError"),
 *     @OA\Property(property="conflict", ref="#/components/schemas/SpkGenerateConflictData")
 * )
 * @OA\Schema(
 *     schema="SpkBasahGenerateRequest",
 *     type="object",
 *     required={"service_date"},
 *     @OA\Property(property="service_date", type="string", example="2026-03-01"),
 *     @OA\Property(property="regenerate", type="boolean", example=false, description="When true, runtime creates a new version even if an active SPK already exists for the same scope.")
 * )
 * @OA\Schema(
 *     schema="SpkKeringPengemasGenerateRequest",
 *     type="object",
 *     required={"target_month"},
 *     @OA\Property(property="target_month", type="string", example="2026-04"),
 *     @OA\Property(property="regenerate", type="boolean", example=false, description="When true, runtime creates a new version even if an active SPK already exists for the same scope.")
 * )
 * @OA\Schema(
 *     schema="SpkBasahPostStockResult",
 *     type="object",
 *     required={"id","version","is_finish","posted_transaction_id"},
 *     @OA\Property(property="id", type="integer", example=31),
 *     @OA\Property(property="version", type="integer", example=2),
 *     @OA\Property(property="is_finish", type="boolean", example=true),
 *     @OA\Property(property="posted_transaction_id", type="integer", example=88)
 * )
 * @OA\Schema(
 *     schema="SpkBasahPostStockResponse",
 *     type="object",
 *     required={"message","data"},
 *     @OA\Property(property="message", type="string", example="SPK posted to stock transaction successfully."),
 *     @OA\Property(property="data", ref="#/components/schemas/SpkBasahPostStockResult")
 * )
 * @OA\Schema(
 *     schema="SpkRecommendationOverrideMutationResult",
 *     type="object",
 *     required={"spk_id","recommendation_id","system_recommended_qty","recommended_qty","override"},
 *     @OA\Property(property="spk_id", type="integer", example=31),
 *     @OA\Property(property="recommendation_id", type="integer", example=44),
 *     @OA\Property(property="system_recommended_qty", type="number", format="float", example=110),
 *     @OA\Property(property="recommended_qty", type="number", format="float", example=123.45),
 *     @OA\Property(property="override", ref="#/components/schemas/SpkRecommendationOverrideState")
 * )
 * @OA\Schema(
 *     schema="SpkRecommendationOverrideResponse",
 *     type="object",
 *     required={"message","errors","data"},
 *     @OA\Property(property="message", type="string", example="SPK recommendation item overridden successfully."),
 *     @OA\Property(property="errors", description="Validation error map on failures; empty array on success."),
 *     @OA\Property(property="data", ref="#/components/schemas/SpkRecommendationOverrideMutationResult", nullable=true)
 * )
 */
final class SpkBasahSchemas
{
}
