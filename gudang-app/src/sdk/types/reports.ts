/** Summary block returned by reporting endpoints. Keys vary by report type. */
export interface ReportSummary {
  total_items?: number;
  active_items?: number;
  total_qty?: number;
  total_rows?: number;
  total_spk?: number;
  total_days?: number;
  planned_total_qty?: number;
  realization_total_qty?: number;
  variance_total_qty?: number;
  [key: string]: unknown;
}

/** Generic report row used by implemented report datasets. */
export interface ReportRow {
  [key: string]: unknown;
}

/** Row returned by `/reports/stocks`. */
export interface StockReportRow extends ReportRow {
    item_id: number;
    item_name: string;
    category_id: number;
    category_name: string;
    qty: number;
    unit_base: string;
    unit_convert: string;
    is_active: boolean;
    updated_at: string;
}

/** Row returned by `/reports/transactions`. */
export interface TransactionReportRow extends ReportRow {
    transaction_id: number;
    transaction_date: string;
    type_id: number;
    type_name: string;
    status_id: number;
    status_name: string;
    user_id: number;
    spk_id: number | null;
    item_id: number;
    item_name: string;
    qty: number;
}

/** Row returned by `/reports/spk-history`. */
export interface SpkHistoryReportRow extends ReportRow {
    spk_id: number;
    spk_type: string;
    version: number;
    calculation_scope: string;
    calculation_date: string;
    target_date_start: string;
    target_date_end: string;
    target_month: string;
    estimated_patients: number;
    is_finish: boolean;
    category_id: number;
    category_name: string;
    user_id: number;
    user_name: string;
    total_recommendations: number;
    total_required_qty: number;
    total_recommended_qty: number;
}

/** Row returned by `/reports/evaluation`. */
export interface EvaluationReportRow extends ReportRow {
    spk_id: number;
    spk_type: string;
    calculation_date: string;
    category_id: number;
    planned_qty: number;
    realization_qty: number;
    variance_qty: number;
}

/** Day entry for monthly stock export. */
export interface MonthlyStockExportDayEntry {
  tanggal: number;
  masuk: number;
  keluar: number;
  sisa: number | null;
}

/** Row returned by `/reports/monthly-stock-export`. */
export interface MonthlyStockExportRow extends ReportRow {
    no: number;
    item_id: number;
    nama_bahan_makanan: string;
    category_id: number;
    category_name: string;
    satuan: string;
    stok_awal: number | null;
    harian: MonthlyStockExportDayEntry[];
}

/** Union of all possible report row types. */
export type ConcreteReportRow =
  | StockReportRow
  | TransactionReportRow
  | SpkHistoryReportRow
  | EvaluationReportRow
  | MonthlyStockExportRow;

/** Additive read-only compatibility projection used by the SPK history report. */
export interface CompatibilityProjection {
  contract: {
    spk_calculations: string[];
    spk_recommendations: string[];
    [key: string]: string[];
  };
  rows: unknown[];
}

/** Generic `data` shape shared by report endpoints. */
export interface ReportData<TRow extends ReportRow = ConcreteReportRow> {
  report_type: string;
  period?: {
    start: string;
    end: string;
  };
  filters?: Record<string, unknown>;
  summary: ReportSummary;
  periode?: string;
  rows: TRow[];
  compatibility_projection?: CompatibilityProjection;
}

/** Generic response envelope for report endpoints. */
export interface ReportResponse<TRow extends ReportRow = ConcreteReportRow> {
  data: ReportData<TRow>;
}

export type StockReportResponse = ReportResponse<StockReportRow>;
export type TransactionReportResponse = ReportResponse<TransactionReportRow>;
export type SpkHistoryReportResponse = ReportResponse<SpkHistoryReportRow>;
export type EvaluationReportResponse = ReportResponse<EvaluationReportRow>;
export type MonthlyStockExportResponse = ReportResponse<MonthlyStockExportRow>;

/** Base required query params for all report endpoints. */
export interface ReportParams {
  period_start: string;
  period_end: string;
}
