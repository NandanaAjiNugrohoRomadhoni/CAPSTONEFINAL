"use client";

import { useEffect, useMemo, useState } from "react";
import sdk from "@/lib";
import { formatNumber, formatQuantity, getErrorMessage, toIsoDate } from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  PrimaryAction,
  SurfaceCard,
} from "@/components/admin/ui";
import GenerateSpkConfirmModal from "@/components/spk/GenerateSpkConfirmModal";

type RecommendationRow = Awaited<ReturnType<typeof sdk.spk.getBasah>>["data"]["items"][number];
type LatestPatient = { id: number; date: string; total: number };

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

export default function Page() {
  const [latestPatient, setLatestPatient] = useState<LatestPatient | null>(null);
  const [basahCategoryId, setBasahCategoryId] = useState<number | null>(null);
  const [rows, setRows] = useState<RecommendationRow[]>([]);
  const [spkMeta, setSpkMeta] = useState<{ targetDates: string[]; estimatedPatients: number } | null>(null);
  const [, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadInitial() {
      setLoading(true);
      try {
        const [patientsResponse, categoriesResponse] = await Promise.all([
          sdk.dailyPatients.list(),
          sdk.itemCategories.list({ paginate: false, sortBy: "name", sortDir: "ASC" }),
        ]);
        if (cancelled) return;
        const latest = [...(patientsResponse.data ?? [])].sort((a, b) => b.service_date.localeCompare(a.service_date))[0];
        if (latest) {
          setLatestPatient({ id: latest.id, date: latest.service_date, total: latest.total_patients });
        }
        const basahCategory = (categoriesResponse.data ?? []).find((category) =>
          category.name.toUpperCase().includes("BASAH")
        );
        setBasahCategoryId(basahCategory?.id ?? null);
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
    setGenerating(true);
    setError(null);
    try {
      if (!latestPatient) {
        throw new Error("Data pasien harian belum tersedia untuk generate SPK basah.");
      }
      if (!basahCategoryId) {
        throw new Error("Kategori bahan BASAH belum tersedia untuk generate SPK basah.");
      }
      const generated = await sdk.spk.generateBasah({
        daily_patient_id: latestPatient.id,
        service_date: latestPatient.date ?? toIsoDate(new Date()),
        category_id: basahCategoryId,
      });
      const detail = await sdk.spk.getBasah(generated.data.id);
      setRows(detail.data.items ?? []);
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

  return (
    <div className="space-y-6">
      <AdminPageHeading title="Rekomendasi Belanja Basah" subtitle="Generate kebutuhan bahan basah untuk 2 hari ke depan" />

      {error ? <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">{error}</div> : null}

      <div className="rounded-xl border-l-4 border-[#2155CD] bg-[#D9EAFE] px-5 py-4 text-[#16213E]">
        <p className="font-mono text-[15px] font-bold">Rumus SPK Bahan Basah</p>
        <p className="mt-3 font-mono text-[15px] leading-relaxed">(Jumlah Pasien Terakhir x 5%) x Komposisi per Paket Menu - Sisa Stok</p>
      </div>

      <div className="rounded-2xl border-2 border-[#2155CD] bg-[#D9EAFE] px-6 py-5 text-[#16213E]">
        <p className="mb-5 text-sm font-bold uppercase tracking-[0.04em]">Parameter</p>
        <div className="grid gap-5 md:grid-cols-3">
          <div>
            <p className="text-base font-medium">Tanggal Belanja</p>
            <p className="mt-1 font-mono text-xl font-bold leading-none">
              {spkMeta?.targetDates.length ? formatSpkDateRange(spkMeta.targetDates) : latestPatient ? formatSpkDate(latestPatient.date) : "-"}
            </p>
          </div>
          <div>
            <p className="text-base font-medium">Pasien Terakhir</p>
            <p className="mt-1 font-mono text-xl font-bold">{latestPatient ? `${formatNumber(latestPatient.total)} orang` : "-"}</p>
          </div>
          <div>
            <p className="text-base font-medium">Setelah Buffer +5%</p>
            <p className="mt-1 font-mono text-xl font-bold">
              {latestPatient ? `${formatNumber(spkMeta?.estimatedPatients ?? bufferPatients)} orang` : "-"}
              {latestPatient ? <span className="font-sans text-base"> (acuan)</span> : null}
            </p>
          </div>
        </div>
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
                    Klik Generate untuk mengambil rekomendasi SPK basah.
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
        targetLabel="belanja bahan basah 2 hari ke depan"
      />
    </div>
  );
}
