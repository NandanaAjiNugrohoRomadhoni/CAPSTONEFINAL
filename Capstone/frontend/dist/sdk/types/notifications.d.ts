/** Notification type values used by `GET /api/v1/notifications` and related endpoints. */
export type NotificationType = "MIN_STOCK" | "STOCK_REVISION" | "STOCK_OPNAME" | string;
/**
 * Notification row returned by the self-scoped notifications API.
 * `related_id` meaning depends on `type`:
 * - `MIN_STOCK` -> `items.id`
 * - `STOCK_REVISION` -> revision/transaction id
 * - `STOCK_OPNAME` -> `stock_opnames.id`
 */
export interface Notification {
    id: number;
    user_id: number;
    title: string;
    message: string;
    type: NotificationType;
    related_id?: number | null;
    is_read: boolean;
    created_at: string;
    updated_at: string;
}
/** Query params for `GET /api/v1/notifications`. */
export interface ListNotificationsQuery {
    page?: number;
    perPage?: number;
    is_read?: boolean | number;
    type?: NotificationType;
    q?: string;
    /** Allowed sort keys: `id`, `created_at`, `updated_at`, `is_read`, `type`. */
    sortBy?: "id" | "created_at" | "updated_at" | "is_read" | "type";
    sortDir?: "ASC" | "DESC";
    /** When false, the endpoint returns all matched records without pagination. */
    paginate?: boolean;
}
