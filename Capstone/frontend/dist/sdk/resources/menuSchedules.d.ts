import type { ApiClient } from "../client";
import type { ApiDataResponse, CreateMenuScheduleRequest, MenuCalendarQuery, MenuCalendarResponse, MenuSchedule, MenuScheduleCreateResponse, MenuSchedulesListResponse, UpdateMenuScheduleRequest } from "../types";
/**
 * MenuSchedules SDK Resource
 *
 * Wraps:    /api/v1/menu-schedules and /api/v1/menu-calendar
 * Contract: api-contract.md §5.6.4
 * Access:   admin | gudang | dapur
 *
 * Manages manual schedule overrides and resolves effective calendar projections.
 */
export declare class MenuSchedulesResource {
    private readonly client;
    constructor(client: ApiClient);
    /**
     * Lists manual schedule overrides.
     *
     * @endpoint GET /api/v1/menu-schedules
     * @access   admin | gudang | dapur
     * @returns {Promise<MenuSchedulesListResponse>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect None
     */
    list(): Promise<MenuSchedulesListResponse>;
    /**
     * Returns one manual schedule override.
     *
     * @endpoint GET /api/v1/menu-schedules/{id}
     * @access   admin | gudang | dapur
     * @returns {Promise<ApiDataResponse<MenuSchedule>>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @throws {NotFoundApiError} if the schedule does not exist (404)
     * @sideeffect None
     */
    get(id: number): Promise<ApiDataResponse<MenuSchedule>>;
    /**
     * Creates a manual day-of-month override.
     *
     * @endpoint POST /api/v1/menu-schedules
     * @access   admin | dapur
     * @returns {Promise<MenuScheduleCreateResponse>}
     * @throws {ValidationApiError} if validation fails or the day-of-month override already exists (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect Creates one override row in `menu_schedules`.
     */
    create(payload: CreateMenuScheduleRequest): Promise<MenuScheduleCreateResponse>;
    /**
     * Updates a manual day-of-month override.
     *
     * @endpoint PUT /api/v1/menu-schedules/{id}
     * @access   admin | dapur
     * @returns {Promise<MenuScheduleCreateResponse>}
     * @throws {ValidationApiError} if validation fails or uniqueness rules fail (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @throws {NotFoundApiError} if the schedule does not exist (404)
     * @sideeffect Updates one override row in `menu_schedules`.
     */
    update(id: number, payload: UpdateMenuScheduleRequest): Promise<MenuScheduleCreateResponse>;
    /**
     * Resolves the effective menu calendar.
     *
     * @endpoint GET /api/v1/menu-calendar
     * @access   admin | gudang | dapur
     * @param query - Send exactly one of `date`, `month`, or `start_date` + `end_date`. Resolution order is: Feb 29 -> Package 9, day 31 -> Package 11, manual `menu_schedules` override, then the default day pattern.
     * @returns {Promise<MenuCalendarResponse>}
     * @throws {ValidationApiError} if query validation fails (400)
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @throws {AuthorizationApiError} if the caller lacks the required role (403)
     * @sideeffect None
     */
    calendarProjection(query?: MenuCalendarQuery): Promise<MenuCalendarResponse>;
}
