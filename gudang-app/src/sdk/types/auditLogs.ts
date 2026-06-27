import type { PaginationLinks, PaginationMeta } from "./common";

export interface AuditLogEntry {
    id: number;
    date: string | null;
    time: string | null;
    actor: string;
    actorInfo: {
        id: number | null;
        name: string;
        username: string | null;
        role: string | null;
    };
    activityType: string;
    activityLabel: string;
    module: string;
    detail: string;
    description: string;
    target: {
        table: string | null;
        recordId: number | null;
    };
    changes: {
        before: Record<string, unknown> | null;
        after: Record<string, unknown> | null;
        diff: Array<{ field: string; before: unknown; after: unknown }>;
        /** Present only for stock transaction revision records. */
        itemDiff?: Array<{
            item_id: number;
            label: string;
            qty_before: string | null;
            qty_after: string | null;
            unit_before: string | null;
            unit_after: string | null;
            status: "added" | "removed" | "changed";
        }>;
    };
    ipAddress: string | null;
    rawActionType: string | null;
    created_at: string | null;
}

export interface AuditLogListQuery {
    page?: number;
    perPage?: number;
    paginate?: boolean;
    q?: string;
    action_type?: string;
    table_name?: string;
    sortBy?: string;
    sortDir?: string;
    start_date?: string;
    end_date?: string;
    user_id?: number;
}

export interface AuditLogListResponse {
    data: AuditLogEntry[];
    meta: PaginationMeta;
    links: PaginationLinks;
}

export interface AuditLogTypesResponse {
    actionTypes: string[];
    moduleTypes: string[];
    tableNames: string[];
}

export interface AuditLogSummary {
    total: number;
    byRole: Record<string, number>;
    byActionType: Record<string, number>;
    byModule: Record<string, number>;
}

export interface AuditLogSummaryResponse {
    data: AuditLogSummary;
}