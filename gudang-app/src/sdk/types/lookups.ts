/** Shared lookup row shape returned by implemented lookup endpoints. */
export interface LookupResource {
  id: number;
  name: string;
  created_at?: string | null;
  updated_at?: string | null;
}

/**
 * Query params for implemented lookup list endpoints:
 * `/item-categories`, `/item-units`, `/transaction-types`, `/approval-statuses`, `/roles`, and `/meal-times`.
 * `paginate=false` keeps the same `data/meta/links` envelope with `meta.paginated=false`.
 */
export interface LookupListQuery {
  paginate?: boolean;
  page?: number;
  perPage?: number;
  q?: string;
  search?: string;
  sortBy?: "id" | "name" | "created_at" | "updated_at";
  sortDir?: "ASC" | "DESC";
  created_at_from?: string;
  created_at_to?: string;
  updated_at_from?: string;
  updated_at_to?: string;
}

/** Request payload for lookup create/update endpoints that only accept `name`. */
export interface LookupNameRequest {
  name: string;
}

/** Query params for `GET /api/v1/roles`. */
export type RoleListQuery = LookupListQuery;
/** Response row for `/api/v1/item-categories`. */
export type ItemCategory = LookupResource;
/** Response row for `/api/v1/item-units`. */
export type ItemUnit = LookupResource;
/** Response row for `/api/v1/transaction-types`. */
export type TransactionType = LookupResource;
/** Response row for `/api/v1/approval-statuses`. */
export type ApprovalStatus = LookupResource;
/** Response row for `/api/v1/meal-times`. */
export type MealTime = LookupResource;
