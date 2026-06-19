"use client";

import { useEffect, useState } from "react";
import sdk from "@/lib";
import { formatDate, formatNumber, formatQuantity, getErrorMessage } from "@/lib/admin-utils";
import {
  buildSpreadsheetDocument,
  downloadSpreadsheetHtml,
  escapeSpreadsheetHtml,
  formatSpreadsheetNumber,
} from "@/lib/spreadsheet-export";
import { AdminPageHeading, ExportButton, SurfaceCard } from "@/components/admin/ui";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getBasah>>["data"]["items"][number];

export default function GudangLatestBasahPage() {
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [meta, setMeta] = useState<{ targetDates: string[]; estimatedPatients: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  function handleExport() {
    if (typeof window === "undefined" || rows.length === 0) return;

    const summaryRows = [
      { label: "Tanggal Belanja", value: meta?.targetDates.length ? meta.targetDates.map((date) => formatDate(date)).join(" / ") : "-" },
      { label: "Estimasi Pasien", value: meta ? `${formatNumber(meta.estimatedPatients)} orang` : "-" },
      { label: "Total Item", value: formatSpreadsheetNumber(rows.length, 0) },
    ];

    const tableRows = rows
      .map(
        (row, index) => `
          <tr>
            <td class="rank">${index + 1}</td>
            <td class="text-strong">${escapeSpreadsheetHtml(row.item_name ?? "-")}</td>
            <td>${escapeSpreadsheetHtml(formatQuantity(row.current_stock_qty, row.item_unit_base))}</td>
            <td>${escapeSpreadsheetHtml(formatQuantity(row.required_qty, row.item_unit_base))}</td>
            <td>${escapeSpreadsheetHtml(formatQuantity(row.final_recommended_qty, row.item_unit_base))}</td>
          </tr>
        `,
      )
      .join("");

    const html = buildSpreadsheetDocument({
      title: "SPS - REKOMENDASI BELANJA BASAH",
      subtitle: "Hasil rekomendasi belanja basah terbaru dari backend.",
      body: `
        <table class="section-gap">
          <tr class="no-border">
            <td class="title" colspan="5">SPS - REKOMENDASI BELANJA BASAH</td>
          </tr>
          <tr class="no-border">
            <td class="subtitle" colspan="5">Hasil rekomendasi belanja basah terbaru dari backend.</td>
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
            <th>Stok Saat Ini</th>
            <th>Kebutuhan</th>
            <th>Rekomendasi Beli</th>
          </tr>
          ${tableRows || `<tr><td class="muted" colspan="5">Belum ada SPK basah yang bisa diexport.</td></tr>`}
        </table>
      `,
    });

    const dateLabel =
      meta?.targetDates.length
        ? `${meta.targetDates[0]}${meta.targetDates.length > 1 ? `-sd-${meta.targetDates[meta.targetDates.length - 1]}` : ""}`
        : "latest";
    downloadSpreadsheetHtml(`SPS-Rekomendasi-Belanja-${dateLabel}.xls`, html);
  }

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const history = await sdk.spk.listBasah();
        const latest = [...(history.data ?? [])].sort((a, b) => b.calculation_date.localeCompare(a.calculation_date))[0];
        if (!latest) {
          if (!cancelled) setRows([]);
          return;
        }

        const detail = await sdk.spk.getBasah(latest.id);
        if (cancelled) return;

        setRows(aggregateRecommendationRows(detail.data.items ?? []) as RecommendationRow[]);
        setMeta({
          targetDates: detail.data.print_ready.target_dates ?? [],
          estimatedPatients: detail.data.estimated_patients,
        });
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat rekomendasi belanja basah."));
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
        title="Rekomendasi Belanja Basah"
        subtitle="Menampilkan hasil SPK basah terbaru yang sudah tersedia di backend"
      />

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <SurfaceCard className="bg-[#DCEAFE] px-4 py-3 text-[13px] text-[#16213E]">
        <p className="font-semibold">Rumus SPK Bahan Basah</p>
        <p className="mt-2 font-mono text-[12px]">
          (Jumlah Pasien Terakhir x 105%) x Komposisi per Paket Menu - Sisa Stok
        </p>
      </SurfaceCard>

      <SurfaceCard className="overflow-hidden">
        <div className="grid gap-4 border border-[#2155CD] px-4 py-4 md:grid-cols-2">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.04em] text-[#94A3B8]">
              Tanggal Belanja
            </p>
            <p className="mt-2 text-[18px] font-bold text-[#16213E]">
              {meta?.targetDates.length ? meta.targetDates.map((date) => formatDate(date)).join(" / ") : "-"}
            </p>
          </div>
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.04em] text-[#94A3B8]">
              Estimasi Pasien
            </p>
            <p className="mt-2 text-[18px] font-bold text-[#16213E]">
              {meta ? `${formatNumber(meta.estimatedPatients)} orang` : "-"}
            </p>
          </div>
        </div>
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
                    Belum ada SPK basah yang bisa ditampilkan.
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
