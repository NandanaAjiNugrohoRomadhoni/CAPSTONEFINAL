import type { ApiClient } from "../client";
import type { CreateDailyPatientRequest, DailyPatientCreateResponse, DailyPatientResponse, DailyPatientsListResponse, DailyPatientUpdateResponse, UpdateDailyPatientRequest } from "../types";
/**
 * DailyPatients SDK Resource
 *
 * Wraps:    /api/v1/daily-patients
 * Contract: api-contract.md §5.7.1
 * Access:   read admin | dapur | gudang; write admin | gudang
 *
 * Manages the standalone daily patient input used as canonical SPK basah input.
 */
export declare class DailyPatientsResource {
    private readonly client;
    constructor(client: ApiClient);
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
    list(): Promise<DailyPatientsListResponse>;
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
    get(serviceDate: string): Promise<DailyPatientResponse>;
    /**
     * Creates a daily patient row.
     *
     * @endpoint POST /api/v1/daily-patients
     * @access   admin | gudang
     * @param payload - Writable fields: `service_date`, `total_patients`, and optional `notes`. `service_date` must remain unique.
     * @returns {Promise<DailyPatientCreateResponse>}
     * @throws {ValidationApiError} if validation fails or the service date already exists (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect Creates a new daily patient row used by SPK generation.
     */
    create(payload: CreateDailyPatientRequest): Promise<DailyPatientCreateResponse>;
    /**
     * Updates a daily patient row by id.
     *
     * @endpoint PUT /api/v1/daily-patients/{id}
     * @access   admin | gudang
     * @returns {Promise<DailyPatientUpdateResponse>}
     * @throws {ValidationApiError} if validation fails or the service date collides with another row (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @throws {NotFoundApiError} if the row does not exist (404)
     * @sideeffect Updates the existing daily patient input row without changing the list/detail envelope shapes.
     */
    update(id: number, payload: UpdateDailyPatientRequest): Promise<DailyPatientUpdateResponse>;
}
