import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  ApiListResponse,
  ApiMessageDataResponse,
  ApiMessageResponse,
  CreateItemRequest,
  Item,
  ListItemsQuery,
  UpdateItemRequest
} from "../types";

// Aligned with api-contract.md §5.4 and schema.md §3.10 — 2026-04-29
/**
 * Items SDK Resource
 *
 * Wraps:    /api/v1/items
 * Contract: api-contract.md §5.4
 * Access:   admin | gudang
 *
 * Manages item master data while leaving stock mutation to stock workflows.
 */
export class ItemsResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists active items with pagination, filtering, and search.
   *
   * @endpoint GET /api/v1/items
   * @access   admin | gudang
   * @param query - Supports `page`, `perPage`, `item_category_id`, `is_active`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted items are excluded.
   * @returns {Promise<ApiListResponse<Item>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public list(query?: ListItemsQuery): Promise<ApiListResponse<Item>> {
    return this.client.request<ApiListResponse<Item>>({
      method: "GET",
      path: "/items",
      ...(query ? { query: buildItemsQuery(query) } : {})
    });
  }

  /**
   * Returns one active item.
   *
   * @endpoint GET /api/v1/items/{id}
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<Item>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the item does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  public get(id: number): Promise<ApiDataResponse<Item>> {
    return this.client.request<ApiDataResponse<Item>>({
      method: "GET",
      path: `/items/${id}`
    });
  }

  /**
   * Creates an item.
   *
   * @endpoint POST /api/v1/items
   * @access   admin | gudang
   * @param payload - Writable fields: `name`, `unit_base`, `unit_convert`, `conversion_base`, `min_stock`, `is_active`, and exactly one of `item_category_id` or `item_category_name`. `unit_base` and `unit_convert` resolve case-insensitively to active `item_units` rows and are still persisted as strings for backward compatibility. `qty`, `id`, and timestamps are backend-managed.
   * @returns {Promise<ApiMessageDataResponse<Item>>}
   * @throws {ValidationApiError} if validation fails, both category fields are sent, units are inactive/missing, or a deleted-name collision requires restore guidance (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None; stock is not mutated here and `qty` remains backend-controlled.
   */
  public create(payload: CreateItemRequest): Promise<ApiMessageDataResponse<Item>> {
    return this.client.request<ApiMessageDataResponse<Item>>({
      method: "POST",
      path: "/items",
      body: payload
    });
  }

  /**
   * Updates an item using the backend's partial-update semantics.
   *
   * @endpoint PUT /api/v1/items/{id}
   * @access   admin | gudang
   * @param payload - Partial update. When changing category, send exactly one of `item_category_id` or `item_category_name`. If `unit_base` or `unit_convert` is sent, each must resolve to an active item unit.
   * @returns {Promise<ApiMessageDataResponse<Item>>}
   * @throws {ValidationApiError} if validation fails, both category fields are sent, or unit/category constraints fail (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the item does not exist or is soft-deleted (404)
   * @sideeffect None; stock is not mutated here and `qty` remains backend-controlled.
   */
  public update(id: number, payload: UpdateItemRequest): Promise<ApiMessageDataResponse<Item>> {
    return this.client.request<ApiMessageDataResponse<Item>>({
      method: "PUT",
      path: `/items/${id}`,
      body: payload
    });
  }

  /**
   * Soft-deletes an item.
   *
   * @endpoint DELETE /api/v1/items/{id}
   * @access   admin
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the item does not exist or is already soft-deleted (404)
   * @sideeffect Sets `deleted_at`; the row remains restorable.
   */
  public delete(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: `/items/${id}`
    });
  }

  /**
   * Restores a soft-deleted item.
   *
   * @endpoint PATCH /api/v1/items/{id}/restore
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<Item>>}
   * @throws {ValidationApiError} if an active item already owns the name or the referenced category/units are inactive (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the item does not exist (404)
   * @sideeffect Clears `deleted_at`. If the item is already active, backend returns the current resource idempotently.
   */
  public restore(id: number): Promise<ApiMessageDataResponse<Item>> {
    return this.client.request<ApiMessageDataResponse<Item>>({
      method: "PATCH",
      path: `/items/${id}/restore`
    });
  }
}

function buildItemsQuery(query: ListItemsQuery): Record<string, string | number | boolean> {
  const result: Record<string, string | number | boolean> = {};

  if (query.page !== undefined) {
    result.page = query.page;
  }

  if (query.perPage !== undefined) {
    result.perPage = query.perPage;
  }

  if (query.item_category_id !== undefined) {
    result.item_category_id = query.item_category_id;
  }

  if (query.is_active !== undefined) {
    result.is_active = query.is_active;
  }

  if (query.q !== undefined) {
    result.q = query.q;
  }

  if (query.search !== undefined) {
    result.search = query.search;
  }

  if (query.sortBy !== undefined) {
    result.sortBy = query.sortBy;
  }

  if (query.sortDir !== undefined) {
    result.sortDir = query.sortDir;
  }

  if (query.created_at_from !== undefined) {
    result.created_at_from = query.created_at_from;
  }

  if (query.created_at_to !== undefined) {
    result.created_at_to = query.created_at_to;
  }

  if (query.updated_at_from !== undefined) {
    result.updated_at_from = query.updated_at_from;
  }

  if (query.updated_at_to !== undefined) {
    result.updated_at_to = query.updated_at_to;
  }

  return result;
}
