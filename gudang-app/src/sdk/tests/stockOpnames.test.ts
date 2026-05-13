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
});
