import type { ApiClient } from "../client";
import type { ApiListResponse, ApiMessageResponse, Notification, ListNotificationsQuery } from "../types";
/**
 * Notifications SDK Resource
 *
 * Wraps:    /api/v1/notifications
 * Contract: api-contract.md §5.10
 * Access:   authenticated
 *
 * Manages self-scoped notifications for the authenticated user.
 */
export declare class NotificationsResource {
    private readonly client;
    constructor(client: ApiClient);
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
    list(query?: ListNotificationsQuery): Promise<ApiListResponse<Notification>>;
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
    markAsRead(id: number): Promise<ApiMessageResponse>;
    /**
     * Marks all notifications as read for the current user.
     *
     * @endpoint POST /api/v1/notifications/read-all
     * @access   authenticated
     * @returns {Promise<ApiMessageResponse>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @sideeffect Updates `is_read=true` for all notifications owned by the caller.
     */
    markAllAsRead(): Promise<ApiMessageResponse>;
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
    delete(id: number): Promise<ApiMessageResponse>;
    /**
     * Deletes all notifications owned by the current user.
     *
     * @endpoint DELETE /api/v1/notifications
     * @access   authenticated
     * @returns {Promise<ApiMessageResponse>}
     * @throws {AuthenticationApiError} if no valid Bearer token is provided (401)
     * @sideeffect Permanently deletes all notifications owned by the caller.
     */
    deleteAll(): Promise<ApiMessageResponse>;
}
