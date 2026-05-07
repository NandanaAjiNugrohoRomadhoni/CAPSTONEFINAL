import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  ApiListResponse,
  ApiMessageDataResponse,
  ApiMessageResponse,
  ItemUnit,
  LookupListQuery,
  LookupNameRequest
} from "../types";

// Aligned with api-contract.md §5.2.4 and schema.md §2.5 — 2026-04-29
/**
 * ItemUnits SDK Resource
 *
 * Wraps:    /api/v1/item-units
 * Contract: api-contract.md §5.2.4
 * Access:   admin | gudang
 *
 * Manages FK-backed item-unit lookups used by item unit resolution.
 */
export class ItemUnitsResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists item units with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/item-units
   * @access   admin | gudang
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<ItemUnit>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public list(query?: LookupListQuery): Promise<ApiListResponse<ItemUnit>> {
    return this.client.request<ApiListResponse<ItemUnit>>({
      method: "GET",
      path: "/item-units",
      ...(query ? { query: buildLookupQuery(query) } : {})
    });
  }

  /**
   * Returns one active item unit.
   *
   * @endpoint GET /api/v1/item-units/{id}
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<ItemUnit>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the unit does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  public get(id: number): Promise<ApiDataResponse<ItemUnit>> {
    return this.client.request<ApiDataResponse<ItemUnit>>({
      method: "GET",
      path: `/item-units/${id}`
    });
  }

  /**
   * Creates an item unit.
   *
   * @endpoint POST /api/v1/item-units
   * @access   admin
   * @param payload - Writable fields: `name`. Name uniqueness applies to active rows only; if a deleted-name collision exists, backend returns restore guidance with `errors.restore_id`.
   * @returns {Promise<ApiMessageDataResponse<ItemUnit>>}
   * @throws {ValidationApiError} if validation fails or the name conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public create(payload: LookupNameRequest): Promise<ApiMessageDataResponse<ItemUnit>> {
    return this.client.request<ApiMessageDataResponse<ItemUnit>>({
      method: "POST",
      path: "/item-units",
      body: payload
    });
  }

  /**
   * Updates an active item unit.
   *
   * @endpoint PUT /api/v1/item-units/{id}
   * @access   admin
   * @param payload - Writable fields: `name`. Active-only uniqueness rules still apply.
   * @returns {Promise<ApiMessageDataResponse<ItemUnit>>}
   * @throws {ValidationApiError} if validation fails or the name conflicts (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the unit does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  public update(id: number, payload: LookupNameRequest): Promise<ApiMessageDataResponse<ItemUnit>> {
    return this.client.request<ApiMessageDataResponse<ItemUnit>>({
      method: "PUT",
      path: `/item-units/${id}`,
      body: payload
    });
  }

  /**
   * Soft-deletes an item unit.
   *
   * @endpoint DELETE /api/v1/item-units/{id}
   * @access   admin
   * @returns {Promise<ApiMessageResponse>}
   * @throws {ValidationApiError} if active items still reference the unit (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the unit does not exist or is already soft-deleted (404)
   * @sideeffect Sets `deleted_at`; the row remains restorable.
   */
  public delete(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: `/item-units/${id}`
    });
  }

  /**
   * Restores a soft-deleted item unit.
   *
   * @endpoint PATCH /api/v1/item-units/{id}/restore
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<ItemUnit>>}
   * @throws {ValidationApiError} if restore fails because an active row already owns the name (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the unit does not exist (404)
   * @sideeffect Clears `deleted_at` when restore succeeds.
   */
  public restore(id: number): Promise<ApiMessageDataResponse<ItemUnit>> {
    return this.client.request<ApiMessageDataResponse<ItemUnit>>({
      method: "PATCH",
      path: `/item-units/${id}/restore`
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
