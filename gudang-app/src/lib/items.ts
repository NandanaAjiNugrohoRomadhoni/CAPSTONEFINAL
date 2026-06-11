"use client";

import sdk from "@/lib";
import type { Item, ListItemsQuery } from "@/sdk/types";
import { listAllPaginatedRows } from "@/lib/pagination";

export async function listAllItems(query: ListItemsQuery = {}) {
  return listAllPaginatedRows<Item>(
    sdk.items.list.bind(sdk.items),
    query as Record<string, unknown>,
    Number(query.perPage ?? 100),
  );
}
