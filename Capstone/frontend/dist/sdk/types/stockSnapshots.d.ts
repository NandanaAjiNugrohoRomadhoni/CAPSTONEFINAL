export interface StockSnapshotRow {
    id: number;
    period_month: string;
    item_id: number;
    item_name: string;
    category_name: string;
    opening_qty: number;
    created_at: string;
}
export interface CreateSnapshotRequest {
    month?: string;
    force?: boolean;
}
export interface CreateSnapshotResponse {
    success: boolean;
    message: string;
    count: number;
}
export interface CurrentSnapshotStatus {
    month: string;
    has_snapshot: boolean;
    item_count: number | null;
}
export interface ListSnapshotsQuery {
    page?: number;
    perPage?: number;
    period_month?: string;
    item_id?: number;
    item_category_id?: number;
}
