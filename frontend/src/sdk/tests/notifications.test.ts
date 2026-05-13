import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";

describe("NotificationsResource", () => {
  it("targets the list endpoint with default pagination", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [],
          meta: { page: 1, perPage: 10, total: 0, totalPages: 0, paginated: true },
          links: {
            self: "/api/v1/notifications?page=1&perPage=10",
            first: "/api/v1/notifications?page=1&perPage=10",
            last: "/api/v1/notifications?page=1&perPage=10",
            next: null,
            previous: null
          }
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.notifications.list();

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/notifications");
    expect(init?.method).toBe("GET");
  });

  it("serializes filter parameters for notifications list", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [],
          meta: { page: 1, perPage: 10, total: 2, totalPages: 1, paginated: true },
          links: {
            self: "/api/v1/notifications?page=1&perPage=10&is_read=0",
            first: "/api/v1/notifications?page=1&perPage=10&is_read=0",
            last: "/api/v1/notifications?page=1&perPage=10&is_read=0",
            next: null,
            previous: null
          }
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.notifications.list({
      is_read: 0,
      type: "MIN_STOCK",
      q: "stock alert",
      sortBy: "created_at",
      sortDir: "DESC",
      paginate: false
    });

    const [url] = fetchMock.mock.calls[0] ?? [];
    const parsedUrl = new URL(String(url));

    expect(parsedUrl.origin + parsedUrl.pathname).toBe(
      "http://127.0.0.1:8080/api/v1/notifications"
    );
    expect(parsedUrl.searchParams.get("is_read")).toBe("0");
    expect(parsedUrl.searchParams.get("type")).toBe("MIN_STOCK");
    expect(parsedUrl.searchParams.get("q")).toBe("stock alert");
    expect(parsedUrl.searchParams.get("sortBy")).toBe("created_at");
    expect(parsedUrl.searchParams.get("sortDir")).toBe("DESC");
    expect(parsedUrl.searchParams.get("paginate")).toBe("false");
  });

  it("serializes pagination parameters correctly", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [],
          meta: { page: 2, perPage: 20, total: 45, totalPages: 3, paginated: true },
          links: {
            self: "/api/v1/notifications?page=2&perPage=20",
            first: "/api/v1/notifications?page=1&perPage=20",
            last: "/api/v1/notifications?page=3&perPage=20",
            next: "/api/v1/notifications?page=3&perPage=20",
            previous: "/api/v1/notifications?page=1&perPage=20"
          }
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.notifications.list({
      page: 2,
      perPage: 20,
      paginate: true
    });

    const [url] = fetchMock.mock.calls[0] ?? [];
    const parsedUrl = new URL(String(url));

    expect(parsedUrl.searchParams.get("page")).toBe("2");
    expect(parsedUrl.searchParams.get("perPage")).toBe("20");
  });

  it("sends POST to mark notification as read", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Notification marked as read."
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    const result = await sdk.notifications.markAsRead(123);

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/notifications/123/read");
    expect(init?.method).toBe("POST");
    expect(result.message).toBe("Notification marked as read.");
  });

  it("sends POST to mark all notifications as read", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "All notifications marked as read."
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    const result = await sdk.notifications.markAllAsRead();

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/notifications/read-all");
    expect(init?.method).toBe("POST");
    expect(result.message).toBe("All notifications marked as read.");
  });

  it("sends DELETE to remove a single notification", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Notification deleted."
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    const result = await sdk.notifications.delete(456);

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/notifications/456");
    expect(init?.method).toBe("DELETE");
    expect(result.message).toBe("Notification deleted.");
  });

  it("sends DELETE to remove all notifications", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "All notifications deleted."
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    const result = await sdk.notifications.deleteAll();

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/notifications");
    expect(init?.method).toBe("DELETE");
    expect(result.message).toBe("All notifications deleted.");
  });
});
