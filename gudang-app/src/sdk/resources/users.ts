import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  ApiListResponse,
  ApiMessageDataResponse,
  ApiMessageResponse,
  ChangePasswordRequest,
  CreateUserRequest,
  ListUsersQuery,
  UpdateUserRequest,
  User
} from "../types";

// Aligned with api-contract.md §5.3 and schema.md §3.2 — 2026-04-29
/**
 * Users SDK Resource
 *
 * Wraps:    /api/v1/users
 * Contract: api-contract.md §5.3
 * Access:   admin
 *
 * Manages operational user accounts and role assignments.
 */
export class UsersResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists active users with pagination, filtering, and search.
   *
   * @endpoint GET /api/v1/users
   * @access   admin
   * @param query - Supports `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `role_id`, `is_active`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted users are excluded.
   * @returns {Promise<ApiListResponse<User>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public list(query?: ListUsersQuery): Promise<ApiListResponse<User>> {
    return this.client.request<ApiListResponse<User>>({
      method: "GET",
      path: "/users",
      ...(query ? { query: buildUsersQuery(query) } : {})
    });
  }

  /**
   * Returns one active user.
   *
   * @endpoint GET /api/v1/users/{id}
   * @access   admin
   * @returns {Promise<ApiDataResponse<User>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect None
   */
  public get(id: number): Promise<ApiDataResponse<User>> {
    return this.client.request<ApiDataResponse<User>>({
      method: "GET",
      path: `/users/${id}`
    });
  }

  /**
   * Creates a user.
   *
   * @endpoint POST /api/v1/users
   * @access   admin
   * @param payload - Writable fields: `name`, `username`, `password`, optional `email`, optional `is_active`, and exactly one of `role_id` or `role_name`.
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {ValidationApiError} if validation fails, both role fields are sent, or a deleted-username collision requires restore guidance (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a new user account and synced auth state.
   */
  public create(payload: CreateUserRequest): Promise<ApiMessageDataResponse<User>> {
    return this.client.request<ApiMessageDataResponse<User>>({
      method: "POST",
      path: "/users",
      body: payload
    });
  }

  /**
   * Updates a user's profile and role assignment.
   *
   * @endpoint PUT /api/v1/users/{id}
   * @access   admin
   * @param payload - Partial update. When changing role, send exactly one of `role_id` or `role_name`.
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {ValidationApiError} if validation fails or both role fields are sent (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect Updates role/profile fields and keeps auth flags synchronized.
   */
  public update(id: number, payload: UpdateUserRequest): Promise<ApiMessageDataResponse<User>> {
    return this.client.request<ApiMessageDataResponse<User>>({
      method: "PUT",
      path: `/users/${id}`,
      body: payload
    });
  }

  /**
   * Activates a user account.
   *
   * @endpoint PATCH /api/v1/users/{id}/activate
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect Sets `is_active=true` and syncs the auth `active` flag.
   */
  public activate(id: number): Promise<ApiMessageDataResponse<User>> {
    return this.client.request<ApiMessageDataResponse<User>>({
      method: "PATCH",
      path: `/users/${id}/activate`
    });
  }

  /**
   * Deactivates a user account.
   *
   * @endpoint PATCH /api/v1/users/{id}/deactivate
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect Sets `is_active=false` and syncs the auth `active` flag. Existing tokens remain valid until separately revoked.
   */
  public deactivate(id: number): Promise<ApiMessageDataResponse<User>> {
    return this.client.request<ApiMessageDataResponse<User>>({
      method: "PATCH",
      path: `/users/${id}/deactivate`
    });
  }

  /**
   * Changes another user's password.
   *
   * @endpoint PATCH /api/v1/users/{id}/password
   * @access   admin
   * @param payload - Writable fields: `password` only.
   * @returns {Promise<ApiMessageResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is soft-deleted (404)
   * @sideeffect Revokes all access tokens for the target user.
   */
  public changePassword(id: number, payload: ChangePasswordRequest): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "PATCH",
      path: `/users/${id}/password`,
      body: payload
    });
  }

  /**
   * Soft-deletes a user.
   *
   * @endpoint DELETE /api/v1/users/{id}
   * @access   admin
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist or is already soft-deleted (404)
   * @sideeffect Sets `deleted_at` and revokes all access tokens for the target user.
   */
  public delete(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: `/users/${id}`
    });
  }

  /**
   * Restores a soft-deleted user.
   *
   * @endpoint PATCH /api/v1/users/{id}/restore
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<User>>}
   * @throws {ValidationApiError} if an active user already owns the username or the assigned role is inactive (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the user does not exist (404)
   * @sideeffect Clears `deleted_at`. If the user is already active, backend returns the current resource idempotently.
   */
  public restore(id: number): Promise<ApiMessageDataResponse<User>> {
    return this.client.request<ApiMessageDataResponse<User>>({
      method: "PATCH",
      path: `/users/${id}/restore`
    });
  }
}

function buildUsersQuery(query: ListUsersQuery): Record<string, string | number | boolean> {
  const result: Record<string, string | number | boolean> = {};

  if (query.page !== undefined) result.page = query.page;
  if (query.perPage !== undefined) result.perPage = query.perPage;
  if (query.q !== undefined) result.q = query.q;
  if (query.search !== undefined) result.search = query.search;
  if (query.sortBy !== undefined) result.sortBy = query.sortBy;
  if (query.sortDir !== undefined) result.sortDir = query.sortDir;
  if (query.role_id !== undefined) result.role_id = query.role_id;
  if (query.is_active !== undefined) result.is_active = query.is_active;
  if (query.created_at_from !== undefined) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== undefined) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== undefined) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== undefined) result.updated_at_to = query.updated_at_to;

  return result;
}
