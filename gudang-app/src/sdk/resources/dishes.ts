import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  ApiMessageDataResponse,
  ApiMessageResponse,
  CreateDishRequest,
  Dish,
  DishesListResponse,
  ListDishesQuery,
  UpdateDishRequest
} from "../types";

// Aligned with api-contract.md §5.6.2 — 2026-04-29
/**
 * Dishes SDK Resource
 *
 * Wraps:    /api/v1/dishes
 * Contract: api-contract.md §5.6.2
 * Access:   admin | gudang | dapur
 *
 * Manages dish master data used by menu slots.
 */
export class DishesResource {
  public constructor(private readonly client: ApiClient) {}

  /** @endpoint GET /api/v1/dishes @access admin | gudang | dapur @param query - Supports standard list pagination, search, sorting, and created/updated date ranges. @returns {Promise<DishesListResponse>} @throws {ValidationApiError} if query validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect None */
  public list(query?: ListDishesQuery): Promise<DishesListResponse> {
    return this.client.request<DishesListResponse>({
      method: "GET",
      path: "/dishes",
      ...(query ? { query: buildDishesQuery(query) } : {})
    });
  }

  /** @endpoint GET /api/v1/dishes/{id} @access admin | gudang | dapur @returns {Promise<ApiDataResponse<Dish>>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect None */
  public get(id: number): Promise<ApiDataResponse<Dish>> {
    return this.client.request<ApiDataResponse<Dish>>({
      method: "GET",
      path: `/dishes/${id}`
    });
  }

  /** @endpoint POST /api/v1/dishes @access admin | dapur @returns {Promise<ApiMessageDataResponse<Dish>>} @throws {ValidationApiError} if validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect Creates a dish row. */
  public create(payload: CreateDishRequest): Promise<ApiMessageDataResponse<Dish>> {
    return this.client.request<ApiMessageDataResponse<Dish>>({
      method: "POST",
      path: "/dishes",
      body: payload
    });
  }

  /** @endpoint PUT /api/v1/dishes/{id} @access admin | dapur @returns {Promise<ApiMessageDataResponse<Dish>>} @throws {ValidationApiError} if validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect Updates a dish row. */
  public update(id: number, payload: UpdateDishRequest): Promise<ApiMessageDataResponse<Dish>> {
    return this.client.request<ApiMessageDataResponse<Dish>>({
      method: "PUT",
      path: `/dishes/${id}`,
      body: payload
    });
  }

  /** @endpoint DELETE /api/v1/dishes/{id} @access admin | dapur @returns {Promise<ApiMessageResponse>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the dish does not exist (404) @sideeffect Permanently deletes the dish row. */
  public delete(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: `/dishes/${id}`
    });
  }
}

function buildDishesQuery(query: ListDishesQuery): Record<string, string | number> {
  const result: Record<string, string | number> = {};

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
