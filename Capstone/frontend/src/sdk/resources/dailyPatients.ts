import type { ApiClient } from "../client";
import type {
  CreateDailyPatientRequest,
  DailyPatientCreateResponse,
  DailyPatientResponse,
  DailyPatientsListResponse
} from "../types";

// Aligned with api-contract.md §5.7.1 — 2026-04-29
/**
 * DailyPatients SDK Resource
 *
 * Wraps:    /api/v1/daily-patients
 * Contract: api-contract.md §5.7.1
 * Access:   admin | dapur | gudang
 *
 * Manages the standalone daily patient input used as canonical SPK basah input.
 */
export class DailyPatientsResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists daily patient rows.
   *
   * @endpoint GET /api/v1/daily-patients
   * @access   admin | dapur | gudang
   * @returns {Promise<DailyPatientsListResponse>} Standard `data[]/meta/links` envelope.
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect None
   */
  public list(): Promise<DailyPatientsListResponse> {
    return this.client.request<DailyPatientsListResponse>({
      method: "GET",
      path: "/daily-patients"
    });
  }

  /**
   * Returns one daily patient row.
   *
   * @endpoint GET /api/v1/daily-patients/{service_date}
   * @access   admin | dapur | gudang
   * @returns {Promise<DailyPatientResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @throws {ValidationApiError} if `service_date` path format is invalid (400)
   * @throws {NotFoundApiError} if the row does not exist for the given service date (404)
   * @sideeffect None
   */
  public get(serviceDate: string): Promise<DailyPatientResponse> {
    return this.client.request<DailyPatientResponse>({
      method: "GET",
      path: `/daily-patients/${serviceDate}`
    });
  }

  /**
   * Creates a daily patient row.
   *
   * @endpoint POST /api/v1/daily-patients
   * @access   admin | dapur
   * @param payload - Writable fields: `service_date`, `total_patients`, and optional `notes`. `service_date` must remain unique.
   * @returns {Promise<DailyPatientCreateResponse>}
   * @throws {ValidationApiError} if validation fails or the service date already exists (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {AuthorizationApiError} if the caller lacks the required role (403)
   * @sideeffect Creates a new immutable audit row; no update/delete endpoint exists.
   */
  public create(payload: CreateDailyPatientRequest): Promise<DailyPatientCreateResponse> {
    return this.client.request<DailyPatientCreateResponse>({
      method: "POST",
      path: "/daily-patients",
      body: payload
    });
  }
}
