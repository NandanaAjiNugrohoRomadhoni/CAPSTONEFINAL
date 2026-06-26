"use client";

import { useEffect, useMemo, useState, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { AlertTriangle, ShoppingCart, Users, Utensils } from "lucide-react";
import sdk from "@/lib";
import {
  formatCompactDate,
  formatNumber,
  formatQuantity,
  getCurrentMonthPeriod,
  getErrorMessage,
  getStockTone,
  normaliseMealLabel,
  toIsoDate,
} from "@/lib/admin-utils";
import { listAllPaginatedRows } from "@/lib/pagination";
import { MiniActionButton, StatusPill, SurfaceCard } from "@/components/admin/ui";
import { listAllItems } from "@/lib/items";
import type {
  DailyPatient,
  MenuSlot,
  SpkBasahDetail,
  SpkKeringPengemasDetail,
  DishComposition,
} from "@/sdk";

type DashboardMode = "gudang" | "dapur";

type DashboardCardRoutes = {
  patients: string;
  menu: string;
  stock: string;
  adjustment: string;
  spk: string;
  composition: string;
  spkDetail: string;
  outbound: string;
};

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
    date?: string;
    menu_id?: number | null;
    menu_name?: string | null;
  };
  current_menu_composition?: Array<{
    meal_time?: string | null;
    dish_id?: number;
    dish_name?: string | null;
    item_id?: number | null;
    item_name?: string | null;
    qty_per_patient?: number | string | null;
  }>;
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
  transaction_id?: number;
  transaction_date?: string;
  type_name?: string;
  item_name?: string;
  qty?: number;
};

type StockReportRow = {
  item_id?: number;
  item_name?: string;
  category_name?: string;
  qty?: number;
  unit_base?: string;
  min_stock?: number;
};

type CompositionGroup = {
  key: string;
  mealTime: string;
  mealTimeId: number;
  dishId: number;
  dishName: string;
  items: Array<NonNullable<DashboardState["current_menu_composition"]>[number]>;
};

type MenuIngredientRow = {
  itemId: number;
  itemName: string;
  unitBase: string;
  currentStockQty: number;
  requiredQty: number;
  tone: "safe" | "warning" | "critical" | "danger";
  label: string;
};

function getStockPriority(tone: MenuIngredientRow["tone"]) {
  switch (tone) {
    case "danger":
      return 0;
    case "critical":
      return 1;
    case "warning":
      return 2;
    default:
      return 3;
  }
}

type SpkPanelDetail = {
  id: number;
  title: string;
  detail: SpkBasahDetail | SpkKeringPengemasDetail | null;
};

type DailyPatientRow = Awaited<ReturnType<typeof sdk.dailyPatients.list>>["data"][number];

