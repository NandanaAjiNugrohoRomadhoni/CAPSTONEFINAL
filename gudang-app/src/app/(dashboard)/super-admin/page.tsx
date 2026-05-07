"use client";

import { useEffect, useMemo, useState, type ReactNode } from "react";
import { AlertTriangle, ShoppingCart, Users, Utensils } from "lucide-react";
import sdk from "@/lib";
import { formatCompactDate, formatNumber, formatQuantity, getCurrentMonthPeriod } from "@/lib/admin-utils";

type DashboardState = {
  stock_summary?: {
    total_items?: number;
    active_items?: number;
    zero_stock_items?: number;
    total_stock_qty?: number;
  };
  dry_stock_status?: {
    status?: string;
    total_items?: number;
    zero_stock_items?: number;
  };
  current_menu_cycle?: {
    menu_name?: string | null;
  };
  latest_spk_history?: {
    basah?: { id?: number | null };
    kering_pengemas?: { id?: number | null };
  };
  patient_fluctuation?: Array<{
    service_date: string;
    total_patients: number;
  }>;
};

export default function Page() {
  const [dashboard, setDashboard] = useState<DashboardState>({});
  const [evaluationRows, setEvaluationRows] = useState<Array<Record<string, unknown>>>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const period = getCurrentMonthPeriod();
        const [dashboardResponse, evaluationResponse] = await Promise.all([
          sdk.dashboard.getAggregate(),
          sdk.reports.getEvaluation(period),
        ]);

        if (cancelled) return;

        setDashboard((dashboardResponse.data?.aggregates ?? {}) as DashboardState);
        setEvaluationRows((evaluationResponse.data?.rows as Array<Record<string, unknown>>) ?? []);
      } catch (loadError) {
        if (cancelled) return;
        const message = loadError instanceof Error ? loadError.message : "Gagal memuat dashboard.";
        setError(message);
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, []);

  const patientPoints = useMemo(() => dashboard.patient_fluctuation ?? [], [dashboard.patient_fluctuation]);
  const patientStats = useMemo(() => {
    if (patientPoints.length === 0) {
      return { average: 0, highest: 0, lowest: 0 };
    }

    const values = patientPoints.map((point) => point.total_patients);
    const total = values.reduce((sum, value) => sum + value, 0);

    return {
      average: Math.round(total / values.length),
      highest: Math.max(...values),
      lowest: Math.min(...values),
    };
  }, [patientPoints]);

  const stockSummary = dashboard.stock_summary ?? {};
  const criticalCount = Number(stockSummary.zero_stock_items ?? 0);
  const activeMenu = dashboard.current_menu_cycle?.menu_name ?? "Belum ada";
  const spkCount = Number(Boolean(dashboard.latest_spk_history?.basah?.id)) + Number(Boolean(dashboard.latest_spk_history?.kering_pengemas?.id));

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold text-gray-900">Dashboard</h1>
        <p className="text-sm text-gray-400">Ringkasan operasional instalasi gizi hari ini</p>
      </div>

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="Pasien Hari Ini"
          value={loading ? "..." : formatNumber(patientPoints.at(-1)?.total_patients ?? 0)}
          subtitle={loading ? "Memuat data pasien" : `${patientPoints.length} hari tercatat`}
          color="border-blue-500"
          icon={<Users className="text-gray-500" />}
        />
        <StatCard
          title="Menu Aktif"
          value={loading ? "..." : activeMenu}
          subtitle={loading ? "Memuat menu aktif" : "Menu hari ini"}
          color="border-green-500"
          icon={<Utensils className="text-gray-500" />}
        />
        <StatCard
          title="Stok Kritis"
          value={loading ? "..." : formatNumber(criticalCount)}
          subtitle={loading ? "Memuat stok" : "Bahan perlu ditindaklanjuti"}
          color="border-red-500"
          icon={<AlertTriangle className="text-gray-500" />}
        />
        <StatCard
          title="SPK Belanja"
          value={loading ? "..." : formatNumber(spkCount)}
          subtitle={loading ? "Memuat SPK" : "Riwayat SPK terbaru"}
          color="border-yellow-500"
          icon={<ShoppingCart className="text-gray-500" />}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
          <div className="mb-4 flex items-start justify-between">
            <div>
              <h3 className="font-semibold text-gray-900">Evaluasi SPK Terbaru</h3>
              <p className="text-xs text-gray-400">Diambil dari laporan evaluasi periode berjalan</p>
            </div>
          </div>

          <div className="overflow-hidden rounded-xl border border-gray-100">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#F1F5F9] text-xs uppercase tracking-wide text-gray-500">
                <tr>
                  <th className="px-4 py-3">SPK</th>
                  <th className="px-4 py-3">Tipe</th>
                  <th className="px-4 py-3">Planned</th>
                  <th className="px-4 py-3">Realisasi</th>
                </tr>
              </thead>
              <tbody>
                {evaluationRows.slice(0, 5).map((row) => (
                  <tr key={String(row.spk_id)} className="border-t border-gray-100">
                    <td className="px-4 py-3 font-medium text-gray-900">SPK-{String(row.spk_id ?? "-")}</td>
                    <td className="px-4 py-3 text-gray-600">{String(row.spk_type ?? "-")}</td>
                    <td className="px-4 py-3 text-gray-600">{formatQuantity(Number(row.planned_qty ?? 0), "kg")}</td>
                    <td className="px-4 py-3 text-gray-600">{formatQuantity(Number(row.realization_qty ?? 0), "kg")}</td>
                  </tr>
                ))}
                {!loading && evaluationRows.length === 0 ? (
                  <tr>
                    <td className="px-4 py-6 text-center text-gray-400" colSpan={4}>
                      Belum ada data evaluasi pada periode ini.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </section>

        <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
          <h3 className="font-semibold text-gray-900">Tren Pasien 7 Hari</h3>
          <div className="mt-5 flex h-[210px] items-end gap-3">
            {patientPoints.map((point) => {
              const highest = Math.max(...patientPoints.map((entry) => entry.total_patients), 1);
              const height = `${Math.max((point.total_patients / highest) * 100, 18)}%`;

              return (
                <div key={point.service_date} className="flex flex-1 flex-col items-center gap-2">
                  <div className="text-xs font-semibold text-[#94A3B8]">{formatNumber(point.total_patients)}</div>
                  <div className="flex h-full w-full items-end">
                    <div className="w-full rounded-t-xl bg-[#D9E7FF]" style={{ height }} />
                  </div>
                  <div className="text-[11px] text-[#94A3B8]">{formatCompactDate(point.service_date)}</div>
                </div>
              );
            })}
            {!loading && patientPoints.length === 0 ? (
              <div className="flex h-full w-full items-center justify-center text-sm text-gray-400">
                Belum ada data pasien.
              </div>
            ) : null}
          </div>
          <div className="mt-4 flex justify-between text-sm text-gray-400">
            <span>Rata-rata: {formatNumber(patientStats.average)}</span>
            <span>Tertinggi: {formatNumber(patientStats.highest)}</span>
            <span>Terendah: {formatNumber(patientStats.lowest)}</span>
          </div>
        </section>
      </div>

      <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
          <h3 className="font-semibold text-gray-900">Ringkasan Stok</h3>
          <div className="mt-4 grid grid-cols-2 gap-3 text-sm">
            <SummaryBox tone="green" label="Item Aktif" value={formatNumber(Number(stockSummary.active_items ?? 0))} />
            <SummaryBox tone="blue" label="Total Item" value={formatNumber(Number(stockSummary.total_items ?? 0))} />
            <SummaryBox tone="red" label="Stok Habis" value={formatNumber(criticalCount)} />
            <SummaryBox tone="yellow" label="Total Qty" value={formatQuantity(Number(stockSummary.total_stock_qty ?? 0))} />
          </div>
        </section>

        <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
          <h3 className="font-semibold text-gray-900">Status Stok Kering</h3>
          <div className="mt-4 space-y-3">
            <SummaryRow label="Status" value={String(dashboard.dry_stock_status?.status ?? "-")} />
            <SummaryRow label="Total Item" value={formatNumber(Number(dashboard.dry_stock_status?.total_items ?? 0))} />
            <SummaryRow label="Stok Nol" value={formatNumber(Number(dashboard.dry_stock_status?.zero_stock_items ?? 0))} />
          </div>
        </section>

        <section className="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
          <h3 className="font-semibold text-gray-900">Menu Aktif</h3>
          <div className="mt-4 space-y-3">
            <SummaryRow label="Paket Hari Ini" value={activeMenu} />
            <SummaryRow label="SPK Basah" value={dashboard.latest_spk_history?.basah?.id ? `#${dashboard.latest_spk_history.basah.id}` : "-"} />
            <SummaryRow
              label="SPK Kering"
              value={
                dashboard.latest_spk_history?.kering_pengemas?.id
                  ? `#${dashboard.latest_spk_history.kering_pengemas.id}`
                  : "-"
              }
            />
          </div>
        </section>
      </div>
    </div>
  );
}

