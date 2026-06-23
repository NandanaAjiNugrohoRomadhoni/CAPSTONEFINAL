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
 *     schema="DashboardStockCategorySummary",
 *     type="object",
 *     required={"category","total","active","zero","qty"},
 *     description="Per-category breakdown within the enhanced stock summary.",
 *     @OA\Property(property="category", type="string", example="BASAH"),
 *     @OA\Property(property="total", type="integer", example=20),
 *     @OA\Property(property="active", type="integer", example=18),
 *     @OA\Property(property="zero", type="integer", example=5),
 *     @OA\Property(property="qty", type="number", format="float", example=1200)
 * )
 * @OA\Schema(
 *     schema="DashboardStockToneSummary",
 *     type="object",
 *     required={"safe","warning","critical","danger"},
 *     description="Count of active items per stock-tone level, aligned with frontend getStockTone().",
 *     @OA\Property(property="safe", type="integer", example=80),
 *     @OA\Property(property="warning", type="integer", example=10),
 *     @OA\Property(property="critical", type="integer", example=5),
 *     @OA\Property(property="danger", type="integer", example=3)
 * )
 * @OA\Schema(
 *     schema="DashboardStockSummaryEnhanced",
 *     type="object",
 *     description="Enhanced stock summary with per-category breakdown and tone distribution. Supersedes DashboardStockSummary but shares the same key 'stock_summary' for backward compatibility.",
 *     required={"total_items","active_items","zero_stock_items","total_stock_qty","by_category","tone_summary"},
 *     @OA\Property(property="total_items", type="integer", example=150),
 *     @OA\Property(property="active_items", type="integer", example=140),
 *     @OA\Property(property="zero_stock_items", type="integer", example=20),
 *     @OA\Property(property="total_stock_qty", type="number", format="float", example=95000),
 *     @OA\Property(
 *         property="by_category",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/DashboardStockCategorySummary")
 *     ),
 *     @OA\Property(property="tone_summary", ref="#/components/schemas/DashboardStockToneSummary")
 * )
 * @OA\Schema(
 *     schema="DashboardStockAlertItem",
 *     type="object",
 *     required={"item_id","item_name","category","qty","unit","min_stock","tone"},
 *     description="A single item that is below its minimum stock threshold or has zero quantity.",
 *     @OA\Property(property="item_id", type="integer", example=12),
 *     @OA\Property(property="item_name", type="string", example="Daging Ayam"),
 *     @OA\Property(property="category", type="string", example="BASAH"),
 *     @OA\Property(property="qty", type="number", format="float", example=0),
 *     @OA\Property(property="unit", type="string", example="gram"),
 *     @OA\Property(property="min_stock", type="number", format="float", example=10),
 *     @OA\Property(property="tone", type="string", enum={"danger","critical","warning"}, example="danger")
 * )
 * @OA\Schema(
 *     schema="DashboardStockAlerts",
 *     type="object",
 *     required={"total_critical","total_danger","items"},
 *     description="Aggregated stock alert data surfacing items at or below minimum stock levels.",
 *     @OA\Property(property="total_critical", type="integer", example=3),
 *     @OA\Property(property="total_danger", type="integer", example=5),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/DashboardStockAlertItem")
 *     )
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
 *     schema="DashboardPatientFluctuationPointEnhanced",
 *     type="object",
 *     required={"service_date","total_patients"},
 *     description="Patient fluctuation data point with optional day-over-day delta.",
 *     @OA\Property(property="service_date", type="string", example="2026-05-08"),
 *     @OA\Property(property="total_patients", type="integer", example=130),
 *     @OA\Property(property="delta", type="integer", nullable=true, example=5,
 *         description="Change from previous day. null for the first data point.")
 * )
 * @OA\Schema(
 *     schema="DashboardPatientStats",
 *     type="object",
 *     required={"average","highest","lowest"},
 *     description="Statistical summary of the last 7 days of patient counts.",
 *     @OA\Property(property="average", type="integer", example=128),
 *     @OA\Property(property="highest", type="integer", example=145),
 *     @OA\Property(property="lowest", type="integer", example=110)
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
 *     schema="DashboardMenuShortageItem",
 *     type="object",
 *     required={"item_id","item_name","unit_base","current_stock","required","tone"},
 *     description="A single ingredient that is insufficient to fulfill today's menu for all patients.",
 *     @OA\Property(property="item_id", type="integer", example=5),
 *     @OA\Property(property="item_name", type="string", example="Beras"),
 *     @OA\Property(property="unit_base", type="string", example="gram"),
 *     @OA\Property(property="current_stock", type="number", format="float", example=50000),
 *     @OA\Property(property="required", type="number", format="float", example=65000),
 *     @OA\Property(property="tone", type="string", enum={"safe","warning","critical","danger"}, example="warning")
 * )
 * @OA\Schema(
 *     schema="DashboardCurrentMenuCycleEnhanced",
 *     type="object",
 *     description="Enhanced menu cycle summary including ingredient shortage analytics.",
 *     required={"date","menu_id","menu_name","assignments","total_ingredient_items","total_required_qty","sufficient_items","insufficient_items","top_shortages"},
 *     @OA\Property(property="date", type="string", example="2026-05-08"),
 *     @OA\Property(property="menu_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="menu_name", type="string", nullable=true, example="Paket 1"),
 *     @OA\Property(property="assignments", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="total_ingredient_items", type="integer", example=25),
 *     @OA\Property(property="total_required_qty", type="number", format="float", example=85000),
 *     @OA\Property(property="sufficient_items", type="integer", example=20),
 *     @OA\Property(property="insufficient_items", type="integer", example=5),
 *     @OA\Property(
 *         property="top_shortages",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/DashboardMenuShortageItem")
 *     )
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
 *     schema="DashboardMenuIngredientSummaryItem",
 *     type="object",
 *     required={"item_id","item_name","unit","current_stock","required","deficit","tone"},
 *     description="Per-ingredient line for dapur dashboard: shows required vs current stock with deficit and tone.",
 *     @OA\Property(property="item_id", type="integer", example=5),
 *     @OA\Property(property="item_name", type="string", example="Beras"),
 *     @OA\Property(property="unit", type="string", example="gram"),
 *     @OA\Property(property="current_stock", type="number", format="float", example=150000),
 *     @OA\Property(property="required", type="number", format="float", example=65000),
 *     @OA\Property(property="deficit", type="number", format="float", example=0,
 *         description="max(0, required - current_stock). 0 means sufficient."),
 *     @OA\Property(property="tone", type="string", enum={"safe","warning","critical","danger"}, example="safe")
 * )
 * @OA\Schema(
 *     schema="DashboardSpkSummaryItem",
 *     type="object",
 *     required={"item_id","item_name","recommended_qty","unit"},
 *     description="Top recommended item from an SPK calculation.",
 *     @OA\Property(property="item_id", type="integer", example=1),
 *     @OA\Property(property="item_name", type="string", example="Daging Ayam"),
 *     @OA\Property(property="recommended_qty", type="number", format="float", example=12.5),
 *     @OA\Property(property="unit", type="string", example="gram")
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
 *     schema="DashboardSpkHistoryEntryEnhanced",
 *     type="object",
 *     nullable=true,
 *     description="SPK history entry enhanced with top-3 recommended items.",
 *     required={"id","version","calculation_date","target_date_start","target_date_end","target_month","created_at","summary_items"},
 *     @OA\Property(property="id", type="integer", example=12),
 *     @OA\Property(property="version", type="integer", example=1),
 *     @OA\Property(property="calculation_date", type="string", nullable=true, example="2026-05-08"),
 *     @OA\Property(property="target_date_start", type="string", nullable=true, example="2026-05-08"),
 *     @OA\Property(property="target_date_end", type="string", nullable=true, example="2026-05-10"),
 *     @OA\Property(property="target_month", type="string", nullable=true, example="2026-05"),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-05-08 09:30:00"),
 *     @OA\Property(
 *         property="summary_items",
 *         type="array",
 *         description="Up to 3 top recommended items by quantity descending.",
 *         @OA\Items(ref="#/components/schemas/DashboardSpkSummaryItem")
 *     )
 * )
 * @OA\Schema(
 *     schema="DashboardLatestSpkHistory",
 *     type="object",
 *     required={"basah","kering_pengemas"},
 *     @OA\Property(property="basah", ref="#/components/schemas/DashboardSpkHistoryEntry"),
 *     @OA\Property(property="kering_pengemas", ref="#/components/schemas/DashboardSpkHistoryEntry")
 * )
 * @OA\Schema(
 *     schema="DashboardTodayOutgoingItem",
 *     type="object",
 *     required={"item_id","item_name","qty","unit","remaining_stock","tone"},
 *     description="An item disbursed today, with post-disbursement remaining stock and tone.",
 *     @OA\Property(property="item_id", type="integer", example=5),
 *     @OA\Property(property="item_name", type="string", example="Beras"),
 *     @OA\Property(property="qty", type="number", format="float", example=30000),
 *     @OA\Property(property="unit", type="string", example="gram"),
 *     @OA\Property(property="remaining_stock", type="number", format="float", example=180000),
 *     @OA\Property(property="tone", type="string", enum={"safe","warning","critical","danger"}, example="safe")
 * )
 * @OA\Schema(
 *     schema="DashboardTodayOutgoing",
 *     type="object",
 *     required={"total_items","total_qty","recent"},
 *     description="Summary of stock disbursed today (gudang role).",
 *     @OA\Property(property="total_items", type="integer", example=8),
 *     @OA\Property(property="total_qty", type="number", format="float", example=95000),
 *     @OA\Property(
 *         property="recent",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/DashboardTodayOutgoingItem")
 *     )
 * )
 * @OA\Schema(
 *     schema="DashboardPendingActions",
 *     type="object",
 *     required={"total","unread_notifications"},
 *     description="Role-specific pending action counts. Keys other than 'total' and 'unread_notifications' depend on the role: admin gets stock_opnames_pending_approval, transaction_revisions_pending_approval, spks_ready_to_post; gudang gets stock_opnames_pending_submit, spks_ready_to_post; dapur gets spks_ready_to_generate.",
 *     additionalProperties=true,
 *     @OA\Property(property="total", type="integer", example=5,
 *         description="Sum of all role-specific pending counts."),
 *     @OA\Property(property="unread_notifications", type="integer", example=2),
 *     @OA\Property(property="stock_opnames_pending_approval", type="integer", example=1,
 *         description="Admin only."),
 *     @OA\Property(property="transaction_revisions_pending_approval", type="integer", example=0,
 *         description="Admin only."),
 *     @OA\Property(property="spks_ready_to_post", type="integer", example=1,
 *         description="Admin and gudang."),
 *     @OA\Property(property="stock_opnames_pending_submit", type="integer", example=1,
 *         description="Gudang only."),
 *     @OA\Property(property="spks_ready_to_generate", type="integer", example=1,
 *         description="Dapur only.")
 * )
 * @OA\Schema(
 *     schema="DashboardAggregates",
 *     type="object",
 *     description="Aggregate keys are role-conditioned. admin receives: stock_summary (enhanced), dry_stock_status, stock_alerts, spending_trend, current_menu_cycle (enhanced), latest_spk_history (enhanced), patient_fluctuation (enhanced), patient_fluctuation_meta, pending_actions. gudang receives: stock_summary, dry_stock_status, stock_alerts, spending_trend, latest_spk_history, patient_fluctuation, patient_fluctuation_meta, today_outgoing, pending_actions. dapur receives: current_menu_cycle, current_menu_composition, menu_ingredient_summary, latest_spk_history, stock_summary, dry_stock_status, pending_actions. additionalProperties=true so the schema stays conservative as the composition evolves.",
 *     additionalProperties=true,
 *     @OA\Property(property="stock_summary", ref="#/components/schemas/DashboardStockSummaryEnhanced"),
 *     @OA\Property(property="dry_stock_status", ref="#/components/schemas/DashboardDryStockStatus"),
 *     @OA\Property(property="stock_alerts", ref="#/components/schemas/DashboardStockAlerts"),
 *     @OA\Property(property="spending_trend", type="array", @OA\Items(ref="#/components/schemas/DashboardSpendingTrendPoint")),
 *     @OA\Property(property="current_menu_cycle", ref="#/components/schemas/DashboardCurrentMenuCycleEnhanced"),
 *     @OA\Property(property="current_menu_composition", type="array", @OA\Items(ref="#/components/schemas/DashboardCurrentMenuCompositionItem")),
 *     @OA\Property(property="menu_ingredient_summary", type="array", @OA\Items(ref="#/components/schemas/DashboardMenuIngredientSummaryItem")),
 *     @OA\Property(property="latest_spk_history", ref="#/components/schemas/DashboardLatestSpkHistory"),
 *     @OA\Property(property="patient_fluctuation", type="array", @OA\Items(ref="#/components/schemas/DashboardPatientFluctuationPointEnhanced")),
 *     @OA\Property(property="patient_fluctuation_meta", ref="#/components/schemas/DashboardPatientStats"),
 *     @OA\Property(property="today_outgoing", ref="#/components/schemas/DashboardTodayOutgoing"),
 *     @OA\Property(property="pending_actions", ref="#/components/schemas/DashboardPendingActions"),
 *     example={
 *         "stock_summary": {"total_items": 150, "active_items": 140, "zero_stock_items": 20, "total_stock_qty": 95000, "by_category": {}, "tone_summary": {"safe": 100, "warning": 20, "critical": 10, "danger": 10}},
 *         "dry_stock_status": {"status": "KRITIS", "total_items": 2, "zero_stock_items": 1, "total_stock_qty": 5000},
 *         "latest_spk_history": {
 *             "basah": {"id": 1, "version": 1, "calculation_date": "2026-05-07", "target_date_start": "2026-05-08", "target_date_end": "2026-05-09", "target_month": null, "created_at": "2026-05-07 10:00:00", "summary_items": {}},
 *             "kering_pengemas": {"id": 2, "version": 1, "calculation_date": "2026-05-08", "target_date_start": "2026-05-01", "target_date_end": "2026-05-31", "target_month": "2026-05", "created_at": "2026-05-08 10:00:00", "summary_items": {}}
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
