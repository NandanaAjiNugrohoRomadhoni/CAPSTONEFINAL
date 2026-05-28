import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  ApiListResponse,
  ApiMessageDataResponse,
  CreateStockTransactionRequest,
  DirectStockCorrectionRequest,
  ListStockTransactionsQuery,
  RejectStockTransactionRequest,
  StockTransaction,
  StockTransactionCreateResult,
  StockTransactionDetail,
  StockTransactionModerationResult,
  StockTransactionRevisionResult,
  SubmitRevisionRequest
} from "../types";

// Aligned with api-contract.md §5.5 and schema.md §4.2-4.3 — 2026-04-29
/**
 * StockTransactions SDK Resource
 *
 * Wraps:    /api/v1/stock-transactions
 * Contract: api-contract.md §5.5
 * Access:   admin | gudang
 *
 * Manages the stock ledger, revision workflow, and direct corrections.
 */
export class StockTransactionsResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists stock transactions with pagination, filtering, and search.
   *
   * @endpoint GET /api/v1/stock-transactions
   * @access   admin | gudang
   * @param query - Supports `page`, `perPage`, `q`/`search` on `spk_id` (`q` wins), `sortBy`, `sortDir`, `type_id`, `status_id`, `transaction_date_from/to`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400.
   * @returns {Promise<ApiListResponse<StockTransaction>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public list(query?: ListStockTransactionsQuery): Promise<ApiListResponse<StockTransaction>> {
    return this.client.request<ApiListResponse<StockTransaction>>({
      method: "GET",
      path: "/stock-transactions",
      ...(query ? { query: buildStockTransactionsQuery(query) } : {})
    });
  }

  /**
   * Returns a stock transaction header only.
   *
   * @endpoint GET /api/v1/stock-transactions/{id}
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<StockTransaction>>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the transaction does not exist (404)
   * @sideeffect None
   */
  public get(id: number): Promise<ApiDataResponse<StockTransaction>> {
    return this.client.request<ApiDataResponse<StockTransaction>>({
      method: "GET",
      path: `/stock-transactions/${id}`
    });
  }

  /**
   * Returns the stock transaction detail rows only.
   *
   * @endpoint GET /api/v1/stock-transactions/{id}/details
   * @access   admin | gudang
   * @returns {Promise<ApiDataResponse<StockTransactionDetail[]>>} Each row includes `item_name`, `item_category_id`, `item_category_name`, `satuan` (base unit label from `items.unit_base`), `qty`, `input_qty`, and `input_unit`.
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the transaction does not exist (404)
   * @sideeffect None
   */
  public details(id: number): Promise<ApiDataResponse<StockTransactionDetail[]>> {
    return this.client.request<ApiDataResponse<StockTransactionDetail[]>>({
      method: "GET",
      path: `/stock-transactions/${id}/details`
    });
  }

  /**
   * Creates a stock transaction.
   *
   * @endpoint POST /api/v1/stock-transactions
   * @access   admin | gudang
   * @param payload - Send exactly one of `type_id` or `type_name`, plus `transaction_date`, optional `spk_id`, and `details`. Each detail supports `item_id`, `qty`, and optional `input_unit`. `user_id` is derived from the Bearer token and cannot be sent by the client. `input_unit="base"` stores qty as submitted; `input_unit="convert"` stores qty × `items.conversion_base`; backend always persists `input_qty` and normalizes response `qty` to base units.
   * @returns {Promise<ApiMessageDataResponse<StockTransactionCreateResult>>}
   * @throws {ValidationApiError} if validation fails, both type fields are sent, duplicate items exist in one request, or an OUT transaction would drive stock negative (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Mutates `items.qty` immediately because normal transactions are created with `APPROVED` status.
   */
  public create(payload: CreateStockTransactionRequest): Promise<ApiMessageDataResponse<StockTransactionCreateResult>> {
    return this.client.request<ApiMessageDataResponse<StockTransactionCreateResult>>({
      method: "POST",
      path: "/stock-transactions",
      body: payload
    });
  }

  /**
   * Applies an admin-only direct stock correction for one item.
   *
   * @endpoint POST /api/v1/stock-transactions/direct-corrections
   * @access   admin
   * @param payload - Required fields: `transaction_date`, `item_id`, `expected_current_qty`, `target_qty`, and `reason`. Backend derives `IN` or `OUT` from `target_qty - expected_current_qty` and rejects the request if actual stock no longer matches `expected_current_qty`.
   * @returns {Promise<ApiMessageDataResponse<StockTransactionCreateResult>>}
   * @throws {ValidationApiError} if validation fails or optimistic concurrency rejects the correction (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Mutates `items.qty` immediately through a final approved ledger transaction.
   */
  public directCorrection(payload: DirectStockCorrectionRequest): Promise<ApiMessageDataResponse<StockTransactionCreateResult>> {
    return this.client.request<ApiMessageDataResponse<StockTransactionCreateResult>>({
      method: "POST",
      path: "/stock-transactions/direct-corrections",
      body: payload
    });
  }

  /**
   * Submits a revision for an existing transaction.
   *
   * @endpoint POST /api/v1/stock-transactions/{id}/submit-revision
   * @access   admin | gudang
   * @param payload - Same detail contract as create. The backend creates a pending child revision on first submit, then reuses and replaces that same pending child payload if the parent is resubmitted before admin review.
   * @returns {Promise<ApiMessageDataResponse<StockTransactionRevisionResult>>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the parent transaction does not exist (404)
   * @sideeffect Does not mutate `items.qty`.
   */
  public submitRevision(id: number, payload: SubmitRevisionRequest): Promise<ApiMessageDataResponse<StockTransactionRevisionResult>> {
    return this.client.request<ApiMessageDataResponse<StockTransactionRevisionResult>>({
      method: "POST",
      path: `/stock-transactions/${id}/submit-revision`,
      body: payload
    });
  }

  /**
   * Approves a revision transaction.
   *
   * @endpoint POST /api/v1/stock-transactions/{id}/approve
   * @access   admin
   * @returns {Promise<ApiMessageDataResponse<StockTransactionModerationResult>>}
   * @throws {ValidationApiError} if the revision is not approvable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the revision does not exist (404)
   * @sideeffect Mutates `items.qty` by applying the net difference between parent and revision details, not by replaying the revision as a second additive movement.
   */
  public approve(id: number): Promise<ApiMessageDataResponse<StockTransactionModerationResult>> {
    return this.client.request<ApiMessageDataResponse<StockTransactionModerationResult>>({
      method: "POST",
      path: `/stock-transactions/${id}/approve`
    });
  }

  /**
   * Rejects a revision transaction.
   *
   * @endpoint POST /api/v1/stock-transactions/{id}/reject
   * @access   admin
   * @param payload - Optional JSON body with `reason`. Omitting the body remains supported for backward compatibility.
   * @returns {Promise<ApiMessageDataResponse<StockTransactionModerationResult>>}
   * @throws {ValidationApiError} if the revision is not rejectable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the revision does not exist (404)
   * @sideeffect Does not mutate `items.qty`.
   */
  public reject(
    id: number,
    payload?: RejectStockTransactionRequest
  ): Promise<ApiMessageDataResponse<StockTransactionModerationResult>> {
    return this.client.request<ApiMessageDataResponse<StockTransactionModerationResult>>({
      method: "POST",
      path: `/stock-transactions/${id}/reject`,
      ...(payload ? { body: payload } : {})
    });
  }
}

function buildStockTransactionsQuery(query: ListStockTransactionsQuery): Record<string, string | number> {
  const result: Record<string, string | number> = {};

  if (query.page !== undefined) {
    result.page = query.page;
  }

  if (query.perPage !== undefined) {
    result.perPage = query.perPage;
  }

  if (query.q !== undefined) result.q = query.q;
  if (query.search !== undefined) result.search = query.search;
  if (query.sortBy !== undefined) result.sortBy = query.sortBy;
  if (query.sortDir !== undefined) result.sortDir = query.sortDir;
  if (query.type_id !== undefined) result.type_id = query.type_id;
  if (query.status_id !== undefined) result.status_id = query.status_id;
  if (query.transaction_date_from !== undefined) result.transaction_date_from = query.transaction_date_from;
  if (query.transaction_date_to !== undefined) result.transaction_date_to = query.transaction_date_to;
  if (query.created_at_from !== undefined) result.created_at_from = query.created_at_from;
  if (query.created_at_to !== undefined) result.created_at_to = query.created_at_to;
  if (query.updated_at_from !== undefined) result.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== undefined) result.updated_at_to = query.updated_at_to;

  return result;
}
