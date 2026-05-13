import type { ApiClient } from "../client";
import type { DashboardResponse } from "../types/dashboard";

// Aligned with api-contract.md §5.8 — 2026-04-29
/**
 * Dashboard SDK Resource
 *
 * Wraps:    /api/v1/dashboard
 * Contract: api-contract.md §5.8
 * Access:   admin | gudang | dapur
 *
 * Fetches the role-shaped dashboard aggregate payload.
 */
export class DashboardResource {
  private readonly client: ApiClient;

  public constructor(client: ApiClient) {
    this.client = client;
  }

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
  public async getAggregate(): Promise<DashboardResponse> {
    return this.client.request<DashboardResponse>({
      method: "GET",
      path: "/dashboard"
    });
  }
}
