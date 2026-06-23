import type { ApiClient } from "../client";
import type { AuditLogListQuery, AuditLogListResponse, AuditLogTypesResponse, AuditLogSummaryResponse } from "../types";

export class AuditLogsResource {
    public constructor(private readonly client: ApiClient) { }

    public list(query?: AuditLogListQuery): Promise<AuditLogListResponse> {
        return this.client.request<AuditLogListResponse>({
            method: "GET",
            path: "/audit-logs",
            ...(query ? { query: buildAuditLogQuery(query) } : {})
        });
    }

    public types(): Promise<AuditLogTypesResponse> {
        return this.client.request<AuditLogTypesResponse>({
            method: "GET",
            path: "/audit-logs/types"
        });
    }

    public summary(): Promise<AuditLogSummaryResponse> {
        return this.client.request<AuditLogSummaryResponse>({
            method: "GET",
            path: "/audit-logs/summary"
        });
    }
}

function buildAuditLogQuery(query: AuditLogListQuery): Record<string, string | number> {
    const result: Record<string, string | number> = {};

    if (query.page !== undefined) result.page = query.page;
    if (query.perPage !== undefined) result.perPage = query.perPage;
    if (query.paginate !== undefined) result.paginate = query.paginate ? "true" : "false";
    if (query.q !== undefined) result.q = query.q;
    if (query.action_type !== undefined) result.action_type = query.action_type;
    if (query.table_name !== undefined) result.table_name = query.table_name;
    if (query.sortBy !== undefined) result.sortBy = query.sortBy;
    if (query.sortDir !== undefined) result.sortDir = query.sortDir;
    if (query.start_date !== undefined) result.start_date = query.start_date;
    if (query.end_date !== undefined) result.end_date = query.end_date;
    if (query.user_id !== undefined) result.user_id = query.user_id;

    return result;
}