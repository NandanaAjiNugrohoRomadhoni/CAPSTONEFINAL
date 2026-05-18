"use client";

import { useState } from "react";
import sdk from "@/lib";
import { formatQuantity, getErrorMessage, toIsoMonth } from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  PrimaryAction,
  SurfaceCard,
} from "@/components/admin/ui";
import GenerateSpkConfirmModal from "@/components/spk/GenerateSpkConfirmModal";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>["data"]["items"][number];

function getItemCategory(row: RecommendationRow) {
  const flexibleRow = row as RecommendationRow & {
    item_category_name?: string | null;
    category_name?: string | null;
    item_category?: { name?: string | null } | null;
  };

  return flexibleRow.item_category_name ?? flexibleRow.category_name ?? flexibleRow.item_category?.name ?? "-";
}

export default function Page() {
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [targetMonth] = useState(toIsoMonth(new Date()));
  const [generating, setGenerating] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleGenerate() {
    setGenerating(true);
    setError(null);
    try {
      const generated = await sdk.spk.generateKeringPengemas({ target_month: targetMonth });
      const detail = await sdk.spk.getKeringPengemas(generated.data.id);
      setRows(detail.data.items ?? []);
      setConfirmOpen(false);
    } catch (generateError) {
      setError(getErrorMessage(generateError, "Gagal generate SPK kering & pengemas."));
    } finally {
      setGenerating(false);
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeading title="Rekomendasi Belanja Kering & Pengemas" subtitle="Generate kebutuhan bahan kering & pengemas untuk belanja bulanan" />

      {error ? <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">{error}</div> : null}

      <div className="rounded-xl border-l-4 border-[#2155CD] bg-[#D9EAFE] px-5 py-4 text-[#16213E]">
        <p className="font-mono text-[15px] font-bold">Rumus SPK Bahan Kering & Pengemas</p>
        <p className="mt-3 font-mono text-[15px] leading-relaxed">Total Pengeluaran Bulan Lalu x 10% - Sisa Stok Saat Ini</p>
      </div>

      <div>
        <PrimaryAction className="rounded-xl px-5 py-3 text-[15px] shadow-[0_8px_18px_rgba(33,85,205,0.32)]" disabled={generating} onClick={() => setConfirmOpen(true)}>
          {generating ? "Generating..." : "Generate"}
        </PrimaryAction>
      </div>

      <SurfaceCard className="overflow-hidden">
        <div className="flex items-center justify-between border-b border-[#E2E8F0] bg-white px-6 py-5">
          <h3 className="text-lg font-bold text-[#16213E]">Hasil Rekomendasi</h3>
          <ExportButton>Export Rekomendasi</ExportButton>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-base">
            <thead className="bg-[#EEF4FC] text-[15px] font-bold text-[#94A3B8]">
              <tr>
                <th className="px-6 py-4">Nama Bahan</th>
                <th className="px-6 py-4">Jenis Bahan</th>
                <th className="px-6 py-4">Pemakaian Bulan Lalu</th>
                <th className="px-6 py-4">Stok Saat Ini</th>
                <th className="px-6 py-4">Rekomendasi Beli</th>
              </tr>
            </thead>
            <tbody className="text-base text-[#16213E]">
              {rows.map((row) => (
                <tr key={row.id} className="transition hover:bg-[#F8FAFC]">
                  <td className="px-6 py-4 font-bold">{row.item_name ?? "-"}</td>
                  <td className="px-6 py-4 uppercase">{getItemCategory(row)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.required_qty, row.item_unit_base)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.current_stock_qty, row.item_unit_base)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.final_recommended_qty, row.item_unit_base)}</td>
                </tr>
              ))}
              {rows.length === 0 ? (
                <tr>
                  <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={5}>
                    Klik Generate untuk mengambil rekomendasi SPK kering & pengemas.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SurfaceCard>

      <GenerateSpkConfirmModal
        loading={generating}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() => void handleGenerate()}
        open={confirmOpen}
        targetLabel="belanja bahan kering & pengemas bulanan"
      />
    </div>
  );
}
