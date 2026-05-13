import type { ApiClient } from "../client";
import type { ApiListResponse, LookupListQuery, TransactionType } from "../types";
/**
 * TransactionTypes SDK Resource
 *
 * Wraps:    /api/v1/transaction-types
 * Contract: api-contract.md §5.2.2
 * Access:   admin | gudang
 *
 * Lists stock transaction type lookup rows.
 */
export declare class TransactionTypesResource {
    private readonly client;
    constructor(client: ApiClient);
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
    list(query?: LookupListQuery): Promise<ApiListResponse<TransactionType>>;
}
