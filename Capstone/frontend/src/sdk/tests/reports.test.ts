import { describe, expect, it } from "vitest";
import { createCapstoneSdk } from "../index";

describe("Reports SDK Contract", () => {
  it("getStocks sends correct request", async () => {
    let requestedUrl = "";
    
    const fetchMock = async (url: RequestInfo | URL) => {
      requestedUrl = url.toString();
      
      return new Response(JSON.stringify({
        data: {
          report_type: "stocks",
          summary: { total_items: 10 },
          rows: []
        }
      }), {
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    const response = await sdk.reports.getStocks({ period_start: "2026-04-01", period_end: "2026-04-30" });

    expect(requestedUrl).toContain("/api/v1/reports/stocks");
    expect(requestedUrl).toContain("period_start=2026-04-01");
    expect(requestedUrl).toContain("period_end=2026-04-30");
    expect(response.data.report_type).toBe("stocks");
  });

  it("getSpkHistory sends correct request", async () => {
    let requestedUrl = "";
    
    const fetchMock = async (url: RequestInfo | URL) => {
      requestedUrl = url.toString();
      
      return new Response(JSON.stringify({
        data: {
          report_type: "spk-history",
          summary: { total_spk: 5 },
          rows: [],
          compatibility_projection: {
            contract: {
              spk_calculations: ["id"]
            },
            rows: []
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

    const response = await sdk.reports.getSpkHistory({ period_start: "2026-04-01", period_end: "2026-04-30" });

    expect(requestedUrl).toContain("/api/v1/reports/spk-history");
    expect(response.data.compatibility_projection?.contract.spk_calculations).toBeDefined();
  });

  it("getMonthlyStockExport sends correct request", async () => {
    let requestedUrl = "";

    const fetchMock = async (url: RequestInfo | URL) => {
      requestedUrl = url.toString();

      return new Response(JSON.stringify({
        data: {
          report_type: "monthly-stock-export",
          period: {
            start: "2026-04-01",
            end: "2026-04-30"
          },
          filters: {
            category_id: 3
          },
          summary: {
            total_items: 1,
            total_days: 30
          },
          periode: "1-30",
          rows: [
            {
              no: 1,
              item_id: 9,
              nama_bahan_makanan: "Plastik Vakum",
              category_id: 3,
              category_name: "PENGEMAS",
              satuan: "gram",
              stok_awal: 250,
              harian: [
                { tanggal: 12, masuk: 40, keluar: 0, sisa: 290 }
              ]
            }
          ]
        }
      }), {
        headers: { "Content-Type": "application/json" }
      });
    };

    const sdk = createCapstoneSdk({
      baseUrl: "http://127.0.0.1:8080",
      fetchImplementation: fetchMock as typeof fetch
    });

    const response = await sdk.reports.getMonthlyStockExport({
      period_start: "2026-04-01",
      period_end: "2026-04-30",
      category_id: 3
    });

    expect(requestedUrl).toContain("/api/v1/reports/monthly-stock-export");
    expect(requestedUrl).toContain("period_start=2026-04-01");
    expect(requestedUrl).toContain("period_end=2026-04-30");
    expect(requestedUrl).toContain("category_id=3");
    expect(response.data.report_type).toBe("monthly-stock-export");
    expect(response.data.summary.total_days).toBe(30);
    expect(response.data.periode).toBe("1-30");
  });
});
