import type { ApiClient } from "../client";
import type { DashboardResponse } from "../types/dashboard";
/**
 * Dashboard SDK Resource
 *
 * Wraps:    /api/v1/dashboard
 * Contract: api-contract.md §5.8
 * Access:   admin | gudang | dapur
 *
 * Fetches the role-shaped dashboard aggregate payload.
 */
export declare class DashboardResource {
    private readonly client;
    constructor(client: ApiClient);
    /**
     * Returns the dashboard aggregate payload for the authenticated user's role.
     *
     * @endpoint GET /api/v1/dashboard
     * @access   admin | gudang | dapur
     * @returns {Promise<DashboardResponse>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role or the account is inactive (403)
     * @sideeffect None
     */
    getAggregate(): Promise<DashboardResponse>;
}
