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
  activityType: AuditLogActivityType;
  module: AuditLogModule;
  detail: string;
  created_at?: string | null;
}

export interface ListAuditLogsQuery {
  page?: number;
  perPage?: number;
  paginate?: boolean;
  q?: string;
  action_type?: string;
  table_name?: string;
  sortBy?: "id" | "created_at" | "action_type" | "table_name" | "record_id";
  sortDir?: "ASC" | "DESC";
}

export type AuditLogsListResponse = ApiListResponse<AuditLogEntry>;
