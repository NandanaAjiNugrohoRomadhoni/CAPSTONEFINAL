import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";
import type { DailyPatientsListResponse } from "../types";

describe("DailyPatientsResource", () => {
  it("calls list/get/create endpoints with backend route shapes", async () => {
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            data: [
              {
                id: 1,
                service_date: "2026-03-01",
                total_patients: 120,
                notes: null,
                created_at: "2026-03-01 06:00:00",
                updated_at: "2026-03-01 06:00:00"
              }
            ],
            meta: { page: 1, perPage: 1, total: 1, totalPages: 1 },
            links: {
              self: "/api/v1/daily-patients",
              first: "/api/v1/daily-patients",
              last: "/api/v1/daily-patients",
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
        new Response(
          JSON.stringify({
            data: {
              id: 1,
              service_date: "2026-03-01",
              total_patients: 120,
              notes: null,
              created_at: "2026-03-01 06:00:00",
              updated_at: "2026-03-01 06:00:00"
            }
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
            message: "Daily patient created successfully.",
            data: {
              id: 2,
              service_date: "2026-03-02",
              total_patients: 130,
              notes: null,
              created_at: "2026-03-02 06:00:00",
              updated_at: "2026-03-02 06:00:00"
            }
          }),
          {
            status: 201,
            headers: { "content-type": "application/json" }
          }
        )
      )
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            message: "Daily patient updated successfully.",
            data: {
              id: 2,
              service_date: "2026-03-03",
              total_patients: 135,
              notes: "Adjusted census",
              created_at: "2026-03-02 06:00:00",
              updated_at: "2026-03-02 07:00:00"
            }
          }),
          {
            status: 200,
            headers: { "content-type": "application/json" }
          }
        )
      );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.dailyPatients.list();
    await sdk.dailyPatients.get("2026-03-01");
    await sdk.dailyPatients.create({
      service_date: "2026-03-02",
      total_patients: 130
    });
    await sdk.dailyPatients.update(2, {
      service_date: "2026-03-03",
      total_patients: 135,
      notes: "Adjusted census"
    });

    const [listUrl, listInit] = fetchMock.mock.calls[0] ?? [];
    const [getUrl, getInit] = fetchMock.mock.calls[1] ?? [];
    const [createUrl, createInit] = fetchMock.mock.calls[2] ?? [];
    const [updateUrl, updateInit] = fetchMock.mock.calls[3] ?? [];

    expect(listUrl).toBe("http://127.0.0.1:8080/api/v1/daily-patients");
    expect(listInit?.method).toBe("GET");
    expect(getUrl).toBe("http://127.0.0.1:8080/api/v1/daily-patients/2026-03-01");
    expect(getInit?.method).toBe("GET");
    expect(createUrl).toBe("http://127.0.0.1:8080/api/v1/daily-patients");
    expect(createInit?.method).toBe("POST");
    expect(createInit?.body).toBe(
      JSON.stringify({
        service_date: "2026-03-02",
        total_patients: 130
      })
    );
    expect(updateUrl).toBe("http://127.0.0.1:8080/api/v1/daily-patients/2");
    expect(updateInit?.method).toBe("PUT");
    expect(updateInit?.body).toBe(
      JSON.stringify({
        service_date: "2026-03-03",
        total_patients: 135,
        notes: "Adjusted census"
      })
    );
  });

  it("keeps list envelope typed with data/meta/links for drift detection", () => {
    const payload: DailyPatientsListResponse = {
      data: [
        {
          id: 3,
          service_date: "2026-03-03",
          total_patients: 90,
          notes: null,
          created_at: "2026-03-03 06:00:00",
          updated_at: "2026-03-03 06:00:00"
        }
      ],
      meta: { page: 1, perPage: 10, total: 1, totalPages: 1 },
      links: {
        self: "/api/v1/daily-patients",
        first: "/api/v1/daily-patients",
        last: "/api/v1/daily-patients",
        next: null,
        previous: null
      }
    };

    expect(payload.meta.total).toBe(1);
    expect(payload.data[0]?.service_date).toBe("2026-03-03");
  });
});
