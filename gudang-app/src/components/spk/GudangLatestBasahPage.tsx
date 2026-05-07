"use client";

import { useEffect, useState } from "react";
import sdk from "@/lib";
import { formatDate, formatNumber, formatQuantity, getErrorMessage } from "@/lib/admin-utils";
import { AdminPageHeading, ExportButton, SurfaceCard } from "@/components/admin/ui";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getBasah>>["data"]["items"][number];

export default function GudangLatestBasahPage() {
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [meta, setMeta] = useState<{ targetDates: string[]; estimatedPatients: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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

        setRows(detail.data.items ?? []);
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
          (Jumlah Pasien Terakhir x 5%) x Komposisi per Paket Menu - Sisa Stok
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
          <ExportButton>Export Rekomendasi</ExportButton>
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
