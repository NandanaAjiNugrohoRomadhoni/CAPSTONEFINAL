import type { ApiDataResponse, ApiMessageResponse, LoginRequest, LoginResponse, SelfServiceChangePasswordRequest, User } from "../types";
import type { ApiClient } from "../client";
/**
 * Auth SDK Resource
 *
 * Wraps:    /api/v1/auth/*
 * Contract: api-contract.md §5.1
 * Access:   public | authenticated
 *
 * Handles login, current-session inspection, logout, and self-service password changes.
 * Two-layer auth model: Shield Groups are system-level (`admin`, `developer`, `user`, `beta`), while App Roles are operational (`admin`, `dapur`, `gudang`) from the `roles` table and enforced by `RoleFilter`.
 */
export declare class AuthResource {
    private readonly client;
    constructor(client: ApiClient);
    /**
     * Logs a user in with username and password.
     *
     * @endpoint POST /api/v1/auth/login
     * @access   public
     * @param payload - Required fields: `username`, `password`.
     * @returns {Promise<LoginResponse>}
     * @throws {ValidationApiError} if required credentials are missing or invalid (400)
     * @throws {AuthenticationApiError} if credentials are rejected (401)
     * @sideeffect Issues a new Bearer access token on success.
     */
    login(payload: LoginRequest): Promise<LoginResponse>;
    /**
     * Returns the current authenticated user profile from the Bearer token.
     *
     * @endpoint GET /api/v1/auth/me
     * @access   authenticated
     * @returns {Promise<ApiDataResponse<User>>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @sideeffect None
     */
    me(): Promise<ApiDataResponse<User>>;
    /**
     * Logs out the current Bearer token.
     *
     * @endpoint POST /api/v1/auth/logout
     * @access   authenticated
     * @returns {Promise<ApiMessageResponse>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @sideeffect Revokes the current access token.
     */
    logout(): Promise<ApiMessageResponse>;
    /**
     * Changes the current authenticated user's password.
     *
     * @endpoint PATCH /api/v1/auth/password
     * @access   authenticated
     * @param payload - Required fields: `current_password`, `password`.
     * @returns {Promise<ApiMessageResponse>}
     * @throws {ValidationApiError} if validation fails or the current password is wrong (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @sideeffect Revokes all access tokens for the current user after a successful password change.
     */
    changePassword(payload: SelfServiceChangePasswordRequest): Promise<ApiMessageResponse>;
}
