import type { ApiClient } from "../client";
import type {
  ApiListResponse,
  CreateStockOpnameRequest,
  ListStockOpnamesQuery,
  RejectStockOpnameRequest,
  StockOpnameHeader,
  StockOpnameResponse,
  StockOpnameActionResponse
} from "../types";

// Aligned with api-contract.md §5.5.8 and §5.5.10 — 2026-04-29
/**
 * StockOpnames SDK Resource
 *
 * Wraps:    /api/v1/stock-opnames
 * Contract: api-contract.md §5.5.8 and §5.5.10
 * Access:   admin | gudang
 *
 * Exposes the dedicated stock opname compatibility facade backed by the unified stock ledger.
 */
export class StockOpnamesResource {
  private readonly client: ApiClient;

  public constructor(client: ApiClient) {
    this.client = client;
  }

  /**
   * Lists stock opnames with pagination, filtering, and search.
   *
   * @endpoint GET /api/v1/stock-opnames
   * @access   admin | gudang (gudang sees only own opnames)
   * @param query - Supports `page`, `perPage`, `q`/`search`, `sortBy`, `sortDir`, `state`, `opname_date_from/to`, `created_at_from/to`, and `updated_at_from/to`. Unknown params return 400.
   * @returns {Promise<ApiListResponse<StockOpnameHeader>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public list(query?: ListStockOpnamesQuery): Promise<ApiListResponse<StockOpnameHeader>> {
    return this.client.request<ApiListResponse<StockOpnameHeader>>({
      method: "GET",
      path: "/stock-opnames",
      ...(query ? { query: buildStockOpnamesQuery(query) } : {})
    });
  }

  /**
   * Creates a stock opname draft.
   *
   * @endpoint POST /api/v1/stock-opnames
   * @access   admin | gudang
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a draft opname only; no stock mutation occurs.
   */
  public async create(request: CreateStockOpnameRequest): Promise<StockOpnameActionResponse> {
    return this.client.request<StockOpnameActionResponse>({
      method: "POST",
      path: "/stock-opnames",
      body: request
    });
  }

  /**
   * Returns one stock opname header and detail set.
   *
   * @endpoint GET /api/v1/stock-opnames/{id}
   * @access   admin | gudang
   * @returns {Promise<StockOpnameResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect None
   */
  public async get(id: number): Promise<StockOpnameResponse> {
    return this.client.request<StockOpnameResponse>({
      method: "GET",
      path: `/stock-opnames/${id}`
    });
  }

  /**
   * Updates an editable stock opname.
   *
   * @endpoint PUT /api/v1/stock-opnames/{id}
   * @access   admin | gudang
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the payload is invalid or the workflow state is immutable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Replaces the draft/rejected detail set in full; does not post stock.
   */
  public async update(id: number, request: CreateStockOpnameRequest): Promise<StockOpnameActionResponse> {
    return this.client.request<StockOpnameActionResponse>({
      method: "PUT",
      path: `/stock-opnames/${id}`,
      body: request
    });
  }

  /**
   * Submits a stock opname draft for approval.
   *
   * @endpoint POST /api/v1/stock-opnames/{id}/submit
   * @access   admin | gudang
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the draft is not submittable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Changes workflow state only; no stock mutation occurs.
   */
  public async submit(id: number): Promise<StockOpnameActionResponse> {
    return this.client.request<StockOpnameActionResponse>({
      method: "POST",
      path: `/stock-opnames/${id}/submit`
    });
  }

  /**
   * Approves a submitted stock opname.
   *
   * @endpoint POST /api/v1/stock-opnames/{id}/approve
   * @access   admin
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the opname is not approvable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Changes workflow state only; no stock mutation occurs.
   */
  public async approve(id: number): Promise<StockOpnameActionResponse> {
    return this.client.request<StockOpnameActionResponse>({
      method: "POST",
      path: `/stock-opnames/${id}/approve`
    });
  }

  /**
   * Rejects a submitted stock opname.
   *
   * @endpoint POST /api/v1/stock-opnames/{id}/reject
   * @access   admin
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the opname is not rejectable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Changes workflow state only; no stock mutation occurs.
   */
  public async reject(id: number, request: RejectStockOpnameRequest): Promise<StockOpnameActionResponse> {
    return this.client.request<StockOpnameActionResponse>({
      method: "POST",
      path: `/stock-opnames/${id}/reject`,
      body: request
    });
  }

  /**
   * Posts approved stock opname variances to the ledger.
   *
   * @endpoint POST /api/v1/stock-opnames/{id}/post
   * @access   admin
   * @returns {Promise<StockOpnameActionResponse>}
   * @throws {ValidationApiError} if the opname is not postable (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the opname does not exist (404)
   * @sideeffect Mutates stock by generating `OPNAME_ADJUSTMENT` ledger transactions.
   */
  public async post(id: number): Promise<StockOpnameActionResponse> {
    return this.client.request<StockOpnameActionResponse>({
      method: "POST",
      path: `/stock-opnames/${id}/post`
    });
  }
}

function buildStockOpnamesQuery(query: ListStockOpnamesQuery): Record<string, string | number> {
  const params: Record<string, string | number> = {};
  if (query.page !== undefined) params.page = query.page;
  if (query.perPage !== undefined) params.perPage = query.perPage;
  if (query.state !== undefined) params.state = query.state;
  if (query.q !== undefined) params.q = query.q;
  if (query.search !== undefined) params.search = query.search;
  if (query.sortBy !== undefined) params.sortBy = query.sortBy;
  if (query.sortDir !== undefined) params.sortDir = query.sortDir;
  if (query.opname_date_from !== undefined) params.opname_date_from = query.opname_date_from;
  if (query.opname_date_to !== undefined) params.opname_date_to = query.opname_date_to;
  if (query.created_at_from !== undefined) params.created_at_from = query.created_at_from;
  if (query.created_at_to !== undefined) params.created_at_to = query.created_at_to;
  if (query.updated_at_from !== undefined) params.updated_at_from = query.updated_at_from;
  if (query.updated_at_to !== undefined) params.updated_at_to = query.updated_at_to;
  return params;
}
