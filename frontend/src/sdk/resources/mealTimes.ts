import type { ApiClient } from "../client";
import type { ApiListResponse, LookupListQuery, MealTime } from "../types";

// Aligned with api-contract.md §5.2 and runtime-status.md §4.2 — 2026-04-29
/**
 * MealTimes SDK Resource
 *
 * Wraps:    /api/v1/meal-times
 * Contract: api-contract.md §5.2
 * Access:   admin | gudang
 *
 * Lists meal-time lookup rows used by menu and SPK workflows.
 */
export class MealTimesResource {
  public constructor(private readonly client: ApiClient) {}

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
  public list(query?: LookupListQuery): Promise<ApiListResponse<MealTime>> {
    return this.client.request<ApiListResponse<MealTime>>({
      method: "GET",
      path: "/meal-times",
      ...(query ? { query: buildLookupQuery(query) } : {})
    });
  }
}

function buildLookupQuery(query: LookupListQuery): Record<string, string | number> {
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
