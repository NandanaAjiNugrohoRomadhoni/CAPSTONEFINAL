import type { ApiClient } from "../client";
import type { ApiDataResponse, ApiListResponse, ApiMessageDataResponse, ApiMessageResponse, ItemUnit, LookupListQuery, LookupNameRequest } from "../types";
/**
 * ItemUnits SDK Resource
 *
 * Wraps:    /api/v1/item-units
 * Contract: api-contract.md §5.2.4
 * Access:   admin | gudang
 *
 * Manages FK-backed item-unit lookups used by item unit resolution.
 */
export declare class ItemUnitsResource {
    private readonly client;
    constructor(client: ApiClient);
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
    list(query?: LookupListQuery): Promise<ApiListResponse<ItemUnit>>;
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
    get(id: number): Promise<ApiDataResponse<ItemUnit>>;
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
    create(payload: LookupNameRequest): Promise<ApiMessageDataResponse<ItemUnit>>;
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
    update(id: number, payload: LookupNameRequest): Promise<ApiMessageDataResponse<ItemUnit>>;
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
    delete(id: number): Promise<ApiMessageResponse>;
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
    restore(id: number): Promise<ApiMessageDataResponse<ItemUnit>>;
}
