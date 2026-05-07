import type { ApiClient } from "../client";
import type { ApiDataResponse, ApiListResponse, ApiMessageDataResponse, ApiMessageResponse, CreateItemRequest, Item, ListItemsQuery, UpdateItemRequest } from "../types";
/**
 * Items SDK Resource
 *
 * Wraps:    /api/v1/items
 * Contract: api-contract.md §5.4
 * Access:   admin | gudang
 *
 * Manages item master data while leaving stock mutation to stock workflows.
 */
export declare class ItemsResource {
    private readonly client;
    constructor(client: ApiClient);
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
    list(query?: ListItemsQuery): Promise<ApiListResponse<Item>>;
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
    get(id: number): Promise<ApiDataResponse<Item>>;
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
    create(payload: CreateItemRequest): Promise<ApiMessageDataResponse<Item>>;
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
    update(id: number, payload: UpdateItemRequest): Promise<ApiMessageDataResponse<Item>>;
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
    delete(id: number): Promise<ApiMessageResponse>;
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
    restore(id: number): Promise<ApiMessageDataResponse<Item>>;
}
