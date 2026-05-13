import type { ApiClient } from "../client";
import type { ReportResponse, ReportParams } from "../types/reports";
/**
 * Reports SDK Resource
 *
 * Wraps:    /api/v1/reports/*
 * Contract: api-contract.md §5.9
 * Access:   admin | gudang | dapur
 *
 * Fetches export-ready reporting datasets.
 */
export declare class ReportsResource {
    private readonly client;
    constructor(client: ApiClient);
    /**
     * Returns the stock report dataset.
     *
     * @endpoint GET /api/v1/reports/stocks
     * @access   admin | gudang | dapur
     * @param params - Must include `period_start` and `period_end`. Unknown params return 400.
     * @returns {Promise<ReportResponse>}
     * @throws {ValidationApiError} if the period is missing, malformed, or reversed (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect None
     */
    getStocks(params: ReportParams): Promise<ReportResponse>;
    /**
     * Returns the stock transaction report dataset.
     *
     * @endpoint GET /api/v1/reports/transactions
     * @access   admin | gudang | dapur
     * @param params - Must include `period_start` and `period_end`. Unknown params return 400.
     * @returns {Promise<ReportResponse>}
     * @throws {ValidationApiError} if the period is missing, malformed, or reversed (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect None
     */
    getTransactions(params: ReportParams): Promise<ReportResponse>;
    /**
     * Returns the SPK history report dataset.
     *
     * @endpoint GET /api/v1/reports/spk-history
     * @access   admin | gudang | dapur
     * @param params - Must include `period_start` and `period_end`. Unknown params return 400.
     * @returns {Promise<ReportResponse>}
     * @throws {ValidationApiError} if the period is missing, malformed, or reversed (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect None
     */
    getSpkHistory(params: ReportParams): Promise<ReportResponse>;
    /**
     * Returns the evaluation report dataset.
     *
     * @endpoint GET /api/v1/reports/evaluation
     * @access   admin | gudang | dapur
     * @param params - Must include `period_start` and `period_end`. Unknown params return 400.
     * @returns {Promise<ReportResponse>}
     * @throws {ValidationApiError} if the period is missing, malformed, or reversed (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect None
     */
    getEvaluation(params: ReportParams): Promise<ReportResponse>;
}
