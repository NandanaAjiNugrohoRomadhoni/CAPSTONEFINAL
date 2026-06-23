import type { ApiClient } from "../client";
import type { AuditLogListQuery, AuditLogListResponse, AuditLogTypesResponse, AuditLogSummaryResponse } from "../types";
export declare class AuditLogsResource {
    private readonly client;
    constructor(client: ApiClient);
    list(query?: AuditLogListQuery): Promise<AuditLogListResponse>;
    types(): Promise<AuditLogTypesResponse>;
    summary(): Promise<AuditLogSummaryResponse>;
}
