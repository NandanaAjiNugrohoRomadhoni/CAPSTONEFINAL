import sdk from "@/lib";
import type { ActivityLogEntry, ActivityLogModule, ActivityLogActivityType } from "@/sdk/types";

export type ActivityType = ActivityLogActivityType;
export type ActivityModule = ActivityLogModule;
export type ActivityRow = ActivityLogEntry;

const ACTIVITY_LAST_VIEWED_KEY = "capstone-activity-log-last-viewed-at";

export async function loadActivityRows(): Promise<ActivityRow[]> {
  try {
    const response = await sdk.auditLogs.list({
      paginate: false,
      sortBy: "created_at",
      sortDir: "DESC",
    });
    return response.data ?? [];
  } catch {
    return [];
  }
}

export async function getLatestActivityTimestamp(): Promise<number> {
  const rows = await loadActivityRows();
  if (rows.length === 0) {
    return 0;
  }

  return parseActivityTimestamp(rows[0]);
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

export function parseActivityTimestamp(row: Pick<ActivityRow, "date" | "time">) {
  const normalizedTime = row.time.replace(".", ":");
  return new Date(`${row.date}T${normalizedTime}:00+07:00`).getTime();
}

export function formatActivityDate(value: string) {
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    timeZone: "Asia/Jakarta",
  }).format(date);
}
