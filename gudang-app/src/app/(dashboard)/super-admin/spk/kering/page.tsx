"use client";

import { useState } from "react";
import sdk from "@/lib";
import { formatNumber, formatQuantity, getErrorMessage } from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  PrimaryAction,
  SurfaceCard,
} from "@/components/admin/ui";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>["data"]["items"][number];

export default function Page() {
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [targetMonth, setTargetMonth] = useState(new Date().toISOString().slice(0, 7));
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleGenerate() {
    setGenerating(true);
    setError(null);
    try {
      const generated = await sdk.spk.generateKeringPengemas({ target_month: targetMonth });
      const detail = await sdk.spk.getKeringPengemas(generated.data.id);
      setRows(detail.data.items ?? []);
    } catch (generateError) {
      setError(getErrorMessage(generateError, "Gagal generate SPK kering & pengemas."));
    } finally {
      setGenerating(false);
    }
  }

  return (
    <div className="space-y-5">
      <AdminPageHeading title="Rekomendasi Belanja Kering & Pengemas" subtitle="Generate kebutuhan bahan kering & pengemas untuk belanja bulanan" />

      {error ? <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">{error}</div> : null}

      <SurfaceCard className="bg-[#DCEAFE] px-4 py-3 text-[13px] text-[#16213E]">
        <p className="font-semibold">Rumus SPK Bahan Kering & Pengemas</p>
        <p className="mt-2 font-mono text-[12px]">Total Pengeluaran Bulan Lalu x 10% - Sisa Stok Saat Ini</p>
      </SurfaceCard>

      <SurfaceCard className="overflow-hidden p-4">
        <label className="block text-sm font-medium text-[#475569]">Target Bulan</label>
        <input
          type="month"
          value={targetMonth}
          onChange={(event) => setTargetMonth(event.target.value)}
          className="mt-2 h-11 rounded-xl border border-slate-200 px-4 text-sm text-slate-700 outline-none"
        />
      </SurfaceCard>

      <div>
        <PrimaryAction onClick={handleGenerate}>{generating ? "Generating..." : "Generate"}</PrimaryAction>
      </div>

      <SurfaceCard className="overflow-hidden">
        <div className="flex items-center justify-between border-b bg-[#F8FAFC] px-5 py-4">
          <h3 className="text-base font-semibold text-[#16213E]">Hasil Rekomendasi</h3>
          <ExportButton>Export Rekomendasi</ExportButton>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <th className="px-6 py-3">Nama Bahan</th>
                <th className="px-6 py-3">Stok Saat Ini</th>
                <th className="px-6 py-3">Kebutuhan</th>
                <th className="px-6 py-3">Rekomendasi Beli</th>
              </tr>
            </thead>
            <tbody className="text-sm text-gray-700">
              {rows.map((row, index) => (
                <tr key={row.id} className={`border-t border-gray-200 transition hover:bg-gray-50 ${index === 2 ? "bg-[#EEF4FF]" : ""}`}>
                  <td className="px-6 py-4 font-medium text-gray-900">{row.item_name ?? "-"}</td>
                  <td className="px-6 py-4">{formatQuantity(row.current_stock_qty, row.item_unit_base)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.required_qty, row.item_unit_base)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.final_recommended_qty, row.item_unit_base)}</td>
                </tr>
              ))}
              {rows.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={4}>
                    Klik Generate untuk mengambil rekomendasi SPK kering & pengemas bulan {formatNumber(Number(targetMonth.slice(5, 7)))}.
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
