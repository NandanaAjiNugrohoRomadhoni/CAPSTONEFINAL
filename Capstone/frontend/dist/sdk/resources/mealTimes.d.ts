import type { ApiClient } from "../client";
import type { ApiListResponse, LookupListQuery, MealTime } from "../types";
/**
 * MealTimes SDK Resource
 *
 * Wraps:    /api/v1/meal-times
 * Contract: api-contract.md §5.2
 * Access:   admin | gudang
 *
 * Lists meal-time lookup rows used by menu and SPK workflows.
 */
export declare class MealTimesResource {
    private readonly client;
    constructor(client: ApiClient);
    /**
     * Lists meal times with pagination, filtering, and optional full lookup reads.
     *
     * @endpoint GET /api/v1/meal-times
     * @access   admin | gudang
     *
     * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
     * @returns {Promise<ApiListResponse<MealTime>>}
     *
     * @throws {ValidationApiError} if query validation fails (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     *
     * @sideeffect None
     */
    list(query?: LookupListQuery): Promise<ApiListResponse<MealTime>>;
}
