"use client";

import { useEffect, useMemo, useState, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { AlertTriangle, ShoppingCart, Users, Utensils } from "lucide-react";
import sdk from "@/lib";
import { formatCompactDate, formatNumber, formatQuantity, getCurrentMonthPeriod, getErrorMessage, getStockTone, normaliseMealLabel, toIsoDate } from "@/lib/admin-utils";
import { MiniActionButton, SurfaceCard } from "@/components/admin/ui";
import type { MenuSlot, SpkBasahDetail, SpkKeringPengemasDetail } from "@/sdk";

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
  latest_spk_history?: {
    basah?: { id?: number | null };
    kering_pengemas?: { id?: number | null };
  };
  patient_fluctuation?: Array<{
    service_date: string;
    total_patients: number;
  }>;
  current_menu_cycle?: {
    date?: string | null;
    menu_id?: number | null;
    menu_name?: string | null;
  };
};

export default function Page() {
  const router = useRouter();
  const [dashboard, setDashboard] = useState<DashboardState>({});
  const [stockRows, setStockRows] = useState<Array<{ item_id?: number; item_name?: string; category_name?: string; qty?: number; unit_base?: string }>>([]);
  const [menuSlots, setMenuSlots] = useState<MenuSlot[]>([]);
  const [menuCalendarMenu, setMenuCalendarMenu] = useState<{ menu_id?: number | null; menu_name?: string | null; date?: string | null }>({});
  const [basahDetail, setBasahDetail] = useState<SpkBasahDetail | null>(null);
  const [keringDetail, setKeringDetail] = useState<SpkKeringPengemasDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const todayIso = toIsoDate(new Date());

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const period = getCurrentMonthPeriod();
        const [dashboardResponse, stocksResponse, menuSlotsResponse, menuCalendarResponse] = await Promise.all([
          sdk.dashboard.getAggregate(),
          sdk.reports.getStocks(period),
          sdk.menus.slots(),
          sdk.menuSchedules.calendarProjection({ date: todayIso }),
        ]);

        if (cancelled) return;

        const dashboardData = (dashboardResponse.data?.aggregates ?? {}) as DashboardState;
        setDashboard(dashboardData);
        setStockRows((stocksResponse.data.rows as Array<{ item_id?: number; item_name?: string; category_name?: string; qty?: number; unit_base?: string }>) ?? []);
        setMenuSlots((menuSlotsResponse.data ?? []) as MenuSlot[]);
        if ("data" in menuCalendarResponse && menuCalendarResponse.data) {
          setMenuCalendarMenu({
            menu_id: (menuCalendarResponse.data as { menu_id?: number | null }).menu_id ?? null,
            menu_name: (menuCalendarResponse.data as { menu_name?: string | null }).menu_name ?? null,
            date: (menuCalendarResponse.data as { date?: string | null }).date ?? null,
          });
        } else {
          setMenuCalendarMenu({});
        }

        const basahId = dashboardData.latest_spk_history?.basah?.id ?? null;
        const keringId = dashboardData.latest_spk_history?.kering_pengemas?.id ?? null;
        const [basahResult, keringResult] = await Promise.allSettled([
          basahId ? sdk.spk.getBasah(Number(basahId)) : Promise.resolve(null),
          keringId ? sdk.spk.getKeringPengemas(Number(keringId)) : Promise.resolve(null),
        ]);

        if (cancelled) return;

        setBasahDetail(basahResult.status === "fulfilled" && basahResult.value ? basahResult.value.data : null);
        setKeringDetail(keringResult.status === "fulfilled" && keringResult.value ? keringResult.value.data : null);
      } catch (loadError) {
        if (cancelled) return;
        setError(getErrorMessage(loadError, "Gagal memuat dashboard."));
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

  const activeMenu = dashboard.current_menu_cycle?.menu_name ?? menuCalendarMenu.menu_name ?? "Belum ada";
  const resolvedMenuId = dashboard.current_menu_cycle?.menu_id ?? menuCalendarMenu.menu_id ?? null;
  const spkCount =
    Number(Boolean(dashboard.latest_spk_history?.basah?.id)) +
    Number(Boolean(dashboard.latest_spk_history?.kering_pengemas?.id));
  const warningRows = useMemo(
    () =>
      stockRows
        .filter((row) => {
          const tone = getStockTone(Number(row.qty ?? 0), 1).tone;
          return tone === "critical" || tone === "danger";
        })
        .slice(0, 5),
    [stockRows],
  );
  const stockSummaryBoxes = useMemo(() => {
    const counts = stockRows.reduce(
      (acc, row) => {
        const tone = getStockTone(Number(row.qty ?? 0), 1).tone;
        acc[tone] += 1;
        return acc;
      },
      { safe: 0, warning: 0, critical: 0, danger: 0 } as Record<"safe" | "warning" | "critical" | "danger", number>,
    );

    return [
      { label: "STOK AMAN", value: counts.safe, tone: "bg-[#DCFCE7] text-[#16A34A]" },
      { label: "MENIPIS", value: counts.warning, tone: "bg-[#FEF3C7] text-[#D97706]" },
      { label: "KRITIS", value: counts.critical, tone: "bg-[#FEE2E2] text-[#DC2626]" },
      { label: "HABIS", value: counts.danger, tone: "bg-[#E2E8F0] text-[#334155]" },
    ];
  }, [stockRows]);
  const stockFocusRows = useMemo(() => stockRows.slice(0, 6), [stockRows]);
  const packageRows = useMemo(() => {
    if (!resolvedMenuId) return [];

    const grouped = new Map<string, MenuSlot[]>();
    menuSlots
      .filter((slot) => slot.menu_id === resolvedMenuId)
      .forEach((slot) => {
        const mealTime = normaliseMealLabel(slot.meal_time?.name);
        const key = `${mealTime}-${slot.meal_time_id}`;
        const existing = grouped.get(key);
        if (existing) {
          existing.push(slot);
          return;
        }
        grouped.set(key, [slot]);
      });

    return [...grouped.entries()]
      .map(([key, slots]) => {
        const first = slots[0];
        return {
          key,
          mealTime: normaliseMealLabel(first.meal_time?.name),
          mealTimeId: first.meal_time_id,
          dishName: first.dish?.name ?? "-",
          menuName: first.menu?.name ?? activeMenu,
        };
      })
      .sort((a, b) => a.mealTimeId - b.mealTimeId);
  }, [activeMenu, menuSlots, resolvedMenuId]);
  const spkPanels = useMemo(() => {
    const panels: Array<{
      id: number;
      title: string;
      detail: SpkBasahDetail | SpkKeringPengemasDetail | null;
    }> = [];

    if (dashboard.latest_spk_history?.basah?.id && basahDetail) {
      panels.push({ id: basahDetail.id, title: "BELANJA BASAH", detail: basahDetail });
    }

    if (dashboard.latest_spk_history?.kering_pengemas?.id && keringDetail) {
      panels.push({ id: keringDetail.id, title: "BELANJA KERING & PENGEMAS", detail: keringDetail });
    }

    return panels;
  }, [basahDetail, dashboard.latest_spk_history?.basah?.id, dashboard.latest_spk_history?.kering_pengemas?.id, keringDetail]);
  const criticalCount = warningRows.length;

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
        <SurfaceCard className="p-6">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-semibold text-gray-900">Paket Menu Hari Ini</h3>
            <MiniActionButton onClick={() => router.push("/super-admin/menu/kalender")}>Detail</MiniActionButton>
          </div>
          <div className="space-y-3">
            {packageRows.map((row) => {
              const palette = getMealPalette(row.mealTime);

              return (
                <div key={row.key} className={`rounded-2xl border px-4 py-3 ${palette.card}`}>
                  <div className="mb-2 flex items-center justify-between">
                    <p className={`text-xs font-semibold uppercase tracking-wide ${palette.label}`}>{row.mealTime}</p>
                    <span className="text-[11px] text-[#94A3B8]">{row.menuName}</span>
                  </div>
                  <p className={`text-sm font-semibold ${palette.title}`}>{row.dishName}</p>
                  <p className="mt-1 text-xs text-[#64748B]">{activeMenu}</p>
                </div>
              );
            })}
            {!loading && packageRows.length === 0 ? (
              <div className="rounded-xl bg-[#F8FAFC] px-4 py-8 text-center text-sm text-gray-400">
                Belum ada paket menu aktif yang dapat ditampilkan.
              </div>
            ) : null}
          </div>
        </SurfaceCard>

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
        <SurfaceCard className="p-5">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-semibold text-gray-900">Ringkasan Stok Bahan</h3>
            <MiniActionButton onClick={() => router.push("/super-admin/stok/riwayat")}>Detail</MiniActionButton>
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
                    <span>{formatQuantity(row.qty ?? 0, row.unit_base ?? "kg")}</span>
                  </div>
                  <div className="h-2 rounded-full bg-[#E2E8F0]">
                    <div className="h-2 rounded-full bg-[#F59E0B]" style={{ width: `${percent}%` }} />
                  </div>
                </div>
              );
            })}
            {!loading && stockFocusRows.length === 0 ? (
              <div className="rounded-xl bg-[#F8FAFC] px-4 py-8 text-center text-sm text-gray-400">
                Belum ada data stok bahan.
              </div>
            ) : null}
          </div>
        </SurfaceCard>

        <SurfaceCard className="p-5">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-semibold text-gray-900">Peringatan Stok Bahan</h3>
            <MiniActionButton onClick={() => router.push("/super-admin/stok/basah")}>Detail</MiniActionButton>
          </div>
          <div className="space-y-3">
            {warningRows.map((row) => {
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
                    <p className="text-sm font-bold">{formatQuantity(row.qty ?? 0, row.unit_base ?? "kg")}</p>
                  </div>
                </div>
              );
            })}
            {!loading && warningRows.length === 0 ? (
              <div className="rounded-xl bg-[#F8FAFC] px-4 py-8 text-center text-sm text-gray-400">
                Tidak ada stok kritis yang perlu perhatian saat ini.
              </div>
            ) : null}
          </div>
        </SurfaceCard>

        <SurfaceCard className="p-5">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-semibold text-gray-900">SPK - Rekomendasi Belanja</h3>
            <MiniActionButton onClick={() => router.push("/super-admin/spk/riwayat")}>Detail</MiniActionButton>
          </div>
          <div className="space-y-4">
            {spkPanels.map((panel) => (
              <div key={panel.id} className="rounded-xl bg-[#EEF4FF] px-4 py-3">
                <div className="mb-3 flex items-center justify-between gap-3">
                  <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-[#64748B]">{panel.title}</p>
                    <p className="mt-2 text-sm font-semibold text-[#16213E]">
                      SPK-{String(panel.id).padStart(4, "0")}
                    </p>
                  </div>
                  <MiniActionButton onClick={() => router.push("/super-admin/spk/riwayat")}>Detail</MiniActionButton>
                </div>

                <div className="space-y-2">
                  {(panel.detail?.items ?? []).slice(0, 3).map((item) => (
                    <div
                      key={item.id}
                      className="flex items-center justify-between rounded-lg bg-white/70 px-3 py-2 text-sm text-[#16213E]"
                    >
                      <span className="font-medium">{item.item_name ?? "-"}</span>
                      <span className="font-semibold">
                        Beli {formatQuantity(item.final_recommended_qty ?? 0, item.item_unit_base)}
                      </span>
                    </div>
                  ))}
                </div>

                {(panel.detail?.items ?? []).length > 3 ? (
                  <p className="mt-2 text-xs text-[#64748B]">
                    +{(panel.detail?.items ?? []).length - 3} item lainnya
                  </p>
                ) : null}
              </div>
            ))}

            {!loading && spkPanels.length === 0 ? (
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

function getMealPalette(mealTime: string) {
  const normalized = normaliseMealLabel(mealTime);

  if (normalized === "PAGI") {
    return {
      card: "border-[#FDE68A] bg-[#FFFBEB]",
      label: "text-[#D97706]",
      title: "text-[#B45309]",
    };
  }

  if (normalized === "SIANG") {
    return {
      card: "border-[#BFDBFE] bg-[#EEF4FF]",
      label: "text-[#2155CD]",
      title: "text-[#16213E]",
    };
  }

  return {
    card: "border-[#DDD6FE] bg-[#F5F3FF]",
    label: "text-[#7C3AED]",
    title: "text-[#4C1D95]",
  };
}
