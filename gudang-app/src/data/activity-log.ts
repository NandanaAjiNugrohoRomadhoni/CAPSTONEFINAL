import sdk from "@/lib";
import type {
  AuditLogEntry,
  ListAuditLogsQuery,
} from "@/sdk/types";
import type { ApiListResponse } from "@/sdk/types/common";

type AuditLogActivityType = "Create" | "Update" | "Delete";
type AuditLogModule = string;

export type ActivityType = AuditLogActivityType;
export type ActivityModule = AuditLogModule;
export type ActivityRow = AuditLogEntry;
export interface NormalizedActivityRow extends AuditLogEntry {
  actorInitials: string;
  activityType: AuditLogActivityType;
  date: string;
  time: string;
}

const ACTIVITY_LAST_VIEWED_KEY = "capstone-activity-log-last-viewed-at";

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function normalizeActivityRow(row: any): NormalizedActivityRow {
  const actorName = row.actorInfo?.username?.trim() || row.actor || "Sistem";

  const initials = actorName
    .split(" ")
    .filter(Boolean)
    .map((n: string) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2) || "S";

  let activityType: ActivityType = "Update";
  const rawType = String(row.activityType).toLowerCase();
  if (rawType === "create" || rawType === "insert" || rawType === "draft") {
    activityType = "Create";
  } else if (rawType === "delete" || rawType === "remove") {
    activityType = "Delete";
  } else {
    activityType = "Update";
  }

  let dateVal = row.date || "";
  let timeVal = row.time || "";

  let dateObj: Date | null = null;
  if (row.created_at) {
    const isoStr = row.created_at.replace(" ", "T") + "Z";
    const d = new Date(isoStr);
    if (!Number.isNaN(d.getTime())) {
      dateObj = d;
    }
  }

  if (!dateObj && row.date && row.time) {
    const normalizedTime = row.time.replace(".", ":");
    const d = new Date(`${row.date}T${normalizedTime}:00Z`);
    if (!Number.isNaN(d.getTime())) {
      dateObj = d;
    }
  }

  if (dateObj) {
    const wibMs = dateObj.getTime() + 7 * 60 * 60 * 1000;
    const wibDate = new Date(wibMs);
    const yyyy = wibDate.getUTCFullYear();
    const mm = String(wibDate.getUTCMonth() + 1).padStart(2, "0");
    const dd = String(wibDate.getUTCDate()).padStart(2, "0");
    const hh = String(wibDate.getUTCHours()).padStart(2, "0");
    const min = String(wibDate.getUTCMinutes()).padStart(2, "0");
    dateVal = `${yyyy}-${mm}-${dd}`;
    timeVal = `${hh}:${min}`;
  }

  return {
    ...row,
    actor: actorName,
    actorInitials: initials,
    activityType,
    date: dateVal,
    time: timeVal,
  };
}

export async function loadActivityRows(query?: ListAuditLogsQuery): Promise<ApiListResponse<NormalizedActivityRow>> {
  try {
    const response = await sdk.auditLogs.list({
      sortBy: "created_at",
      sortDir: "DESC",
      ...query,
    });
    
    const normalizedData = (response.data ?? []).map(normalizeActivityRow);

    return {
      data: normalizedData,
      meta: response.meta ?? {
        page: query?.page ?? 1,
        perPage: query?.perPage ?? 10,
        total: normalizedData.length,
        totalPages: 1,
        paginated: query?.paginate !== false,
      },
      links: response.links ?? {
        self: "",
        first: "",
        last: "",
        next: null,
        previous: null,
      },
    };
  } catch {
    return {
      data: [],
      meta: {
        page: query?.page ?? 1,
        perPage: query?.perPage ?? 10,
        total: 0,
        totalPages: 0,
        paginated: query?.paginate !== false,
      },
      links: {
        self: "",
        first: "",
        last: "",
        next: null,
        previous: null,
      },
    };
  }
}

export function buildActivityLogQuery(query: {
  page?: number;
  perPage?: number;
  paginate?: boolean;
  q?: string;
  action_type?: string;
  table_name?: string;
  start_date?: string;
  end_date?: string;
  sortBy?: ListAuditLogsQuery["sortBy"];
  sortDir?: ListAuditLogsQuery["sortDir"];
}) {
  const result: ListAuditLogsQuery = {};

  if (query.page !== undefined) result.page = query.page;
  if (query.perPage !== undefined) result.perPage = query.perPage;
  if (query.paginate !== undefined) result.paginate = query.paginate;
  if (query.q !== undefined) result.q = query.q;
  if (query.action_type !== undefined) result.action_type = query.action_type;
  if (query.table_name !== undefined) result.table_name = query.table_name;
  if (query.start_date !== undefined) result.start_date = query.start_date;
  if (query.end_date !== undefined) result.end_date = query.end_date;
  if (query.sortBy !== undefined) result.sortBy = query.sortBy;
  if (query.sortDir !== undefined) result.sortDir = query.sortDir;

  return result;
}

export async function getLatestActivityTimestamp(): Promise<number> {
  try {
    const response = await sdk.auditLogs.list({
      page: 1,
      perPage: 1,
      sortBy: "created_at",
      sortDir: "DESC",
    });
    const rows = response.data ?? [];
    if (rows.length === 0) {
      return 0;
    }
    const normalized = normalizeActivityRow(rows[0]);
    return parseActivityTimestamp(normalized);
  } catch {
    return 0;
  }
}

  export function getStoredActivityLogSeenAt() {
    if (typeof window === "undefined") {
      return 0;
    }

    const rawValue = window.localStorage.getItem(ACTIVITY_LAST_VIEWED_KEY);
    const parsedValue = rawValue ? Number(rawValue) : 0;
    return Number.isFinite(parsedValue) ? parsedValue : 0;
  }

  export function markActivityLogSeen(latestTimestamp: number) {
    if (typeof window === "undefined") {
      return;
    }

    window.localStorage.setItem(ACTIVITY_LAST_VIEWED_KEY, String(latestTimestamp));
  }

  export function hasUnreadActivityLog(latestTimestamp: number) {
    if (typeof window === "undefined") {
      return false;
    }

    return latestTimestamp > getStoredActivityLogSeenAt();
  }

  export function parseActivityTimestamp(row: Pick<NormalizedActivityRow, "date" | "time" | "created_at">) {
    if (row.created_at) {
      const isoStr = row.created_at.replace(" ", "T") + "Z";
      const d = new Date(isoStr);
      if (!Number.isNaN(d.getTime())) {
        return d.getTime();
      }
    }
    if (row.time) {
      const normalizedTime = row.time.replace(".", ":");
      return new Date(`${row.date}T${normalizedTime}:00+07:00`).getTime();
    }
    return 0;
  }

  export function formatActivityDate(value: string) {
    const parts = value.split("-");
    if (parts.length === 3) {
      const [year, month, day] = parts;
      return `${day}/${month}/${year}`;
    }
    return value;
  }