export default function OperationalDashboardPage({ mode }: Readonly<{ mode: DashboardMode }>) {
  const router = useRouter();
  const [dashboard, setDashboard] = useState<DashboardState>({});
  const [dailyPatients, setDailyPatients] = useState<DailyPatient[]>([]);
  const [transactionRows, setTransactionRows] = useState<TransactionReportRow[]>([]);
  const [stockRows, setStockRows] = useState<StockReportRow[]>([]);
  const [menuSlots, setMenuSlots] = useState<MenuSlot[]>([]);
  const [dishCompositionRows, setDishCompositionRows] = useState<DishComposition[]>([]);
  const [menuCalendarMenu, setMenuCalendarMenu] = useState<{ menu_id?: number | null; menu_name?: string | null; date?: string | null }>({});
  const [basahDetail, setBasahDetail] = useState<SpkBasahDetail | null>(null);
  const [keringDetail, setKeringDetail] = useState<SpkKeringPengemasDetail | null>(null);
  const [draftAdjustmentCount, setDraftAdjustmentCount] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const period = getCurrentMonthPeriod();
        const [
          dashboardResponse,
          patientsResponse,
          transactionsResponse,
          menuSlotsResponse,
          menuCalendarResponse,
          itemsResponse,
        ] = await Promise.all([
          sdk.dashboard.getAggregate(),
          listAllPaginatedRows<DailyPatientRow>(sdk.dailyPatients.list.bind(sdk.dailyPatients), {
            sortBy: "service_date",
            sortDir: "ASC",
          }),
          sdk.reports.getTransactions(period),
          sdk.menus.slots(),
          sdk.menuSchedules.calendarProjection({ date: todayIso }),
          listAllItems(),
        ]);

        if (cancelled) return;

        const dashboardData = (dashboardResponse.data?.aggregates ?? {}) as DashboardState;
        const patientRows = (patientsResponse as DailyPatient[])
          .slice()
          .sort((a, b) => a.service_date.localeCompare(b.service_date));

        setDashboard(dashboardData);
        setDailyPatients(patientRows);
        setTransactionRows((transactionsResponse.data.rows as TransactionReportRow[]) ?? []);

        const enrichedStockRows = itemsResponse.map((item) => ({
          item_id: item.id,
          item_name: item.name,
          category_name: item.category?.name ?? "Lainnya",
          qty: Number(item.qty),
          unit_base: item.unit_base,
          min_stock: item.min_stock,
        }));
        setStockRows(enrichedStockRows);

        setMenuSlots((menuSlotsResponse.data ?? []) as MenuSlot[]);
        if ("data" in menuCalendarResponse && menuCalendarResponse.data) {
          setMenuCalendarMenu({
            menu_id: (menuCalendarResponse.data as { menu_id?: number | null }).menu_id ?? null,
            menu_name: (menuCalendarResponse.data as { menu_name?: string | null }).menu_name ?? null,
            date: (menuCalendarResponse.data as { date?: string | null }).date ?? null,
          });
        }

        const dishCompositionsRows = await listAllPaginatedRows<DishComposition>(
          sdk.dishCompositions.list.bind(sdk.dishCompositions),
          {
            sortBy: "id",
            sortDir: "ASC",
          },
          100,
        );

        setDishCompositionRows(dishCompositionsRows);

        const basahId = dashboardData.latest_spk_history?.basah?.id ?? null;
        const keringId = dashboardData.latest_spk_history?.kering_pengemas?.id ?? null;

        const [basahResult, keringResult] = await Promise.allSettled([
          basahId ? sdk.spk.getBasah(Number(basahId)) : Promise.resolve(null),
          keringId ? sdk.spk.getKeringPengemas(Number(keringId)) : Promise.resolve(null),
        ]);

        if (cancelled) return;

        if (basahResult.status === "fulfilled" && basahResult.value) {
          setBasahDetail(basahResult.value.data);
        } else {
          setBasahDetail(null);
        }

        if (keringResult.status === "fulfilled" && keringResult.value) {
          setKeringDetail(keringResult.value.data);
        } else {
          setKeringDetail(null);
        }

        setDraftAdjustmentCount(mode === "gudang" ? await loadGudangDraftAdjustmentCount() : 0);
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

  const currentDateLabel = useMemo(
    () =>
      new Intl.DateTimeFormat("id-ID", {
        timeZone: "Asia/Jakarta",
        weekday: "short",
        day: "2-digit",
        month: "short",
        year: "numeric",
      }).format(new Date()),
    [],
  );

  const patientPoints = useMemo(() => {
    const aggregatePoints = (dashboard.patient_fluctuation ?? []).map((point) => ({
      service_date: point.service_date,
      total_patients: Number(point.total_patients ?? 0),
    }));

    const source = aggregatePoints.length > 0
      ? aggregatePoints
      : dailyPatients.map((point) => ({
          service_date: point.service_date,
          total_patients: Number(point.total_patients ?? 0),
        }));

    return [...source]
      .sort((a, b) => a.service_date.localeCompare(b.service_date))
      .slice(-7);
  }, [dashboard.patient_fluctuation, dailyPatients]);
  const patientStats = useMemo(() => {
    if (patientPoints.length === 0) {
      return { average: 0, highest: 0, lowest: 0 };
    }

    const values = patientPoints.map((point) => Number(point.total_patients ?? 0));
    const total = values.reduce((sum, value) => sum + value, 0);

    return {
      average: Math.round(total / values.length),
      highest: Math.max(...values),
      lowest: Math.min(...values),
    };
  }, [patientPoints]);

  const latestPatients = patientPoints.at(-1)?.total_patients ?? 0;
  const previousPatients = patientPoints.at(-2)?.total_patients ?? latestPatients;
  const patientDelta =
    previousPatients > 0 ? ((latestPatients - previousPatients) / previousPatients) * 100 : 0;

  const stockSummary = dashboard.stock_summary ?? {};
  const criticalCount = Number(stockSummary.zero_stock_items ?? 0);
  const resolvedMenuId = dashboard.current_menu_cycle?.menu_id ?? menuCalendarMenu.menu_id ?? null;
  const activeMenu =
    dashboard.current_menu_cycle?.menu_name ??
    menuCalendarMenu.menu_name ??
    "Belum ada";
  const spkCount =
    Number(Boolean(dashboard.latest_spk_history?.basah?.id)) +
    Number(Boolean(dashboard.latest_spk_history?.kering_pengemas?.id));
  const draftAdjustmentLabel = formatNumber(draftAdjustmentCount ?? 0);
  const dashboardCardRoutes: DashboardCardRoutes =
    mode === "dapur"
      ? {
          patients: "/gizi/laporan",
          menu: "/gizi/menu/kalender",
          stock: "/gizi/stok",
          adjustment: "/gizi/stok",
          spk: "/gizi/spk",
          composition: "/gizi/menu/kalender",
          spkDetail: "/gizi/spk",
          outbound: "/gizi/laporan",
        }
      : {
          patients: "/gudang/laporan",
          menu: "/gudang/stok/penyesuaian",
          stock: "/gudang/stok",
          adjustment: "/gudang/stok/penyesuaian",
          spk: "/gudang/spk/riwayat",
          composition: "/gudang/transaksi/keluar",
          spkDetail: "/gudang/spk/riwayat",
          outbound: "/gudang/transaksi/keluar",
        };

  const todayIso = toIsoDate(new Date());

  const todayOutRows = useMemo(() => {
    const outRows = transactionRows.filter(
      (row) => row.type_name === "OUT" && row.transaction_date === todayIso,
    );
    const sourceRows = outRows.length > 0 ? outRows : transactionRows.filter((row) => row.type_name === "OUT");

    return sourceRows
      .slice()
      .sort((a, b) => String(b.transaction_date ?? "").localeCompare(String(a.transaction_date ?? "")))
      .slice(0, 5)
      .map((row) => {
        const relatedStock = stockRows.find((stock) => stock.item_name === row.item_name);
        const tone = getStockTone(Number(relatedStock?.qty ?? 0), Number(relatedStock?.min_stock ?? 1));

        return {
          id: row.transaction_id ?? Number(`${row.transaction_date ?? "0"}${row.item_name ?? ""}`.length),
          itemName: row.item_name ?? "-",
          category: relatedStock?.category_name ?? "-",
          outgoing: formatQuantity(row.qty ?? 0, relatedStock?.unit_base ?? "kg"),
          remaining: formatQuantity(relatedStock?.qty ?? 0, relatedStock?.unit_base ?? "kg"),
          tone: tone.tone,
          label: tone.label,
        };
      });
  }, [stockRows, todayIso, transactionRows]);

  const stockSummaryBoxes = useMemo(() => {
    const counts = stockRows.reduce(
      (acc, row) => {
        const tone = getStockTone(Number(row.qty ?? 0), Number(row.min_stock ?? 1)).tone;
        acc[tone] += 1;
        return acc;
      },
      { safe: 0, warning: 0, critical: 0, danger: 0 } as Record<
        "safe" | "warning" | "critical" | "danger",
        number
      >,
    );

    return [
      { label: "HABIS", value: counts.danger, tone: "bg-[#E0E7FF] text-[#3730A3]" },
      { label: "KRITIS", value: counts.critical, tone: "bg-[#FFE4E6] text-[#BE123C]" },
      { label: "MENIPIS", value: counts.warning, tone: "bg-[#FFF7CC] text-[#92400E]" },
      { label: "STOK AMAN", value: counts.safe, tone: "bg-[#DCFCE7] text-[#166534]" },
    ];
  }, [stockRows]);

  const warningRows = useMemo(
    () =>
      stockRows
        .filter((row) => {
          const tone = getStockTone(Number(row.qty ?? 0), Number(row.min_stock ?? 1)).tone;
          return tone === "danger" || tone === "critical";
        })
        .sort(
          (left, right) =>
            Number(left.qty ?? 0) - Number(right.qty ?? 0) ||
            String(left.item_name ?? "").localeCompare(String(right.item_name ?? "")),
        ),
    [stockRows],
  );
  const visibleWarningRows = useMemo(() => warningRows.slice(0, 8), [warningRows]);

  const stockFocusRows = useMemo(
    () =>
      [...stockRows]
        .sort((left, right) => {
          const priority = (row: StockReportRow) => {
            const tone = getStockTone(Number(row.qty ?? 0), Number(row.min_stock ?? 1)).tone;
            return getStockPriority(tone as MenuIngredientRow["tone"]);
          };
          return priority(left) - priority(right) || Number(left.qty ?? 0) - Number(right.qty ?? 0);
        })
        .slice(0, 9),
    [stockRows],
  );

  const compositionRows = useMemo(() => {
    if (mode !== "dapur") {
      return [];
    }

    const rows = (dashboard.current_menu_composition ?? []) as NonNullable<
      DashboardState["current_menu_composition"]
    >;
    const grouped = new Map<string, CompositionGroup>();

    rows.forEach((row, index) => {
      const mealTime = normaliseMealLabel(row.meal_time);
      const dishId = Number(row.dish_id ?? index + 1);
      const key = `${mealTime}-${dishId}`;
      const existing = grouped.get(key);
      const mealTimeId = mealTime === "PAGI" ? 1 : mealTime === "SIANG" ? 2 : mealTime === "SORE" ? 3 : 99;

      if (existing) {
        existing.items.push(row);
        return;
      }

      grouped.set(key, {
        key,
        mealTime,
        mealTimeId,
        dishId,
        dishName: row.dish_name ?? "-",
        items: [row],
      });
    });

    return [...grouped.values()].sort((a, b) => a.mealTimeId - b.mealTimeId || a.dishName.localeCompare(b.dishName));
  }, [dashboard.current_menu_composition, mode]);

  const menuIngredientRows = useMemo(() => {
    if (!resolvedMenuId) return [];

    const currentMenuSlotDishIds = menuSlots
      .filter((slot) => slot.menu_id === resolvedMenuId)
      .map((slot) => slot.dish_id);

    if (currentMenuSlotDishIds.length === 0) {
      return [];
    }

    const compositionMap = new Map<number, MenuIngredientRow>();
    const currentStockByItemId = new Map<number, StockReportRow>();
    stockRows.forEach((row) => {
      if (typeof row.item_id === "number") {
        currentStockByItemId.set(row.item_id, row);
      }
    });

    dishCompositionRows
      .filter((row) => currentMenuSlotDishIds.includes(row.dish_id))
      .forEach((row) => {
        const itemId = row.item_id;
        const itemName = row.item?.name ?? `Item #${itemId}`;
        const unitBase = row.item?.unit_base ?? currentStockByItemId.get(itemId)?.unit_base ?? "pcs";
        const currentStockQty = Number(currentStockByItemId.get(itemId)?.qty ?? 0);
        const requiredQty = Number(row.qty_per_patient ?? 0) * Number(latestPatients ?? 0);
        const tone = getStockTone(currentStockQty, Math.max(requiredQty, 1));
        const existing = compositionMap.get(itemId);

        if (existing) {
          existing.requiredQty += requiredQty;
          existing.currentStockQty = currentStockQty;
          existing.tone = tone.tone;
          existing.label = tone.label;
          return;
        }

        compositionMap.set(itemId, {
          itemId,
          itemName,
          unitBase,
          currentStockQty,
          requiredQty,
          tone: tone.tone,
          label: tone.label,
        });
      });

    return [...compositionMap.values()]
      .sort((a, b) => getStockPriority(a.tone) - getStockPriority(b.tone) || a.itemName.localeCompare(b.itemName))
      .slice(0, 6);
  }, [dishCompositionRows, latestPatients, menuSlots, resolvedMenuId, stockRows]);

  const packageRows = useMemo(() => {
    const currentMenuId = resolvedMenuId;
    const relevantSlots = menuSlots.filter((slot) => slot.menu_id === currentMenuId);
    const grouped = new Map<string, MenuSlot[]>();

    relevantSlots.forEach((slot) => {
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
          slots,
        };
      })
      .sort((a, b) => a.mealTimeId - b.mealTimeId);
  }, [activeMenu, dashboard.current_menu_cycle?.menu_id, menuSlots]);

  const spkPanels = useMemo(() => {
    const panels: SpkPanelDetail[] = [];

    if (dashboard.latest_spk_history?.basah?.id && basahDetail) {
      panels.push({
        id: basahDetail.id,
        title: "BELANJA BASAH",
        detail: basahDetail,
      });
    }

    if (dashboard.latest_spk_history?.kering_pengemas?.id && keringDetail) {
      panels.push({
        id: keringDetail.id,
        title: "BELANJA KERING & PENGEMAS",
        detail: keringDetail,
      });
    }

    return panels;
  }, [basahDetail, dashboard.latest_spk_history?.basah?.id, dashboard.latest_spk_history?.kering_pengemas?.id, keringDetail]);

  const title = "Dashboard";
  const subtitle =
    mode === "gudang"
      ? "Ringkasan operasional gudang hari ini"
      : "";

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold text-gray-900">{title}</h1>
        <p className="text-sm text-gray-400">{subtitle}</p>
      </div>

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
        <DashboardStatCard
          title="Pasien Hari Ini"
          value={loading ? "..." : formatNumber(Number(latestPatients ?? 0))}
          subtitle={
            loading
              ? "Memuat data pasien"
              : `${patientDelta >= 0 ? "+" : ""}${formatNumber(Math.abs(patientDelta), 1)}% dari kemarin`
          }
          color="border-blue-300"
          outlineClass="border-blue-200"
          icon={<Users className="text-gray-500" />}
          onClick={dashboardCardRoutes ? () => router.push(dashboardCardRoutes.patients) : undefined}
        />
        <DashboardStatCard
          title={mode === "gudang" ? "Penyesuaian Stok" : "Menu Aktif"}
          value={loading ? "..." : mode === "gudang" ? draftAdjustmentLabel : activeMenu}
          subtitle={loading ? (mode === "gudang" ? "Memuat draft" : "Memuat menu aktif") : mode === "gudang" ? "Belum diajukan" : "Menu hari ini"}
          color="border-green-500"
          icon={<Utensils className="text-gray-500" />}
          onClick={dashboardCardRoutes ? () => router.push(dashboardCardRoutes.menu) : undefined}
        />
        <DashboardStatCard
          title="Stok Kritis"
          value={loading ? "..." : formatNumber(criticalCount)}
          subtitle={loading ? "Memuat stok" : "Bahan butuh restock"}
          color="border-red-500"
          icon={<AlertTriangle className="text-gray-500" />}
          onClick={dashboardCardRoutes ? () => router.push(dashboardCardRoutes.stock) : undefined}
        />
        <DashboardStatCard
          title="SPK Belanja"
          value={loading ? "..." : formatNumber(spkCount)}
          subtitle={loading ? "Memuat SPK" : "Bahan rekomendasi aktif"}
          color="border-yellow-500"
          icon={<ShoppingCart className="text-gray-500" />}
          onClick={dashboardCardRoutes ? () => router.push(dashboardCardRoutes.spk) : undefined}
        />
      </div>

      {mode === "dapur" ? (
        <>
          <div className="grid grid-cols-1 gap-6 xl:grid-cols-[1.12fr_0.88fr]">
            <SurfaceCard
              className="flex h-full flex-col overflow-hidden border border-[#D9E6FF] bg-white shadow-sm"
              onClick={() => router.push(dashboardCardRoutes.composition)}
            >
              <div className="border-b border-[#D9E6FF] bg-gradient-to-r from-[#F4F8FF] to-white px-5 py-4">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#2155CD]">
                      Komposisi Hari Ini
                    </p>
                    <h3 className="mt-1 text-base font-semibold text-[#16213E]">Komposisi Menu Hari Ini</h3>
                    <p className="mt-1 text-xs text-[#64748B]">
                      {currentDateLabel} - {activeMenu} - {formatNumber(Number(latestPatients ?? 0))} pasien
                    </p>
                  </div>
                  <MiniActionButton onClick={() => router.push(dashboardCardRoutes.composition)}>
                    Detail
                  </MiniActionButton>
                </div>
              </div>

              <div className="flex-1 overflow-auto">
                <table className="min-w-full text-left text-sm">
                  <thead className="sticky top-0 z-10 bg-gradient-to-r from-[#2155CD] to-[#6EA8FF] text-[11px] font-semibold uppercase tracking-wide text-white">
                    <tr>
                      <th className="px-4 py-3">Menu</th>
                      <th className="px-4 py-3">Komposisi Bahan</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[#E6EEFF] text-sm text-gray-700">
                    {compositionRows.map((row, index) => (
                      <tr key={row.key} className={index % 2 === 0 ? "bg-white" : "bg-[#F8FBFF] hover:bg-[#EEF4FF]/70"}>
                        <td className="px-4 py-4 align-top">
                          <div className="flex items-start gap-3">
                            <span
                              className={`inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-wide ${
                                getMealPalette(row.mealTime).label
                              } bg-white/90 ring-1 ring-inset ring-[#D7E0EE]`}
                            >
                              {row.mealTime}
                            </span>
                            <div className="min-w-0">
                              <p className="font-semibold text-[#16213E]">{row.dishName}</p>
                              <p className="mt-1 text-xs text-[#64748B]">{row.items.length} bahan utama</p>
                            </div>
                          </div>
                        </td>
                        <td className="px-4 py-4 text-[#475569]">
                          <div className="flex flex-wrap gap-2">
                            {renderCompositionChips(row.items)}
                            {row.items.length > 4 ? (
                              <span className="rounded-full bg-[#E2E8F0] px-3 py-1 text-xs font-semibold text-[#475569]">
                                +{row.items.length - 4} lagi
                              </span>
                            ) : null}
                          </div>
                        </td>
                      </tr>
                    ))}
                    {!loading && compositionRows.length === 0 ? (
                      <tr>
                        <td className="px-4 py-10 text-center text-gray-400" colSpan={2}>
                          Belum ada komposisi menu yang tersedia.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
            </SurfaceCard>

            <SurfaceCard
              className="flex h-full flex-col overflow-hidden border border-[#D9E6FF] bg-white shadow-sm"
              onClick={() => router.push(dashboardCardRoutes.spk)}
            >
              <div className="border-b border-[#D9E6FF] bg-gradient-to-r from-[#EEF4FF] to-white px-5 py-4">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#64748B]">
                      Rekomendasi Belanja
                    </p>
                    <h3 className="mt-1 text-base font-semibold text-[#16213E]">SPK - Rekomendasi Belanja</h3>
                    <p className="mt-1 text-xs text-[#64748B]">Hasil rekomendasi aktif dari SPK hari ini</p>
                  </div>
                  <MiniActionButton onClick={() => router.push(dashboardCardRoutes.spk)}>
                    Detail
                  </MiniActionButton>
                </div>
              </div>

              <div className="flex-1 space-y-4 overflow-auto px-5 py-4">
                {spkPanels.map((panel) => (
                  <div key={panel.id} className="rounded-2xl border border-[#C7D2FE] bg-gradient-to-br from-[#EEF4FF] to-white p-4 shadow-sm">
                    <div className="mb-3 flex items-start justify-between gap-3">
                      <div>
                        <p className="text-xs font-semibold uppercase tracking-wide text-[#64748B]">{panel.title}</p>
                        <p className="mt-2 text-sm font-semibold text-[#16213E]">SPK-{String(panel.id).padStart(4, "0")}</p>
                        <p className="mt-1 text-xs text-[#64748B]">
                          {formatCompactDate(panel.detail?.calculation_date ?? null)}
                        </p>
                      </div>
                      <MiniActionButton onClick={() => router.push(dashboardCardRoutes.spk)}>
                        Detail
                      </MiniActionButton>
                    </div>

                    <div className="space-y-2">
                      {(panel.detail?.items ?? []).slice(0, 4).map((item) => (
                        <div
                          key={item.id}
                          className="flex items-center justify-between rounded-lg border border-white/70 bg-white/80 px-3 py-2 text-sm text-[#16213E]"
                        >
                          <span className="font-medium">{item.item_name ?? "-"}</span>
                          <span className="font-semibold">
                            Beli {formatQuantity(item.final_recommended_qty ?? 0, item.item_unit_base)}
                          </span>
                        </div>
                      ))}
                    </div>

                    {(panel.detail?.items ?? []).length > 4 ? (
                      <p className="mt-2 text-xs text-[#64748B]">
                        +{(panel.detail?.items ?? []).length - 4} item lainnya
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

          <div className="grid grid-cols-1 gap-6 xl:grid-cols-[0.94fr_1.06fr]">
            <SurfaceCard
              className="flex h-full flex-col overflow-hidden border border-[#D9E6FF] bg-white shadow-sm"
              onClick={() => router.push(dashboardCardRoutes.stock)}
            >
              <div className="border-b border-[#D9E6FF] bg-gradient-to-r from-[#F8FAFF] to-white px-5 py-4">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#2155CD]">
                      Ringkasan Stok
                    </p>
                    <h3 className="mt-1 text-base font-semibold text-[#16213E]">Ringkasan Stok Bahan</h3>
                    <p className="mt-1 text-xs text-[#64748B]">Ikhtisar singkat untuk bahan yang perlu perhatian</p>
                  </div>
                  <MiniActionButton onClick={() => router.push(dashboardCardRoutes.stock)}>
                    Detail
                  </MiniActionButton>
                </div>
              </div>

              <div className="flex-1 px-5 py-4">
                <div className="grid grid-cols-2 gap-3">
                  {stockSummaryBoxes.map((box) => (
                    <div key={box.label} className={`rounded-2xl px-4 py-3 ${box.tone}`}>
                      <div className="text-[11px] font-semibold uppercase tracking-wide">{box.label}</div>
                      <div className="mt-1 text-lg font-bold">{formatNumber(box.value)}</div>
                      <div className="text-xs opacity-80">Bahan</div>
                    </div>
                  ))}
                </div>
              </div>
            </SurfaceCard>

            <SurfaceCard
              className="flex h-full flex-col overflow-hidden border border-[#D9E6FF] bg-white shadow-sm"
              onClick={() => router.push(dashboardCardRoutes.outbound)}
            >
              <div className="border-b border-[#D9E6FF] bg-gradient-to-r from-[#F4F8FF] to-white px-5 py-4">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#64748B]">
                      Tren Pasien
                    </p>
                    <h3 className="mt-1 text-base font-semibold text-[#16213E]">Tren Pasien 7 Hari Terakhir</h3>
                    <p className="mt-1 text-xs text-[#64748B]">Ringkasan visual jumlah pasien per hari</p>
                  </div>
                  <MiniActionButton onClick={() => router.push(dashboardCardRoutes.outbound)}>
                    Detail
                  </MiniActionButton>
                </div>
              </div>

              <div className="flex flex-1 flex-col px-5 py-4">
                <div className="grid grid-cols-3 gap-3">
                  <div className="rounded-2xl bg-[#EEF4FF] px-3 py-2">
                    <div className="text-[11px] uppercase tracking-wide text-[#64748B]">Rata-rata</div>
                    <div className="mt-1 text-lg font-bold text-[#16213E]">{formatNumber(patientStats.average)}</div>
                  </div>
                  <div className="rounded-2xl bg-[#ECFDF3] px-3 py-2">
                    <div className="text-[11px] uppercase tracking-wide text-[#64748B]">Tertinggi</div>
                    <div className="mt-1 text-lg font-bold text-[#16213E]">{formatNumber(patientStats.highest)}</div>
                  </div>
                  <div className="rounded-2xl bg-[#FFF7CC] px-3 py-2">
                    <div className="text-[11px] uppercase tracking-wide text-[#64748B]">Terendah</div>
                    <div className="mt-1 text-lg font-bold text-[#16213E]">{formatNumber(patientStats.lowest)}</div>
                  </div>
                </div>

                <div className="mt-4 flex flex-1 items-end gap-3 min-h-[260px]">
                  {patientPoints.map((point) => {
                    const highest = Math.max(...patientPoints.map((entry) => Number(entry.total_patients ?? 0)), 1);
                    const barHeight = Math.max((Number(point.total_patients ?? 0) / highest) * 180, 24);

                    return (
                      <div key={point.service_date} className="flex flex-1 flex-col items-center gap-2">
                        <div className="text-xs font-semibold text-[#64748B]">
                          {formatNumber(Number(point.total_patients ?? 0))}
                        </div>
                        <div className="flex h-[190px] w-full items-end">
                          <div
                            className="w-full rounded-t-2xl bg-gradient-to-t from-[#BFD7FF] to-[#DDE9FF] shadow-[0_8px_20px_rgba(33,85,205,0.12)]"
                            style={{ height: `${barHeight}px` }}
                          />
                        </div>
                        <div className="text-[11px] text-[#64748B]">{formatCompactDate(point.service_date)}</div>
                      </div>
                    );
                  })}
                  {!loading && patientPoints.length === 0 ? (
                    <div className="flex h-full w-full items-center justify-center text-sm text-gray-400">
                      Belum ada data pasien.
                    </div>
                  ) : null}
                </div>
              </div>
            </SurfaceCard>
          </div>
        </>
      ) : (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {mode === "gudang" ? (
          <SurfaceCard className="flex h-full flex-col overflow-hidden border border-[#D9E6FF] bg-white shadow-sm">
            <div className="border-b border-[#D9E6FF] bg-gradient-to-r from-[#F4F8FF] to-white px-5 py-4">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#64748B]">Transaksi Keluar</p>
                  <h3 className="mt-1 text-base font-semibold text-[#16213E]">Bahan Keluar Hari Ini</h3>
                  <p className="mt-1 text-xs text-[#64748B]">Ringkasan transaksi keluar terbaru</p>
                </div>
                <MiniActionButton onClick={() => router.push(dashboardCardRoutes.outbound)}>
                  Detail
                </MiniActionButton>
              </div>
            </div>

            <div className="flex-1 overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-gradient-to-r from-[#2155CD] to-[#6EA8FF] text-[11px] font-semibold uppercase tracking-wide text-white">
                  <tr>
                    <th className="px-4 py-3">Bahan</th>
                    <th className="px-4 py-3">Keluar</th>
                    <th className="px-4 py-3">Sisa Stok</th>
                    <th className="px-4 py-3">Status</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#E6EEFF] text-sm text-gray-700">
                  {todayOutRows.map((row, index) => (
                    <tr key={`${row.id}-${index}`} className={index % 2 === 0 ? "bg-white" : "bg-[#F8FBFF] hover:bg-[#EEF4FF]/70"}>
                      <td className="px-4 py-4">
                        <p className="font-semibold text-[#16213E]">{row.itemName}</p>
                        <p className="text-xs text-[#64748B]">{row.category}</p>
                      </td>
                      <td className="px-4 py-4">{row.outgoing}</td>
                      <td className="px-4 py-4">{row.remaining}</td>
                      <td className="px-4 py-4">
                        <StatusPill tone={row.tone}>{row.label}</StatusPill>
                      </td>
                    </tr>
                  ))}
                  {!loading && todayOutRows.length === 0 ? (
                    <tr>
                      <td className="px-4 py-10 text-center text-gray-400" colSpan={4}>
                        Belum ada transaksi keluar pada periode ini.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </SurfaceCard>
        ) : (
          <SurfaceCard className="overflow-hidden">
            <div className="flex items-center justify-between border-b px-5 py-4">
              <div>
                <h3 className="text-base font-semibold text-[#16213E]">Komposisi Menu Hari Ini</h3>
                <p className="mt-1 text-xs text-[#94A3B8]">
                  {currentDateLabel} - {activeMenu} - {formatNumber(Number(latestPatients ?? 0))} pasien
                </p>
              </div>
              <MiniActionButton>Detail</MiniActionButton>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                  <tr>
                    <th className="px-4 py-3">Menu</th>
                    <th className="px-4 py-3">Komposisi Bahan</th>
                  </tr>
                </thead>
                <tbody className="text-sm text-gray-700">
                  {compositionRows.map((row) => (
                    <tr key={row.key} className="border-t border-gray-200">
                      <td className="px-4 py-4">
                        <p className="font-medium text-gray-900">{row.dishName}</p>
                        <p className="text-xs text-[#94A3B8]">{row.mealTime}</p>
                      </td>
                      <td className="px-4 py-4 text-[#475569]">
                        {renderCompositionSummary(row.items)}
                      </td>
                    </tr>
                  ))}
                  {!loading && compositionRows.length === 0 ? (
                    <tr>
                      <td className="px-4 py-8 text-center text-gray-400" colSpan={2}>
                        Belum ada komposisi menu yang tersedia.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>
          </SurfaceCard>
        )}

          <SurfaceCard className="overflow-hidden p-6">
            <h3 className="font-semibold text-gray-900">Tren Pasien 7 Hari Terakhir</h3>
            <div className="mt-4 flex h-[240px] items-end gap-3">
            {patientPoints.map((point) => {
              const highest = Math.max(...patientPoints.map((entry) => Number(entry.total_patients ?? 0)), 1);
              const barHeight = Math.max((Number(point.total_patients ?? 0) / highest) * 170, 24);

              return (
                <div key={point.service_date} className="flex flex-1 flex-col items-center gap-2">
                  <div className="text-xs font-semibold text-[#94A3B8]">
                    {formatNumber(Number(point.total_patients ?? 0))}
                  </div>
                  <div className="flex h-[180px] w-full items-end">
                    <div
                      className="w-full rounded-t-xl bg-[#D9E7FF] shadow-[0_8px_20px_rgba(33,85,205,0.12)]"
                      style={{ height: `${barHeight}px` }}
                    />
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
          </SurfaceCard>
        </div>
      )}

      {mode === "gudang" ? (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
        <SurfaceCard className="flex h-full flex-col p-5">
          <div className="mb-4 flex items-center justify-between">
            <h3 className="font-semibold text-gray-900">Ringkasan Stok Bahan</h3>
            <MiniActionButton onClick={() => router.push(dashboardCardRoutes.stock)}>
              Detail
            </MiniActionButton>
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
            {menuIngredientRows.length > 0
              ? menuIngredientRows.map((row) => {
                  const percent = Math.max(
                    Math.min((row.currentStockQty / Math.max(row.requiredQty, 1)) * 100, 100),
                    4,
                  );
                  return (
                    <div key={row.itemId}>
                      <div className="mb-1 flex items-center justify-between text-sm text-[#475569]">
                        <span>{row.itemName}</span>
                        <span>{formatQuantity(row.currentStockQty, row.unitBase)}</span>
                      </div>
                      <div className="h-2 rounded-full bg-[#E2E8F0]">
                        <div className="h-2 rounded-full bg-[#F59E0B]" style={{ width: `${percent}%` }} />
                      </div>
                    </div>
                  );
                })
              : stockFocusRows.map((row) => {
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

        <SurfaceCard className="flex h-full flex-col overflow-hidden border border-[#D9E6FF] bg-white shadow-sm">
          <div className="border-b border-[#D9E6FF] bg-gradient-to-r from-[#F8FAFF] to-white px-4 py-3">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="text-[10px] font-semibold uppercase tracking-[0.24em] text-[#2155CD]">
                  Peringatan Stok
                </p>
                <h3 className="mt-1 text-[15px] font-semibold text-[#16213E]">
                  {mode === "gudang" ? "Peringatan Stok Bahan" : "Paket Menu Hari Ini"}
                </h3>
              </div>
              <MiniActionButton className="px-3 py-1.5 text-xs" onClick={() => router.push(dashboardCardRoutes.adjustment)}>
                Detail
              </MiniActionButton>
            </div>
          </div>
          {mode === "gudang" ? (
            <div className="flex flex-1 flex-col px-4 py-3">
              <div className="space-y-2.5">
                {visibleWarningRows.map((row) => {
                  const tone = getStockTone(Number(row.qty ?? 0), Number(row.min_stock ?? 1));
                  const palette =
                    tone.tone === "danger"
                      ? "border-[#FCA5A5] bg-[#FEE2E2] text-[#DC2626]"
                      : tone.tone === "critical"
                        ? "border-[#FB7185] bg-[#FFE4E6] text-[#BE123C]"
                        : "border-[#F59E0B] bg-[#FFF7CC] text-[#92400E]";

                  return (
                    <div key={row.item_id} className={`rounded-lg border px-3 py-2.5 ${palette}`}>
                      <div className="flex items-center justify-between gap-3">
                        <div className="min-w-0">
                          <p className="truncate text-[15px] font-semibold leading-tight">{row.item_name}</p>
                          <p className="mt-1 text-[11px] uppercase tracking-wide opacity-80">{row.category_name}</p>
                        </div>
                        <p className="shrink-0 text-sm font-bold leading-none">{formatQuantity(row.qty, row.unit_base)}</p>
                      </div>
                    </div>
                  );
                })}
              </div>
              {!loading && warningRows.length === 0 ? (
                <div className="mt-3 rounded-lg bg-[#F8FAFC] px-4 py-6 text-center text-sm text-gray-400">
                  Tidak ada stok kritis yang perlu perhatian saat ini.
                </div>
              ) : null}
              {!loading && warningRows.length > visibleWarningRows.length ? (
                <p className="mt-2 text-xs text-[#64748B]">
                  +{warningRows.length - visibleWarningRows.length} item lainnya
                </p>
              ) : null}
            </div>
          ) : (
            <div className="space-y-3">
              {packageRows.map((row) => {
                const detailComposition = compositionRows.filter((item) => item.mealTime === row.mealTime);
                const summary =
                  detailComposition.length > 0
                    ? renderPackageSummary(detailComposition)
                    : "Menu aktif hari ini";
                const palette = getMealPalette(row.mealTime);

                return (
                  <div key={row.key} className={`rounded-2xl border px-4 py-3 ${palette.card}`}>
                    <div className="mb-2 flex items-center justify-between">
                      <p className={`text-xs font-semibold uppercase tracking-wide ${palette.label}`}>{row.mealTime}</p>
                      <span className="text-[11px] text-[#94A3B8]">{row.menuName}</span>
                    </div>
                    <p className={`text-sm font-semibold ${palette.title}`}>{row.dishName}</p>
                    <p className="mt-1 text-xs text-[#64748B]">{summary}</p>
                  </div>
                );
              })}
              {!loading && packageRows.length === 0 ? (
                <div className="rounded-xl bg-[#F8FAFC] px-4 py-8 text-center text-sm text-gray-400">
                  Belum ada paket menu aktif yang dapat ditampilkan.
                </div>
              ) : null}
            </div>
          )}
        </SurfaceCard>

        <SurfaceCard className="flex h-full flex-col overflow-hidden border border-[#D9E6FF] bg-white shadow-sm">
          <div className="border-b border-[#D9E6FF] bg-gradient-to-r from-[#EEF4FF] to-white px-5 py-4">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#64748B]">
                  SPK Perencanaan
                </p>
                <h3 className="mt-1 text-base font-semibold text-[#16213E]">SPK - Rekomendasi Belanja</h3>
              </div>
              <MiniActionButton onClick={() => router.push(dashboardCardRoutes.spk)}>
                Detail
              </MiniActionButton>
            </div>
          </div>
          <div className="flex-1 space-y-4 overflow-auto px-5 py-4">
            {spkPanels.map((panel) => (
              <div key={panel.id} className="rounded-2xl border border-[#C7D2FE] bg-gradient-to-br from-[#EEF4FF] to-white p-4 shadow-sm">
                <div className="mb-3 flex items-start justify-between gap-3">
                  <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-[#64748B]">{panel.title}</p>
                    <p className="mt-2 text-sm font-semibold text-[#16213E]">
                      SPK-{String(panel.id).padStart(4, "0")}
                    </p>
                    <p className="mt-1 text-xs text-[#64748B]">{formatCompactDate(panel.detail?.calculation_date ?? null)}</p>
                  </div>
                  <MiniActionButton onClick={() => router.push(dashboardCardRoutes.spk)}>
                    Detail
                  </MiniActionButton>
                </div>

                <div className="space-y-2">
                  {(panel.detail?.items ?? []).slice(0, 3).map((item) => (
                    <div
                      key={item.id}
                      className="flex items-center justify-between rounded-lg border border-white/70 bg-white/80 px-3 py-2 text-sm text-[#16213E]"
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
      ) : null}
    </div>
  );
}

function DashboardStatCard({
  title,
  value,
  subtitle,
  color,
  outlineClass = "border-gray-100",
  icon,
  onClick,
}: {
  title: string;
  value: string;
  subtitle: string;
  color: string;
  outlineClass?: string;
  icon: ReactNode;
  onClick?: () => void;
}) {
  const className = `w-full rounded-2xl border ${outlineClass} border-t-4 bg-white p-6 text-left shadow-sm transition-all duration-300 ease-out ${color} ${
    onClick ? "cursor-pointer hover:-translate-y-0.5 hover:shadow-[0_14px_30px_rgba(15,23,42,0.08)]" : ""
  }`;

  const content = (
    <div className="flex items-start justify-between">
      <div>
        <p className="text-xs uppercase tracking-wide text-gray-400">{title}</p>
        <h2 className="mt-1 text-2xl font-semibold text-gray-900">{value}</h2>
        <p className="mt-1 text-xs text-gray-400">{subtitle}</p>
      </div>
      <div className="rounded-lg bg-gray-100 p-2">{icon}</div>
    </div>
  );

  if (onClick) {
    return (
      <button className={className} onClick={onClick} type="button">
        {content}
      </button>
    );
  }

  return <div className={className}>{content}</div>;
}

function renderCompositionSummary(items: NonNullable<DashboardState["current_menu_composition"]>) {
  const names = [...new Set(items.map((item) => item.item_name).filter((value): value is string => Boolean(value)))];
  if (names.length === 0) return "-";
  const preview = names.slice(0, 4).join(", ");
  const remaining = names.length - 4;
  return remaining > 0 ? `${preview} +${remaining} lagi` : preview;
}

function renderPackageSummary(groups: CompositionGroup[]) {
  const flattened = groups.flatMap((group) => group.items);
  const names = [...new Set(flattened.map((item) => item.item_name).filter((value): value is string => Boolean(value)))];
  if (names.length === 0) return "Menu aktif hari ini";
  const preview = names.slice(0, 3).join(", ");
  return names.length > 3 ? `${preview} +${names.length - 3} lagi` : preview;
}

function renderCompositionChips(items: Array<{ item_name?: string | null }>) {
  const names = [...new Set(items.map((item) => item.item_name).filter((value): value is string => Boolean(value)))];
  const chipColors = [
    "bg-[#DBEAFE] text-[#1D4ED8]",
    "bg-[#DCFCE7] text-[#166534]",
    "bg-[#FEF3C7] text-[#B45309]",
    "bg-[#F3E8FF] text-[#7C3AED]",
  ];

  return names.slice(0, 4).map((name, index) => (
    <span key={name} className={`rounded-full px-3 py-1 text-xs font-medium ${chipColors[index % chipColors.length]}`}>
      {name}
    </span>
  ));
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

async function loadGudangDraftAdjustmentCount() {
  if (typeof window === "undefined") return 0;

  const historyStorageKey = "gudang-stock-opname-history";
  const legacyLatestKey = "gudang-latest-stock-opname-id";
  const mergedIds = readStoredOpnameIds(historyStorageKey);

  const legacyId = Number(window.sessionStorage.getItem(legacyLatestKey) ?? 0);
  if (legacyId > 0 && !mergedIds.includes(legacyId)) {
    mergedIds.unshift(legacyId);
  }
  if (legacyId > 0) {
    window.sessionStorage.removeItem(legacyLatestKey);
  }

  if (mergedIds.length === 0) {
    return 0;
  }

  const responses = await Promise.allSettled(mergedIds.map((id) => sdk.stockOpnames.get(id)));
  return responses.reduce((count, response) => {
    if (response.status !== "fulfilled" || !response.value.data) {
      return count;
    }

    return response.value.data.header.state === "DRAFT" ? count + 1 : count;
  }, 0);
}

function readStoredOpnameIds(storageKey: string) {
  if (typeof window === "undefined") return [] as number[];

  try {
    const raw = window.localStorage.getItem(storageKey);
    if (!raw) return [];

    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];

    return parsed
      .map((value) => Number(value))
      .filter((value) => Number.isFinite(value) && value > 0);
  } catch {
    return [];
  }
}
