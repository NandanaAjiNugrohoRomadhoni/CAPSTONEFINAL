import type { ApiClient } from "../client";
import type {
  GenerateSpkBasahRequest,
  GenerateSpkKeringPengemasRequest,
  OperationalStockPreviewRequest,
  OperationalStockPreviewResponse,
  SpkBasahDetailResponse,
  SpkBasahGenerateResponse,
  SpkBasahHistoryListResponse,
  SpkMenuCalendarQuery,
  SpkMenuCalendarResponse,
  SpkOverrideRequest,
  SpkOverrideResponse,
  SpkKeringPengemasDetailResponse,
  SpkKeringPengemasGenerateResponse,
  SpkKeringPengemasHistoryListResponse,
  SpkPostStockResponse,
  SpkStockInPrefillResponse
} from "../types";

// Aligned with api-contract.md §5.7 — 2026-04-29
/**
 * SPK SDK Resource
 *
 * Wraps:    /api/v1/spk/*
 * Contract: api-contract.md §5.7
 * Access:   admin | gudang | dapur
 *
 * Wraps SPK basah and kering/pengemas generation, history, override, posting, and helper endpoints.
 */
export class SpkResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Resolves the SPK basah menu-calendar projection.
   *
   * @endpoint GET /api/v1/spk/basah/menu-calendar
   * @access   admin | gudang | dapur
   * @param query - Send exactly one of `date`, `month`, or `start_date` + `end_date`.
   * @returns {Promise<SpkMenuCalendarResponse>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public basahMenuCalendar(query?: SpkMenuCalendarQuery): Promise<SpkMenuCalendarResponse> {
    return this.client.request<SpkMenuCalendarResponse>({
      method: "GET",
      path: "/spk/basah/menu-calendar",
      ...(query ? { query: buildMenuCalendarQuery(query) } : {})
    });
  }

  /**
   * Previews same-day operational stock consumption for basah preparation.
   *
   * @endpoint POST /api/v1/spk/basah/operational-stock-preview
   * @access   admin | dapur
   * @returns {Promise<OperationalStockPreviewResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None; this is a calculation helper only.
   */
  public operationalStockPreview(
    payload: OperationalStockPreviewRequest
  ): Promise<OperationalStockPreviewResponse> {
    return this.client.request<OperationalStockPreviewResponse>({
      method: "POST",
      path: "/spk/basah/operational-stock-preview",
      body: payload
    });
  }

  /**
   * Generates a basah SPK version.
   *
   * @endpoint POST /api/v1/spk/basah/generate
   * @access   admin | dapur
   * @param payload - Basah generation input. Recommendations follow `((daily_patients × 1.05) × composition_qty) - current_stock`, clamped to 0.
   * @returns {Promise<SpkBasahGenerateResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a new history/version row. Does not create stock transactions and does not mutate stock.
   */
  public generateBasah(payload: GenerateSpkBasahRequest): Promise<SpkBasahGenerateResponse> {
    return this.client.request<SpkBasahGenerateResponse>({
      method: "POST",
      path: "/spk/basah/generate",
      body: payload
    });
  }

  /**
   * Lists SPK basah history versions.
   *
   * @endpoint GET /api/v1/spk/basah/history
   * @access   admin | dapur | gudang
   * @returns {Promise<SpkBasahHistoryListResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public listBasah(): Promise<SpkBasahHistoryListResponse> {
    return this.client.request<SpkBasahHistoryListResponse>({
      method: "GET",
      path: "/spk/basah/history"
    });
  }

  /**
   * Returns one SPK basah history version.
   *
   * @endpoint GET /api/v1/spk/basah/history/{id}
   * @access   admin | dapur | gudang
   * @returns {Promise<SpkBasahDetailResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the history row does not exist (404)
   * @sideeffect None
   */
  public getBasah(id: number): Promise<SpkBasahDetailResponse> {
    return this.client.request<SpkBasahDetailResponse>({
      method: "GET",
      path: `/spk/basah/history/${id}`
    });
  }

  /**
   * Overrides one basah recommendation row.
   *
   * @endpoint POST /api/v1/spk/basah/history/{id}/override
   * @access   admin | dapur
   * @returns {Promise<SpkOverrideResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row or recommendation does not exist (404)
   * @sideeffect Updates override metadata only; no stock mutation occurs.
   */
  public overrideBasah(id: number, payload: SpkOverrideRequest): Promise<SpkOverrideResponse> {
    return this.client.request<SpkOverrideResponse>({
      method: "POST",
      path: `/spk/basah/history/${id}/override`,
      body: payload
    });
  }

  /**
   * Posts one basah SPK to stock.
   *
   * @endpoint POST /api/v1/spk/basah/history/{id}/post-stock
   * @access   admin
   * @returns {Promise<SpkPostStockResponse>}
   * @throws {ValidationApiError} if the SPK cannot be posted or was already finalized (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row does not exist (404)
   * @sideeffect Creates a stock transaction and finalizes the SPK with `is_finish=true`. This action can only happen once per SPK version.
   */
  public postBasahStock(id: number): Promise<SpkPostStockResponse> {
    return this.client.request<SpkPostStockResponse>({
      method: "POST",
      path: `/spk/basah/history/${id}/post-stock`
    });
  }

  /**
   * Resolves the SPK kering/pengemas menu-calendar projection.
   *
   * @endpoint GET /api/v1/spk/kering-pengemas/menu-calendar
   * @access   admin | gudang | dapur
   * @param query - Send exactly one of `date`, `month`, or `start_date` + `end_date`.
   * @returns {Promise<SpkMenuCalendarResponse>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public keringPengemasMenuCalendar(query?: SpkMenuCalendarQuery): Promise<SpkMenuCalendarResponse> {
    return this.client.request<SpkMenuCalendarResponse>({
      method: "GET",
      path: "/spk/kering-pengemas/menu-calendar",
      ...(query ? { query: buildMenuCalendarQuery(query) } : {})
    });
  }

  /**
   * Generates a kering/pengemas SPK version.
   *
   * @endpoint POST /api/v1/spk/kering-pengemas/generate
   * @access   admin | dapur
   * @param payload - Monthly generation input. Recommendations follow `(prev_month_actual_usage × 1.10) - current_stock`, clamped to 0.
   * @returns {Promise<SpkKeringPengemasGenerateResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a new history/version row. Does not create stock transactions and does not mutate stock.
   */
  public generateKeringPengemas(
    payload: GenerateSpkKeringPengemasRequest
  ): Promise<SpkKeringPengemasGenerateResponse> {
    return this.client.request<SpkKeringPengemasGenerateResponse>({
      method: "POST",
      path: "/spk/kering-pengemas/generate",
      body: payload
    });
  }

  /**
   * Lists kering/pengemas SPK history versions.
   *
   * @endpoint GET /api/v1/spk/kering-pengemas/history
   * @access   admin | dapur | gudang
   * @returns {Promise<SpkKeringPengemasHistoryListResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public listKeringPengemas(): Promise<SpkKeringPengemasHistoryListResponse> {
    return this.client.request<SpkKeringPengemasHistoryListResponse>({
      method: "GET",
      path: "/spk/kering-pengemas/history"
    });
  }

  /**
   * Returns one kering/pengemas SPK history version.
   *
   * @endpoint GET /api/v1/spk/kering-pengemas/history/{id}
   * @access   admin | dapur | gudang
   * @returns {Promise<SpkKeringPengemasDetailResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the history row does not exist (404)
   * @sideeffect None
   */
  public getKeringPengemas(id: number): Promise<SpkKeringPengemasDetailResponse> {
    return this.client.request<SpkKeringPengemasDetailResponse>({
      method: "GET",
      path: `/spk/kering-pengemas/history/${id}`
    });
  }

  /**
   * Overrides one kering/pengemas recommendation row.
   *
   * @endpoint POST /api/v1/spk/kering-pengemas/history/{id}/override
   * @access   admin | dapur
   * @returns {Promise<SpkOverrideResponse>}
   * @throws {ValidationApiError} if validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row or recommendation does not exist (404)
   * @sideeffect Updates override metadata only; no stock mutation occurs.
   */
  public overrideKeringPengemas(id: number, payload: SpkOverrideRequest): Promise<SpkOverrideResponse> {
    return this.client.request<SpkOverrideResponse>({
      method: "POST",
      path: `/spk/kering-pengemas/history/${id}/override`,
      body: payload
    });
  }

  /**
   * Posts one kering/pengemas SPK to stock.
   *
   * @endpoint POST /api/v1/spk/kering-pengemas/history/{id}/post-stock
   * @access   admin
   * @returns {Promise<SpkPostStockResponse>}
   * @throws {ValidationApiError} if the SPK cannot be posted or was already finalized (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row does not exist (404)
   * @sideeffect Creates a stock transaction and finalizes the SPK with `is_finish=true`. This action can only happen once per SPK version.
   */
  public postKeringPengemasStock(id: number): Promise<SpkPostStockResponse> {
    return this.client.request<SpkPostStockResponse>({
      method: "POST",
      path: `/spk/kering-pengemas/history/${id}/post-stock`
    });
  }

  /**
   * Returns a stock-transaction prefill payload derived from an SPK.
   *
   * @endpoint GET /api/v1/spk/stock-in-prefill/{id}
   * @access   admin | dapur
   * @returns {Promise<SpkStockInPrefillResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {NotFoundApiError} if the SPK history row does not exist (404)
   * @sideeffect None; this helper does not mutate stock.
   */
  public stockInPrefill(id: number): Promise<SpkStockInPrefillResponse> {
    return this.client.request<SpkStockInPrefillResponse>({
      method: "GET",
      path: `/spk/stock-in-prefill/${id}`
    });
  }
}

function buildMenuCalendarQuery(query: SpkMenuCalendarQuery): Record<string, string> {
  const result: Record<string, string> = {};

  if (query.month !== undefined) result.month = query.month;
  if (query.date !== undefined) result.date = query.date;
  if (query.start_date !== undefined) result.start_date = query.start_date;
  if (query.end_date !== undefined) result.end_date = query.end_date;

  return result;
}
