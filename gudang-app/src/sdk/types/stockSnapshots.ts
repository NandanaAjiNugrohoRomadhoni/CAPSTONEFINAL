import type { ApiListResponse } from "./common";

export interface StockSnapshotRow {
  id: number;
  period_month: string;
  item_id: number;
  item_name: string;
  category_name: string;
  opening_qty: number;
  created_at: string;
  updated_at: string;
}

export interface ListStockSnapshotsQuery {
  page?: number;
  perPage?: number;
  period_month?: string;
  item_id?: number;
  item_category_id?: number;
}

export interface TakeStockSnapshotRequest {
  month?: string;
  force?: boolean;
}

export interface TakeStockSnapshotResponse {
  success: boolean;
  message: string;
  count: number;
}

export interface StockSnapshotCurrentStatus {
  month: string;
  has_snapshot: boolean;
  item_count?: number | null;
}

export type StockSnapshotsListResponse = ApiListResponse<StockSnapshotRow>;
