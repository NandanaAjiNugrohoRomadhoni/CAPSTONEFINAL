import type { ApiListResponse } from "./common";

export type AuditLogActivityType = "Create" | "Update" | "Delete";
export type AuditLogModule =
  | "Transaksi"
  | "Master Barang"
  | "Menu"
  | "Pengguna"
  | "SPK"
  | "Stok"
  | "Laporan";

export interface AuditLogEntry {
  id: number;
  date: string;
  time: string;
  actor: string;
  actorInitials: string;
  actorInfo?: {
    id: number | null;
    name: string;
    username?: string | null;
  };
  activityType: AuditLogActivityType;
  activityLabel?: string;
  module: AuditLogModule;
  detail: string;
  description?: string;
  target?: {
    table?: string | null;
    recordId?: number | null;
  };
  changes?: {
    before?: unknown;
    after?: unknown;
    diff?: unknown[];
  };
  ipAddress?: string | null;
  rawActionType?: string | null;
  created_at?: string | null;
}

export interface ListAuditLogsQuery {
  page?: number;
  perPage?: number;
  paginate?: boolean;
  q?: string;
  action_type?: string;
  table_name?: string;
  start_date?: string;
  end_date?: string;
  user_id?: number;
  sortBy?: "id" | "created_at" | "action_type" | "table_name" | "record_id";
  sortDir?: "ASC" | "DESC";
}

export type AuditLogsListResponse = ApiListResponse<AuditLogEntry>;
