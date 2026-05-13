/** Summary block returned by reporting endpoints. Keys vary by report type. */
export interface ReportSummary {
    total_items?: number;
    active_items?: number;
    total_qty?: number;
    total_rows?: number;
    total_spk?: number;
    planned_total_qty?: number;
    realization_total_qty?: number;
    variance_total_qty?: number;
    [key: string]: unknown;
}
/** Generic report row used by implemented report datasets. */
export interface ReportRow {
    [key: string]: unknown;
}
/** Additive read-only compatibility projection used by the SPK history report. */
export interface CompatibilityProjection {
    contract: {
        spk_calculations: string[];
        spk_recommendations: string[];
        [key: string]: string[];
    };
    rows: unknown[];
}
/** Shared `data` shape used by `/reports/stocks`, `/reports/transactions`, `/reports/spk-history`, and `/reports/evaluation`. */
export interface ReportData {
    report_type: string;
    summary: ReportSummary;
    rows: ReportRow[];
    compatibility_projection?: CompatibilityProjection;
}
/** Response envelope for implemented report endpoints. */
export interface ReportResponse {
    data: ReportData;
}
/** Base required query params for all report endpoints. */
export interface ReportParams {
    period_start: string;
    period_end: string;
}
