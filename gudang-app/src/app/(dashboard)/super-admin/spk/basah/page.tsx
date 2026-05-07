"use client";

import { useEffect, useMemo, useState } from "react";
import sdk from "@/lib";
import { formatDate, formatNumber, formatQuantity, getErrorMessage, toIsoDate } from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  PrimaryAction,
  SurfaceCard,
} from "@/components/admin/ui";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getBasah>>["data"]["items"][number];

export default function Page() {
  const [latestPatient, setLatestPatient] = useState<{ date: string; total: number } | null>(null);
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [spkMeta, setSpkMeta] = useState<{ targetDates: string[]; estimatedPatients: number } | null>(null);
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadInitial() {
      setLoading(true);
      try {
        const patientsResponse = await sdk.dailyPatients.list();
        if (cancelled) return;
        const latest = [...(patientsResponse.data ?? [])].sort((a, b) => b.service_date.localeCompare(a.service_date))[0];
        if (latest) {
          setLatestPatient({ date: latest.service_date, total: latest.total_patients });
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

  async function handleGenerate() {
    const serviceDate = latestPatient?.date ?? toIsoDate(new Date());
    setGenerating(true);
    setError(null);
    try {
      const generated = await sdk.spk.generateBasah({ service_date: serviceDate });
      const detail = await sdk.spk.getBasah(generated.data.id);
      setRows(detail.data.items ?? []);
      setSpkMeta({
        targetDates: detail.data.print_ready.target_dates ?? [],
        estimatedPatients: detail.data.estimated_patients,
      });
    } catch (generateError) {
      setError(getErrorMessage(generateError, "Gagal generate SPK basah."));
    } finally {
      setGenerating(false);
    }
  }

  return (
    <div className="space-y-5">
      <AdminPageHeading title="Rekomendasi Belanja Basah" subtitle="Generate kebutuhan bahan basah untuk 2 hari ke depan" />

      {error ? <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">{error}</div> : null}

      <SurfaceCard className="bg-[#DCEAFE] px-4 py-3 text-[13px] text-[#16213E]">
        <p className="font-semibold">Rumus SPK Bahan Basah</p>
        <p className="mt-2 font-mono text-[12px]">(Jumlah Pasien Terakhir x 5%) x Komposisi per Paket Menu - Sisa Stok</p>
      </SurfaceCard>

      <SurfaceCard className="overflow-hidden">
        <div className="grid gap-4 border border-[#2155CD] px-4 py-4 md:grid-cols-3">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.04em] text-[#94A3B8]">Parameter</p>
            <p className="mt-2 text-[13px] text-[#475569]">Tanggal Belanja</p>
            <p className="text-[25px] font-bold leading-none text-[#16213E]">
              {spkMeta?.targetDates.length ? spkMeta.targetDates.map((date) => formatDate(date)).join(" / ") : latestPatient ? formatDate(latestPatient.date) : "-"}
            </p>
          </div>
          <div>
            <p className="mt-7 text-[13px] text-[#475569]">Pasien Terakhir</p>
            <p className="text-[22px] font-bold text-[#16213E]">{latestPatient ? `${formatNumber(latestPatient.total)} orang` : "-"}</p>
          </div>
          <div>
            <p className="mt-7 text-[13px] text-[#475569]">Setelah Buffer +5%</p>
            <p className="text-[22px] font-bold text-[#16213E]">
              {latestPatient ? `${formatNumber(spkMeta?.estimatedPatients ?? bufferPatients)} orang` : "-"}
            </p>
          </div>
        </div>
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
                    Klik Generate untuk mengambil rekomendasi SPK basah.
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
