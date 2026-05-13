import type { ApiClient } from "../client";
import type { ApiListResponse, Role, RoleListQuery } from "../types";
/**
 * Roles SDK Resource
 *
 * Wraps:    /api/v1/roles
 * Contract: api-contract.md §5.1 and §5.2
 * Access:   admin
 *
 * Lists operational app roles from the `roles` table.
 */
export declare class RolesResource {
    private readonly client;
    constructor(client: ApiClient);
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
    list(query?: RoleListQuery): Promise<ApiListResponse<Role>>;
}
