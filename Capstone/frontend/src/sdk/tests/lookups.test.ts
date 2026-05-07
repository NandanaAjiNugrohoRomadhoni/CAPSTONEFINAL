import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";

describe("Lookup resources", () => {
  it("serializes role list filters as paginated lookup query params", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [],
          meta: { page: 1, perPage: 10, total: 0, totalPages: 0 },
          links: {
            self: "/api/v1/roles?page=1&perPage=10",
            first: "/api/v1/roles?page=1&perPage=10",
            last: "/api/v1/roles?page=1&perPage=10",
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
    await sdk.roles.list({ q: "ad", sortBy: "name", sortDir: "ASC" });

    const [url] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/roles?q=ad&sortBy=name&sortDir=ASC");
  });

  it("serializes paginate=false for dropdown lookup calls without changing response shape", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [{ id: 1, name: "admin" }, { id: 2, name: "gudang" }],
          meta: { page: 1, perPage: 2, total: 2, totalPages: 1, paginated: false },
          links: {
            self: "/api/v1/roles?paginate=false",
            first: "/api/v1/roles?paginate=false",
            last: "/api/v1/roles?paginate=false",
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
    const response = await sdk.roles.list({ paginate: false, q: "ad" });

    expect(response.meta.paginated).toBe(false);
    expect(response.data).toHaveLength(2);

    const [url] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/roles?paginate=false&q=ad");
  });

  it("calls the verified item-units CRUD endpoints", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: "Item unit created successfully.", data: { id: 1, name: "box" } }), {
        status: 201,
        headers: { "content-type": "application/json" }
      })
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });
    await sdk.itemUnits.create({ name: "box" });

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/item-units");
    expect(init?.method).toBe("POST");
  });

  it("calls the restore endpoints for deleted item lookups", async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Item unit restored successfully.", data: { id: 8, name: "pack" } }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Item category restored successfully.", data: { id: 9, name: "MINUMAN" } }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });
    await sdk.itemUnits.restore(8);
    await sdk.itemCategories.restore(9);

    const [firstUrl, firstInit] = fetchMock.mock.calls[0] ?? [];
    const [secondUrl, secondInit] = fetchMock.mock.calls[1] ?? [];
    expect(firstUrl).toBe("http://127.0.0.1:8080/api/v1/item-units/8/restore");
    expect(firstInit?.method).toBe("PATCH");
    expect(secondUrl).toBe("http://127.0.0.1:8080/api/v1/item-categories/9/restore");
    expect(secondInit?.method).toBe("PATCH");
  });

  it("calls the item-categories CRUD endpoints", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(JSON.stringify({ message: "Item category deleted successfully." }), {
        status: 200,
        headers: { "content-type": "application/json" }
      })
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });
    await sdk.itemCategories.delete(7);

    const [url, init] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/item-categories/7");
    expect(init?.method).toBe("DELETE");
  });

  it("calls the read-only transaction-types and approval-statuses endpoints", async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: [], meta: { page: 1, perPage: 10, total: 0, totalPages: 0 }, links: { self: "", first: "", last: "", next: null, previous: null } }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: [], meta: { page: 1, perPage: 10, total: 0, totalPages: 0 }, links: { self: "", first: "", last: "", next: null, previous: null } }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });
    await sdk.transactionTypes.list({ q: "IN" });
    await sdk.approvalStatuses.list({ q: "APP" });

    const [firstUrl] = fetchMock.mock.calls[0] ?? [];
    const [secondUrl] = fetchMock.mock.calls[1] ?? [];
    expect(firstUrl).toBe("http://127.0.0.1:8080/api/v1/transaction-types?q=IN");
    expect(secondUrl).toBe("http://127.0.0.1:8080/api/v1/approval-statuses?q=APP");
  });

  it("calls the meal-times lookup endpoint", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [],
          meta: { page: 1, perPage: 10, total: 0, totalPages: 0 },
          links: { self: "", first: "", last: "", next: null, previous: null }
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });
    await sdk.mealTimes.list({ q: "pagi", sortBy: "id", sortDir: "ASC" });

    const [url] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe("http://127.0.0.1:8080/api/v1/meal-times?q=pagi&sortBy=id&sortDir=ASC");
  });
});
