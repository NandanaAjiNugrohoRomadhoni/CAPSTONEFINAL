"use client";

import { useEffect, useMemo, useState } from "react";
import sdk from "@/lib";
import { formatCompactDate, formatNumber, getCurrentMonthPeriod } from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  FilterSearch,
  FilterSelect,
  Pagination,
  SurfaceCard,
} from "@/components/admin/ui";

type EvaluationRow = Record<string, unknown>;

export default function Page() {
  const [rows, setRows] = useState<EvaluationRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const period = getCurrentMonthPeriod();
        const response = await sdk.reports.getEvaluation(period);
        if (!cancelled) setRows((response.data?.rows as EvaluationRow[]) ?? []);
      } catch (loadError) {
        if (!cancelled) {
          setError(loadError instanceof Error ? loadError.message : "Gagal memuat laporan evaluasi.");
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

  const chartRows = useMemo(() => rows.slice(-4), [rows]);

  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Laporan"
        subtitle="Laporan evaluasi hasil perbandingan riwayat SPK, pemasukan bahan, dan penggunaan bahan"
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
              <FilterSearch placeholder="Cari SPK" />
            </div>
            <FilterSelect label="Evaluasi Bulanan" />
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
                <th className="px-6 py-3">Tipe</th>
                <th className="px-6 py-3">Tanggal</th>
                <th className="px-6 py-3">Planned Qty</th>
                <th className="px-6 py-3">Realisasi Qty</th>
                <th className="px-6 py-3">Selisih</th>
              </tr>
            </thead>
            <tbody className="text-sm text-gray-700">
              {rows.map((row) => {
                const variance = Number(row.variance_qty ?? 0);
                return (
                  <tr key={String(row.spk_id)} className="border-t border-gray-200 transition hover:bg-gray-50">
                    <td className="px-6 py-4 font-medium text-gray-900">SPK-{String(row.spk_id ?? "-")}</td>
                    <td className="px-6 py-4">{String(row.spk_type ?? "-")}</td>
                    <td className="px-6 py-4">{formatCompactDate(String(row.calculation_date ?? ""))}</td>
                    <td className="px-6 py-4">{formatNumber(Number(row.planned_qty ?? 0), 2)} kg</td>
                    <td className="px-6 py-4">{formatNumber(Number(row.realization_qty ?? 0), 2)} kg</td>
                    <td className={`px-6 py-4 ${variance < 0 ? "text-[#EF4444]" : variance > 0 ? "text-[#10B981]" : ""}`}>
                      {variance > 0 ? "+" : ""}
                      {formatNumber(variance, 2)} kg
                    </td>
                  </tr>
                );
              })}
              {!loading && rows.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={6}>
                    Belum ada data laporan evaluasi.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <Pagination totalLabel={`${rows.length === 0 ? 0 : 1}-${rows.length} dari ${rows.length} item`} />
      </SurfaceCard>

      <SurfaceCard className="overflow-hidden px-6 py-5">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-base font-semibold text-[#16213E]">Laporan Evaluasi 4 SPK Terakhir</h3>
          <button
            className="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-2 text-sm text-[#475569]"
            type="button"
          >
            Variance
          </button>
        </div>

        <div className="rounded-[14px] bg-white p-4">
          <svg viewBox="0 0 540 190" className="h-[230px] w-full">
            {[0, 1, 2, 3, 4].map((index) => (
              <g key={index}>
                <line
                  x1="20"
                  x2="520"
                  y1={160 - index * 30}
                  y2={160 - index * 30}
                  stroke="#E2E8F0"
                  strokeDasharray="4 4"
                />
              </g>
            ))}
            {chartRows.map((row, index) => {
              const x = 70 + index * 120;
              const value = Number(row.variance_qty ?? 0);
              const y = 150 - Math.max(Math.min(value, 40), -40) * 2;
              return (
                <g key={String(row.spk_id)}>
                  <circle cx={x} cy={y} r="6" fill={value < 0 ? "#EF4444" : "#2563EB"} />
                  <text x={x - 14} y={y - 12} fontSize="10" fill="#475569">
                    {formatNumber(value, 1)}
                  </text>
                  <text x={x - 20} y="178" fontSize="11" fill="#94A3B8">
                    SPK-{String(row.spk_id ?? "-")}
                  </text>
                </g>
              );
            })}
          </svg>
        </div>
      </SurfaceCard>
    </div>
  );
}
