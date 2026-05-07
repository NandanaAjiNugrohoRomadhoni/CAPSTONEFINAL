import { describe, expect, it, vi } from "vitest";

import { CapstoneSdk } from "..";
import type { StockTransactionDetail } from "../types";

describe("StockTransactionsResource", () => {
  it("posts revision payloads to the submit-revision workflow endpoint", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Revision submitted successfully.",
          data: { id: 12, approval_status_id: 2, is_revision: true, parent_transaction_id: 10 }
        }),
        {
          status: 201,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.stockTransactions.submitRevision(10, {
      transaction_date: "2026-04-18",
      details: [{ item_id: 1, qty: 5000 }]
    });

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/stock-transactions/10/submit-revision");
    expect(init?.method).toBe("POST");
  });

  it("posts direct correction payloads to the direct-corrections endpoint", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Direct stock correction applied successfully.",
          data: { id: 15, approval_status_id: 1, is_revision: false }
        }),
        {
          status: 201,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.stockTransactions.directCorrection({
      transaction_date: "2026-04-13",
      item_id: 1,
      expected_current_qty: 5000,
      target_qty: 4800,
      reason: "Damaged goods found during physical count"
    });

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/stock-transactions/direct-corrections");
    expect(init?.method).toBe("POST");
    const body = JSON.parse(init?.body as string);
    expect(body.expected_current_qty).toBe(5000);
    expect(body.target_qty).toBe(4800);
  });

  it("keeps approve as a command endpoint for revision correction workflow", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Revision approved successfully.",
          data: { id: 12, approval_status_id: 1, approved_by: 1 }
        }),
        {
          status: 200,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.stockTransactions.approve(12);

    const [url, init] = fetchMock.mock.calls[0] ?? [];

    expect(url).toBe("http://127.0.0.1:8080/api/v1/stock-transactions/12/approve");
    expect(init?.method).toBe("POST");
  });

  it("serializes the verified list filters for stock transactions", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          data: [],
          meta: { page: 1, perPage: 10, total: 0, totalPages: 0 },
          links: {
            self: "/api/v1/stock-transactions?page=1&perPage=10",
            first: "/api/v1/stock-transactions?page=1&perPage=10",
            last: "/api/v1/stock-transactions?page=1&perPage=10",
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

    await sdk.stockTransactions.list({
      q: "12345",
      sortBy: "updated_at",
      sortDir: "ASC",
      type_id: 1,
      status_id: 2,
      transaction_date_from: "2026-04-01",
      transaction_date_to: "2026-04-30",
      created_at_from: "2026-04-01",
      updated_at_to: "2026-04-30"
    });

    const [url] = fetchMock.mock.calls[0] ?? [];
    expect(url).toBe(
      "http://127.0.0.1:8080/api/v1/stock-transactions?q=12345&sortBy=updated_at&sortDir=ASC&type_id=1&status_id=2&transaction_date_from=2026-04-01&transaction_date_to=2026-04-30&created_at_from=2026-04-01&updated_at_to=2026-04-30"
    );
  });

  it("sends input_unit=convert in create request body when specified", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Stock transaction created successfully.",
          data: { id: 5, approval_status_id: 1, is_revision: false }
        }),
        {
          status: 201,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.stockTransactions.create({
      type_name: "IN",
      transaction_date: "2026-08-01",
      details: [{ item_id: 1, qty: 2, input_unit: "convert" }]
    });

    const [, init] = fetchMock.mock.calls[0] ?? [];
    const body = JSON.parse(init?.body as string);

    expect(body.details[0].input_unit).toBe("convert");
    expect(body.details[0].qty).toBe(2);
  });

  it("sends input_unit=base explicitly when specified", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Stock transaction created successfully.",
          data: { id: 6, approval_status_id: 1, is_revision: false }
        }),
        {
          status: 201,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.stockTransactions.create({
      type_name: "IN",
      transaction_date: "2026-08-02",
      details: [{ item_id: 1, qty: 500, input_unit: "base" }]
    });

    const [, init] = fetchMock.mock.calls[0] ?? [];
    const body = JSON.parse(init?.body as string);

    expect(body.details[0].input_unit).toBe("base");
  });

  it("omits input_unit from request when not specified (legacy backward compat)", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Stock transaction created successfully.",
          data: { id: 7, approval_status_id: 1, is_revision: false }
        }),
        {
          status: 201,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.stockTransactions.create({
      type_name: "IN",
      transaction_date: "2026-08-03",
      details: [{ item_id: 1, qty: 100 }]
    });

    const [, init] = fetchMock.mock.calls[0] ?? [];
    const body = JSON.parse(init?.body as string);

    expect(body.details[0].input_unit).toBeUndefined();
  });

  it("detail response type includes item metadata and input fields", () => {
    const detail: StockTransactionDetail = {
      id: 1,
      transaction_id: 10,
      item_id: 1,
      item_name: "Beras",
      item_category_id: 1,
      item_category_name: "KERING",
      satuan: "gram",
      qty: "5000.00",
      input_qty: "5.00",
      input_unit: "convert"
    };

    expect(detail.item_name).toBe("Beras");
    expect(detail.item_category_name).toBe("KERING");
    expect(detail.satuan).toBe("gram");
    expect(detail.input_qty).toBe("5.00");
    expect(detail.input_unit).toBe("convert");
    expect(detail.qty).toBe("5000.00");
  });

  it("posts revision payloads with input_unit unchanged when specified", async () => {
    const fetchMock = vi.fn<typeof fetch>().mockResolvedValue(
      new Response(
        JSON.stringify({
          message: "Revision submitted successfully.",
          data: { id: 13, approval_status_id: 2, is_revision: true, parent_transaction_id: 11 }
        }),
        {
          status: 201,
          headers: { "content-type": "application/json" }
        }
      )
    );

    const sdk = new CapstoneSdk({ fetchImplementation: fetchMock });

    await sdk.stockTransactions.submitRevision(11, {
      transaction_date: "2026-08-05",
      details: [{ item_id: 1, qty: 4, input_unit: "convert" }]
    });

    const [, init] = fetchMock.mock.calls[0] ?? [];
    const body = JSON.parse(init?.body as string);

    expect(body.details[0].qty).toBe(4);
    expect(body.details[0].input_unit).toBe("convert");
  });

  it("does not expose a delete method on the StockTransactionsResource", () => {
    const sdk = new CapstoneSdk({});

    expect("delete" in sdk.stockTransactions).toBe(false);
  });
});
