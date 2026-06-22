import type { ApiClient } from "../client";
import type { ApiListResponse } from "../types";

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

type AuditLogsListResponse = ApiListResponse<AuditLogEntry>;

export class AuditLogsResource {
  public constructor(private readonly client: ApiClient) {}

  public list(query?: ListAuditLogsQuery): Promise<AuditLogsListResponse> {
    return this.client.request<AuditLogsListResponse>({
      method: "GET",
      path: "/audit-logs",
      ...(query ? { query: buildAuditLogsQuery(query) } : {}),
    });
  }
}

function buildAuditLogsQuery(query: ListAuditLogsQuery): Record<string, string | number | boolean> {
  const result: Record<string, string | number | boolean> = {};

  if (query.page !== undefined) result.page = query.page;
  if (query.perPage !== undefined) result.perPage = query.perPage;
  if (query.paginate !== undefined) result.paginate = query.paginate;
  if (query.q !== undefined) result.q = query.q;
  if (query.action_type !== undefined) result.action_type = query.action_type;
  if (query.table_name !== undefined) result.table_name = query.table_name;
  if (query.sortBy !== undefined) result.sortBy = query.sortBy;
  if (query.sortDir !== undefined) result.sortDir = query.sortDir;

  return result;
}
