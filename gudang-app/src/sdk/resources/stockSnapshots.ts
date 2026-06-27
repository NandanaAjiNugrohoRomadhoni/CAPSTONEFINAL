import type { ApiClient } from "../client";
import type {
  ListStockSnapshotsQuery,
  StockSnapshotCurrentStatus,
  StockSnapshotsListResponse,
  TakeStockSnapshotRequest,
  TakeStockSnapshotResponse,
} from "../types";

// Aligned with backend StockSnapshots controller â€” 2026-06-27
/**
 * StockSnapshots SDK Resource
 *
 * Wraps:    /api/v1/stock-snapshots
 * Access:   admin | gudang
 *
 * Manages monthly opening stock snapshots and their current-month status.
 */
export class StockSnapshotsResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists monthly stock snapshots.
   *
   * @endpoint GET /api/v1/stock-snapshots
   * @access   admin | gudang
   * @param query - Supports `page`, `perPage`, `period_month`, `item_id`, and `item_category_id`.
   * @returns {Promise<StockSnapshotsListResponse>}
   */
  public list(query?: ListStockSnapshotsQuery): Promise<StockSnapshotsListResponse> {
    return this.client.request<StockSnapshotsListResponse>({
      method: "GET",
      path: "/stock-snapshots",
      ...(query ? { query: buildStockSnapshotsQuery(query) } : {}),
    });
  }

  /**
   * Takes or retakes the opening stock snapshot for a month.
   *
   * @endpoint POST /api/v1/stock-snapshots
   * @access   admin | gudang
   * @returns {Promise<TakeStockSnapshotResponse>}
   */
  public take(request: TakeStockSnapshotRequest): Promise<TakeStockSnapshotResponse> {
    return this.client.request<TakeStockSnapshotResponse>({
      method: "POST",
      path: "/stock-snapshots",
      body: request,
    });
  }

  /**
   * Returns the current month snapshot status.
   *
   * @endpoint GET /api/v1/stock-snapshots/current
   * @access   admin | gudang
   * @returns {Promise<StockSnapshotCurrentStatus>}
   */
  public current(): Promise<StockSnapshotCurrentStatus> {
    return this.client.request<StockSnapshotCurrentStatus>({
      method: "GET",
      path: "/stock-snapshots/current",
    });
  }
}

function buildStockSnapshotsQuery(
  query: ListStockSnapshotsQuery,
): Record<string, string | number> {
  const result: Record<string, string | number> = {};

  if (query.page !== undefined) result.page = query.page;
  if (query.perPage !== undefined) result.perPage = query.perPage;
  if (query.period_month !== undefined) result.period_month = query.period_month;
  if (query.item_id !== undefined) result.item_id = query.item_id;
  if (query.item_category_id !== undefined) result.item_category_id = query.item_category_id;

  return result;
}
