import { describe, expect, it } from "vitest";
import { createCapstoneSdk } from "../index";

describe("StockSnapshots SDK Contract", () => {
  it("list sends GET with query params", async () => {
    let requestedUrl = "";
    let requestedMethod = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";

      return new Response(JSON.stringify({
        data: [],
        meta: {},
        links: {}
      }), {
        status: 200,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    await sdk.stockSnapshots.list({ page: 1, perPage: 10 });

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-snapshots?page=1&perPage=10");
    expect(requestedMethod).toBe("GET");
  });

  it("list sends GET without query", async () => {
    let requestedUrl = "";
    let requestedMethod = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";

      return new Response(JSON.stringify({
        data: [],
        meta: {},
        links: {}
      }), {
        status: 200,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    await sdk.stockSnapshots.list();

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-snapshots");
    expect(requestedMethod).toBe("GET");
  });

  it("take sends POST with month", async () => {
    let requestedUrl = "";
    let requestedMethod = "";
    let requestedBody = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";
      requestedBody = String(init?.body);

      return new Response(JSON.stringify({
        success: true,
        message: "Snapshot created.",
        count: 42
      }), {
        status: 201,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    await sdk.stockSnapshots.take({ month: "2026-06" });

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-snapshots");
    expect(requestedMethod).toBe("POST");
    expect(JSON.parse(requestedBody)).toEqual({ month: "2026-06" });
  });

  it("take sends POST without body", async () => {
    let requestedUrl = "";
    let requestedMethod = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";

      return new Response(JSON.stringify({
        success: true,
        message: "Snapshot created.",
        count: 42
      }), {
        status: 201,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    await sdk.stockSnapshots.take();

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-snapshots");
    expect(requestedMethod).toBe("POST");
  });

  it("take sends POST with force", async () => {
    let requestedBody = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedBody = String(init?.body);

      return new Response(JSON.stringify({
        success: true,
        message: "Snapshot retaken.",
        count: 42
      }), {
        status: 201,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    await sdk.stockSnapshots.take({ month: "2026-06", force: true });

    expect(JSON.parse(requestedBody)).toEqual({ month: "2026-06", force: true });
  });

  it("current sends GET to /current", async () => {
    let requestedUrl = "";
    let requestedMethod = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";

      return new Response(JSON.stringify({
        month: "2026-06",
        has_snapshot: true,
        item_count: 42
      }), {
        status: 200,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    await sdk.stockSnapshots.current();

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-snapshots/current");
    expect(requestedMethod).toBe("GET");
  });
});
