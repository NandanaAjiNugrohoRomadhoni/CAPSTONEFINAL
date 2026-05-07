import type { ApiClient } from "../client";
import type { ApiDataResponse, ApiMessageDataResponse, ApiMessageResponse, CreateDishCompositionRequest, DishComposition, DishCompositionsListResponse, ListDishCompositionsQuery, UpdateDishCompositionRequest } from "../types";
/**
 * DishCompositions SDK Resource
 *
 * Wraps:    /api/v1/dish-compositions
 * Contract: api-contract.md §5.6.3
 * Access:   admin | gudang | dapur
 *
 * Manages per-dish item composition rows.
 */
export declare class DishCompositionsResource {
    private readonly client;
    constructor(client: ApiClient);
    /** @endpoint GET /api/v1/dish-compositions @access admin | gudang | dapur @param query - Supports standard list pagination, `dish_id`, `item_id`, search, sorting, and created/updated date ranges. @returns {Promise<DishCompositionsListResponse>} @throws {ValidationApiError} if query validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect None */
    list(query?: ListDishCompositionsQuery): Promise<DishCompositionsListResponse>;
    /** @endpoint GET /api/v1/dish-compositions/{id} @access admin | gudang | dapur @returns {Promise<ApiDataResponse<DishComposition>>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect None */
    get(id: number): Promise<ApiDataResponse<DishComposition>>;
    /** @endpoint POST /api/v1/dish-compositions @access admin | dapur @returns {Promise<ApiMessageDataResponse<DishComposition>>} @throws {ValidationApiError} if validation fails or a dish/item pair already exists (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect Creates a composition row. */
    create(payload: CreateDishCompositionRequest): Promise<ApiMessageDataResponse<DishComposition>>;
    /** @endpoint PUT /api/v1/dish-compositions/{id} @access admin | dapur @returns {Promise<ApiMessageDataResponse<DishComposition>>} @throws {ValidationApiError} if validation fails or uniqueness rules fail (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect Updates a composition row. */
    update(id: number, payload: UpdateDishCompositionRequest): Promise<ApiMessageDataResponse<DishComposition>>;
    /** @endpoint DELETE /api/v1/dish-compositions/{id} @access admin | dapur @returns {Promise<ApiMessageResponse>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect Permanently deletes the composition row. */
    delete(id: number): Promise<ApiMessageResponse>;
}
