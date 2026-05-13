import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";

describe("RolesResource", () => {
  it("loads the admin-only roles endpoint", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ data: [{ id: 1, name: "admin" }] }), {
        status: 200,
        headers: { "content-type": "application/json" }
      })
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    const response = await sdk.roles.list();

    expect(response.data[0]?.name).toBe("admin");

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/roles");
    expect(init?.method).toBe("GET");
  });
});
