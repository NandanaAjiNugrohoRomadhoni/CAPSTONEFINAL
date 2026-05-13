import { describe, expect, it } from "vitest";
import { createCapstoneSdk } from "../index";

describe("Dashboard SDK Contract", () => {
  it("getAggregate sends correct request", async () => {
    let requestedUrl = "";
    let requestedMethod = "";

    const fetchMock = async (url: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = url.toString();
      requestedMethod = init?.method ?? "GET";
      
      return new Response(JSON.stringify({
        data: {
          role: "admin",
          generated_at: "2026-04-15",
          aggregates: {
            stock_summary: {}
          }
        }
      }), {
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    const response = await sdk.dashboard.getAggregate();

    expect(requestedUrl).toBe("http://127.0.0.1:8080/api/v1/dashboard");
    expect(requestedMethod).toBe("GET");
    expect(response.data.role).toBe("admin");
    expect(response.data.aggregates).toBeDefined();
  });
});
