import type { ApiClient } from "../client";
import type {
  ReportParams,
  StockReportResponse,
  TransactionReportResponse,
  SpkHistoryReportResponse,
  EvaluationReportResponse,
  MonthlyStockExportResponse,
} from "../types/reports";

// Aligned with api-contract.md §5.9 — 2026-04-29
/**
 * Reports SDK Resource
 *
 * Wraps:    /api/v1/reports/*
 * Contract: api-contract.md §5.9
 * Access:   admin | gudang | dapur
 *
 * Fetches export-ready reporting datasets.
 */
export class ReportsResource {
  private readonly client: ApiClient;

  public constructor(client: ApiClient) {
    this.client = client;
  }

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
  public async getStocks(params: ReportParams): Promise<StockReportResponse> {
    return this.client.request<StockReportResponse>({
      method: "GET",
      path: "/reports/stocks",
      query: { ...params }
    });
  }

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
  public async getTransactions(params: ReportParams): Promise<TransactionReportResponse> {
    return this.client.request<TransactionReportResponse>({
      method: "GET",
      path: "/reports/transactions",
      query: { ...params }
    });
  }

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
  public async getSpkHistory(params: ReportParams): Promise<SpkHistoryReportResponse> {
    return this.client.request<SpkHistoryReportResponse>({
      method: "GET",
      path: "/reports/spk-history",
      query: { ...params }
    });
  }

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
  public async getEvaluation(params: ReportParams): Promise<EvaluationReportResponse> {
    return this.client.request<EvaluationReportResponse>({
      method: "GET",
      path: "/reports/evaluation",
      query: { ...params }
    });
  }

  /**
   * Returns the monthly per-item stock movement export dataset.
   *
   * @endpoint GET /api/v1/reports/monthly-stock-export
   * @access   admin | gudang | dapur
   * @param params - Must include `period_start` and `period_end`. Supports `category_id` and `item_id`. Unknown params return 400.
   * @returns {Promise<ReportResponse>}
   * @throws {ValidationApiError} if the period is missing, malformed, reversed, or query params are unsupported (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public async getMonthlyStockExport(params: ReportParams & { category_id?: number; item_id?: number }): Promise<MonthlyStockExportResponse> {
    return this.client.request<MonthlyStockExportResponse>({
      method: "GET",
      path: "/reports/monthly-stock-export",
      query: { ...params }
    });
  }
}
