"use client";

import { useEffect, useMemo, useState } from "react";
import sdk from "@/lib";
import { formatNumber, formatQuantity, getErrorMessage, toIsoDate } from "@/lib/admin-utils";
import { buildExportFilename } from "@/lib/export-filename";
import { listAllPaginatedRows } from "@/lib/pagination";
import { getSpkConflictId } from "@/lib/spk-conflicts";
import { addDaysIsoDate, findExistingBasahSpk } from "@/lib/spk-recommendations";
import {
  AdminPageHeading,
  ExportButton,
  PrimaryAction,
  SurfaceCard,
} from "@/components/admin/ui";
import GenerateSpkConfirmModal from "@/components/spk/GenerateSpkConfirmModal";
import {
  buildBasahRecommendationSpreadsheet,
  downloadRecommendationSpreadsheet,
} from "@/components/spk/recommendation-export";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getBasah>>["data"]["items"][number];
type LatestPatient = { id: number; date: string; total: number };
type RecommendationDetail = Awaited<ReturnType<typeof sdk.spk.getBasah>>["data"];
type StockReportRow = Awaited<ReturnType<typeof sdk.reports.getStocks>>["data"]["rows"][number];
type DailyPatientRow = Awaited<ReturnType<typeof sdk.dailyPatients.list>>["data"][number];

const ALL_STOCK_REPORT_PERIOD = {
  period_start: "2000-01-01",
  period_end: "2099-12-31",
} as const;

function formatSpkDate(value?: string | null) {
  if (!value) return "-";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("id-ID", {
    timeZone: "Asia/Jakarta",
    day: "2-digit",
    month: "short",
    year: "numeric",
  })
    .format(date)
    .replace(".", "");
}

function formatSpkDateRange(dates: string[]) {
  if (dates.length === 0) return "-";
  if (dates.length === 1) return formatSpkDate(dates[0]);

  const first = new Date(dates[0]);
  const last = new Date(dates[dates.length - 1]);
  if (Number.isNaN(first.getTime()) || Number.isNaN(last.getTime())) {
    return dates.map((date) => formatSpkDate(date)).join(" - ");
  }

  const sameMonth = first.getMonth() === last.getMonth() && first.getFullYear() === last.getFullYear();
  if (!sameMonth) return `${formatSpkDate(dates[0])} - ${formatSpkDate(dates[dates.length - 1])}`;

  const monthYear = new Intl.DateTimeFormat("id-ID", {
    timeZone: "Asia/Jakarta",
    month: "short",
    year: "numeric",
  })
    .format(last)
    .replace(".", "");
  return `${first.getDate()}-${last.getDate()} ${monthYear}`;
}

function getBasahTargetDates(serviceDate?: string | null) {
  if (!serviceDate) return [];
  const date = serviceDate.slice(0, 10);
  return [addDaysIsoDate(date, 1), addDaysIsoDate(date, 2)];
}

function overlayBasahRowsWithCurrentStock(rows: RecommendationRow[], stockRows: StockReportRow[]) {
  const stockMap = new Map<number, number>();

  for (const row of stockRows) {
    const itemId = Number(row.item_id ?? 0);
    const qty = Number(row.qty ?? row.current_stock ?? row.stock ?? row.stock_qty ?? 0);
    if (Number.isFinite(itemId) && itemId > 0 && Number.isFinite(qty)) {
      stockMap.set(itemId, qty);
    }
  }

  return rows.map((row) => {
    const latestStock = stockMap.get(Number(row.item_id));
    if (!Number.isFinite(latestStock)) {
      return row;
    }

    const resolvedLatestStock = Number(latestStock);
    const requiredQty = Number(row.required_qty ?? 0);
    const nextRecommendedQty = Math.max(requiredQty - resolvedLatestStock, 0);

    return {
      ...row,
      current_stock_qty: resolvedLatestStock,
      system_recommended_qty: nextRecommendedQty,
      final_recommended_qty: nextRecommendedQty,
    };
  });
}

