import type { ApiClient } from "../client";
import type { ApiDataResponse, ApiListResponse, ApiMessageDataResponse, ApiMessageResponse, ChangePasswordRequest, CreateUserRequest, ListUsersQuery, UpdateUserRequest, User } from "../types";
/**
 * Users SDK Resource
 *
 * Wraps:    /api/v1/users
 * Contract: api-contract.md §5.3
 * Access:   admin
 *
 * Manages operational user accounts and role assignments.
 */
export declare class UsersResource {
    private readonly client;
    constructor(client: ApiClient);
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
    list(query?: ListUsersQuery): Promise<ApiListResponse<User>>;
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
    get(id: number): Promise<ApiDataResponse<User>>;
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
    create(payload: CreateUserRequest): Promise<ApiMessageDataResponse<User>>;
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
    update(id: number, payload: UpdateUserRequest): Promise<ApiMessageDataResponse<User>>;
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
    activate(id: number): Promise<ApiMessageDataResponse<User>>;
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
    deactivate(id: number): Promise<ApiMessageDataResponse<User>>;
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
    changePassword(id: number, payload: ChangePasswordRequest): Promise<ApiMessageResponse>;
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
    delete(id: number): Promise<ApiMessageResponse>;
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
    restore(id: number): Promise<ApiMessageDataResponse<User>>;
}
