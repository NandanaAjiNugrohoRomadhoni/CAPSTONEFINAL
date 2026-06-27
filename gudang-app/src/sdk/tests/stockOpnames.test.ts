import { describe, expect, it } from "vitest";
import { createCapstoneSdk } from "../index";

describe("StockOpnames SDK Contract", () => {
  it("create sends correct request", async () => {
    let requestedUrl = "";
    let requestedMethod = "";
    let requestedBody = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";
      requestedBody = String(init?.body);
      
      return new Response(JSON.stringify({
        data: {
          id: 1,
          state: "DRAFT"
        }
      }), {
        status: 201,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    const payload = {
      opname_date: "2026-06-20",
      details: [{ item_id: 1, counted_qty: 100 }]
    };

    const response = await sdk.stockOpnames.create(payload);

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-opnames");
    expect(requestedMethod).toBe("POST");
    expect(JSON.parse(requestedBody)).toEqual(payload);
    expect(response.data.state).toBe("DRAFT");
  });

  it("submit sends correct request", async () => {
    let requestedUrl = "";
    let requestedMethod = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";
      
      return new Response(JSON.stringify({
        data: {
          id: 1,
          state: "SUBMITTED"
        }
      }), {
        status: 200,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    const response = await sdk.stockOpnames.submit(1);

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-opnames/1/submit");
    expect(requestedMethod).toBe("POST");
    expect(response.data.state).toBe("SUBMITTED");
  });

  it("update sends correct request", async () => {
    let requestedUrl = "";
    let requestedMethod = "";
    let requestedBody = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";
      requestedBody = String(init?.body);

      return new Response(JSON.stringify({
        message: "Stock opname updated successfully.",
        data: {
          id: 1,
          state: "REJECTED"
        }
      }), {
        status: 200,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    const payload = {
      opname_date: "2026-06-21",
      notes: "Recount after rejection",
      details: [{ item_id: 1, counted_qty: 95 }]
    };

    const response = await sdk.stockOpnames.update(1, payload);

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-opnames/1");
    expect(requestedMethod).toBe("PUT");
    expect(JSON.parse(requestedBody)).toEqual(payload);
    expect(response.data.state).toBe("REJECTED");
  });

  it("list sends correct request without query", async () => {
    let requestedUrl = "";
    let requestedMethod = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";

      return new Response(JSON.stringify({
        data: [],
        meta: {
          page: 1,
          perPage: 10,
          total: 0,
          totalPages: 0
        },
        links: {
          self: "http://127.0.0.1:8080/api/v1/stock-opnames?page=1&perPage=10",
          first: "http://127.0.0.1:8080/api/v1/stock-opnames?page=1&perPage=10",
          last: "http://127.0.0.1:8080/api/v1/stock-opnames?page=1&perPage=10",
          next: null,
          previous: null
        }
      }), {
        status: 200,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as unknown as typeof fetch
    });

    const response = await sdk.stockOpnames.list();

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-opnames");
    expect(requestedMethod).toBe("GET");
    expect(response.meta.total).toBe(0);
    expect(response.data).toEqual([]);
  });

  it("list sends correct request with query params", async () => {
    let requestedUrl = "";
    let requestedMethod = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";

      return new Response(JSON.stringify({
        data: [
          {
            id: 1,
            opname_date: "2026-06-20",
            state: "SUBMITTED",
            created_by: 2,
            created_at: "2026-06-20 08:00:00",
            updated_at: "2026-06-20 14:00:00"
          }
        ],
        meta: {
          page: 1,
          perPage: 10,
          total: 1,
          totalPages: 1
        },
        links: {
          self: "http://127.0.0.1:8080/api/v1/stock-opnames?page=1&perPage=10",
          first: "http://127.0.0.1:8080/api/v1/stock-opnames?page=1&perPage=10",
          last: "http://127.0.0.1:8080/api/v1/stock-opnames?page=1&perPage=10",
          next: null,
          previous: null
        }
      }), {
        status: 200,
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as unknown as typeof fetch
    });

    const response = await sdk.stockOpnames.list({ state: "SUBMITTED", perPage: 10 });

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/stock-opnames?perPage=10&state=SUBMITTED");
    expect(requestedMethod).toBe("GET");
    expect(response.meta.total).toBe(1);
    expect(response.data[0]?.state).toBe("SUBMITTED");
  });
});
