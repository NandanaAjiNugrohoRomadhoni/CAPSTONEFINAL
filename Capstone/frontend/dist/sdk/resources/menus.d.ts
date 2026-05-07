import type { ApiClient } from "../client";
import type { ApiMessageDataResponse, ApiMessageResponse, CreateMenuSlotRequest, MenuSlot, MenuSlotsListResponse, MenusListResponse, UpdateMenuSlotRequest } from "../types";
/**
 * Menus SDK Resource
 *
 * Wraps:    /api/v1/menus and /api/v1/menu-dishes
 * Contract: api-contract.md §5.6.2 and §5.6.5
 * Access:   admin | gudang | dapur
 *
 * Lists fixed package menus and manages menu slot assignments.
 */
export declare class MenusResource {
    private readonly client;
    constructor(client: ApiClient);
    /**
     * Lists fixed menu package headers.
     *
     * @endpoint GET /api/v1/menus
     * @access   admin | gudang | dapur
     * @returns {Promise<MenusListResponse>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect None
     */
    list(): Promise<MenusListResponse>;
    /**
     * Lists menu slot assignments.
     *
     * @endpoint GET /api/v1/menu-dishes
     * @access   admin | gudang | dapur
     * @returns {Promise<MenuSlotsListResponse>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect None
     */
    slots(): Promise<MenuSlotsListResponse>;
    /**
     * Assigns a dish to a menu slot.
     *
     * @endpoint POST /api/v1/menu-dishes
     * @access   admin | dapur
     * @param payload - Writable fields: `menu_id`, `meal_time_id`, `dish_id`. Occupied slots are rejected; this is not an upsert endpoint.
     * @returns {Promise<ApiMessageDataResponse<MenuSlot>>}
     * @throws {ValidationApiError} if validation fails or the slot is already occupied (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect Creates a menu slot assignment.
     */
    assignSlot(payload: CreateMenuSlotRequest): Promise<ApiMessageDataResponse<MenuSlot>>;
    /**
     * Updates a menu slot assignment.
     *
     * @endpoint PUT /api/v1/menu-dishes/{id}
     * @access   admin | dapur
     * @returns {Promise<ApiMessageDataResponse<MenuSlot>>}
     * @throws {ValidationApiError} if validation fails or the target slot conflicts (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @throws {NotFoundApiError} if the slot assignment does not exist (404)
     * @sideeffect Replaces slot assignment metadata.
     */
    updateSlot(id: number, payload: UpdateMenuSlotRequest): Promise<ApiMessageDataResponse<MenuSlot>>;
    /**
     * Deletes a menu slot assignment.
     *
     * @endpoint DELETE /api/v1/menu-dishes/{id}
     * @access   admin | dapur
     * @returns {Promise<ApiMessageResponse>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @throws {NotFoundApiError} if the slot assignment does not exist (404)
     * @sideeffect Permanently deletes the slot assignment.
     */
    deleteSlot(id: number): Promise<ApiMessageResponse>;
}
