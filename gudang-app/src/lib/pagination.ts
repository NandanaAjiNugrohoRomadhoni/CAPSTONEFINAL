"use client";

type PaginatedListResponse<T> = {
  data?: T[];
  meta?: {
    totalPages?: number | null;
  } | null;
};

export async function listAllPaginatedRows<T>(
  listFn: (query?: Record<string, unknown>) => Promise<PaginatedListResponse<T>>,
  query: Record<string, unknown> = {},
  pageSize = 100,
  maxPages = 500,
): Promise<T[]> {
  const rows: T[] = [];
  const safePageSize = Math.max(1, Math.min(Math.floor(pageSize), 100));
  const safeMaxPages = Math.max(1, Math.floor(maxPages));

  for (let page = 1; page <= safeMaxPages; page += 1) {
    const response = await listFn({
      ...query,
      page,
      perPage: safePageSize,
    });

    rows.push(...(response.data ?? []));

    const totalPages = Number(response.meta?.totalPages ?? 0);
    if (totalPages > 0 && page >= totalPages) {
      break;
    }

    if ((response.data ?? []).length < safePageSize) {
      break;
    }
  }

  return rows;
}
