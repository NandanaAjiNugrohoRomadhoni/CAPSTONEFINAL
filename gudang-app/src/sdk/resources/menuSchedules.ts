import type { ApiClient } from "../client";
import type {
  ApiDataResponse,
  CreateMenuScheduleRequest,
  MenuCalendarQuery,
  MenuCalendarResponse,
  MenuSchedule,
  MenuScheduleCreateResponse,
  MenuSchedulesListResponse,
  UpdateMenuScheduleRequest
} from "../types";

// Aligned with api-contract.md §5.6.4 — 2026-04-29
/**
 * MenuSchedules SDK Resource
 *
 * Wraps:    /api/v1/menu-schedules and /api/v1/menu-calendar
 * Contract: api-contract.md §5.6.4
 * Access:   admin | gudang | dapur
 *
 * Manages manual schedule overrides and resolves effective calendar projections.
 */
export class MenuSchedulesResource {
  public constructor(private readonly client: ApiClient) {}

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
  public list(): Promise<MenuSchedulesListResponse> {
    return this.client.request<MenuSchedulesListResponse>({
      method: "GET",
      path: "/menu-schedules"
    });
  }

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
  public get(id: number): Promise<ApiDataResponse<MenuSchedule>> {
    return this.client.request<ApiDataResponse<MenuSchedule>>({
      method: "GET",
      path: `/menu-schedules/${id}`
    });
  }

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
  public create(payload: CreateMenuScheduleRequest): Promise<MenuScheduleCreateResponse> {
    return this.client.request<MenuScheduleCreateResponse>({
      method: "POST",
      path: "/menu-schedules",
      body: payload
    });
  }

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
  public update(id: number, payload: UpdateMenuScheduleRequest): Promise<MenuScheduleCreateResponse> {
    return this.client.request<MenuScheduleCreateResponse>({
      method: "PUT",
      path: `/menu-schedules/${id}`,
      body: payload
    });
  }

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
  public calendarProjection(query?: MenuCalendarQuery): Promise<MenuCalendarResponse> {
    return this.client.request<MenuCalendarResponse>({
      method: "GET",
      path: "/menu-calendar",
      ...(query ? { query: buildMenuCalendarQuery(query) } : {})
    });
  }
}

function buildMenuCalendarQuery(query: MenuCalendarQuery): Record<string, string> {
  const result: Record<string, string> = {};

  if (query.month !== undefined) result.month = query.month;
  if (query.date !== undefined) result.date = query.date;
  if (query.start_date !== undefined) result.start_date = query.start_date;
  if (query.end_date !== undefined) result.end_date = query.end_date;

  return result;
}
