import type { ApiClient } from "../client";
import type { ApiListResponse, Role, RoleListQuery } from "../types";

// Aligned with api-contract.md §5.1 and §5.2 — 2026-04-29
/**
 * Roles SDK Resource
 *
 * Wraps:    /api/v1/roles
 * Contract: api-contract.md §5.1 and §5.2
 * Access:   admin
 *
 * Lists operational app roles from the `roles` table.
 */
export class RolesResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists all available app roles with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/roles
   * @access   admin
   *
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<Role>>}
   *
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   *
   * @sideeffect None
   */
  public list(query?: RoleListQuery): Promise<ApiListResponse<Role>> {
    return this.client.request<ApiListResponse<Role>>({
      method: "GET",
      path: "/roles",
      ...(query ? { query: buildRoleQuery(query) } : {})
    });
  }
}

function buildRoleQuery(query: RoleListQuery): Record<string, string | number> {
  const result: Record<string, string | number> = {};

  if (query.paginate !== undefined) result.paginate = query.paginate ? "true" : "false";
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
