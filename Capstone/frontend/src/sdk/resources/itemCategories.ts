import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  ApiListResponse,
  ApiMessageDataResponse,
  ApiMessageResponse,
  ItemCategory,
  LookupListQuery,
  LookupNameRequest
} from "../types";

// Aligned with api-contract.md §5.2.1 and schema.md §2.1 — 2026-04-29
/**
 * ItemCategories SDK Resource
 *
 * Wraps:    /api/v1/item-categories
 * Contract: api-contract.md §5.2.1
 * Access:   admin | gudang
 *
 * Manages item category lookups used by item master and SPK categorization.
 */
export class ItemCategoriesResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists item categories with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/item-categories
   * @access   admin | gudang
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<ItemCategory>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public list(query?: LookupListQuery): Promise<ApiListResponse<ItemCategory>> {
    return this.client.request<ApiListResponse<ItemCategory>>({
      method: "GET",
      path: "/item-categories",
      ...(query ? { query: buildLookupQuery(query) } : {})
    });
  }

  /**
   * Returns one active item category.
   *
   * @endpoint GET /api/v1/item-categories/{id}
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<ItemCategory>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the category does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  public get(id: number): Promise<ApiDataResponse<ItemCategory>> {
    return this.client.request<ApiDataResponse<ItemCategory>>({
      method: "GET",
      path: `/item-categories/${id}`
    });
  }

  /**
   * Creates an item category.
   *
   * @endpoint POST /api/v1/item-categories
   * @access   admin
   * @param payload - Writable fields: `name`. Name uniqueness applies to active rows only; if a deleted-name collision exists, backend returns restore guidance with `errors.restore_id`.
   * @returns {Promise<ApiMessageDataResponse<ItemCategory>>}
   * @throws {ValidationApiError} if validation fails or the name conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public create(payload: LookupNameRequest): Promise<ApiMessageDataResponse<ItemCategory>> {
    return this.client.request<ApiMessageDataResponse<ItemCategory>>({
      method: "POST",
      path: "/item-categories",
      body: payload
    });
  }

  /**
   * Updates an active item category.
   *
   * @endpoint PUT /api/v1/item-categories/{id}
   * @access   admin
   * @param payload - Writable fields: `name`. Active-only uniqueness rules still apply.
   * @returns {Promise<ApiMessageDataResponse<ItemCategory>>}
   * @throws {ValidationApiError} if validation fails or the name conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the category does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  public update(id: number, payload: LookupNameRequest): Promise<ApiMessageDataResponse<ItemCategory>> {
    return this.client.request<ApiMessageDataResponse<ItemCategory>>({
      method: "PUT",
      path: `/item-categories/${id}`,
      body: payload
    });
  }

  /**
   * Soft-deletes an item category.
   *
   * @endpoint DELETE /api/v1/item-categories/{id}
   * @access   admin
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the category does not exist or is already soft-deleted (404)
   * @sideeffect Sets `deleted_at`; the row remains restorable.
   */
  public delete(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: `/item-categories/${id}`
    });
  }

  /**
   * Restores a soft-deleted item category.
   *
   * @endpoint PATCH /api/v1/item-categories/{id}/restore
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<ItemCategory>>}
   * @throws {ValidationApiError} if restore fails because an active row already owns the name (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the category does not exist (404)
   * @sideeffect Clears `deleted_at` when restore succeeds.
   */
  public restore(id: number): Promise<ApiMessageDataResponse<ItemCategory>> {
    return this.client.request<ApiMessageDataResponse<ItemCategory>>({
      method: "PATCH",
      path: `/item-categories/${id}/restore`
    });
  }
}

function buildLookupQuery(query: LookupListQuery): Record<string, string | number> {
  const result: Record<string, string | number> = {};

  if (query.paginate !== undefined) result.paginate = query.paginate ? "true" : "false";
  if (query.page !== undefined) result.page = query.page;
  if (query.perPage !== undefined) result.perPage = query.perPage;
  if (query.q !== undefined) result.q = query.q;
  if (query.search !== undefined) result.search = query.search;
  if (query.sortBy !== undefined) result.sortBy = query.sortBy;
  if (query.sortDir !== undefined) result.sortDir = query.sortDir;
  if (query.created_at_from !== undefined) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== undefined) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== undefined) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== undefined) result.updated_at_to = query.updated_at_to;

  return result;
}
