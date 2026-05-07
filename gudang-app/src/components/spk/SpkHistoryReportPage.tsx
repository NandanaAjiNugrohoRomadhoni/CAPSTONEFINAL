"use client";

import { useEffect, useState } from "react";
import { X } from "lucide-react";
import sdk from "@/lib";
import { formatDate, formatQuantity, getCurrentMonthPeriod, getErrorMessage } from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  FilterDate,
  FilterSearch,
  FilterSelect,
  MiniActionButton,
  Pagination,
  SurfaceCard,
} from "@/components/admin/ui";

type SpkHistoryRow = {
  spk_id: number;
  calculation_date: string;
  spk_type: string;
  category_name?: string | null;
  total_recommendations: number;
  total_recommended_qty: number;
};

type BasahDetail = Awaited<ReturnType<typeof sdk.spk.getBasah>>["data"];
type KeringDetail = Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>["data"];
type SpkDetailState =
  | { type: "BASAH"; detail: BasahDetail }
  | { type: "KERING_PENGEMAS"; detail: KeringDetail };

export default function SpkHistoryReportPage() {
  const [rows, setRows] = useState<SpkHistoryRow[]>([]);
  const [detailState, setDetailState] = useState<SpkDetailState | null>(null);
  const [openingDetailId, setOpeningDetailId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const response = await sdk.reports.getSpkHistory(getCurrentMonthPeriod());
        if (cancelled) return;

        setRows((response.data.rows as SpkHistoryRow[]) ?? []);
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
                <FilterSearch placeholder="Cari" />
              </div>
              <FilterDate />
              <FilterSelect label="Semua Jenis" />
            </div>
            <div className="ml-auto">
              <ExportButton>Export Riwayat</ExportButton>
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
                {rows.map((row) => (
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
                      {row.spk_type === "BASAH" ? "BASAH" : "KERING & PENGEMAS"}
                    </td>
                    <td className="px-6 py-4">{row.total_recommendations} bahan</td>
                    <td className="px-6 py-4">
                      <MiniActionButton onClick={() => void openDetail(row)}>
                        {openingDetailId === row.spk_id ? "Memuat..." : "Detail"}
                      </MiniActionButton>
                    </td>
                  </tr>
                ))}
                {!loading && rows.length === 0 ? (
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
            totalLabel={`${rows.length === 0 ? 0 : 1}-${rows.length} dari ${rows.length} item`}
          />
        </SurfaceCard>
      </div>

      {detailState ? <SpkDetailModal detailState={detailState} onClose={() => setDetailState(null)} /> : null}
    </>
  );
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
  const targetLabel =
    detailState.type === "BASAH"
      ? detail.print_ready.target_dates.map((date) => formatDate(date)).join(" / ")
      : detail.print_ready.target_month ?? "-";

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
              <InfoBlock label="Total Item" value={`${detail.items.length} bahan`} />
            </div>

            <div className="overflow-hidden rounded-2xl border border-[#D7E0EE]">
              <div
                className={`grid ${
                  detailState.type === "BASAH" ? "grid-cols-[52px_1.5fr_120px_120px_140px_120px]" : "grid-cols-[52px_1.7fr_120px_120px_140px]"
                } bg-[#F1F5F9] px-4 py-3 text-xs font-semibold uppercase tracking-wide text-[#94A3B8]`}
              >
                <div>#</div>
                <div>Nama Bahan</div>
                <div>Stok</div>
                <div>Kebutuhan</div>
                <div>Rekomendasi</div>
                {detailState.type === "BASAH" ? <div>Tanggal</div> : null}
              </div>
              <div className="divide-y divide-[#E2E8F0]">
                {detail.items.map((item, index) => (
                  <div
                    key={item.id}
                    className={`grid ${
                      detailState.type === "BASAH" ? "grid-cols-[52px_1.5fr_120px_120px_140px_120px]" : "grid-cols-[52px_1.7fr_120px_120px_140px]"
                    } px-4 py-3 text-sm text-[#334155]`}
                  >
                    <div>{index + 1}</div>
                    <div className="font-semibold text-[#16213E]">{item.item_name ?? "-"}</div>
                    <div>{formatQuantity(item.current_stock_qty, item.item_unit_base)}</div>
                    <div>{formatQuantity(item.required_qty, item.item_unit_base)}</div>
                    <div>{formatQuantity(item.final_recommended_qty, item.item_unit_base)}</div>
                    {detailState.type === "BASAH" ? (
                      <div>{item.target_date ? formatDate(item.target_date) : "-"}</div>
                    ) : null}
                  </div>
                ))}
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

function InfoBlock({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">{label}</div>
      <div className="mt-1 text-base font-semibold text-[#16213E]">{value}</div>
    </div>
  );
}
