"use client";

import sdk from "@/lib";
import type { Item, ListItemsQuery } from "@/sdk/types";

export async function listAllItems(query: ListItemsQuery = {}) {
  const perPage = Math.min(Math.max(Number(query.perPage ?? 100), 1), 100);
  const rows: Item[] = [];

  for (let page = 1; page <= 50; page += 1) {
    const response = await sdk.items.list({
      ...query,
      page,
      perPage,
    });

    rows.push(...(response.data ?? []));

    const totalPages = Number(response.meta?.totalPages ?? 0);
    if (totalPages > 0 && page >= totalPages) break;
    if ((response.data ?? []).length < perPage) break;
  }

  return rows;
}