function StatCard({
  title,
  value,
  subtitle,
  color,
  icon,
}: {
  title: string;
  value: string;
  subtitle: string;
  color: string;
  icon: ReactNode;
}) {
  return (
    <div className={`rounded-2xl border border-gray-100 border-t-4 bg-white p-6 shadow-sm ${color}`}>
      <div className="flex items-start justify-between">
        <div>
          <p className="text-xs uppercase tracking-wide text-gray-400">{title}</p>
          <h2 className="mt-1 text-2xl font-semibold text-gray-900">{value}</h2>
          <p className="mt-1 text-xs text-gray-400">{subtitle}</p>
        </div>
        <div className="rounded-lg bg-gray-100 p-2">{icon}</div>
      </div>
    </div>
  );
}

function SummaryBox({
  tone,
  label,
  value,
}: {
  tone: "green" | "blue" | "red" | "yellow";
  label: string;
  value: string;
}) {
  const classes = {
    green: "bg-[#DCFCE7] text-[#16A34A]",
    blue: "bg-[#DBEAFE] text-[#2155CD]",
    red: "bg-[#FEE2E2] text-[#DC2626]",
    yellow: "bg-[#FEF3C7] text-[#D97706]",
  }[tone];

  return (
    <div className={`rounded-xl px-4 py-3 ${classes}`}>
      <div className="text-[11px] font-semibold uppercase tracking-wide">{label}</div>
      <div className="mt-1 text-lg font-bold">{value}</div>
    </div>
  );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between rounded-xl bg-[#F8FAFC] px-4 py-3">
      <span className="text-sm text-[#64748B]">{label}</span>
      <span className="text-sm font-semibold text-[#16213E]">{value}</span>
    </div>
  );
}
