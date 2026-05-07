"use client";

import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, ShoppingCart, Users, Utensils } from "lucide-react";
import sdk from "@/lib";
import { formatCompactDate, formatNumber, formatQuantity, getCurrentMonthPeriod, getErrorMessage, getStockTone } from "@/lib/admin-utils";
import { MiniActionButton, StatusPill, SurfaceCard } from "@/components/admin/ui";

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

type TransactionReportRow = {
  transaction_id: number;
  transaction_date: string;
  type_name: string;
  item_name: string;
  qty: number;
};

type StockReportRow = {
  item_id: number;
  item_name: string;
  category_name: string;
  qty: number;
  unit_base: string;
};

type SpkHistoryRow = {
  spk_id: number;
  spk_type: string;
  calculation_date: string;
  total_recommended_qty: number;
};

export default function GudangDashboardPage() {
  const [dashboard, setDashboard] = useState<DashboardState>({});
  const [transactionRows, setTransactionRows] = useState<TransactionReportRow[]>([]);
  const [stockRows, setStockRows] = useState<StockReportRow[]>([]);
  const [spkRows, setSpkRows] = useState<SpkHistoryRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const period = getCurrentMonthPeriod();
        const [dashboardResponse, transactionsResponse, stocksResponse, spkResponse] = await Promise.all([
          sdk.dashboard.getAggregate(),
          sdk.reports.getTransactions(period),
          sdk.reports.getStocks(period),
          sdk.reports.getSpkHistory(period),
        ]);

        if (cancelled) return;

        setDashboard((dashboardResponse.data?.aggregates ?? {}) as DashboardState);
        setTransactionRows((transactionsResponse.data.rows as TransactionReportRow[]) ?? []);
        setStockRows((stocksResponse.data.rows as StockReportRow[]) ?? []);
        setSpkRows((spkResponse.data.rows as SpkHistoryRow[]) ?? []);
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat dashboard gudang."));
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

  const patientPoints = dashboard.patient_fluctuation ?? [];
  const latestPatients = patientPoints.at(-1)?.total_patients ?? 0;
  const activeMenu = dashboard.current_menu_cycle?.menu_name ?? "Belum ada";
  const criticalStock = Number(dashboard.stock_summary?.zero_stock_items ?? 0);
  const spkCount =
    Number(Boolean(dashboard.latest_spk_history?.basah?.id)) +
    Number(Boolean(dashboard.latest_spk_history?.kering_pengemas?.id));

  const todayOutRows = useMemo(
    () =>
      transactionRows
        .filter((row) => row.type_name === "OUT")
        .slice(0, 5)
        .map((row) => {
          const relatedStock = stockRows.find((stock) => stock.item_name === row.item_name);
          const tone = getStockTone(Number(relatedStock?.qty ?? 0), 1);
          return {
            id: row.transaction_id,
            itemName: row.item_name,
            category: relatedStock?.category_name ?? "-",
            outgoing: formatQuantity(row.qty, relatedStock?.unit_base ?? "kg"),
            remaining: formatQuantity(relatedStock?.qty ?? 0, relatedStock?.unit_base ?? "kg"),
            tone: tone.tone,
            label: tone.label,
          };
        }),
    [stockRows, transactionRows],
  );

  const stockSummaryBoxes = useMemo(() => {
    const counts = stockRows.reduce(
      (acc, row) => {
        const tone = getStockTone(Number(row.qty ?? 0), 1).tone;
        acc[tone] += 1;
        return acc;
      },
      { safe: 0, warning: 0, critical: 0, danger: 0 } as Record<
        "safe" | "warning" | "critical" | "danger",
        number
      >,
    );

    return [
      { label: "STOK AMAN", value: counts.safe, tone: "bg-[#DCFCE7] text-[#16A34A]" },
      { label: "MENIPIS", value: counts.warning, tone: "bg-[#FEF3C7] text-[#D97706]" },
      { label: "KRITIS", value: counts.critical, tone: "bg-[#FEE2E2] text-[#DC2626]" },
      { label: "HABIS", value: counts.danger, tone: "bg-[#E2E8F0] text-[#334155]" },
    ];
  }, [stockRows]);

  const stockFocusRows = useMemo(() => stockRows.slice(0, 6), [stockRows]);
  const latestSpkCards = useMemo(() => spkRows.slice(0, 2), [spkRows]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold text-gray-900">Dashboard</h1>
        <p className="text-sm text-gray-400">Ringkasan operasional gudang hari ini</p>
      </div>

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <DashboardStatCard
          title="Pasien Hari Ini"
          value={loading ? "..." : formatNumber(latestPatients)}
          subtitle={loading ? "Memuat data pasien" : "Data pasien terbaru"}
          color="border-blue-500"
          icon={<Users className="text-gray-500" />}
        />
        <DashboardStatCard
          title="Menu Aktif"
          value={loading ? "..." : activeMenu}
          subtitle={loading ? "Memuat menu aktif" : "Menu hari ini"}
          color="border-green-500"
          icon={<Utensils className="text-gray-500" />}
        />
        <DashboardStatCard
          title="Stok Kritis"
          value={loading ? "..." : formatNumber(criticalStock)}
          subtitle={loading ? "Memuat stok" : "Bahan perlu restock"}
          color="border-red-500"
          icon={<AlertTriangle className="text-gray-500" />}
        />
        <DashboardStatCard
          title="SPK Belanja"
          value={loading ? "..." : formatNumber(spkCount)}
          subtitle={loading ? "Memuat SPK" : "Bahan rekomendasi aktif"}
          color="border-yellow-500"
          icon={<ShoppingCart className="text-gray-500" />}
        />
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <SurfaceCard className="overflow-hidden">
          <div className="flex items-center justify-between border-b px-5 py-4">
            <div>
              <h3 className="text-base font-semibold text-[#16213E]">Bahan Keluar Hari Ini</h3>
              <p className="mt-1 text-xs text-[#94A3B8]">Ringkasan transaksi keluar terbaru</p>
            </div>
            <MiniActionButton>Detail</MiniActionButton>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                  <th className="px-4 py-3">Bahan</th>
                  <th className="px-4 py-3">Keluar</th>
                  <th className="px-4 py-3">Sisa Stok</th>
                  <th className="px-4 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="text-sm text-gray-700">
                {todayOutRows.map((row) => (
                  <tr key={row.id} className="border-t border-gray-200">
                    <td className="px-4 py-3">
                      <p className="font-medium text-gray-900">{row.itemName}</p>
                      <p className="text-xs text-[#94A3B8]">{row.category}</p>
                    </td>
                    <td className="px-4 py-3">{row.outgoing}</td>
                    <td className="px-4 py-3">{row.remaining}</td>
                    <td className="px-4 py-3">
                      <StatusPill tone={row.tone}>{row.label}</StatusPill>
                    </td>
                  </tr>
                ))}
                {!loading && todayOutRows.length === 0 ? (
                  <tr>
                    <td className="px-4 py-8 text-center text-gray-400" colSpan={4}>
                      Belum ada transaksi keluar pada periode ini.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </SurfaceCard>

        <SurfaceCard className="overflow-hidden p-6">
          <h3 className="font-semibold text-gray-900">Tren Pasien 7 Hari Terakhir</h3>
          <div className="mt-5 flex h-[210px] items-end gap-3">
            {patientPoints.map((point) => {
              const highest = Math.max(...patientPoints.map((entry) => entry.total_patients), 1);
              const height = `${Math.max((point.total_patients / highest) * 100, 18)}%`;

              return (
                <div key={point.service_date} className="flex flex-1 flex-col items-center gap-2">
                  <div className="text-xs font-semibold text-[#94A3B8]">
                    {formatNumber(point.total_patients)}
                  </div>
                  <div className="flex h-full w-full items-end">
                    <div className="w-full rounded-t-xl bg-[#D9E7FF]" style={{ height }} />
                  </div>
                  <div className="text-[11px] text-[#94A3B8]">
                    {formatCompactDate(point.service_date)}
                  </div>
                </div>
              );
            })}
            {!loading && patientPoints.length === 0 ? (
              <div className="flex h-full w-full items-center justify-center text-sm text-gray-400">
                Belum ada data pasien.
              </div>
            ) : null}
          </div>
        </SurfaceCard>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <SurfaceCard className="p-5">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-semibold text-gray-900">Ringkasan Stok Bahan</h3>
            <MiniActionButton>Detail</MiniActionButton>
          </div>
          <div className="grid grid-cols-2 gap-3">
            {stockSummaryBoxes.map((box) => (
              <div key={box.label} className={`rounded-xl px-4 py-3 ${box.tone}`}>
                <div className="text-[11px] font-semibold uppercase tracking-wide">{box.label}</div>
                <div className="mt-1 text-lg font-bold">{formatNumber(box.value)}</div>
                <div className="text-xs opacity-80">Bahan</div>
              </div>
            ))}
          </div>
          <div className="mt-4 space-y-3">
            {stockFocusRows.map((row) => {
              const qty = Number(row.qty ?? 0);
              const percent = Math.max(Math.min(qty, 100), 0);
              return (
                <div key={row.item_id}>
                  <div className="mb-1 flex items-center justify-between text-sm text-[#475569]">
                    <span>{row.item_name}</span>
                    <span>{formatQuantity(row.qty, row.unit_base)}</span>
                  </div>
                  <div className="h-2 rounded-full bg-[#E2E8F0]">
                    <div className="h-2 rounded-full bg-[#F59E0B]" style={{ width: `${percent}%` }} />
                  </div>
                </div>
              );
            })}
          </div>
        </SurfaceCard>

        <SurfaceCard className="p-5">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-semibold text-gray-900">Peringatan Stok Bahan</h3>
            <MiniActionButton>Detail</MiniActionButton>
          </div>
          <div className="space-y-3">
            {stockRows
              .filter((row) => getStockTone(Number(row.qty ?? 0), 1).tone !== "safe")
              .slice(0, 5)
              .map((row) => {
                const tone = getStockTone(Number(row.qty ?? 0), 1);
                const palette =
                  tone.tone === "danger"
                    ? "border-red-200 bg-[#FFF1F2] text-[#DC2626]"
                    : tone.tone === "critical"
                      ? "border-[#FECACA] bg-[#FFF7F7] text-[#DC2626]"
                      : "border-[#FDE68A] bg-[#FFFBEB] text-[#D97706]";
                return (
                  <div key={row.item_id} className={`rounded-xl border px-4 py-3 ${palette}`}>
                    <div className="flex items-center justify-between gap-3">
                      <div>
                        <p className="font-semibold">{row.item_name}</p>
                        <p className="mt-1 text-xs opacity-80">{row.category_name}</p>
                      </div>
                      <p className="text-sm font-bold">{formatQuantity(row.qty, row.unit_base)}</p>
                    </div>
                  </div>
                );
              })}
            {!loading &&
            stockRows.filter((row) => getStockTone(Number(row.qty ?? 0), 1).tone !== "safe").length === 0 ? (
              <div className="rounded-xl bg-[#F8FAFC] px-4 py-8 text-center text-sm text-gray-400">
                Tidak ada stok yang perlu perhatian saat ini.
              </div>
            ) : null}
          </div>
        </SurfaceCard>

        <SurfaceCard className="p-5">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-semibold text-gray-900">SPK Rekomendasi Belanja</h3>
            <MiniActionButton>Detail</MiniActionButton>
          </div>
          <div className="space-y-4">
            {latestSpkCards.map((row) => (
              <div key={row.spk_id} className="rounded-xl bg-[#EEF4FF] px-4 py-3">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-[#64748B]">
                      {row.spk_type === "BASAH" ? "Belanja Basah" : "Belanja Kering & Pengemas"}
                    </p>
                    <p className="mt-2 text-sm font-semibold text-[#16213E]">
                      SPK-{String(row.spk_id).padStart(4, "0")}
                    </p>
                    <p className="mt-1 text-xs text-[#64748B]">{formatCompactDate(row.calculation_date)}</p>
                  </div>
                  <p className="text-sm font-bold text-[#16213E]">
                    {formatQuantity(row.total_recommended_qty, "kg")}
                  </p>
                </div>
              </div>
            ))}
            {!loading && latestSpkCards.length === 0 ? (
              <div className="rounded-xl bg-[#F8FAFC] px-4 py-8 text-center text-sm text-gray-400">
                Belum ada riwayat SPK pada periode ini.
              </div>
            ) : null}
          </div>
        </SurfaceCard>
      </div>
    </div>
  );
}

function DashboardStatCard({
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
  icon: React.ReactNode;
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
