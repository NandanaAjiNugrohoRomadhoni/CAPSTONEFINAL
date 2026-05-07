import type { ApiClient } from "../client";
import type { ApiListResponse, ApprovalStatus, LookupListQuery } from "../types";
/**
 * ApprovalStatuses SDK Resource
 *
 * Wraps:    /api/v1/approval-statuses
 * Contract: api-contract.md §5.2.3
 * Access:   admin | gudang
 *
 * Lists approval status lookup rows used by stock and workflow modules.
 */
export declare class ApprovalStatusesResource {
    private readonly client;
    constructor(client: ApiClient);
    /**
     * Lists approval statuses with pagination, filtering, and optional full lookup reads.
     *
     * @endpoint GET /api/v1/approval-statuses
     * @access   admin | gudang
     *
     * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
     * @returns {Promise<ApiListResponse<ApprovalStatus>>}
     *
     * @throws {ValidationApiError} if query validation fails (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     *
     * @sideeffect None
     */
    list(query?: LookupListQuery): Promise<ApiListResponse<ApprovalStatus>>;
}
