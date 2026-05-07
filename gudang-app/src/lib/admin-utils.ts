"use client";

export function formatDate(value?: string | null, locale = "id-ID") {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale, {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(date);
}

export function formatLongDate(value?: string | null, locale = "id-ID") {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale, {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(date);
}

export function formatCompactDate(value?: string | null, locale = "id-ID") {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale, {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(date);
}

export function formatNumber(value: number, maximumFractionDigits = 0) {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits,
  }).format(value);
}

export function formatQuantity(value: unknown, unit?: string | null) {
  const numeric = typeof value === "number" ? value : Number(value ?? 0);
  const safe = Number.isFinite(numeric) ? numeric : 0;
  const digits = Number.isInteger(safe) ? 0 : 1;
  return `${formatNumber(safe, digits)}${unit ? ` ${unit}` : ""}`;
}

export function getCurrentMonthPeriod() {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  return {
    period_start: toIsoDate(start),
    period_end: toIsoDate(end),
  };
}

export function toIsoDate(date: Date) {
  return date.toISOString().slice(0, 10);
}

export function getStockTone(
  qty: number,
  minimum: number,
): { label: string; tone: "safe" | "warning" | "critical" | "danger" } {
  if (qty <= 0) return { label: "Habis", tone: "danger" };
  if (qty < minimum * 0.5) return { label: "Kritis", tone: "critical" };
  if (qty < minimum) return { label: "Menipis", tone: "warning" };
  return { label: "Aman", tone: "safe" };
}

export function normaliseMealLabel(value?: string | null) {
  const lower = (value ?? "").toLowerCase();
  if (lower.includes("pagi")) return "PAGI";
  if (lower.includes("siang")) return "SIANG";
  if (lower.includes("sore")) return "SORE";
  return (value ?? "-").toUpperCase();
}

export function getErrorMessage(error: unknown, fallback: string) {
  if (error && typeof error === "object") {
    const maybeErrors = "errors" in error ? error.errors : undefined;
    if (maybeErrors && typeof maybeErrors === "object") {
      const messages = Object.values(maybeErrors)
        .flatMap((value) => {
          if (Array.isArray(value)) return value;
          return [value];
        })
        .filter((value): value is string => typeof value === "string" && value.trim() !== "");

      if (messages.length > 0) {
        return messages.join(" ");
      }
    }

    const maybeMessage = "message" in error ? error.message : undefined;
    if (typeof maybeMessage === "string" && maybeMessage.trim() !== "") {
      return maybeMessage;
    }
  }

  if (error instanceof Error && error.message.trim() !== "") {
    return error.message;
  }

  return fallback;
}

type DetailLike = {
  item_id: number;
  item_name?: string | null;
  item_category_name?: string | null;
  satuan?: string | null;
  input_unit?: "base" | "convert" | string | null;
  item?: {
    name?: string | null;
    category?: { name?: string | null } | null;
  } | null;
  category?: { name?: string | null } | null;
};

type ItemLike = {
  name?: string | null;
  unit_base?: string | null;
  unit_convert?: string | null;
  item_unit_base_id?: number | null;
  item_unit_convert_id?: number | null;
  item_unit_base?: { name?: string | null } | null;
  item_unit_convert?: { name?: string | null } | null;
  category?: { name?: string | null } | null;
};

export function resolveDetailItemName(detail: DetailLike, item?: ItemLike) {
  return detail.item_name ?? item?.name ?? detail.item?.name ?? `Item #${detail.item_id}`;
}

export function resolveDetailItemCategory(detail: DetailLike, item?: ItemLike) {
  return (
    detail.item_category_name ??
    item?.category?.name ??
    detail.item?.category?.name ??
    detail.category?.name ??
    "-"
  );
}

export function resolveDetailUnit(
  detail: DetailLike,
  item: ItemLike | undefined,
  unitMap: Map<number, string> = new Map(),
) {
  if (detail.satuan) return detail.satuan;

  if (detail.input_unit === "convert") {
    return (
      item?.item_unit_convert?.name ??
      (item?.item_unit_convert_id ? unitMap.get(item.item_unit_convert_id) : undefined) ??
      item?.unit_convert ??
      "convert"
    );
  }
  return (
    item?.item_unit_base?.name ??
    (item?.item_unit_base_id ? unitMap.get(item.item_unit_base_id) : undefined) ??
    item?.unit_base ??
    "base"
  );
}
