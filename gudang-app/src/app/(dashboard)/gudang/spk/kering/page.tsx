"use client";

import { useEffect, useState } from "react";
import sdk from "@/lib";
import { formatQuantity, getErrorMessage, toIsoMonth } from "@/lib/admin-utils";
import { buildExportFilename } from "@/lib/export-filename";
import { getSpkConflictId } from "@/lib/spk-conflicts";
import { findExistingKeringSpk } from "@/lib/spk-recommendations";
import { aggregateKeringRecommendationRows } from "@/lib/spk-kering";
import {
  AdminPageHeading,
  ExportButton,
  PrimaryAction,
  SurfaceCard,
} from "@/components/admin/ui";
import GenerateSpkConfirmModal from "@/components/spk/GenerateSpkConfirmModal";
import {
  buildKeringRecommendationSpreadsheet,
  downloadRecommendationSpreadsheet,
} from "@/components/spk/recommendation-export";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>["data"]["items"][number];
type RecommendationDetail = Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>["data"];

function getItemCategory(row: RecommendationRow) {
  const flexibleRow = row as RecommendationRow & {
    item_category_name?: string | null;
    category_name?: string | null;
    item_category?: { name?: string | null } | null;
  };

  return flexibleRow.item_category_name ?? flexibleRow.category_name ?? flexibleRow.item_category?.name ?? "-";
}

function formatTargetMonthLabel(value: string) {
  const date = new Date(`${value}-01T00:00:00`);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("id-ID", {
    month: "long",
    year: "numeric",
    timeZone: "Asia/Jakarta",
  }).format(date);
}

