export type StockOpnameState = "DRAFT" | "SUBMITTED" | "APPROVED" | "REJECTED" | "POSTED";

export interface StockOpnameDetail {
  id: number;
  opname_id: number;
  item_id: number;
  system_qty: number;
  counted_qty: number;
  variance_qty: number;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface StockOpnameHeader {
  id: number;
  opname_date: string;
  state: StockOpnameState;
  created_by: number;
  approved_by: number | null;
  rejection_reason: string | null;
  notes: string | null;
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
  message?: string;
  data: StockOpnameHeader;
}
