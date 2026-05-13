import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";

describe("UsersResource", () => {
  it("targets the password change endpoint with PATCH", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: "Password changed successfully. All access tokens have been revoked." }), {
        status: 200,
        headers: { "content-type": "application/json" }
      })
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.users.changePassword(4, { password: "newpassword123" });

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/users/4/password");
    expect(init?.method).toBe("PATCH");
  });

  it("serializes paginated user list filters with backend query names", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [],
          meta: { page: 1, perPage: 10, total: 0, totalPages: 0 },
          links: {
            self: "/api/v1/users?page=1&perPage=10",
            first: "/api/v1/users?page=1&perPage=10",
            last: "/api/v1/users?page=1&perPage=10",
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

    await sdk.users.list({
      q: "warehouse",
      role_id: 3,
      is_active: false,
      sortBy: "email",
      sortDir: "DESC",
      created_at_from: "2026-04-01",
      updated_at_to: "2026-04-30"
    });

    const [url] = fetchMock.mock.calls[0] ?? [];
    const parsedUrl = new URL(String(url));

    expect(parsedUrl.origin + parsedUrl.pathname).toBe("http://127.0.0.1:8080/api/v1/users");
    expect(parsedUrl.searchParams.get("q")).toBe("warehouse");
    expect(parsedUrl.searchParams.get("role_id")).toBe("3");
    expect(parsedUrl.searchParams.get("is_active")).toBe("false");
    expect(parsedUrl.searchParams.get("sortBy")).toBe("email");
    expect(parsedUrl.searchParams.get("sortDir")).toBe("DESC");
    expect(parsedUrl.searchParams.get("created_at_from")).toBe("2026-04-01");
    expect(parsedUrl.searchParams.get("updated_at_to")).toBe("2026-04-30");
  });

  it("sends PATCH to the restore endpoint", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "User restored successfully.",
          data: {
            id: 4,
            role_id: 2,
            name: "Gudang User",
            username: "gudang1",
            email: null,
            is_active: true,
            created_at: "2026-04-01 10:00:00",
            updated_at: "2026-04-01 11:00:00",
            role: { id: 2, name: "gudang" }
          }
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    const result = await sdk.users.restore(4);

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/users/4/restore");
    expect(init?.method).toBe("PATCH");
    expect(result.message).toBe("User restored successfully.");
    expect(result.data.id).toBe(4);
  });
});
