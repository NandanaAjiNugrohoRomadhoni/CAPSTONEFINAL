"use client";

import { useEffect, useState } from "react";
import sdk from "@/lib";
import { formatQuantity, getErrorMessage } from "@/lib/admin-utils";
import {
  buildSpreadsheetDocument,
  downloadSpreadsheetHtml,
  escapeSpreadsheetHtml,
  formatSpreadsheetNumber,
} from "@/lib/spreadsheet-export";
import { AdminPageHeading, ExportButton, SurfaceCard } from "@/components/admin/ui";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>["data"]["items"][number];

export default function GudangLatestKeringPage() {
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [targetMonth, setTargetMonth] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function handleExport() {
    if (typeof window === "undefined" || rows.length === 0) return;

    const summaryRows = [
      { label: "Periode Target", value: targetMonth ?? "-" },
      { label: "Total Item", value: formatSpreadsheetNumber(rows.length, 0) },
    ];

    const tableRows = rows
      .map(
        (row, index) => `
          <tr>
            <td class="rank">${index + 1}</td>
            <td class="text-strong">${escapeSpreadsheetHtml(row.item_name ?? "-")}</td>
            <td>${escapeSpreadsheetHtml(getItemCategory(row))}</td>
            <td>${escapeSpreadsheetHtml(formatQuantity(row.required_qty, row.item_unit_base))}</td>
            <td>${escapeSpreadsheetHtml(formatQuantity(row.current_stock_qty, row.item_unit_base))}</td>
            <td>${escapeSpreadsheetHtml(formatQuantity(row.final_recommended_qty, row.item_unit_base))}</td>
          </tr>
        `,
      )
      .join("");

    const html = buildSpreadsheetDocument({
      title: "SPS - REKOMENDASI BELANJA KERING & PENGEMAS",
      subtitle: "Hasil rekomendasi belanja kering & pengemas terbaru dari backend.",
      body: `
        <table class="section-gap">
          <tr class="no-border">
            <td class="title" colspan="6">SPS - REKOMENDASI BELANJA KERING & PENGEMAS</td>
          </tr>
          <tr class="no-border">
            <td class="subtitle" colspan="6">Hasil rekomendasi belanja kering & pengemas terbaru dari backend.</td>
          </tr>
        </table>

        <table class="section-gap">
          <tr><td class="section" colspan="2">RINGKASAN</td></tr>
          ${summaryRows
            .map(
              (row) => `<tr class="summary">
                <td class="summary-label">${escapeSpreadsheetHtml(row.label)}</td>
                <td class="summary-value">${escapeSpreadsheetHtml(row.value)}</td>
              </tr>`,
            )
            .join("")}
        </table>

        <table>
          <tr class="head">
            <th>No</th>
            <th>Nama Bahan</th>
            <th>Jenis Bahan</th>
            <th>Pemakaian Bulan Lalu</th>
            <th>Stok Saat Ini</th>
            <th>Rekomendasi Beli</th>
          </tr>
          ${tableRows || `<tr><td class="muted" colspan="6">Belum ada SPK kering & pengemas yang bisa diexport.</td></tr>`}
        </table>
      `,
    });

    downloadSpreadsheetHtml(`SPS-Rekomendasi-Belanja-Kering-Pengemas-${targetMonth ?? "latest"}.xls`, html);
  }

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const history = await sdk.spk.listKeringPengemas();
        const latest = [...(history.data ?? [])].sort((a, b) => b.calculation_date.localeCompare(a.calculation_date))[0];
        if (!latest) {
          if (!cancelled) setRows([]);
          return;
        }

        const detail = await sdk.spk.getKeringPengemas(latest.id);
        if (cancelled) return;

        setRows(aggregateRecommendationRows(detail.data.items ?? []) as RecommendationRow[]);
        setTargetMonth(detail.data.print_ready.target_month ?? null);
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat rekomendasi belanja kering & pengemas."));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Rekomendasi Belanja Kering & Pengemas"
        subtitle="Menampilkan hasil SPK kering & pengemas terbaru yang sudah tersedia di backend"
      />

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <SurfaceCard className="bg-[#DCEAFE] px-4 py-3 text-[13px] text-[#16213E]">
        <p className="font-semibold">Rumus SPK Bahan Kering & Pengemas</p>
        <p className="mt-2 font-mono text-[12px]">
          Total Pengeluaran Bulan Lalu x 10% - Sisa Stok Saat Ini
        </p>
      </SurfaceCard>

      <SurfaceCard className="overflow-hidden p-4">
        <p className="text-sm font-medium text-[#475569]">Periode Target</p>
        <p className="mt-2 text-lg font-semibold text-[#16213E]">{targetMonth ?? "-"}</p>
      </SurfaceCard>

      <SurfaceCard className="overflow-hidden">
        <div className="flex items-center justify-between border-b bg-[#F8FAFC] px-5 py-4">
          <h3 className="text-base font-semibold text-[#16213E]">Hasil Rekomendasi</h3>
          <ExportButton onClick={handleExport}>Export Rekomendasi</ExportButton>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <th className="px-6 py-3">Nama Bahan</th>
                <th className="px-6 py-3">Stok Saat Ini</th>
                <th className="px-6 py-3">Kebutuhan</th>
                <th className="px-6 py-3">Rekomendasi Beli</th>
              </tr>
            </thead>
            <tbody className="text-sm text-gray-700">
              {rows.map((row) => (
                <tr key={row.id} className="border-t border-gray-200 transition hover:bg-gray-50">
                  <td className="px-6 py-4 font-medium text-gray-900">{row.item_name ?? "-"}</td>
                  <td className="px-6 py-4">{formatQuantity(row.current_stock_qty, row.item_unit_base)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.required_qty, row.item_unit_base)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.final_recommended_qty, row.item_unit_base)}</td>
                </tr>
              ))}
              {!loading && rows.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={4}>
                    Belum ada SPK kering & pengemas yang bisa ditampilkan.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SurfaceCard>
    </div>
  );
}

function aggregateRecommendationRows<T extends {
  item_id?: number | null;
  item_name?: string | null;
  current_stock_qty?: unknown;
  required_qty?: unknown;
  final_recommended_qty?: unknown;
  item_unit_base?: string | null;
}>(rows: T[]) {
  const grouped = new Map<string, T & {
    current_stock_qty: number;
    required_qty: number;
    final_recommended_qty: number;
  }>();

  rows.forEach((row) => {
    const recommendedQty = Number(row.final_recommended_qty ?? 0);
    if (!Number.isFinite(recommendedQty) || recommendedQty <= 0) return;

    const key = String(row.item_id ?? row.item_name ?? grouped.size);
    const existing = grouped.get(key);
    if (existing) {
      existing.required_qty = (Number(existing.required_qty ?? 0) || 0) + (Number(row.required_qty ?? 0) || 0);
      existing.final_recommended_qty = (Number(existing.final_recommended_qty ?? 0) || 0) + recommendedQty;
      return;
    }

    grouped.set(key, {
      ...row,
      current_stock_qty: Number(row.current_stock_qty ?? 0) || 0,
      required_qty: Number(row.required_qty ?? 0) || 0,
      final_recommended_qty: recommendedQty,
    });
  });

  return [...grouped.values()];
}

function getItemCategory(row: RecommendationRow) {
  return (
    (row as { category_name?: string | null }).category_name ??
    (row as { item_category_name?: string | null }).item_category_name ??
    "-"
  );
}
