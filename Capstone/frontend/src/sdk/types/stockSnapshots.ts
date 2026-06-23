// --- Domain Models ---

export interface StockSnapshotRow {
  id: number;
  period_month: string;     // "YYYY-MM-DD" (always first of month)
  item_id: number;
  item_name: string;
  category_name: string;
  opening_qty: number;      // DECIMAL(12,2) from backend, parsed as number
  created_at: string;
}

// --- Request DTOs ---

export interface CreateSnapshotRequest {
  month?: string;    // YYYY-MM — defaults to current month on server
  force?: boolean;   // delete & retake if true
}

// --- Response Envelopes ---

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

// --- Query Interface ---

export interface ListSnapshotsQuery {
  page?: number;
  perPage?: number;
  period_month?: string;      // "YYYY-MM-DD" format
  item_id?: number;
  item_category_id?: number;
}
