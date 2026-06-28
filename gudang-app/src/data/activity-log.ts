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

function parseBackendActivityTimestamp(row: Pick<NormalizedActivityRow, "date" | "time" | "created_at">) {
  if (row.created_at) {
    const isoStr = row.created_at.replace(" ", "T");
    const parsed = new Date(isoStr);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed;
    }
  }

  if (row.date && row.time) {
    const normalizedTime = row.time.replace(".", ":");
    const parsed = new Date(`${row.date}T${normalizedTime}:00`);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed;
    }
  }

  return null;
}

function formatDateParts(date: Date) {
  const year = String(date.getFullYear());
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");

  return {
    date: `${year}-${month}-${day}`,
    time: `${hours}:${minutes}`,
  };
}

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

  const dateObj = parseBackendActivityTimestamp(row);

  if (dateObj) {
    const parts = formatDateParts(dateObj);
    dateVal = parts.date;
    timeVal = parts.time;
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
    const parsed = parseBackendActivityTimestamp(row);
    return parsed ? parsed.getTime() : 0;
  }

  export function formatActivityDate(value: string) {
    const parts = value.split("-");
    if (parts.length === 3) {
      const [year, month, day] = parts;
      return `${day}/${month}/${year}`;
    }
    return value;
  }

  export function formatActivityDateFromDate(date: Date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return formatActivityDate(`${year}-${month}-${day}`);
  }
