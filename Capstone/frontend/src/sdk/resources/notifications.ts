import type { ApiClient } from "../client";
import type {
  ApiListResponse,
  ApiMessageResponse,
  Notification,
  ListNotificationsQuery,
} from "../types";

// Aligned with api-contract.md §5.10 — 2026-04-29
/**
 * Notifications SDK Resource
 *
 * Wraps:    /api/v1/notifications
 * Contract: api-contract.md §5.10
 * Access:   authenticated
 *
 * Manages self-scoped notifications for the authenticated user.
 */
export class NotificationsResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists the authenticated user's notifications.
   *
   * @endpoint GET /api/v1/notifications
   * @access   authenticated
   * @param query - Supports `page`, `perPage`, `paginate`, `is_read`, `type`, `q`, `sortBy`, and `sortDir`. `paginate=false` keeps the same envelope and sets `meta.paginated=false`.
   * @returns {Promise<ApiListResponse<Notification>>}
   * @throws {ValidationApiError} if query validation fails (400)
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect None
   */
  public list(
    query?: ListNotificationsQuery,
  ): Promise<ApiListResponse<Notification>> {
    return this.client.request<ApiListResponse<Notification>>({
      method: "GET",
      path: "/notifications",
      ...(query ? { query: buildNotificationsQuery(query) } : {}),
    });
  }

  /**
   * Marks one notification as read for the current user.
   *
   * @endpoint POST /api/v1/notifications/{id}/read
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {NotFoundApiError} if the notification does not exist or does not belong to the caller (404)
   * @sideeffect Updates the notification's `is_read` flag.
   */
  public markAsRead(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "POST",
      path: `/notifications/${id}/read`,
    });
  }

  /**
   * Marks all notifications as read for the current user.
   *
   * @endpoint POST /api/v1/notifications/read-all
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect Updates `is_read=true` for all notifications owned by the caller.
   */
  public markAllAsRead(): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "POST",
      path: "/notifications/read-all",
    });
  }

  /**
   * Deletes one notification owned by the current user.
   *
   * @endpoint DELETE /api/v1/notifications/{id}
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @throws {NotFoundApiError} if the notification does not exist or does not belong to the caller (404)
   * @sideeffect Permanently deletes the matching notification row.
   */
  public delete(id: number): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: `/notifications/${id}`,
    });
  }

  /**
   * Deletes all notifications owned by the current user.
   *
   * @endpoint DELETE /api/v1/notifications
   * @access   authenticated
   * @returns {Promise<ApiMessageResponse>}
   * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
   * @sideeffect Permanently deletes all notifications owned by the caller.
   */
  public deleteAll(): Promise<ApiMessageResponse> {
    return this.client.request<ApiMessageResponse>({
      method: "DELETE",
      path: "/notifications",
    });
  }
}

function buildNotificationsQuery(
  query: ListNotificationsQuery,
): Record<string, string | number | boolean> {
  const result: Record<string, string | number | boolean> = {};

  if (query.page !== undefined) result.page = query.page;
  if (query.perPage !== undefined) result.perPage = query.perPage;
  if (query.is_read !== undefined) result.is_read = query.is_read;
  if (query.type !== undefined) result.type = query.type;
  if (query.q !== undefined) result.q = query.q;
  if (query.sortBy !== undefined) result.sortBy = query.sortBy;
  if (query.sortDir !== undefined) result.sortDir = query.sortDir;
  if (query.paginate !== undefined) result.paginate = query.paginate;

  return result;
}
