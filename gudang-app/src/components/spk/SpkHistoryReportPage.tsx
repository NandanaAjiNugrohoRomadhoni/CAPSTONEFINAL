"use client";

import { useEffect, useMemo, useState } from "react";
import { X } from "lucide-react";
import sdk from "@/lib";
import { formatDate, formatQuantity, getCurrentMonthPeriod, getErrorMessage } from "@/lib/admin-utils";
import { addDaysIsoDate } from "@/lib/spk-recommendations";
import { isIsoDateInRange } from "@/lib/date-range";
import { buildExportFilename } from "@/lib/export-filename";
import {
  AdminPageHeading,
  FilterSearch,
  MiniActionButton,
  Pagination,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import DateRangePicker from "@/components/filters/DateRangePicker";

type SpkHistoryRow = {
  spk_id: number;
  calculation_date: string;
  created_at?: string | null;
  spk_type: string;
  category_name?: string | null;
  total_recommendations?: number | null;
  total_recommended_qty?: number | null;
};

type SpkHistoryEntry = Awaited<ReturnType<typeof sdk.spk.listBasah>>["data"][number];
type BasahDetail = Awaited<ReturnType<typeof sdk.spk.getBasah>>["data"];
type KeringDetail = Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>["data"];
type SpkDetailState =
  | { type: "BASAH"; detail: BasahDetail }
  | { type: "KERING_PENGEMAS"; detail: KeringDetail };
type SpkExportRow = {
  key: string;
  itemName: string;
  categoryName: string;
  currentStock: string;
  requiredQty: string;
  recommendedQty: string;
  numericRecommendedQty: number;
  volume: string;
};

export default function SpkHistoryReportPage() {
  const [rows, setRows] = useState<SpkHistoryRow[]>([]);
  const [detailState, setDetailState] = useState<SpkDetailState | null>(null);
  const [exportState, setExportState] = useState<SpkDetailState | null>(null);
  const [openingDetailId, setOpeningDetailId] = useState<number | null>(null);
  const [openingExportId, setOpeningExportId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [dateRange, setDateRange] = useState({ startDate: "", endDate: "" });
  const [selectedType, setSelectedType] = useState("all");
  const [currentPage, setCurrentPage] = useState(1);
  const pageSize = 10;
  const filteredRows = useMemo(() => {
    const query = search.trim().toLowerCase();
    return rows.filter((row) => {
      const spkId = `spk-${String(row.spk_id).padStart(4, "0")}`.toLowerCase();
      const category = (row.category_name ?? "").toLowerCase();
      const typeLabel = getSpkTypeLabel(row.spk_type).toLowerCase();
      const matchesSearch =
        query.length === 0 ||
        spkId.includes(query) ||
        category.includes(query) ||
        typeLabel.includes(query);
      const rowDate = row.calculation_date.slice(0, 10);
      const matchesDate = isIsoDateInRange(rowDate, dateRange);
      const matchesType = selectedType === "all" || row.spk_type === selectedType;
      return matchesSearch && matchesDate && matchesType;
    });
  }, [dateRange, rows, search, selectedType]);
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
  const pageStartIndex = (currentPage - 1) * pageSize;
  const paginatedRows = useMemo(
    () => filteredRows.slice(pageStartIndex, pageStartIndex + pageSize),
    [filteredRows, pageStartIndex],
  );
  const pageStartLabel = filteredRows.length === 0 ? 0 : pageStartIndex + 1;
  const pageEndLabel = Math.min(filteredRows.length, pageStartIndex + pageSize);

  useEffect(() => {
    setCurrentPage(1);
  }, [dateRange, filteredRows.length, search, selectedType]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const nextRows = await loadSpkHistoryRows();
        if (cancelled) return;

        setRows(nextRows);
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat riwayat SPK."));
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, []);

  async function openDetail(row: SpkHistoryRow) {
    setOpeningDetailId(row.spk_id);
    setError(null);

    try {
      if (row.spk_type === "BASAH") {
        const response = await sdk.spk.getBasah(row.spk_id);
        setDetailState({ type: "BASAH", detail: response.data });
        return;
      }

      const response = await sdk.spk.getKeringPengemas(row.spk_id);
      setDetailState({ type: "KERING_PENGEMAS", detail: response.data });
    } catch (loadError) {
      setError(getErrorMessage(loadError, "Gagal memuat detail SPK."));
    } finally {
      setOpeningDetailId(null);
    }
  }

  async function openExport(row: SpkHistoryRow) {
    setOpeningExportId(row.spk_id);
    setError(null);

    try {
      if (row.spk_type === "BASAH") {
        const response = await sdk.spk.getBasah(row.spk_id);
        setExportState({ type: "BASAH", detail: response.data });
        return;
      }

      const response = await sdk.spk.getKeringPengemas(row.spk_id);
      setExportState({ type: "KERING_PENGEMAS", detail: response.data });
    } catch (loadError) {
      setError(getErrorMessage(loadError, "Gagal memuat data export SPK."));
    } finally {
      setOpeningExportId(null);
    }
  }

  return (
    <>
      <div className="space-y-5">
        <AdminPageHeading
          title="Riwayat SPK"
          subtitle="Riwayat Sistem Pengambilan Keputusan Belanja Basah, Kering & Pengemas"
        />

        {error ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
            {error}
          </div>
        ) : null}

        <SurfaceCard className="overflow-hidden">
          <div className="flex flex-wrap items-center gap-3 border-b bg-[#F8FAFC] px-5 py-4">
            <div className="flex flex-col gap-3 lg:flex-row">
              <div className="w-full lg:w-[220px]">
                <FilterSearch
                  onChange={setSearch}
                  placeholder="Cari"
                  readOnly={false}
                  value={search}
                />
              </div>
              <DateRangePicker
                ariaLabel="Rentang tanggal SPK"
                className="min-w-[240px]"
                endDate={dateRange.endDate}
                onChange={setDateRange}
                placeholder="dd/mm/yyyy"
                startDate={dateRange.startDate}
              />
              <ThemedSelect
                className="min-w-[170px]"
                value={selectedType}
                onChange={setSelectedType}
                options={[
                  { value: "all", label: "Semua Jenis" },
                  { value: "BASAH", label: "BASAH" },
                  { value: "KERING_PENGEMAS", label: "KERING & PENGEMAS" },
                ]}
              />
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                  <th className="px-6 py-3">ID SPK</th>
                  <th className="px-6 py-3">Tanggal</th>
                  <th className="px-6 py-3">Kategori</th>
                  <th className="px-6 py-3">Jenis SPK</th>
                  <th className="px-6 py-3">Total Rekomendasi</th>
                  <th className="px-6 py-3">Aksi</th>
                </tr>
              </thead>
              <tbody className="text-sm text-gray-700">
                {paginatedRows.map((row) => (
                  <tr
                    key={`${row.spk_type}-${row.spk_id}`}
                    className="border-t border-gray-200 transition hover:bg-gray-50"
                  >
                    <td className="px-6 py-4 font-medium text-gray-900">
                      SPK-{String(row.spk_id).padStart(4, "0")}
                    </td>
                    <td className="px-6 py-4">{formatDate(row.calculation_date)}</td>
                    <td className="px-6 py-4 font-medium text-gray-900">
                      {row.category_name ?? "-"}
                    </td>
                    <td className="px-6 py-4">
                      {getSpkTypeLabel(row.spk_type)}
                    </td>
                    <td className="px-6 py-4">
                      {typeof row.total_recommendations === "number"
                        ? `${row.total_recommendations} bahan`
                        : "-"}
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex flex-wrap items-center gap-2">
                        <MiniActionButton onClick={() => void openDetail(row)}>
                          {openingDetailId === row.spk_id ? "Memuat..." : "Detail"}
                        </MiniActionButton>
                        <button
                          className="rounded-lg border border-[#2155CD] bg-[#2155CD] px-3 py-2 text-sm font-semibold text-white shadow-[0_8px_20px_rgba(33,85,205,0.24)] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-[#1E4BC0] hover:shadow-[0_10px_24px_rgba(33,85,205,0.28)]"
                          onClick={() => void openExport(row)}
                          type="button"
                        >
                          {openingExportId === row.spk_id ? "Memuat..." : "Export"}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {!loading && filteredRows.length === 0 ? (
                  <tr>
                    <td className="px-6 py-8 text-center text-gray-400" colSpan={6}>
                      Belum ada riwayat SPK pada periode ini.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>

          <Pagination
            currentPage={currentPage}
            onPageChange={setCurrentPage}
            totalLabel={`${pageStartLabel}-${pageEndLabel} dari ${filteredRows.length} item`}
            totalPages={totalPages}
          />
        </SurfaceCard>
      </div>

      {detailState ? <SpkDetailModal detailState={detailState} onClose={() => setDetailState(null)} /> : null}
      {exportState ? <SpkExportModal detailState={exportState} onClose={() => setExportState(null)} /> : null}
    </>
  );
}

async function loadSpkHistoryRows() {
  const [reportResult, basahResult, keringResult] = await Promise.allSettled([
    sdk.reports.getSpkHistory(getCurrentMonthPeriod()),
    sdk.spk.listBasah(),
    sdk.spk.listKeringPengemas(),
  ]);

  const rowsByKey = new Map<string, SpkHistoryRow>();

  if (reportResult.status === "fulfilled") {
    const reportRows = (reportResult.value.data.rows as SpkHistoryRow[] | undefined) ?? [];
    reportRows.forEach((row) => {
      const flexibleRow = row as SpkHistoryRow & { updated_at?: string | null };
      const spkType = normalizeSpkType(row.spk_type, row.category_name);
      rowsByKey.set(historyKey(row.spk_id, spkType), {
        ...row,
        created_at: row.created_at ?? flexibleRow.updated_at ?? row.calculation_date,
        spk_type: spkType,
      });
    });
  }

  if (basahResult.status === "fulfilled") {
    basahResult.value.data.forEach((entry) => {
      const key = historyKey(entry.id, "BASAH");
      const existing = rowsByKey.get(key);
      if (existing) {
        rowsByKey.set(key, mergeHistoryEntry(existing, entry));
      } else {
        rowsByKey.set(key, rowFromHistoryEntry(entry, "BASAH"));
      }
    });
  }

  if (keringResult.status === "fulfilled") {
    keringResult.value.data.forEach((entry) => {
      const key = historyKey(entry.id, "KERING_PENGEMAS");
      const existing = rowsByKey.get(key);
      if (existing) {
        rowsByKey.set(key, mergeHistoryEntry(existing, entry));
      } else {
        rowsByKey.set(key, rowFromHistoryEntry(entry, "KERING_PENGEMAS"));
      }
    });
  }

  if (
    reportResult.status === "rejected" &&
    basahResult.status === "rejected" &&
    keringResult.status === "rejected"
  ) {
    throw reportResult.reason;
  }

  return [...rowsByKey.values()].sort((a, b) => {
    const timeDiff = getHistorySortTime(b) - getHistorySortTime(a);
    if (timeDiff !== 0) return timeDiff;
    return b.spk_id - a.spk_id;
  });
}

function rowFromHistoryEntry(entry: SpkHistoryEntry, spkType: "BASAH" | "KERING_PENGEMAS"): SpkHistoryRow {
  const flexibleEntry = entry as SpkHistoryEntry & {
    total_recommendations?: number | null;
    total_recommended_qty?: number | null;
  };

  return {
    spk_id: entry.id,
    calculation_date: entry.calculation_date,
    created_at: entry.created_at ?? entry.calculation_date,
    spk_type: spkType,
    category_name: entry.category?.name ?? null,
    total_recommendations: flexibleEntry.total_recommendations ?? null,
    total_recommended_qty: flexibleEntry.total_recommended_qty ?? null,
  };
}

function mergeHistoryEntry(row: SpkHistoryRow, entry: SpkHistoryEntry): SpkHistoryRow {
  return {
    ...row,
    created_at: row.created_at ?? entry.created_at ?? entry.calculation_date,
    category_name: row.category_name ?? entry.category?.name ?? null,
  };
}

function getHistorySortTime(row: SpkHistoryRow) {
  const value = row.created_at || row.calculation_date;
  const time = new Date(value).getTime();
  return Number.isFinite(time) ? time : 0;
}

function normalizeSpkType(value: string, categoryName?: string | null) {
  const category = (categoryName ?? "").toUpperCase();
  if (category.includes("BASAH")) return "BASAH";
  if (category.includes("KERING") || category.includes("PENGEMAS")) return "KERING_PENGEMAS";
  return value === "BASAH" ? "BASAH" : "KERING_PENGEMAS";
}

function historyKey(spkId: number, spkType: string) {
  return `${spkType}-${spkId}`;
}

function getSpkTypeLabel(spkType: string) {
  return spkType === "BASAH" ? "BASAH" : "KERING & PENGEMAS";
}

function SpkDetailModal({
  detailState,
  onClose,
}: {
  detailState: SpkDetailState;
  onClose: () => void;
}) {
  const detail = detailState.detail;
  const typeLabel = detailState.type === "BASAH" ? "BASAH" : "KERING & PENGEMAS";
  const aggregatedItems = aggregateSpkItems(detail.items);
  const targetLabel = getSpkRecommendationDateLabel(detailState);

  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center px-4 py-6">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative flex max-h-[calc(100vh-3rem)] w-full max-w-[760px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
          <div>
            <h2 className="text-[24px] font-semibold text-[#16213E]">Detail SPK {typeLabel}</h2>
            <p className="mt-1 text-sm text-[#94A3B8]">Rincian hasil rekomendasi belanja yang tersimpan di backend.</p>
          </div>
          <button
            className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD]"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-5 py-5">
          <div className="space-y-4">
            <div className="grid gap-3 rounded-2xl bg-[#EEF4FF] px-4 py-3 md:grid-cols-3">
              <InfoBlock label="ID SPK" value={`SPK-${String(detail.id).padStart(4, "0")}`} />
              <InfoBlock label="Tanggal Hitung" value={formatDate(detail.calculation_date)} />
              <InfoBlock label="Jenis SPK" value={typeLabel} />
              <InfoBlock label="Kategori" value={detail.category?.name ?? "-"} />
              <InfoBlock label="Target" value={targetLabel} />
              <InfoBlock label="Estimasi Pasien" value={`${detail.estimated_patients} orang`} />
              <InfoBlock label="Dibuat Oleh" value={detail.user?.name ?? detail.user?.username ?? "-"} />
              <InfoBlock label="Versi" value={`v${detail.version}`} />
              <InfoBlock label="Total Item" value={`${aggregatedItems.length} bahan`} />
            </div>

            <div className="overflow-hidden rounded-2xl border border-[#D7E0EE]">
              <div
                className="grid grid-cols-[52px_1.7fr_120px_120px_140px] bg-[#F1F5F9] px-4 py-3 text-xs font-semibold uppercase tracking-wide text-[#94A3B8]"
              >
                <div>#</div>
                <div>Nama Bahan</div>
                <div>Stok</div>
                <div>Kebutuhan</div>
                <div>Rekomendasi</div>
              </div>
              <div className="divide-y divide-[#E2E8F0]">
                {aggregatedItems.map((item, index) => (
                  <div
                    key={item.key}
                    className="grid grid-cols-[52px_1.7fr_120px_120px_140px] px-4 py-3 text-sm text-[#334155]"
                  >
                    <div>{index + 1}</div>
                    <div className="font-semibold text-[#16213E]">{item.item_name ?? "-"}</div>
                    <div>{formatQuantity(item.current_stock_qty, item.item_unit_base)}</div>
                    <div>{formatQuantity(item.required_qty, item.item_unit_base)}</div>
                    <div>{formatQuantity(item.final_recommended_qty, item.item_unit_base)}</div>
                  </div>
                ))}
                {aggregatedItems.length === 0 ? (
                  <div className="px-4 py-6 text-center text-sm text-[#94A3B8]">
                    Tidak ada item rekomendasi dari backend.
                  </div>
                ) : null}
              </div>
            </div>
          </div>
        </div>

        <div className="flex justify-end border-t border-[#E2E8F0] px-5 py-4">
          <button
            className="rounded-xl border border-[#2155CD] bg-white px-5 py-2.5 text-base font-medium text-[#2155CD] transition hover:bg-[#EEF4FF]"
            onClick={onClose}
            type="button"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  );
}

function SpkExportModal({
  detailState,
  onClose,
}: {
  detailState: SpkDetailState;
  onClose: () => void;
}) {
  const detail = detailState.detail;
  const typeLabel = detailState.type === "BASAH" ? "BASAH" : "KERING & PENGEMAS";
  const rows = buildSpkExportRows(detailState);
  const recommendationDateLabel = getSpkRecommendationDateLabel(detailState);

  function handleExport() {
    downloadSpkExport(detailState, rows);
    onClose();
  }

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center px-4 py-6">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative flex max-h-[calc(100vh-3rem)] w-full max-w-[980px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-[#E2E8F0] px-6 py-5">
          <div>
            <h2 className="text-[26px] font-semibold text-[#16213E]">Export SPK {typeLabel}</h2>
            <p className="mt-1 text-base text-[#94A3B8]">
              Periksa tanggal rekomendasi dan total volume belanja per item sebelum file diunduh.
            </p>
          </div>
          <button
            className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD]"
            onClick={onClose}
            type="button"
          >
            <X size={20} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-6 py-5">
          <div className="mb-5 grid gap-3 rounded-2xl bg-[#EEF4FF] px-5 py-4 md:grid-cols-4">
            <InfoBlock label="ID SPK" value={`SPK-${String(detail.id).padStart(4, "0")}`} />
            <InfoBlock label="Tanggal Rekomendasi" value={recommendationDateLabel} />
            <InfoBlock label="Jenis SPK" value={typeLabel} />
            <InfoBlock label="Jumlah Pasien" value={`${detail.estimated_patients} orang`} />
          </div>

          <div className="overflow-hidden rounded-2xl border border-[#D7E0EE]">
            <div className="grid grid-cols-[56px_1.5fr_180px] bg-[#F1F5F9] px-4 py-3 text-xs font-semibold uppercase tracking-wide text-[#64748B]">
              <div>#</div>
              <div>Nama Bahan</div>
              <div>Total Volume Belanja</div>
            </div>
            <div className="divide-y divide-[#E2E8F0]">
              {rows.map((row, index) => (
                <div
                  key={row.key}
                  className="grid grid-cols-[56px_1.5fr_180px] px-4 py-3 text-base text-[#334155]"
                >
                  <div>{index + 1}</div>
                  <div className="font-semibold text-[#16213E]">{row.itemName}</div>
                  <div>{row.volume}</div>
                </div>
              ))}
              {rows.length === 0 ? (
                <div className="px-4 py-8 text-center text-sm text-[#94A3B8]">
                  Tidak ada data item dari backend untuk SPK ini.
                </div>
              ) : null}
            </div>
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-[#E2E8F0] px-6 py-5">
          <button
            className="rounded-xl border border-[#CBD5E1] bg-white px-6 py-3 text-base font-medium text-[#475569] transition hover:bg-[#F8FAFC]"
            onClick={onClose}
            type="button"
          >
            Batal
          </button>
          <button
            className="rounded-xl bg-[#2155CD] px-6 py-3 text-base font-semibold text-white shadow-[0_10px_24px_rgba(33,85,205,0.24)] transition hover:-translate-y-0.5"
            onClick={handleExport}
            type="button"
          >
            Export
          </button>
        </div>
      </div>
    </div>
  );
}

function InfoBlock({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">{label}</div>
      <div className="mt-1 text-base font-semibold text-[#16213E]">{value}</div>
    </div>
  );
}

function aggregateSpkItems(items: Array<{
  item_id?: number | null;
  item_name?: string | null;
  current_stock_qty?: unknown;
  required_qty?: unknown;
  final_recommended_qty?: unknown;
  item_unit_base?: string | null;
}>) {
  const grouped = new Map<string, {
    key: string;
    item_name?: string | null;
    current_stock_qty: number;
    required_qty: number;
    final_recommended_qty: number;
    item_unit_base?: string | null;
  }>();

  items.forEach((item) => {
    const recommendation = Number(item.final_recommended_qty ?? 0);
    if (!Number.isFinite(recommendation)) return;

    const key = String(item.item_id ?? item.item_name ?? grouped.size);
    const current = grouped.get(key);
    if (current) {
      current.required_qty += Number(item.required_qty ?? 0) || 0;
      current.final_recommended_qty += recommendation;
      return;
    }

    grouped.set(key, {
      key,
      item_name: item.item_name,
      current_stock_qty: Number(item.current_stock_qty ?? 0) || 0,
      required_qty: Number(item.required_qty ?? 0) || 0,
      final_recommended_qty: recommendation,
      item_unit_base: item.item_unit_base,
    });
  });

  return [...grouped.values()];
}

function buildSpkExportRows(detailState: SpkDetailState): SpkExportRow[] {
  const detail = detailState.detail;
  const grouped = new Map<
    string,
    SpkExportRow & {
      numericCurrentStock: number;
      numericRequiredQty: number;
      numericRecommendedQty: number;
      unit?: string | null;
    }
  >();

  detail.items.forEach((item) => {
    const numericVolume = Number(item.final_recommended_qty ?? 0);
    if (!Number.isFinite(numericVolume)) return;

    const key = String(item.item_id ?? item.item_name ?? item.id);
    const current = grouped.get(key);
    if (current) {
      current.numericRequiredQty += Number(item.required_qty ?? 0) || 0;
      current.numericRecommendedQty += numericVolume;
      current.requiredQty = formatQuantity(current.numericRequiredQty, current.unit);
      current.recommendedQty = formatQuantity(current.numericRecommendedQty, current.unit);
      current.volume = current.recommendedQty;
      return;
    }

    const numericCurrentStock = Number(item.current_stock_qty ?? 0) || 0;
    const numericRequiredQty = Number(item.required_qty ?? 0) || 0;

    grouped.set(key, {
      key,
      itemName: item.item_name ?? "-",
      categoryName: detail.category?.name ?? getSpkTypeLabel(detail.spk_type),
      currentStock: formatQuantity(numericCurrentStock, item.item_unit_base),
      requiredQty: formatQuantity(numericRequiredQty, item.item_unit_base),
      recommendedQty: formatQuantity(numericVolume, item.item_unit_base),
      numericCurrentStock,
      numericRequiredQty,
      numericRecommendedQty: numericVolume,
      unit: item.item_unit_base,
      volume: formatQuantity(numericVolume, item.item_unit_base),
    });
  });

  return [...grouped.values()];
}

function downloadSpkExport(detailState: SpkDetailState, rows: SpkExportRow[]) {
  const detail = detailState.detail;
  const html = buildSpkExportSpreadsheet(detailState, rows);
  const blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = buildExportFilename(`sps-rekomendasi-belanja-spk-${String(detail.id).padStart(4, "0")}`);
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

function buildSpkExportSpreadsheet(detailState: SpkDetailState, rows: SpkExportRow[]) {
  const detail = detailState.detail;
  const typeLabel = detailState.type === "BASAH" ? "Basah" : "Kering & Pengemas";
  const recommendationDateLabel = getSpkRecommendationDateLabel(detailState);
  const formula =
    detailState.type === "BASAH"
      ? "(Jumlah Pasien Terakhir x 5%) x Komposisi per Paket Menu - Sisa Stok"
      : "Total Pengeluaran Bulan Lalu x 10% - Sisa Stok Saat Ini";
  const generatedBy = detail.user?.name ?? detail.user?.username ?? "-";
  const totalRecommended = rows.reduce((total, row) => total + row.numericRecommendedQty, 0);

  return `<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <style>
    body { font-family: Arial, sans-serif; color: #173321; }
    .title { font-size: 24px; font-weight: 800; color: #166534; margin-bottom: 4px; }
    .subtitle { color: #4B5563; font-size: 12px; margin-bottom: 14px; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #D9EAD3; padding: 8px; font-size: 12px; vertical-align: middle; }
    .no-border td { border: 0; }
    .summary { background: #F0FDF4; border: 1px solid #BBF7D0; }
    .summary-label { color: #14532D; font-weight: 700; width: 150px; }
    .summary-value { color: #111827; font-weight: 600; }
    .pill { background: #DCFCE7; color: #166534; font-weight: 800; text-align: center; }
    .method { background: #ECFDF5; color: #166534; font-weight: 800; font-size: 18px; text-align: center; }
    .section { background: #DCFCE7; color: #14532D; font-weight: 800; }
    .head { background: #166534; color: #FFFFFF; font-weight: 800; text-align: center; }
    .rank { text-align: center; font-weight: 700; }
    .number { text-align: right; }
    .ok { color: #15803D; font-weight: 700; }
    .muted { color: #64748B; }
  </style>
</head>
<body>
  <div class="title">SPS - REKOMENDASI BELANJA</div>
  <div class="subtitle">Hasil rekomendasi belanja berdasarkan data SPK dan stok bahan pada sistem.</div>

  <table class="no-border">
    <tr>
      <td style="width: 38%; padding: 0 12px 12px 0;">
        <table class="summary">
          <tr><td class="summary-label">Nama Pengaju</td><td class="summary-value">${escapeHtml(generatedBy)}</td></tr>
          <tr><td class="summary-label">Jenis SPK</td><td class="summary-value">${escapeHtml(typeLabel)}</td></tr>
          <tr><td class="summary-label">Tanggal Berlaku</td><td class="summary-value">${escapeHtml(recommendationDateLabel)}</td></tr>
          <tr><td class="summary-label">Jumlah Produk</td><td class="summary-value">${rows.length} Produk</td></tr>
          <tr><td class="summary-label">Total Item Rekomendasi</td><td class="summary-value">${formatPlainNumber(totalRecommended)}</td></tr>
        </table>
      </td>
      <td style="width: 40%; padding: 0 12px 12px 0;">
        <table>
          <tr><td class="section" colspan="4">KRITERIA YANG DIGUNAKAN</td></tr>
          <tr class="head"><th>Kode</th><th>Kriteria</th><th>Tipe</th><th>Keterangan</th></tr>
          <tr><td>C1</td><td>Stok Saat Ini</td><td>Cost</td><td>Semakin rendah, semakin prioritas</td></tr>
          <tr><td>C2</td><td>Kebutuhan</td><td>Benefit</td><td>Semakin tinggi, semakin prioritas</td></tr>
          <tr><td>C3</td><td>Rekomendasi Sistem</td><td>Benefit</td><td>Jumlah belanja akhir dari sistem</td></tr>
        </table>
      </td>
      <td style="width: 22%; padding: 0 0 12px 0;">
        <table>
          <tr><td class="pill">Tanggal Perhitungan</td><td>${escapeHtml(formatDate(detail.calculation_date))}</td></tr>
          <tr><td class="pill">ID SPK</td><td>SPK-${String(detail.id).padStart(4, "0")}</td></tr>
          <tr><td class="method" colspan="2">METODE<br/>SPK</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <table style="margin-bottom: 12px;">
    <tr><td class="section">Rumus ${escapeHtml(getSpkTypeLabel(detail.spk_type))}</td></tr>
    <tr><td>${escapeHtml(formula)}</td></tr>
  </table>

  <table>
    <tr class="head">
      <th>Ranking</th>
      <th>Nama Bahan</th>
      <th>Kategori</th>
      <th>Stok Saat Ini</th>
      <th>Kebutuhan</th>
      <th>Rekomendasi Beli</th>
      <th>Rekomendasi</th>
    </tr>
    ${rows
      .map(
        (row, index) => `
    <tr>
      <td class="rank">${index + 1}</td>
      <td><strong>${escapeHtml(row.itemName)}</strong></td>
      <td>${escapeHtml(row.categoryName)}</td>
      <td class="number">${escapeHtml(row.currentStock)}</td>
      <td class="number">${escapeHtml(row.requiredQty)}</td>
      <td class="number"><strong>${escapeHtml(row.recommendedQty)}</strong></td>
      <td class="${row.numericRecommendedQty > 0 ? "ok" : "muted"}">${row.numericRecommendedQty > 0 ? "Direkomendasikan" : "Tidak ada tambahan"}</td>
    </tr>`,
      )
      .join("")}
  </table>
</body>
</html>`;
}

function escapeHtml(value: unknown) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function formatPlainNumber(value: number) {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
  }).format(value);
}

function formatMonthLabel(value?: string | null) {
  if (!value) return "-";
  const date = value.length === 7 ? new Date(`${value}-01T00:00:00`) : new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat("id-ID", {
    month: "long",
    timeZone: "Asia/Jakarta",
    year: "numeric",
  }).format(date);
}

function getSpkRecommendationDateLabel(detailState: SpkDetailState) {
  if (detailState.type === "KERING_PENGEMAS") {
    const detail = detailState.detail;
    return formatMonthLabel(detail.target_month ?? detail.calculation_date);
  }

  const detail = detailState.detail;
  const calculatedTargetDates = detail.calculation_date
    ? [
        addDaysIsoDate(detail.calculation_date.slice(0, 10), 1),
        addDaysIsoDate(detail.calculation_date.slice(0, 10), 2),
      ]
    : [];
  const targetDates = calculatedTargetDates.length
    ? calculatedTargetDates
    : detail.print_ready.target_dates?.length
      ? detail.print_ready.target_dates
      : [detail.target_date_start, detail.target_date_end].filter((value): value is string => Boolean(value));

  return formatDateRangeLabel(targetDates.length ? targetDates : [detail.calculation_date]);
}

function formatDateRangeLabel(values: string[]) {
  const dates = values
    .map((value) => parseLocalDate(value))
    .filter((date): date is Date => Boolean(date))
    .sort((a, b) => a.getTime() - b.getTime());

  if (dates.length === 0) return "-";
  if (dates.length === 1) return formatDate(toDateInputValue(dates[0]));

  const first = dates[0];
  const last = dates[dates.length - 1];
  const sameMonth = first.getFullYear() === last.getFullYear() && first.getMonth() === last.getMonth();

  if (sameMonth) {
    const monthYear = new Intl.DateTimeFormat("id-ID", {
      month: "long",
      timeZone: "Asia/Jakarta",
      year: "numeric",
    }).format(first);
    return `${first.getDate()}-${last.getDate()} ${monthYear}`;
  }

  return `${formatDate(toDateInputValue(first))} - ${formatDate(toDateInputValue(last))}`;
}

function parseLocalDate(value?: string | null) {
  if (!value) return null;
  const dateValue = value.slice(0, 10);
  const date = new Date(`${dateValue}T00:00:00`);
  return Number.isNaN(date.getTime()) ? null : date;
}

function toDateInputValue(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}
