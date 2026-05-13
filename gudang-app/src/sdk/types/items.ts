import type { XOR } from "./common";

/** Nested item-category summary returned in item responses. */
export interface ItemCategorySummary {
  id: number;
  name: string | null;
}

/**
 * Item response model used by implemented item endpoints.
 * Endpoint: `/api/v1/items*`
 * Contract: api-contract.md §5.4
 */
export interface Item {
  /** Backend-managed identifier. Do not send in requests. */
  id: number;
  item_category_id: number;
  name: string;
  /** String form still persisted for backward compatibility and resolved against active `item_units`. */
  unit_base: string;
  /** String form still persisted for backward compatibility and resolved against active `item_units`. */
  unit_convert: string;
  item_unit_base_id: number | null;
  item_unit_convert_id: number | null;
  conversion_base: number;
  min_stock: number;
  /** Backend-managed running stock balance in base units. Read-only in item master endpoints. */
  qty: string;
  is_active: boolean;
  /** Backend-managed. Do not send in requests. */
  created_at: string;
  /** Backend-managed. Do not send in requests. */
  updated_at: string;
  category: ItemCategorySummary;
  item_unit_base?: { id: number | null; name: string | null } | null;
  item_unit_convert?: { id: number | null; name: string | null } | null;
}

/** Type-level XOR for item category lookup: send `item_category_id` OR `item_category_name`, not both. */
type ItemCategoryIdentifier = XOR<{ item_category_id: number }, { item_category_name: string }>;
type OptionalItemCategoryIdentifier = ItemCategoryIdentifier | { item_category_id?: undefined; item_category_name?: undefined };

/** Query params for `GET /api/v1/items`. Unknown params return 400. */
export interface ListItemsQuery {
  page?: number;
  perPage?: number;
  item_category_id?: number;
  is_active?: boolean;
  q?: string;
  search?: string;
  sortBy?: "id" | "name" | "item_category_id" | "created_at" | "updated_at";
  sortDir?: "ASC" | "DESC";
  created_at_from?: string;
  created_at_to?: string;
  updated_at_from?: string;
  updated_at_to?: string;
}

/**
 * Request payload for `POST /api/v1/items`.
 * Send exactly one of `item_category_id` or `item_category_name`.
 * `unit_base` and `unit_convert` must resolve to active `item_units` rows.
 */
export type CreateItemRequest = ItemCategoryIdentifier & {
  name: string;
  unit_base: string;
  unit_convert: string;
  conversion_base: number;
  min_stock?: number;
  is_active?: boolean;
};

/**
 * Request payload for `PUT /api/v1/items/{id}`.
 * Partial-update semantics apply. When changing category, send exactly one of `item_category_id` or `item_category_name`.
 */
export type UpdateItemRequest = OptionalItemCategoryIdentifier & {
  name?: string;
  unit_base?: string;
  unit_convert?: string;
  conversion_base?: number;
  min_stock?: number;
  is_active?: boolean;
};
