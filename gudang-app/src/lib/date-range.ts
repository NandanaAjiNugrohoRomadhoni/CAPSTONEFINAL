export type IsoDateRange = {
  startDate: string;
  endDate: string;
};

export function isIsoDateInRange(value: string, range: IsoDateRange) {
  if (!range.startDate && !range.endDate) {
    return true;
  }

  if (range.startDate && value < range.startDate) {
    return false;
  }

  if (range.endDate && value > range.endDate) {
    return false;
  }

  return true;
}

export function getDateRangeQuery(range: IsoDateRange) {
  return {
    ...(range.startDate ? { transaction_date_from: range.startDate } : {}),
    ...(range.endDate ? { transaction_date_to: range.endDate } : {}),
  };
}
