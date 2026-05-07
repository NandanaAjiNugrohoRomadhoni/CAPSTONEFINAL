import type { ApiClient } from "../client";
import type { ApiListResponse, LookupListQuery, TransactionType } from "../types";

// Aligned with api-contract.md §5.2.2 — 2026-04-29
/**
 * TransactionTypes SDK Resource
 *
 * Wraps:    /api/v1/transaction-types
 * Contract: api-contract.md §5.2.2
 * Access:   admin | gudang
 *
 * Lists stock transaction type lookup rows.
 */
export class TransactionTypesResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists transaction types with pagination, filtering, and optional full lookup reads.
   *
   * @endpoint GET /api/v1/transaction-types
   * @access   admin | gudang
   *
   * @param query - Supports `paginate`, `page`, `perPage`, `q`/`search` (`q` wins), `sortBy`, `sortDir`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400. Soft-deleted rows are excluded. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<TransactionType>>}
   *
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   *
   * @sideeffect None
   */
  public list(query?: LookupListQuery): Promise<ApiListResponse<TransactionType>> {
    return this.client.request<ApiListResponse<TransactionType>>({
      method: "GET",
      path: "/transaction-types",
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
