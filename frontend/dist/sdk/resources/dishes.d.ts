import type { ApiClient } from "../client";
import type { ApiDataResponse, ApiMessageDataResponse, ApiMessageResponse, CreateDishRequest, Dish, DishesListResponse, ListDishesQuery, UpdateDishRequest } from "../types";
/**
 * Dishes SDK Resource
 *
 * Wraps:    /api/v1/dishes
 * Contract: api-contract.md §5.6.2
 * Access:   admin | gudang | dapur
 *
 * Manages dish master data used by menu slots.
 */
export declare class DishesResource {
    private readonly client;
    constructor(client: ApiClient);
    /** @endpoint GET /api/v1/dishes @access admin | gudang | dapur @param query - Supports standard list pagination, search, sorting, and created/updated date ranges. @returns {Promise<DishesListResponse>} @throws {ValidationApiError} if query validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect None */
    list(query?: ListDishesQuery): Promise<DishesListResponse>;
    /** @endpoint GET /api/v1/dishes/{id} @access admin | gudang | dapur @returns {Promise<ApiDataResponse<Dish>>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect None */
    get(id: number): Promise<ApiDataResponse<Dish>>;
    /** @endpoint POST /api/v1/dishes @access admin | dapur @returns {Promise<ApiMessageDataResponse<Dish>>} @throws {ValidationApiError} if validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect Creates a dish row. */
    create(payload: CreateDishRequest): Promise<ApiMessageDataResponse<Dish>>;
    /** @endpoint PUT /api/v1/dishes/{id} @access admin | dapur @returns {Promise<ApiMessageDataResponse<Dish>>} @throws {ValidationApiError} if validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect Updates a dish row. */
    update(id: number, payload: UpdateDishRequest): Promise<ApiMessageDataResponse<Dish>>;
    /** @endpoint DELETE /api/v1/dishes/{id} @access admin | dapur @returns {Promise<ApiMessageResponse>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect Permanently deletes the dish row. */
    delete(id: number): Promise<ApiMessageResponse>;
}
