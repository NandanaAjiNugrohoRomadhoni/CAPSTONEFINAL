export type IsoDateRange = {
  startDate: string;
  endDate: string;
};

export function normalizeIsoDateRange(range: IsoDateRange) {
  const startDate = range.startDate.trim();
  const endDate = range.endDate.trim();

  if (!startDate && !endDate) {
    return { startDate: "", endDate: "" };
  }

  if (startDate && endDate) {
    return { startDate, endDate };
  }

  const onlyDate = startDate || endDate;
  return { startDate: onlyDate, endDate: onlyDate };
}

export function isIsoDateInRange(value: string, range: IsoDateRange) {
  const normalized = normalizeIsoDateRange(range);

  if (!normalized.startDate && !normalized.endDate) {
    return true;
  }

  if (normalized.startDate && value < normalized.startDate) {
    return false;
  }

  if (normalized.endDate && value > normalized.endDate) {
    return false;
  }

  return true;
}

export function getDateRangeQuery(range: IsoDateRange) {
  const normalized = normalizeIsoDateRange(range);

  return {
    ...(normalized.startDate ? { transaction_date_from: normalized.startDate } : {}),
    ...(normalized.endDate ? { transaction_date_to: normalized.endDate } : {}),
  };
}
