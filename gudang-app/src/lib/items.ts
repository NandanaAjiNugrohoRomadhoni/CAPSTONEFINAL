"use client";

import sdk from "@/lib";
import type { Item, ListItemsQuery } from "@/sdk/types";
import { listAllPaginatedRows } from "@/lib/pagination";

const itemsCache = new Map<string, Item[]>();

function normalizeQueryKey(query: ListItemsQuery) {
  const entries = Object.entries(query)
    .filter(([, value]) => value !== undefined && value !== null && value !== "")
    .sort(([leftKey], [rightKey]) => leftKey.localeCompare(rightKey));

  return JSON.stringify(entries);
}

export async function listAllItems(query: ListItemsQuery = {}) {
  const cacheKey = normalizeQueryKey(query);
  const cachedRows = itemsCache.get(cacheKey);
  if (cachedRows) {
    return [...cachedRows];
  }

  return listAllPaginatedRows<Item>(
    sdk.items.list.bind(sdk.items),
    query as Record<string, unknown>,
    Number(query.perPage ?? 100),
  ).then((rows) => {
    itemsCache.set(cacheKey, rows);
    return [...rows];
  });
}
