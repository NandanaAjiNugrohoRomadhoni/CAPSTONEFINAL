/** Role-shaped aggregate block returned by `GET /api/v1/dashboard` (api-contract.md §5.8). */

export interface StockCategorySummary {
  category: string;
  total: number;
  active: number;
  zero: number;
  qty: number;
}

export interface StockToneSummary {
  safe: number;
  warning: number;
  critical: number;
  danger: number;
}

export interface StockAlertItem {
  item_id: number;
  item_name: string;
  category: string;
  qty: number;
  unit: string;
  min_stock: number;
  tone: 'safe' | 'warning' | 'critical' | 'danger';
}

export interface StockSummaryEnhanced {
  total_items: number;
  active_items: number;
  zero_stock_items: number;
  total_stock_qty: number;
  by_category: StockCategorySummary[];
  tone_summary: StockToneSummary;
}

export interface PatientFluctuationPoint {
  service_date: string;
  total_patients: number;
  delta: number | null;
}

export interface PatientStats {
  average: number;
  highest: number;
  lowest: number;
}

export interface SpkSummaryItem {
  item_id: number;
  item_name: string;
  recommended_qty: number;
  unit: string;
}

export interface SpkHistoryEntryEnhanced {
  id: number;
  version: number;
  calculation_date: string | null;
  target_date_start: string | null;
  target_date_end: string | null;
  target_month: string | null;
  created_at: string | null;
  summary_items?: SpkSummaryItem[];
}

export interface MenuShortageItem {
  item_id: number;
  item_name: string;
  unit_base: string;
  current_stock: number;
  required: number;
  tone: string;
}

export interface CurrentMenuCycleEnhanced {
  date: string;
  menu_id: number | null;
  menu_name: string | null;
  total_ingredient_items?: number;
  total_required_qty?: number;
  sufficient_items?: number;
  insufficient_items?: number;
  top_shortages?: MenuShortageItem[];
  assignments: any[];
}

export interface TodayOutgoing {
  total_items: number;
  total_qty: number;
  recent: Array<{
    item_id: number;
    item_name: string;
    qty: number;
    unit: string;
    remaining_stock: number;
    tone: string;
  }>;
}

export interface PendingActions {
  total: number;
  stock_opnames_pending_approval?: number;
  stock_opnames_pending_submit?: number;
  transaction_revisions_pending_approval?: number;
  spks_ready_to_post?: number;
  spks_ready_to_generate?: number;
  unread_notifications: number;
}

export interface MenuIngredientSummaryItem {
  item_id: number;
  item_name: string;
  unit: string;
  current_stock: number;
  required: number;
  deficit: number;
  tone: string;
}

export interface DryStockStatus {
  status: string;
  total_items: number;
  zero_stock_items: number;
  total_stock_qty: number;
}

// Per-role aggregate interfaces
export interface AdminAggregates {
  stock_summary: StockSummaryEnhanced;
  dry_stock_status: DryStockStatus;
  stock_alerts: { total_critical: number; total_danger: number; items: StockAlertItem[] };
  spending_trend: Array<{ date: string; total_out_qty: number }>;
  current_menu_cycle: CurrentMenuCycleEnhanced;
  latest_spk_history: {
    basah: SpkHistoryEntryEnhanced | null;
    kering_pengemas: SpkHistoryEntryEnhanced | null;
  };
  patient_fluctuation: PatientFluctuationPoint[];
  patient_fluctuation_meta: PatientStats;
  pending_actions: PendingActions;
}

export interface GudangAggregates {
  stock_summary: StockSummaryEnhanced;
  dry_stock_status: DryStockStatus;
  stock_alerts: { total_critical: number; total_danger: number; items: StockAlertItem[] };
  spending_trend: Array<{ date: string; total_out_qty: number }>;
  latest_spk_history: {
    basah: SpkHistoryEntryEnhanced | null;
    kering_pengemas: SpkHistoryEntryEnhanced | null;
  };
  patient_fluctuation: PatientFluctuationPoint[];
  patient_fluctuation_meta: PatientStats;
  today_outgoing: TodayOutgoing;
  pending_actions: PendingActions;
}

export interface DapurAggregates {
  current_menu_cycle: CurrentMenuCycleEnhanced;
  current_menu_composition: any[];
  menu_ingredient_summary: MenuIngredientSummaryItem[];
  latest_spk_history: {
    basah: SpkHistoryEntryEnhanced | null;
    kering_pengemas: SpkHistoryEntryEnhanced | null;
  };
  stock_summary: StockSummaryEnhanced;
  dry_stock_status: DryStockStatus;
  pending_actions: PendingActions;
}

export interface DashboardAggregate {
  /** Operational app role resolved by the backend. */
  role: string;
  /** Generated date time string */
  generated_at: string;
  /** Backend aggregate keys vary by role */
  aggregates: AdminAggregates & GudangAggregates & DapurAggregates;
}

/** Response envelope for `GET /api/v1/dashboard`. */
export interface DashboardResponse {
  data: DashboardAggregate;
}
