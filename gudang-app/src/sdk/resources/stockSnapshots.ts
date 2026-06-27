import type { ApiClient } from "../client";
import type {
  ApiListResponse,
} from "../types/common";
import type {
  StockSnapshotRow,
  ListSnapshotsQuery,
  CreateSnapshotRequest,
  CreateSnapshotResponse,
  CurrentSnapshotStatus,
} from "../types/stockSnapshots";

/**
 * Wraps: /api/v1/stock-snapshots
 * Contract: §6.2 in api-contract.md
 * Access: admin, gudang (current: admin, dapur, gudang)
 */
export class StockSnapshotsResource {
  private readonly client: ApiClient;

  public constructor(client: ApiClient) {
    this.client = client;
  }

  /**
   * List paginated stock snapshots with item and category details.
   *
   * @endpoint GET /api/v1/stock-snapshots
   * @access admin, gudang
   * @param query - Optional filters and pagination
   * @returns Paginated list of snapshot rows
   * @throws {AuthenticationApiError} 401 if not authenticated
   * @throws {AuthorizationApiError} 403 if role not allowed
   */
  public async list(
    query?: ListSnapshotsQuery,
  ): Promise<ApiListResponse<StockSnapshotRow>> {
    return this.client.request<ApiListResponse<StockSnapshotRow>>({
      method: "GET",
      path: "/stock-snapshots",
      query: query as Record<string, string | number>,
    });
  }

  /**
   * Take (or retake) an opening stock snapshot for a month.
   *
   * @endpoint POST /api/v1/stock-snapshots
   * @access admin, gudang
   * @param request - Optional month and force flag
   * @returns Creation result with item count
   * @throws {AuthenticationApiError} 401 if not authenticated
   * @throws {AuthorizationApiError} 403 if role not allowed
   * @throws {ValidationApiError} 400 if month format invalid
   */
  public async take(
    request?: CreateSnapshotRequest,
  ): Promise<CreateSnapshotResponse> {
    return this.client.request<CreateSnapshotResponse>({
      method: "POST",
      path: "/stock-snapshots",
      body: request,
    });
  }

  /**
   * Check current month's snapshot status.
   *
   * @endpoint GET /api/v1/stock-snapshots/current
   * @access admin, dapur, gudang
   * @returns Status object with has_snapshot flag and item count
   * @throws {AuthenticationApiError} 401 if not authenticated
   */
  public async current(): Promise<CurrentSnapshotStatus> {
    return this.client.request<CurrentSnapshotStatus>({
      method: "GET",
      path: "/stock-snapshots/current",
    });
  }
}
