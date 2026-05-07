import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  ApiMessageDataResponse,
  ApiMessageResponse,
  CreateDishCompositionRequest,
  DishComposition,
  DishCompositionsListResponse,
  ListDishCompositionsQuery,
  UpdateDishCompositionRequest
} from "../types";

// Aligned with api-contract.md §5.6.3 — 2026-04-29
/**
 * DishCompositions SDK Resource
 *
 * Wraps:    /api/v1/dish-compositions
 * Contract: api-contract.md §5.6.3
 * Access:   admin | gudang | dapur
 *
 * Manages per-dish item composition rows.
 */
export class DishCompositionsResource {
  public constructor(private readonly client: ApiClient) {}

  /** @endpoint GET /api/v1/dish-compositions @access admin | gudang | dapur @param query - Supports standard list pagination, `dish_id`, `item_id`, search, sorting, and created/updated date ranges. @returns {Promise<DishCompositionsListResponse>} @throws {ValidationApiError} if query validation fails (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect None */
  public list(query?: ListDishCompositionsQuery): Promise<DishCompositionsListResponse> {
    return this.client.request<DishCompositionsListResponse>({
      method: "GET",
      path: "/dish-compositions",
      ...(query ? { query: buildDishCompositionsQuery(query) } : {})
    });
  }

  /** @endpoint GET /api/v1/dish-compositions/{id} @access admin | gudang | dapur @returns {Promise<ApiDataResponse<DishComposition>>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect None */
  public get(id: number): Promise<ApiDataResponse<DishComposition>> {
    return this.client.request<ApiDataResponse<DishComposition>>({
      method: "GET",
      path: `/dish-compositions/${id}`
    });
  }

  /** @endpoint POST /api/v1/dish-compositions @access admin | dapur @returns {Promise<ApiMessageDataResponse<DishComposition>>} @throws {ValidationApiError} if validation fails or a dish/item pair already exists (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @sideeffect Creates a composition row. */
  public create(payload: CreateDishCompositionRequest): Promise<ApiMessageDataResponse<DishComposition>> {
    return this.client.request<ApiMessageDataResponse<DishComposition>>({
      method: "POST",
      path: "/dish-compositions",
      body: payload
    });
  }

  /** @endpoint PUT /api/v1/dish-compositions/{id} @access admin | dapur @returns {Promise<ApiMessageDataResponse<DishComposition>>} @throws {ValidationApiError} if validation fails or uniqueness rules fail (400) @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect Updates a composition row. */
  public update(id: number, payload: UpdateDishCompositionRequest): Promise<ApiMessageDataResponse<DishComposition>> {
    return this.client.request<ApiMessageDataResponse<DishComposition>>({
      method: "PUT",
      path: `/dish-compositions/${id}`,
      body: payload
    });
  }

  /** @endpoint DELETE /api/v1/dish-compositions/{id} @access admin | dapur @returns {Promise<ApiMessageResponse>} @throws {AuthenticationApiError} if no valid Bearer token is provided (401) @throws {AuthorizationApiError} if the caller lacks the required role (403) @throws {NotFoundApiError} if the row does not exist (404) @sideeffect Permanently deletes the composition row. */
  public delete(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: `/dish-compositions/${id}`
    });
  }
}

function buildDishCompositionsQuery(query: ListDishCompositionsQuery): Record<string, string | number> {
  const result: Record<string, string | number> = {};

  if (query.page !== undefined) result.page = query.page;
  if (query.perPage !== undefined) result.perPage = query.perPage;
  if (query.dish_id !== undefined) result.dish_id = query.dish_id;
  if (query.item_id !== undefined) result.item_id = query.item_id;
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
