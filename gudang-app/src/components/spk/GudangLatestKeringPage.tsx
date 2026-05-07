"use client";

import { useEffect, useState } from "react";
import sdk from "@/lib";
import { formatQuantity, getErrorMessage } from "@/lib/admin-utils";
import { AdminPageHeading, ExportButton, SurfaceCard } from "@/components/admin/ui";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getKeringPengemas>>["data"]["items"][number];

export default function GudangLatestKeringPage() {
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [targetMonth, setTargetMonth] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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

        setRows(detail.data.items ?? []);
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
