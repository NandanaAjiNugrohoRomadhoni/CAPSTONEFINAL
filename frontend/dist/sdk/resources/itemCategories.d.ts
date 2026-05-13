import type { ApiClient } from "../client";
import type { ApiDataResponse, ApiListResponse, ApiMessageDataResponse, ApiMessageResponse, ItemCategory, LookupListQuery, LookupNameRequest } from "../types";
/**
 * ItemCategories SDK Resource
 *
 * Wraps:    /api/v1/item-categories
 * Contract: api-contract.md §5.2.1
 * Access:   admin | gudang
 *
 * Manages item category lookups used by item master and SPK categorization.
 */
export declare class ItemCategoriesResource {
    private readonly client;
    constructor(client: ApiClient);
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
    list(query?: LookupListQuery): Promise<ApiListResponse<ItemCategory>>;
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
    get(id: number): Promise<ApiDataResponse<ItemCategory>>;
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
    create(payload: LookupNameRequest): Promise<ApiMessageDataResponse<ItemCategory>>;
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
    update(id: number, payload: LookupNameRequest): Promise<ApiMessageDataResponse<ItemCategory>>;
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
    delete(id: number): Promise<ApiMessageResponse>;
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
    restore(id: number): Promise<ApiMessageDataResponse<ItemCategory>>;
}