export default function Page() {
  const [latestPatient, setLatestPatient] = useState<LatestPatient | null>(null);
  const [basahCategoryId, setBasahCategoryId] = useState<number | null>(null);
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [detailData, setDetailData] = useState<RecommendationDetail | null>(null);
  const [spkMeta, setSpkMeta] = useState<{ targetDates: string[]; estimatedPatients: number } | null>(null);
  const [hasLoadedRecommendation, setHasLoadedRecommendation] = useState(false);
  const [, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function loadLatestPatientSnapshot() {
    const patientsResponse = await listAllPaginatedRows<DailyPatientRow>(sdk.dailyPatients.list.bind(sdk.dailyPatients), {
      sortBy: "service_date",
      sortDir: "DESC",
    });
    const latest = [...patientsResponse].sort((a, b) => b.service_date.localeCompare(a.service_date))[0];
    if (latest) {
      const snapshot = { id: latest.id, date: latest.service_date, total: latest.total_patients };
      setLatestPatient(snapshot);
      return snapshot;
    }
    setLatestPatient(null);
    return null;
  }

  useEffect(() => {
    let cancelled = false;

    async function loadInitial() {
      setLoading(true);
      try {
        const [latestSnapshot, categoriesResponse, stockResponse] = await Promise.all([
          loadLatestPatientSnapshot(),
          sdk.itemCategories.list({ paginate: false, sortBy: "name", sortDir: "ASC" }),
          sdk.reports.getStocks(ALL_STOCK_REPORT_PERIOD).catch(() => ({ data: { rows: [] } })),
        ]);
        if (cancelled) return;
        const basahCategory = (categoriesResponse.data ?? []).find((category) =>
          category.name.toUpperCase().includes("BASAH")
        );
        setBasahCategoryId(basahCategory?.id ?? null);

        try {
          const history = await sdk.spk.listBasah();
          if (cancelled) return;
          const matchingSpk =
            latestSnapshot && basahCategory?.id
              ? findExistingBasahSpk(history.data ?? [], latestSnapshot.date, basahCategory.id)
              : null;
          if (matchingSpk) {
            const detail = await sdk.spk.getBasah(matchingSpk.id);
            if (cancelled) return;
            const hydratedRows = overlayBasahRowsWithCurrentStock(
              detail.data.items ?? [],
              (stockResponse.data.rows as StockReportRow[]) ?? [],
            );
            setDetailData(detail.data);
            setRows(aggregateRecommendationRows(hydratedRows));
            setHasLoadedRecommendation(true);
            setSpkMeta({
              targetDates: detail.data.print_ready.target_dates ?? [],
              estimatedPatients: detail.data.estimated_patients,
            });
          } else {
            setDetailData(null);
            setRows([]);
            setHasLoadedRecommendation(false);
            setSpkMeta(null);
          }
        } catch {
          // Riwayat SPK boleh kosong; halaman tetap bisa generate rekomendasi baru.
        }
      } catch (loadError) {
        if (!cancelled) setError(getErrorMessage(loadError, "Gagal memuat pasien harian."));
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void loadInitial();
    return () => {
      cancelled = true;
    };
  }, []);

  const bufferPatients = useMemo(() => {
    if (!latestPatient) return 0;
    return Math.round(latestPatient.total * 1.05);
  }, [latestPatient]);

  const displayedTargetDates = useMemo(() => {
    if (latestPatient) return getBasahTargetDates(latestPatient.date);
    return spkMeta?.targetDates ?? [];
  }, [latestPatient, spkMeta]);

  async function handleGenerate() {
    setGenerating(true);
    setError(null);
    try {
      const freshLatestPatient = await loadLatestPatientSnapshot();
      if (!freshLatestPatient) {
        throw new Error("Data pasien harian belum tersedia untuk generate SPK basah.");
      }
      if (!basahCategoryId) {
        throw new Error("Kategori bahan BASAH belum tersedia untuk generate SPK basah.");
      }
      let detail: Awaited<ReturnType<typeof sdk.spk.getBasah>>;
      const stockResponse = await sdk.reports.getStocks(ALL_STOCK_REPORT_PERIOD).catch(() => ({ data: { rows: [] } }));
      const generatePayload = {
        daily_patient_id: freshLatestPatient.id,
        service_date: freshLatestPatient.date ?? toIsoDate(new Date()),
        category_id: basahCategoryId,
      };
      try {
        const generated = await sdk.spk.generateBasah({
          ...generatePayload,
          regenerate: hasLoadedRecommendation,
        });
        detail = await sdk.spk.getBasah(generated.data.id);
      } catch (generateError) {
        const conflictSpkId = getSpkConflictId(generateError);
        if (!conflictSpkId) throw generateError;
        detail = await sdk.spk.getBasah(conflictSpkId);
      }
      const hydratedRows = overlayBasahRowsWithCurrentStock(
        detail.data.items ?? [],
        (stockResponse.data.rows as StockReportRow[]) ?? [],
      );
      setDetailData(detail.data);
      setRows(aggregateRecommendationRows(hydratedRows));
      setHasLoadedRecommendation(true);
      setSpkMeta({
        targetDates: detail.data.print_ready.target_dates ?? [],
        estimatedPatients: detail.data.estimated_patients,
      });
      setConfirmOpen(false);
    } catch (generateError) {
      setError(getErrorMessage(generateError, "Gagal generate SPK basah."));
    } finally {
      setGenerating(false);
    }
  }

  function handleExport() {
    if (typeof window === "undefined" || rows.length === 0) {
      setError("Belum ada hasil rekomendasi yang bisa diexport.");
      return;
    }

    const dateLabel =
      displayedTargetDates.length > 0
        ? `${displayedTargetDates[0]}${displayedTargetDates.length > 1 ? `_sd_${displayedTargetDates[displayedTargetDates.length - 1]}` : ""}`
        : toIsoDate(new Date());

    const html = buildBasahRecommendationSpreadsheet(
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
        targetLabel: formatSpkDateRange(displayedTargetDates),
        itemCountLabel: `${rows.length} Produk`,
        formulaTitle: "Rumus BAHAN BASAH",
        formulaDescription: "(Jumlah Pasien Terakhir x 105%) x Komposisi per Paket Menu - Sisa Stok",
      },
      rows.map((row) => ({
        itemName: row.item_name ?? "-",
        categoryName: detailData?.category?.name ?? "BASAH",
        currentStock: formatQuantity(row.current_stock_qty, row.item_unit_base),
        requiredQty: formatQuantity(row.required_qty, row.item_unit_base),
        recommendedQty: formatQuantity(row.final_recommended_qty, row.item_unit_base),
        numericRecommendedQty: Number(row.final_recommended_qty ?? 0),
      })),
    );

    downloadRecommendationSpreadsheet({
      filename: buildExportFilename(
        `sps-rekomendasi-belanja-spk-${detailData?.id ? String(detailData.id).padStart(3, "0") : dateLabel}`,
      ),
      html,
    });
  }

  return (
    <div className="space-y-6">
      <AdminPageHeading title="Rekomendasi Belanja Basah" subtitle="Generate kebutuhan bahan basah untuk 2 hari ke depan" />

      {error ? <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">{error}</div> : null}

      <div className="rounded-xl border-l-4 border-[#2155CD] bg-[#D9EAFE] px-5 py-4 text-[#16213E]">
        <p className="font-mono text-[15px] font-bold">Rumus SPK Bahan Basah</p>
        <p className="mt-3 font-mono text-[15px] leading-relaxed">(Jumlah Pasien Terakhir x 105%) x Komposisi per Paket Menu - Sisa Stok</p>
      </div>

      <div className="rounded-2xl border-2 border-[#2155CD] bg-[#D9EAFE] px-6 py-5 text-[#16213E]">
        <p className="mb-5 text-sm font-bold uppercase tracking-[0.04em]">Parameter</p>
        <div className="grid gap-5 md:grid-cols-3">
          <div>
            <p className="text-base font-medium">Tanggal Belanja</p>
            <p className="mt-1 font-mono text-xl font-bold leading-none">
              {formatSpkDateRange(displayedTargetDates)}
            </p>
          </div>
          <div>
            <p className="text-base font-medium">Pasien Terakhir</p>
            <p className="mt-1 font-mono text-xl font-bold">{latestPatient ? `${formatNumber(latestPatient.total)} orang` : "-"}</p>
          </div>
          <div>
            <p className="text-base font-medium">Setelah Buffer +5%</p>
            <p className="mt-1 font-mono text-xl font-bold">
              {latestPatient ? `${formatNumber(bufferPatients)} orang` : "-"}
              {latestPatient ? (
                <span className="font-sans text-base">
                  {" "}
                </span>
              ) : null}
            </p>
          </div>
        </div>
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
                <th className="px-6 py-4">Stok Saat Ini</th>
                <th className="px-6 py-4">Stok Minimal</th>
                <th className="px-6 py-4">Rekomendasi Beli</th>
              </tr>
            </thead>
            <tbody className="text-base text-[#16213E]">
              {rows.map((row) => (
                <tr key={row.id} className="transition hover:bg-[#F8FAFC]">
                  <td className="px-6 py-4 font-bold">{row.item_name ?? "-"}</td>
                  <td className="px-6 py-4">{formatQuantity(row.current_stock_qty, row.item_unit_base)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.required_qty, row.item_unit_base)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.final_recommended_qty, row.item_unit_base)}</td>
                </tr>
              ))}
              {rows.length === 0 ? (
                <tr>
                  <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={4}>
                    {hasLoadedRecommendation
                      ? "Tidak ada rekomendasi belanja karena kebutuhan bahan sudah tercukupi oleh stok saat ini."
                      : "Klik Generate untuk mengambil rekomendasi SPK basah."}
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
        targetLabel="belanja bahan basah 2 hari ke depan"
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

function aggregateRecommendationRows<T extends RecommendationRow>(rows: T[]) {
  const byItem = new Map<string, T>();

  for (const row of rows) {
    const recommendation = Number(row.final_recommended_qty ?? 0);
    if (!Number.isFinite(recommendation)) continue;

    const key = String(row.item_id ?? row.item_name ?? row.id);
    const current = byItem.get(key);
    if (current) {
      byItem.set(key, {
        ...current,
        required_qty: Number(current.required_qty ?? 0) + Number(row.required_qty ?? 0),
        final_recommended_qty: Number(current.final_recommended_qty ?? 0) + recommendation,
      });
      continue;
    }

    byItem.set(key, { ...row, final_recommended_qty: recommendation });
  }

  return Array.from(byItem.values());
}
