import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";
import type { MenuCalendarResponse } from "../types";

describe("Menu domain resources", () => {
  it("calls the dishes endpoints with backend query names and response envelopes", async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            data: [],
            meta: { page: 1, perPage: 10, total: 0, totalPages: 0 },
            links: {
              self: "/api/v1/dishes?page=1&perPage=10",
              first: "/api/v1/dishes?page=1&perPage=10",
              last: "/api/v1/dishes?page=1&perPage=10",
              next: null,
              previous: null
            }
          }),
          {
            status: 200,
            headers: { "content-type": "application/json" }
          }
        )
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Dish created successfully.", data: { id: 1, name: "Bubur" } }), {
          status: 201,
          headers: { "content-type": "application/json" }
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Dish deleted successfully." }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.dishes.list({ page: 2, perPage: 20, q: "bubur", sortBy: "updated_at", sortDir: "DESC" });
    await sdk.dishes.create({ name: "Bubur" });
    await sdk.dishes.delete(9);

    const [listUrl] = fetchMock.mock.calls[0] ?? [];
    const [, createInit] = fetchMock.mock.calls[1] ?? [];
    const [deleteUrl, deleteInit] = fetchMock.mock.calls[2] ?? [];

    expect(listUrl).toBe(
      "http://127.0.0.1:8080/api/v1/dishes?page=2&perPage=20&q=bubur&sortBy=updated_at&sortDir=DESC"
    );
    expect(createInit?.method).toBe("POST");
    expect(createInit?.body).toBe(JSON.stringify({ name: "Bubur" }));
    expect(deleteUrl).toBe("http://127.0.0.1:8080/api/v1/dishes/9");
    expect(deleteInit?.method).toBe("DELETE");
  });

  it("preserves dish composition envelopes and validation-friendly payload shapes", async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Dish composition created successfully.", data: { id: 1 } }), {
          status: 201,
          headers: { "content-type": "application/json" }
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Dish composition deleted successfully." }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.dishCompositions.create({ dish_id: 1, item_id: 2, qty_per_patient: "125.50" });
    await sdk.dishCompositions.delete(4);

    const [, createInit] = fetchMock.mock.calls[0] ?? [];
    const [deleteUrl, deleteInit] = fetchMock.mock.calls[1] ?? [];

    expect(JSON.parse(createInit?.body as string).qty_per_patient).toBe("125.50");
    expect(deleteUrl).toBe("http://127.0.0.1:8080/api/v1/dish-compositions/4");
    expect(deleteInit?.method).toBe("DELETE");
  });

  it("calls menu slot assignment and menu schedule/calendar endpoints", async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            data: [],
            meta: { page: 1, perPage: 11, total: 11, totalPages: 1, paginated: false },
            links: { self: "/api/v1/menus", first: "/api/v1/menus", last: "/api/v1/menus", next: null, previous: null }
          }),
          {
            status: 200,
            headers: { "content-type": "application/json" }
          }
        )
      )
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            data: [],
            meta: { page: 1, perPage: 0, total: 0, totalPages: 0, paginated: false },
            links: {
              self: "/api/v1/menu-dishes",
              first: "/api/v1/menu-dishes",
              last: "/api/v1/menu-dishes",
              next: null,
              previous: null
            }
          }),
          {
            status: 200,
            headers: { "content-type": "application/json" }
          }
        )
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Menu slot assigned successfully.", data: { id: 1 } }), {
          status: 201,
          headers: { "content-type": "application/json" }
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Menu schedule updated successfully.", data: { id: 5 } }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Menu slot updated successfully.", data: { id: 8 } }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ message: "Menu slot deleted successfully." }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { date: "2024-02-29", day_of_month: 29, menu_id: 9, menu_name: "Paket 9" } }), {
          status: 200,
          headers: { "content-type": "application/json" }
        })
      );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.menus.list();
    await sdk.menus.slots();
    await sdk.menus.assignSlot({ menu_id: 1, meal_time_id: 1, dish_id: 2 });
    await sdk.menuSchedules.update(5, { menu_id: 7 });
    await sdk.menus.updateSlot(8, { dish_id: 3 });
    await sdk.menus.deleteSlot(8);
    await sdk.menuSchedules.calendarProjection({ date: "2024-02-29" });

    const [menusUrl] = fetchMock.mock.calls[0] ?? [];
    const [slotUrl] = fetchMock.mock.calls[1] ?? [];
    const [, assignInit] = fetchMock.mock.calls[2] ?? [];
    const [updateUrl, updateInit] = fetchMock.mock.calls[3] ?? [];
    const [menuUpdateUrl, menuUpdateInit] = fetchMock.mock.calls[4] ?? [];
    const [menuDeleteUrl, menuDeleteInit] = fetchMock.mock.calls[5] ?? [];
    const [calendarUrl] = fetchMock.mock.calls[6] ?? [];

    expect(menusUrl).toBe("http://127.0.0.1:8080/api/v1/menus");
    expect(slotUrl).toBe("http://127.0.0.1:8080/api/v1/menu-dishes");
    expect(assignInit?.method).toBe("POST");
    expect(updateUrl).toBe("http://127.0.0.1:8080/api/v1/menu-schedules/5");
    expect(updateInit?.method).toBe("PUT");
    expect(menuUpdateUrl).toBe("http://127.0.0.1:8080/api/v1/menu-dishes/8");
    expect(menuUpdateInit?.method).toBe("PUT");
    expect(menuDeleteUrl).toBe("http://127.0.0.1:8080/api/v1/menu-dishes/8");
    expect(menuDeleteInit?.method).toBe("DELETE");
    expect(calendarUrl).toBe("http://127.0.0.1:8080/api/v1/menu-calendar?date=2024-02-29");
  });

  it("keeps the menu-calendar response envelope typed for date and range projections", () => {
    const calendarResponse: MenuCalendarResponse = {
      data: [
        { date: "2026-03-31", day_of_month: 31, menu_id: 11, menu_name: "Paket 11" }
      ],
      meta: { start_date: "2026-03-01", end_date: "2026-03-31", total: 31 }
    };

    expect(calendarResponse.data[30]).toBeUndefined();
    expect(calendarResponse.meta.total).toBe(31);
  });
});
