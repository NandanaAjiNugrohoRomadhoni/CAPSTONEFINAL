<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="DashboardStockSummary",
 *     type="object",
 *     required={"total_items","active_items","zero_stock_items","total_stock_qty"},
 *     @OA\Property(property="total_items", type="integer", example=3),
 *     @OA\Property(property="active_items", type="integer", example=3),
 *     @OA\Property(property="zero_stock_items", type="integer", example=1),
 *     @OA\Property(property="total_stock_qty", type="number", format="float", example=7500)
 * )
 * @OA\Schema(
 *     schema="DashboardDryStockStatus",
 *     type="object",
 *     required={"status","total_items","zero_stock_items","total_stock_qty"},
 *     @OA\Property(property="status", type="string", enum={"AMAN","KRITIS"}, example="KRITIS"),
 *     @OA\Property(property="total_items", type="integer", example=2),
 *     @OA\Property(property="zero_stock_items", type="integer", example=1),
 *     @OA\Property(property="total_stock_qty", type="number", format="float", example=5000)
 * )
 * @OA\Schema(
 *     schema="DashboardSpendingTrendPoint",
 *     type="object",
 *     required={"date","total_out_qty"},
 *     @OA\Property(property="date", type="string", example="2026-05-08"),
 *     @OA\Property(property="total_out_qty", type="number", format="float", example=65)
 * )
 * @OA\Schema(
 *     schema="DashboardPatientFluctuationPoint",
 *     type="object",
 *     required={"service_date","total_patients"},
 *     @OA\Property(property="service_date", type="string", example="2026-05-08"),
 *     @OA\Property(property="total_patients", type="integer", example=130)
 * )
 * @OA\Schema(
 *     schema="DashboardCurrentMenuCycle",
 *     type="object",
 *     required={"date","menu_id","menu_name"},
 *     @OA\Property(property="date", type="string", example="2026-05-08"),
 *     @OA\Property(property="menu_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="menu_name", type="string", nullable=true, example="Paket 1")
 * )
 * @OA\Schema(
 *     schema="DashboardCurrentMenuCompositionItem",
 *     type="object",
 *     required={"meal_time","dish_id","dish_name","item_id","item_name","qty_per_patient"},
 *     @OA\Property(property="meal_time", type="string", example="PAGI"),
 *     @OA\Property(property="dish_id", type="integer", example=1),
 *     @OA\Property(property="dish_name", type="string", example="Nasi Pagi"),
 *     @OA\Property(property="item_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="item_name", type="string", nullable=true, example="Beras"),
 *     @OA\Property(property="qty_per_patient", type="number", format="float", nullable=true, example=100)
 * )
 * @OA\Schema(
 *     schema="DashboardSpkHistoryEntry",
 *     type="object",
 *     nullable=true,
 *     required={"id","version","calculation_date","target_date_start","target_date_end","target_month","created_at"},
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="version", type="integer", example=1),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-05-08"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-05-08"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-05-10"),
 *     @OA\Property(property="target_month", type="string", nullable=true, example="2026-05"),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-05-08 09:30:00")
 * )
 * @OA\Schema(
 *     schema="DashboardLatestSpkHistory",
 *     type="object",
 *     required={"basah","kering_pengemas"},
 *     @OA\Property(property="basah", ref="#/components/schemas/DashboardSpkHistoryEntry"),
 *     @OA\Property(property="kering_pengemas", ref="#/components/schemas/DashboardSpkHistoryEntry")
 * )
 * @OA\Schema(
 *     schema="DashboardAggregates",
 *     type="object",
 *     description="Aggregate keys are role-conditioned. admin receives stock_summary, dry_stock_status, spending_trend, current_menu_cycle, latest_spk_history, and patient_fluctuation. gudang receives stock_summary, dry_stock_status, spending_trend, latest_spk_history, and patient_fluctuation. dapur receives current_menu_cycle, current_menu_composition, latest_spk_history, stock_summary, and dry_stock_status. Additional properties remain allowed so the schema stays conservative if the runtime aggregate composition evolves.",
 *     @OA\Property(property="stock_summary", ref="#/components/schemas/DashboardStockSummary"),
 *     @OA\Property(property="dry_stock_status", ref="#/components/schemas/DashboardDryStockStatus"),
 *     @OA\Property(property="spending_trend", type="array", @OA\Items(ref="#/components/schemas/DashboardSpendingTrendPoint")),
 *     @OA\Property(property="current_menu_cycle", ref="#/components/schemas/DashboardCurrentMenuCycle"),
 *     @OA\Property(property="current_menu_composition", type="array", @OA\Items(ref="#/components/schemas/DashboardCurrentMenuCompositionItem")),
 *     @OA\Property(property="latest_spk_history", ref="#/components/schemas/DashboardLatestSpkHistory"),
 *     @OA\Property(property="patient_fluctuation", type="array", @OA\Items(ref="#/components/schemas/DashboardPatientFluctuationPoint")),
 *     additionalProperties=true,
 *     example={
 *         "stock_summary": {"total_items": 3, "active_items": 3, "zero_stock_items": 1, "total_stock_qty": 7500},
 *         "dry_stock_status": {"status": "KRITIS", "total_items": 2, "zero_stock_items": 1, "total_stock_qty": 5000},
 *         "latest_spk_history": {
 *             "basah": {"id": 1, "version": 1, "calculation_date": "2026-05-07", "target_date_start": "2026-05-08", "target_date_end": "2026-05-09", "target_month": null, "created_at": "2026-05-07 10:00:00"},
 *             "kering_pengemas": {"id": 2, "version": 1, "calculation_date": "2026-05-08", "target_date_start": "2026-05-01", "target_date_end": "2026-05-31", "target_month": "2026-05", "created_at": "2026-05-08 10:00:00"}
 *         }
 *     }
 * )
 * @OA\Schema(
 *     schema="DashboardAggregateData",
 *     type="object",
 *     required={"role","generated_at","aggregates"},
 *     @OA\Property(property="role", type="string", enum={"admin","dapur","gudang"}, example="admin"),
 *     @OA\Property(property="generated_at", type="string", example="2026-05-08 10:30:00"),
 *     @OA\Property(property="aggregates", ref="#/components/schemas/DashboardAggregates")
 * )
 * @OA\Schema(
 *     schema="DashboardResponse",
 *     type="object",
 *     required={"data"},
 *     @OA\Property(property="data", ref="#/components/schemas/DashboardAggregateData")
 * )
 */
final class DashboardSchemas
{
}
