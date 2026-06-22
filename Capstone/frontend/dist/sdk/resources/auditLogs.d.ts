import type { ApiClient } from "../client";
import type { AuditLogListQuery, AuditLogListResponse, AuditLogTypesResponse } from "../types";
export declare class AuditLogsResource {
    private readonly client;
    constructor(client: ApiClient);
    list(query?: AuditLogListQuery): Promise<AuditLogListResponse>;
    types(): Promise<AuditLogTypesResponse>;
}
