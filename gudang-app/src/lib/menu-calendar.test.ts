import { describe, expect, it } from "vitest";

import { resolveFrontendMenuCalendarEntry } from "@/lib/menu-calendar";

describe("resolveFrontendMenuCalendarEntry", () => {
  const menuPackages = Array.from({ length: 11 }, (_, index) => ({
    id: index + 1,
    name: `Paket ${index + 1}`,
  }));

  it("forces leap-year day 29 to Paket 9 in the frontend", () => {
    const entry = resolveFrontendMenuCalendarEntry({
      year: 2024,
      monthIndex: 0,
      day: 29,
      menuPackages,
    });

    expect(entry).toMatchObject({
      date: "2024-02-29",
      day_of_month: 29,
      menu_id: 9,
      menu_name: "Paket 9",
    });
  });

  it("forces leap-year day 30 to Paket 10 in the frontend", () => {
    const entry = resolveFrontendMenuCalendarEntry({
      year: 2024,
      monthIndex: 3,
      day: 30,
      menuPackages,
    });

    expect(entry).toMatchObject({
      date: "2024-04-30",
      day_of_month: 30,
      menu_id: 10,
      menu_name: "Paket 10",
    });
  });

  it("forces leap-year day 31 to Paket 11 in the frontend", () => {
    const entry = resolveFrontendMenuCalendarEntry({
      year: 2024,
      monthIndex: 2,
      day: 31,
      menuPackages,
    });

    expect(entry).toMatchObject({
      date: "2026-03-31",
      day_of_month: 31,
      menu_id: 11,
      menu_name: "Paket 11",
    });
  });

  it("does not force day 29 or 30 on non-leap years", () => {
    const day29 = resolveFrontendMenuCalendarEntry({
      year: 2025,
      monthIndex: 0,
      day: 29,
      menuPackages,
    });

    const day30 = resolveFrontendMenuCalendarEntry({
      year: 2025,
      monthIndex: 3,
      day: 30,
      menuPackages,
    });

    expect(day29).toMatchObject({
      date: "2025-01-29",
      day_of_month: 29,
      menu_id: 7,
      menu_name: "Paket 7",
    });
    expect(day30).toMatchObject({
      date: "2025-04-30",
      day_of_month: 30,
      menu_id: 8,
      menu_name: "Paket 8",
    });
  });

  it("still respects projected overrides on regular days", () => {
    const entry = resolveFrontendMenuCalendarEntry({
      year: 2026,
      monthIndex: 3,
      day: 15,
      menuPackages,
      projected: {
        date: "2026-04-15",
        day_of_month: 15,
        assignments: [{ menu_id: 5, patient_count: null }],
      },
    });

    expect(entry).toMatchObject({
      date: "2026-04-15",
      day_of_month: 15,
      menu_id: 5,
      menu_name: "Paket 5",
    });
  });
});
