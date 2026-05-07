import type { ApiClient } from "../client";
import type { GenerateSpkBasahRequest, GenerateSpkKeringPengemasRequest, OperationalStockPreviewRequest, OperationalStockPreviewResponse, SpkBasahDetailResponse, SpkBasahGenerateResponse, SpkBasahHistoryListResponse, SpkMenuCalendarQuery, SpkMenuCalendarResponse, SpkOverrideRequest, SpkOverrideResponse, SpkKeringPengemasDetailResponse, SpkKeringPengemasGenerateResponse, SpkKeringPengemasHistoryListResponse, SpkPostStockResponse, SpkStockInPrefillResponse } from "../types";
/**
 * SPK SDK Resource
 *
 * Wraps:    /api/v1/spk/*
 * Contract: api-contract.md §5.7
 * Access:   admin | gudang | dapur
 *
 * Wraps SPK basah and kering/pengemas generation, history, override, posting, and helper endpoints.
 */
export declare class SpkResource {
    private readonly client;
    constructor(client: ApiClient);
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
    basahMenuCalendar(query?: SpkMenuCalendarQuery): Promise<SpkMenuCalendarResponse>;
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
    operationalStockPreview(payload: OperationalStockPreviewRequest): Promise<OperationalStockPreviewResponse>;
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
    generateBasah(payload: GenerateSpkBasahRequest): Promise<SpkBasahGenerateResponse>;
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
    listBasah(): Promise<SpkBasahHistoryListResponse>;
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
    getBasah(id: number): Promise<SpkBasahDetailResponse>;
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
    overrideBasah(id: number, payload: SpkOverrideRequest): Promise<SpkOverrideResponse>;
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
    postBasahStock(id: number): Promise<SpkPostStockResponse>;
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
    keringPengemasMenuCalendar(query?: SpkMenuCalendarQuery): Promise<SpkMenuCalendarResponse>;
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
    generateKeringPengemas(payload: GenerateSpkKeringPengemasRequest): Promise<SpkKeringPengemasGenerateResponse>;
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
    listKeringPengemas(): Promise<SpkKeringPengemasHistoryListResponse>;
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
    getKeringPengemas(id: number): Promise<SpkKeringPengemasDetailResponse>;
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
    overrideKeringPengemas(id: number, payload: SpkOverrideRequest): Promise<SpkOverrideResponse>;
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
    postKeringPengemasStock(id: number): Promise<SpkPostStockResponse>;
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
    stockInPrefill(id: number): Promise<SpkStockInPrefillResponse>;
}
