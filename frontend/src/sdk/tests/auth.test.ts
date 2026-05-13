import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";

describe("AuthResource", () => {
  it("posts login credentials to the auth login endpoint", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Login successful.",
          access_token: "token",
          token_type: "Bearer",
          user: {
            id: 1,
            role_id: 1,
            name: "Admin User",
            username: "admin",
            email: "admin@example.com",
            is_active: true,
            created_at: "2026-04-02 10:00:00",
            updated_at: "2026-04-02 10:00:00",
            role: { id: 1, name: "admin" }
          }
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    const response = await sdk.auth.login({ username: "admin", password: "password123" });

    expect(response.token_type).toBe("Bearer");

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/auth/login");
    expect(init?.method).toBe("POST");
    expect(init?.body).toBe(JSON.stringify({ username: "admin", password: "password123" }));
  });

  it("patches self-service password change endpoint", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: "Password changed successfully. All access tokens have been revoked." }), {
        status: 200,
        headers: { "content-type": "application/json" }
      })
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.auth.changePassword({
      current_password: "password123",
      password: "newpassword123"
    });

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/auth/password");
    expect(init?.method).toBe("PATCH");
    expect(init?.body).toBe(JSON.stringify({
      current_password: "password123",
      password: "newpassword123"
    }));
  });
});
