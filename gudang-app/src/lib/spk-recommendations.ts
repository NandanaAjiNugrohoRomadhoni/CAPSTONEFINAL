import { toIsoDate } from "@/lib/admin-utils";
import type { SpkHistoryEntry } from "@/sdk/types/spk";

export function addDaysIsoDate(dateValue: string, days: number) {
  const date = new Date(`${dateValue.slice(0, 10)}T00:00:00`);
  date.setDate(date.getDate() + days);
  return toIsoDate(date);
}

export function isSameIsoDate(left?: string | null, right?: string | null) {
  if (!left || !right) return false;
  return left.slice(0, 10) === right.slice(0, 10);
}

export function findExistingBasahSpk(rows: SpkHistoryEntry[], serviceDate: string, categoryId: number) {
  const startDate = serviceDate.slice(0, 10);
  const nextDate = addDaysIsoDate(startDate, 1);
  const endDate = startDate.slice(0, 7) === nextDate.slice(0, 7) ? nextDate : startDate;
  const scopeSuffix = `|${startDate}|${endDate}|${categoryId}`;

  return (
    rows.find(
      (row) =>
        row.is_latest &&
        Number(row.category?.id ?? 0) === categoryId &&
        isSameIsoDate(row.target_date_start, startDate) &&
        isSameIsoDate(row.target_date_end, endDate)
    ) ??
    rows.find((row) => row.is_latest && row.scope_key?.includes(scopeSuffix)) ??
    null
  );
}

export function findExistingKeringSpk(rows: SpkHistoryEntry[], targetMonth: string) {
  return (
    rows.find((row) => row.is_latest && row.target_month === targetMonth) ??
    rows.find((row) => row.is_latest && row.scope_key?.includes(targetMonth)) ??
    null
  );
}