export default function Page() {
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [detailData, setDetailData] = useState<RecommendationDetail | null>(null);
  const [hasLoadedRecommendation, setHasLoadedRecommendation] = useState(false);
  const [targetMonth] = useState(toIsoMonth(new Date()));
  const [generating, setGenerating] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadLatestRecommendation() {
      setError(null);
      try {
        const history = await sdk.spk.listKeringPengemas();
        if (cancelled) return;
        const matchingSpk = findExistingKeringSpk(history.data ?? [], targetMonth);
        const latestSpk = matchingSpk ?? getLatestSpk(history.data ?? []);
        if (!latestSpk) return;

        const detail = await sdk.spk.getKeringPengemas(latestSpk.id);
        if (cancelled) return;
        setDetailData(detail.data);
        setRows(aggregateKeringRecommendationRows(detail.data.items ?? []));
        setHasLoadedRecommendation(true);
      } catch {
        // Riwayat SPK boleh kosong; halaman tetap bisa generate rekomendasi baru.
      }
    }

    void loadLatestRecommendation();
    return () => {
      cancelled = true;
    };
  }, []);

  async function handleGenerate() {
    setGenerating(true);
    setError(null);
    try {
      let detail: Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>;
      try {
        const generated = await sdk.spk.generateKeringPengemas({
          target_month: targetMonth,
          regenerate: hasLoadedRecommendation,
        });
        detail = await sdk.spk.getKeringPengemas(generated.data.id);
      } catch (generateError) {
        const conflictSpkId = getSpkConflictId(generateError);
        if (!conflictSpkId) throw generateError;
        const regenerated = await sdk.spk.generateKeringPengemas({
          target_month: targetMonth,
          regenerate: true,
        });
        detail = await sdk.spk.getKeringPengemas(regenerated.data.id);
      }
      setDetailData(detail.data);
      setRows(aggregateKeringRecommendationRows(detail.data.items ?? []));
      setHasLoadedRecommendation(true);
      setConfirmOpen(false);
    } catch (generateError) {
      setError(getErrorMessage(generateError, "Gagal generate SPK kering & pengemas."));
    } finally {
      setGenerating(false);
    }
  }

  function handleExport() {
    if (typeof window === "undefined" || rows.length === 0) {
      setError("Belum ada hasil rekomendasi yang bisa diexport.");
      return;
    }
    const html = buildKeringRecommendationSpreadsheet(
      {
        spkId: detailData?.id ?? null,
        generatedBy: detailData?.user?.name ?? detailData?.user?.username ?? "Gudang User",
        calculationDate: detailData?.calculation_date
          ? new Intl.DateTimeFormat("id-ID", {
              timeZone: "Asia/Jakarta",
              day: "2-digit",
              month: "2-digit",
              year: "numeric",
            }).format(new Date(detailData.calculation_date))
          : "-",
        targetLabel: formatTargetMonthLabel(targetMonth),
        itemCountLabel: `${rows.length} Produk`,
        formulaTitle: "Rumus KERING & PENGEMAS",
        formulaDescription: "Total Pengeluaran Bulan Lalu x 110% - Sisa Stok Saat Ini",
        },
      rows.map((row) => ({
        itemName: row.item_name ?? "-",
        categoryName: getItemCategory(row),
        currentStock: formatQuantity(row.current_stock_qty, row.item_unit_base),
        requiredQty: formatQuantity(row.required_qty, row.item_unit_base),
        recommendedQty: formatQuantity(row.system_recommended_qty ?? row.final_recommended_qty, row.item_unit_base),
        numericRecommendedQty: Number(row.system_recommended_qty ?? row.final_recommended_qty ?? 0),
      })),
    );

    downloadRecommendationSpreadsheet({
      filename: buildExportFilename(
        `sps-rekomendasi-belanja-spk-${detailData?.id ? String(detailData.id).padStart(3, "0") : targetMonth}`,
      ),
      html,
    });
  }

  return (
    <div className="space-y-6">
      <AdminPageHeading title="Rekomendasi Belanja Kering & Pengemas" subtitle="Generate kebutuhan bahan kering & pengemas untuk belanja bulanan" />

      {error ? <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">{error}</div> : null}

      <div className="rounded-xl border-l-4 border-[#2155CD] bg-[#D9EAFE] px-5 py-4 text-[#16213E]">
        <p className="font-mono text-[15px] font-bold">Rumus SPK Bahan Kering & Pengemas</p>
        <p className="mt-3 font-mono text-[15px] leading-relaxed">Total Pengeluaran Bulan Lalu x 110% - Sisa Stok Saat Ini</p>
      </div>

      <div>
        <PrimaryAction className="rounded-xl px-5 py-3 text-[15px] shadow-[0_8px_18px_rgba(33,85,205,0.32)]" disabled={generating} onClick={() => setConfirmOpen(true)}>
          {generating ? "Generating..." : hasLoadedRecommendation ? "Regenerate" : "Generate"}
        </PrimaryAction>
      </div>

      <SurfaceCard className="overflow-hidden">
        <div className="flex items-center justify-between border-b border-[#E2E8F0] bg-white px-6 py-5">
          <h3 className="text-lg font-bold text-[#16213E]">Hasil Rekomendasi</h3>
          <ExportButton onClick={handleExport}>Export Rekomendasi</ExportButton>
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
                  <td className="px-6 py-4">{formatQuantity(row.system_recommended_qty ?? row.final_recommended_qty, row.item_unit_base)}</td>
                </tr>
              ))}
              {rows.length === 0 ? (
                <tr>
                  <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={5}>
                    {hasLoadedRecommendation
                      ? "Tidak ada data rekomendasi dari backend untuk bulan ini."
                      : "Klik Generate untuk mengambil rekomendasi SPK kering & pengemas."}
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>
      </SurfaceCard>

      <GenerateSpkConfirmModal
        isRegenerate={hasLoadedRecommendation}
        loading={generating}
        onClose={() => setConfirmOpen(false)}
        onConfirm={() => void handleGenerate()}
        open={confirmOpen}
        targetLabel="belanja bahan kering & pengemas bulanan"
      />
    </div>
  );
}

function getLatestSpk<T extends { id: number; created_at?: string | null; calculation_date?: string | null }>(rows: T[]) {
  return [...rows].sort((left, right) => {
    const rightTime = new Date(right.created_at ?? right.calculation_date ?? "").getTime();
    const leftTime = new Date(left.created_at ?? left.calculation_date ?? "").getTime();
    if (Number.isFinite(rightTime) && Number.isFinite(leftTime) && rightTime !== leftTime) {
      return rightTime - leftTime;
    }
    return Number(right.id) - Number(left.id);
  })[0];
}

