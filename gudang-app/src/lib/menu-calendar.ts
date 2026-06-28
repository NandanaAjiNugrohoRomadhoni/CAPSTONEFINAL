import { getCsvMenuPackageLabel } from "@/lib/menu-csv-plan";
import type { CalendarDay } from "@/sdk/types";

export type MenuCalendarPackage = {
  id: number;
  name?: string | null;
};

export type ResolvedMenuCalendarEntry = {
  date: string;
  day_of_month: number;
  menu_id: number;
  menu_name: string;
};

type ProjectedCalendarDay = Pick<CalendarDay, "date" | "day_of_month" | "assignments">;

function toLocalDateString(year: number, monthIndex: number, day: number) {
  return `${year}-${String(monthIndex + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}

function isLeapYear(year: number) {
  return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
}

function getPackageLabel(menuId: number, menuPackages: MenuCalendarPackage[]) {
  const packageIndex = menuPackages.findIndex((item) => item.id === menuId);
  if (packageIndex < 0) {
    return getCsvMenuPackageLabel(menuId - 1);
  }

  return menuPackages[packageIndex]?.name?.trim() || getCsvMenuPackageLabel(packageIndex);
}

function getSpecialCalendarMenuId(year: number, day: number) {
  if (!isLeapYear(year)) {
    if (day === 31) return 11;
    return null;
  }

  if (day === 29) return 9;
  if (day === 30) return 10;
  if (day === 31) return 11;
  return null;
}

/**
 * Frontend-only resolver for the menu calendar badge and detail panel.
 *
 * The backend projection remains the source of truth when it already provides
 * an assignment, but we still enforce the special front-end display rules for
 * Feb 29 and day 31 so the UI never falls back to the regular cycle there.
 */
export function resolveFrontendMenuCalendarEntry(params: {
  year: number;
  monthIndex: number;
  day: number;
  menuPackages: MenuCalendarPackage[];
  projected?: ProjectedCalendarDay | null;
}): ResolvedMenuCalendarEntry | null {
  const { year, monthIndex, day, menuPackages, projected } = params;
  const specialMenuId = getSpecialCalendarMenuId(year, day);
  const projectedMenuId = projected?.assignments?.[0]?.menu_id ?? null;
  const menuId =
    specialMenuId ?? projectedMenuId ?? (menuPackages.length > 0 ? menuPackages[(day - 1) % menuPackages.length]?.id ?? null : null);

  if (menuId == null) {
    return null;
  }

  return {
    date: projected?.date ?? toLocalDateString(year, monthIndex, day),
    day_of_month: day,
    menu_id: menuId,
    menu_name: getPackageLabel(menuId, menuPackages),
  };
}
