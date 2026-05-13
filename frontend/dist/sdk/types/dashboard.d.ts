/** Role-shaped aggregate block returned by `GET /api/v1/dashboard` (api-contract.md §5.8). */
export interface DashboardAggregate {
    /** Operational app role resolved by the backend. */
    role: string;
    /** Backend aggregate keys vary by role; see api-contract.md §5.8 for the minimum key set. */
    aggregates: {
        stock_summary?: unknown;
        dry_stock_status?: unknown;
        spending_trend?: unknown;
        current_menu_cycle?: unknown;
        latest_spk_history?: unknown;
        patient_fluctuation?: unknown;
        [key: string]: unknown;
    };
}
/** Response envelope for `GET /api/v1/dashboard`. */
export interface DashboardResponse {
    data: DashboardAggregate;
}
