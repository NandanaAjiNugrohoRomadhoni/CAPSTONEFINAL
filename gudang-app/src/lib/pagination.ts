"use client";

type PaginatedListResponse<T> = {
  data?: T[];
  meta?: {
    totalPages?: number | null;
  } | null;
};

const inFlightPaginationRequests = new Map<string, Promise<unknown>>();

function normalizePaginationKey(value: unknown): string {
  if (value === null) return "null";
  if (value === undefined) return "undefined";
  if (Array.isArray(value)) {
    return `[${value.map((entry) => normalizePaginationKey(entry)).join(",")}]`;
  }
  if (typeof value === "object") {
    const entries = Object.entries(value as Record<string, unknown>).sort(([left], [right]) =>
      left.localeCompare(right),
    );
    return `{${entries.map(([key, entry]) => `${key}:${normalizePaginationKey(entry)}`).join(",")}}`;
  }
  return JSON.stringify(value);
}

export async function listAllPaginatedRows<T>(
  listFn: (query?: Record<string, unknown>) => Promise<PaginatedListResponse<T>>,
  query: Record<string, unknown> = {},
  pageSize = 100,
  maxPages = 500,
): Promise<T[]> {
  const cacheKey = `${listFn.name || "anonymous"}|${normalizePaginationKey(query)}|${pageSize}|${maxPages}`;
  const cachedRequest = inFlightPaginationRequests.get(cacheKey);
  if (cachedRequest) {
    return (await cachedRequest) as T[];
  }

  const requestPromise = (async () => {
  const rows: T[] = [];
  const safePageSize = Math.max(1, Math.min(Math.floor(pageSize), 100));
  const safeMaxPages = Math.max(1, Math.floor(maxPages));

  const firstResponse = await listFn({
    ...query,
    page: 1,
    perPage: safePageSize,
  });

  rows.push(...(firstResponse.data ?? []));

  const totalPages = Number(firstResponse.meta?.totalPages ?? 0);
  if (totalPages <= 1) {
    if ((firstResponse.data ?? []).length < safePageSize) {
      return rows;
    }

    for (let page = 2; page <= safeMaxPages; page += 5) {
      const pageBatch = Array.from({ length: Math.min(5, safeMaxPages - page + 1) }, (_, index) => page + index);
      if (pageBatch.length === 0) {
        break;
      }

      const responses = await Promise.all(
        pageBatch.map((nextPage) =>
          listFn({
            ...query,
            page: nextPage,
            perPage: safePageSize,
          }),
        ),
      );

      let reachedEnd = false;

      for (const response of responses) {
        const data = response.data ?? [];
        rows.push(...data);
        if (data.length < safePageSize) {
          reachedEnd = true;
        }
      }

      if (reachedEnd) {
        break;
      }
    }

    return rows;
  }

  const cappedTotalPages = Math.min(totalPages, safeMaxPages);
  const remainingPages = Array.from({ length: Math.max(0, cappedTotalPages - 1) }, (_, index) => index + 2);
  const batchSize = 5;

  for (let index = 0; index < remainingPages.length; index += batchSize) {
    const pageBatch = remainingPages.slice(index, index + batchSize);
    const responses = await Promise.all(
      pageBatch.map((page) =>
        listFn({
          ...query,
          page,
          perPage: safePageSize,
        }),
      ),
    );

    for (const response of responses) {
      rows.push(...(response.data ?? []));
    }
  }

  if (totalPages > cappedTotalPages) {
    return rows;
  }

  if ((firstResponse.data ?? []).length < safePageSize) {
    return rows;
  }

  return rows;
  })();

  inFlightPaginationRequests.set(cacheKey, requestPromise);

  try {
    return await requestPromise;
  } finally {
    inFlightPaginationRequests.delete(cacheKey);
  }
}
