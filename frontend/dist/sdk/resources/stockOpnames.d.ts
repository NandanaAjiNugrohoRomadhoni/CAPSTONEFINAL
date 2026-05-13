import type { ApiClient } from "../client";
import type { CreateStockOpnameRequest, RejectStockOpnameRequest, StockOpnameResponse, StockOpnameActionResponse } from "../types/stockOpnames";
/**
 * StockOpnames SDK Resource
 *
 * Wraps:    /api/v1/stock-opnames
 * Contract: api-contract.md §5.5.8 and §5.5.10
 * Access:   admin | gudang
 *
 * Exposes the dedicated stock opname compatibility facade backed by the unified stock ledger.
 */
export declare class StockOpnamesResource {
    private readonly client;
    constructor(client: ApiClient);
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
    create(request: CreateStockOpnameRequest): Promise<StockOpnameActionResponse>;
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
    get(id: number): Promise<StockOpnameResponse>;
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
    submit(id: number): Promise<StockOpnameActionResponse>;
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
    approve(id: number): Promise<StockOpnameActionResponse>;
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
    reject(id: number, request: RejectStockOpnameRequest): Promise<StockOpnameActionResponse>;
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
    post(id: number): Promise<StockOpnameActionResponse>;
}
