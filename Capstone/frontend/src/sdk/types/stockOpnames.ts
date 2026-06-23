export type StockOpnameState = "DRAFT" | "SUBMITTED" | "APPROVED" | "REJECTED" | "POSTED";

export interface StockOpnameDetail {
  id: number;
  stock_opname_id: number;
  item_id: number;
  system_qty: number;
  counted_qty: number;
  variance_qty: number;
}

export interface StockOpnameHeader {
  id: number;
  opname_date: string;
  state: StockOpnameState;
  created_by: number;
  submitted_by: number | null;
  approved_by: number | null;
  rejected_by: number | null;
  posted_by: number | null;
  submitted_at: string | null;
  approved_at: string | null;
  rejected_at: string | null;
  posted_at: string | null;
  rejection_reason: string | null;
  notes: string | null;
  created_by_name?: string | null;
  submitted_by_name?: string | null;
  approved_by_name?: string | null;
  rejected_by_name?: string | null;
  posted_by_name?: string | null;
  created_at: string;
  updated_at: string;
}

export interface StockOpname {
  header: StockOpnameHeader;
  details: StockOpnameDetail[];
}

export interface StockOpnameDetailInput {
  item_id: number;
  counted_qty: number;
  notes?: string;
}

export interface CreateStockOpnameRequest {
  opname_date: string;
  notes?: string;
  details: StockOpnameDetailInput[];
}

export interface RejectStockOpnameRequest {
  reason: string;
}

export interface StockOpnameResponse {
  data: StockOpname;
}

export interface StockOpnameActionResponse {
  message: string;
  data: {
    id: number;
    state: StockOpnameState;
  };
}

export interface ListStockOpnamesQuery {
  page?: number;
  perPage?: number;
  state?: StockOpnameState;
  q?: string;
  search?: string;
  sortBy?: "id" | "opname_date" | "state" | "created_at" | "updated_at";
  sortDir?: "ASC" | "DESC";
  opname_date_from?: string;
  opname_date_to?: string;
  created_at_from?: string;
  created_at_to?: string;
  updated_at_from?: string;
  updated_at_to?: string;
}
