import { describe, expect, it, vi } from "vitest";

import { ApiClient } from "../client";
import { ValidationApiError } from "../errors";

describe("ApiClient", () => {
  it("adds the api prefix, bearer token, and json body", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: "ok" }), {
        status: 200,
        headers: { "content-type": "application/json" }
      })
    );

    const client = new ApiClient({
      baseUrl: "http://127.0.0.1:8080/",
      accessToken: "token-123",
      fetchImplementation: fetchMock
    });

    await client.request<{ message: string }>({
      method: "POST",
      path: "/auth/logout",
      body: { reason: "manual" }
    });

    expect(fetchMock).toHaveBeenCalledTimes(1);

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/auth/logout");
    expect(init?.method).toBe("POST");

    const headers = new Headers(init?.headers);
    expect(headers.get("authorization")).toBe("Bearer token-123");
    expect(headers.get("content-type")).toBe("application/json");
    expect(init?.body).toBe(JSON.stringify({ reason: "manual" }));
  });

  it("maps validation responses to ValidationApiError", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: "Validation failed.", errors: { name: "Required." } }), {
        status: 400,
        headers: { "content-type": "application/json" }
      })
    );

    const client = new ApiClient({ fetchImplementation: fetchMock });

    await expect(client.request({ method: "GET", path: "/items" })).rejects.toBeInstanceOf(ValidationApiError);
  });
});
