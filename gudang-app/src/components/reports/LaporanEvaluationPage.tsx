"use client";

import { useEffect, useMemo, useState } from "react";
import {
  CartesianGrid,
  LabelList,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import sdk from "@/lib";
import { listAllItems } from "@/lib/items";
import type { Item } from "@/sdk/types";
import {
  formatDate,
  formatQuantity,
  getCurrentMonthPeriod,
  getErrorMessage,
  toIsoDate,
} from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  FilterSearch,
  Pagination,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";

const ALL_STOCK_REPORT_PERIOD = {
  period_start: "2000-01-01",
  period_end: "2099-12-31",
} as const;

type PeriodMode = "MONTHLY" | "YEARLY";

type StockReportRow = {
  item_id?: number;
  item_name?: string | null;
  category_name?: string | null;
  qty?: number | string | null;
  unit_base?: string | null;
};

type TransactionReportRow = {
  transaction_id?: number;
  transaction_date?: string | null;
  type_name?: string | null;
  status_id?: number | null;
  status_name?: string | null;
  item_id?: number | null;
  item_name?: string | null;
  qty?: number | string | null;
};

type SpkHistoryProjectionRow = {
  spk_recommendations?: Array<{
    item_id?: number | null;
    qty?: number | string | null;
  }>;
};

type SpkHistoryReportRow = {
  spk_id?: number;
  calculation_date?: string | null;
  compatibility_projection?: {
    rows?: SpkHistoryProjectionRow[];
  };
};

type ReportTableRow = {
  itemId: number;
  itemName: string;
  categoryName: string;
  unit: string;
  spkQty: number;
  incomingQty: number;
  outgoingQty: number;
  spkMinusIncoming: number;
  incomingMinusOutgoing: number;
};

type ChartPoint = {
  monthKey: string;
  label: string;
  spk: number;
  incoming: number;
  outgoing: number;
};

type ItemSelectOption = {
  value: string;
  label: string;
};

type SelectOption = {
  value: string;
  label: string;
};

export default function LaporanEvaluationPage() {
  const [periodMode, setPeriodMode] = useState<PeriodMode>("MONTHLY");
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [searchTerm, setSearchTerm] = useState("");
  const [categoryFilter, setCategoryFilter] = useState("all");
  const [selectedItemId, setSelectedItemId] = useState("");
  const [stockRows, setStockRows] = useState<StockReportRow[]>([]);
  const [itemMasterRows, setItemMasterRows] = useState<Item[]>([]);
  const [tableTransactions, setTableTransactions] = useState<TransactionReportRow[]>([]);
  const [tableSpkHistory, setTableSpkHistory] = useState<SpkHistoryReportRow[]>([]);
  const [chartTransactions, setChartTransactions] = useState<TransactionReportRow[]>([]);
  const [chartSpkHistory, setChartSpkHistory] = useState<SpkHistoryReportRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const tablePeriod =
          periodMode === "MONTHLY"
            ? getCurrentMonthPeriod()
            : getYearPeriod(selectedYear);
        const chartPeriod =
          periodMode === "MONTHLY"
            ? getLastFourMonthsPeriod()
            : getYearPeriod(selectedYear);

        const [
          itemsResult,
          stocksResult,
          tableTransactionsResult,
          tableSpkResult,
          chartTransactionsResult,
          chartSpkResult,
        ] =
          await Promise.allSettled([
            listAllItems({
              perPage: 100,
              sortBy: "id",
              sortDir: "ASC",
            }),
            sdk.reports.getStocks(ALL_STOCK_REPORT_PERIOD),
            sdk.reports.getTransactions(tablePeriod),
            sdk.reports.getSpkHistory(tablePeriod),
            periodMode === "MONTHLY"
              ? sdk.reports.getTransactions(chartPeriod)
              : Promise.resolve(null),
            periodMode === "MONTHLY"
              ? sdk.reports.getSpkHistory(chartPeriod)
              : Promise.resolve(null),
          ]);

        if (cancelled) return;

        const nextItemMasterRows =
          itemsResult.status === "fulfilled" && Array.isArray(itemsResult.value)
            ? itemsResult.value
            : [];
        const nextStockRows =
          stocksResult.status === "fulfilled"
            ? ((stocksResult.value.data.rows as StockReportRow[]) ?? [])
            : [];
        const nextTableTransactions =
          tableTransactionsResult.status === "fulfilled"
            ? ((tableTransactionsResult.value.data.rows as TransactionReportRow[]) ?? [])
            : [];
        const nextTableSpkHistory =
          tableSpkResult.status === "fulfilled"
            ? ((tableSpkResult.value.data.rows as SpkHistoryReportRow[]) ?? [])
            : [];
        const nextChartTransactions =
          periodMode === "MONTHLY"
            ? chartTransactionsResult.status === "fulfilled" && chartTransactionsResult.value
              ? ((chartTransactionsResult.value.data.rows as TransactionReportRow[]) ?? [])
              : []
            : nextTableTransactions;
        const nextChartSpkHistory =
          periodMode === "MONTHLY"
            ? chartSpkResult.status === "fulfilled" && chartSpkResult.value
              ? ((chartSpkResult.value.data.rows as SpkHistoryReportRow[]) ?? [])
              : []
            : nextTableSpkHistory;

        setItemMasterRows(nextItemMasterRows);
        setStockRows(nextStockRows);
        setTableTransactions(nextTableTransactions);
        setTableSpkHistory(nextTableSpkHistory);
        setChartTransactions(nextChartTransactions);
        setChartSpkHistory(nextChartSpkHistory);

        const failureMessages = [
          itemsResult.status === "rejected" ? "master item" : null,
          stocksResult.status === "rejected" ? "stok" : null,
          tableTransactionsResult.status === "rejected" ? "transaksi" : null,
          tableSpkResult.status === "rejected" ? "riwayat SPK" : null,
          chartTransactionsResult.status === "rejected" ? "grafik transaksi" : null,
          chartSpkResult.status === "rejected" ? "grafik SPK" : null,
        ].filter((value): value is string => Boolean(value));

        if (failureMessages.length > 0) {
          setError(`Sebagian data laporan gagal dimuat: ${failureMessages.join(", ")}.`);
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat laporan."));
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
  }, [periodMode, selectedYear]);

  const categoryOptions = useMemo(() => {
    const categories = new Set<string>();

    itemMasterRows.forEach((row) => {
      const category = String(row.category?.name ?? "").trim();
      if (category) categories.add(category);
    });

    return [
      { value: "all", label: "Semua Jenis" },
      ...Array.from(categories)
        .sort((left, right) => left.localeCompare(right, "id-ID"))
        .map((value) => ({ value, label: value })),
    ];
  }, [stockRows]);

  const tableRows = useMemo<ReportTableRow[]>(() => {
    const itemMap = new Map<
      number,
      {
        itemId: number;
        itemName: string;
        categoryName: string;
        unit: string;
        spkQty: number;
        incomingQty: number;
        outgoingQty: number;
      }
    >();

    itemMasterRows.forEach((row) => {
      itemMap.set(row.id, {
        itemId: row.id,
        itemName: row.name,
        categoryName: row.category?.name ?? "-",
        unit: row.unit_base || row.item_unit_base?.name || "",
        spkQty: 0,
        incomingQty: 0,
        outgoingQty: 0,
      });
    });

    stockRows.forEach((row) => {
      const itemId = Number(row.item_id ?? 0);
      if (!itemId) return;

      const fallbackMaster = itemMap.get(itemId);
      if (!fallbackMaster) {
        itemMap.set(itemId, {
          itemId,
          itemName: String(row.item_name ?? `Item #${itemId}`),
          categoryName: String(row.category_name ?? "-"),
          unit: String(row.unit_base ?? ""),
          spkQty: 0,
          incomingQty: 0,
          outgoingQty: 0,
        });
        return;
      }

      fallbackMaster.itemName = String(row.item_name ?? fallbackMaster.itemName);
      fallbackMaster.categoryName = String(row.category_name ?? fallbackMaster.categoryName);
      fallbackMaster.unit = String(row.unit_base ?? fallbackMaster.unit);
    });

    tableTransactions.forEach((row) => {
      const itemId = Number(row.item_id ?? 0);
      if (!itemId) return;

      const current = itemMap.get(itemId) ?? {
        itemId,
        itemName: String(row.item_name ?? `Item #${itemId}`),
        categoryName: "-",
        unit: "",
        spkQty: 0,
        incomingQty: 0,
        outgoingQty: 0,
      };

      current.itemName = current.itemName || String(row.item_name ?? `Item #${itemId}`);
      const typeName = normalizeTransactionType(row.type_name);
      const qty = Number(row.qty ?? 0) || 0;

      if (typeName === "masuk") {
        current.incomingQty += qty;
      }

      if (typeName === "keluar") {
        current.outgoingQty += qty;
      }

      itemMap.set(itemId, current);
    });

    tableSpkHistory.forEach((row) => {
      const projections = row.compatibility_projection?.rows ?? [];
      projections.forEach((projection) => {
        const recommendations = projection.spk_recommendations ?? [];
        recommendations.forEach((recommendation) => {
          const itemId = Number(recommendation.item_id ?? 0);
          if (!itemId) return;

          const current = itemMap.get(itemId) ?? {
            itemId,
            itemName: `Item #${itemId}`,
            categoryName: "-",
            unit: "",
            spkQty: 0,
            incomingQty: 0,
            outgoingQty: 0,
          };

          current.spkQty += Number(recommendation.qty ?? 0) || 0;
          itemMap.set(itemId, current);
        });
      });
    });

    return [...itemMap.values()]
      .map((row) => ({
        ...row,
        spkMinusIncoming: row.spkQty - row.incomingQty,
        incomingMinusOutgoing: row.incomingQty - row.outgoingQty,
      }))
      .sort((left, right) =>
        left.categoryName.localeCompare(right.categoryName, "id-ID") ||
        left.itemName.localeCompare(right.itemName, "id-ID"),
      );
  }, [itemMasterRows, stockRows, tableTransactions, tableSpkHistory]);

  const filteredRows = useMemo(() => {
    const query = searchTerm.trim().toLowerCase();

    return tableRows.filter((row) => {
      const matchesSearch =
        query.length === 0 ||
        row.itemName.toLowerCase().includes(query) ||
        row.categoryName.toLowerCase().includes(query) ||
        `br-${String(row.itemId).padStart(4, "0")}`.includes(query);
      const matchesCategory =
        categoryFilter === "all" || row.categoryName === categoryFilter;

      return matchesSearch && matchesCategory;
    });
  }, [tableRows, searchTerm, categoryFilter]);

  const pageSize = 10;
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
  const safeCurrentPage = Math.min(currentPage, totalPages);
  const pageStartIndex = (safeCurrentPage - 1) * pageSize;
  const paginatedRows = useMemo(
    () => filteredRows.slice(pageStartIndex, pageStartIndex + pageSize),
    [filteredRows, pageStartIndex],
  );

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, categoryFilter, periodMode, selectedYear]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  const itemOptions = useMemo<ItemSelectOption[]>(() => {
    return tableRows.map((row) => ({
      value: String(row.itemId),
      label: row.itemName,
    }));
  }, [tableRows]);

  useEffect(() => {
    if (itemOptions.length === 0) {
      if (selectedItemId !== "") {
        setSelectedItemId("");
      }
      return;
    }

    const exists = itemOptions.some((option) => option.value === selectedItemId);
    if (!exists) {
      setSelectedItemId(itemOptions[0].value);
    }
  }, [itemOptions, selectedItemId]);

  const chartRows = useMemo(() => {
    const chartPeriod = periodMode === "MONTHLY" ? getLastFourMonthsPeriod() : getYearPeriod(selectedYear);
    const monthKeys = buildMonthKeys(chartPeriod.period_start, chartPeriod.period_end);
    const monthLookup = new Map(monthKeys.map((month) => [month.monthKey, { ...month, spk: 0, incoming: 0, outgoing: 0 }]));
    const selectedId = Number(selectedItemId || 0);

    chartTransactions.forEach((row) => {
      const itemId = Number(row.item_id ?? 0);
      if (!itemId || itemId !== selectedId) return;

      const monthKey = String(row.transaction_date ?? "").slice(0, 7);
      const bucket = monthLookup.get(monthKey);
      if (!bucket) return;

      const qty = Number(row.qty ?? 0) || 0;
      const typeName = normalizeTransactionType(row.type_name);

      if (typeName === "masuk") {
        bucket.incoming += qty;
      }

      if (typeName === "keluar") {
        bucket.outgoing += qty;
      }
    });

    chartSpkHistory.forEach((row) => {
      const monthKey = String(row.calculation_date ?? "").slice(0, 7);
      const bucket = monthLookup.get(monthKey);
      if (!bucket) return;

      const projections = row.compatibility_projection?.rows ?? [];
      projections.forEach((projection) => {
        const recommendations = projection.spk_recommendations ?? [];
        recommendations.forEach((recommendation) => {
          const itemId = Number(recommendation.item_id ?? 0);
          if (!itemId || itemId !== selectedId) return;

          bucket.spk += Number(recommendation.qty ?? 0) || 0;
        });
      });
    });

    return [...monthLookup.values()];
  }, [chartTransactions, chartSpkHistory, periodMode, selectedYear, selectedItemId]);

  const selectedItemLabel =
    itemOptions.find((option) => option.value === selectedItemId)?.label ?? "Pilih item";

  const currentMonthPeriod = getCurrentMonthPeriod();
  const periodLabel =
    periodMode === "MONTHLY"
      ? `Bulan berjalan ${formatDateRangeLabel(currentMonthPeriod.period_start, currentMonthPeriod.period_end)}`
      : `Tahun ${selectedYear}`;

  const totalLabel = `${filteredRows.length === 0 ? 0 : pageStartIndex + 1}-${Math.min(filteredRows.length, pageStartIndex + pageSize)} dari ${filteredRows.length} item`;
  const chartTitle =
    periodMode === "MONTHLY"
      ? "Laporan Evaluasi 4 Bulan Terakhir"
      : `Laporan Evaluasi 12 Bulan Tahun ${selectedYear}`;
  const chartSubtitle =
    periodMode === "MONTHLY"
      ? "Menampilkan tren SPK, barang masuk, dan barang keluar untuk item terpilih."
      : "Menampilkan tren 12 bulan untuk item yang dipilih.";

  const exportDisabled = filteredRows.length === 0;

  function handleExport() {
    if (exportDisabled) return;

    const rows = filteredRows.map((row) => ({
      "Nama Bahan": row.itemName,
      "Jenis Bahan": row.categoryName,
      SPK: formatQuantity(row.spkQty, row.unit),
      "Bahan Masuk": formatQuantity(row.incomingQty, row.unit),
      "Bahan Keluar": formatQuantity(row.outgoingQty, row.unit),
      "SPK-Bahan Masuk": formatQuantity(row.spkMinusIncoming, row.unit),
      "Bahan Masuk-Keluar": formatQuantity(row.incomingMinusOutgoing, row.unit),
    }));

    const filename = `laporan-${periodMode === "MONTHLY" ? "bulanan" : "tahunan"}-${periodMode === "MONTHLY" ? formatMonthKey(new Date()) : selectedYear}.csv`;
    downloadCsv(rows, filename);
  }

  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Laporan"
        subtitle="Melihat laporan evaluasi hasil perbandingan riwayat SPK, pemasukan bahan, dan penggunaan bahan"
      />

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <SurfaceCard className="overflow-hidden">
        <div className="flex flex-wrap items-center gap-3 border-b border-[#D7E0EE] bg-[#F8FAFC] px-5 py-4">
          <div className="flex flex-1 flex-wrap items-center gap-3">
            <div className="w-full lg:w-[240px]">
              <FilterSearch
                placeholder="Cari Bahan"
                value={searchTerm}
                onChange={setSearchTerm}
                readOnly={false}
              />
            </div>
            <div className="min-w-[170px]">
              <ThemedSelect
                value={categoryFilter}
                onChange={setCategoryFilter}
                options={categoryOptions}
                placeholder="Semua Jenis"
              />
            </div>
            <div className="min-w-[170px]">
              <ThemedSelect
                value={periodMode}
                onChange={(value) => setPeriodMode(value === "YEARLY" ? "YEARLY" : "MONTHLY")}
                options={[
                  { value: "MONTHLY", label: "Evaluasi Bulanan" },
                  { value: "YEARLY", label: "Evaluasi Tahunan" },
                ]}
                placeholder="Evaluasi Bulanan"
              />
            </div>
            {periodMode === "YEARLY" ? (
              <div className="min-w-[140px]">
                <ThemedSelect
                  value={String(selectedYear)}
                  onChange={(value) => setSelectedYear(Number(value) || new Date().getFullYear())}
                  options={buildYearOptions()}
                  placeholder="Pilih Tahun"
                />
              </div>
            ) : null}
          </div>

          <div className="ml-auto">
            <ExportButton onClick={handleExport}>Export Riwayat</ExportButton>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <th className="px-6 py-3">Nama Bahan</th>
                <th className="px-6 py-3">Jenis Bahan</th>
                <th className="px-6 py-3">SPK</th>
                <th className="px-6 py-3">Bahan Masuk</th>
                <th className="px-6 py-3">Bahan Keluar</th>
                <th className="px-6 py-3">SPK-Bahan Masuk</th>
                <th className="px-6 py-3">Bahan Masuk-Keluar</th>
              </tr>
            </thead>
            <tbody className="text-sm text-gray-700">
              {paginatedRows.map((row) => (
                <tr
                  key={row.itemId}
                  className="border-t border-gray-200 transition hover:bg-gray-50"
                >
                  <td className="px-6 py-4 font-semibold text-gray-900">{row.itemName}</td>
                  <td className="px-6 py-4 text-gray-600">{row.categoryName}</td>
                  <td className="px-6 py-4">{formatQuantity(row.spkQty, row.unit)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.incomingQty, row.unit)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.outgoingQty, row.unit)}</td>
                  <td className={diffToneClass(row.spkMinusIncoming)}>
                    {formatSignedQuantity(row.spkMinusIncoming, row.unit)}
                  </td>
                  <td className={diffToneClass(row.incomingMinusOutgoing)}>
                    {formatSignedQuantity(row.incomingMinusOutgoing, row.unit)}
                  </td>
                </tr>
              ))}

              {!loading && filteredRows.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={7}>
                    Belum ada data laporan pada periode ini.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <Pagination
          currentPage={safeCurrentPage}
          onPageChange={setCurrentPage}
          totalLabel={totalLabel}
          totalPages={totalPages}
        />
      </SurfaceCard>

      <SurfaceCard className="overflow-hidden px-6 py-5">
        <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div>
            <h3 className="text-base font-semibold text-[#16213E]">{chartTitle}</h3>
            <p className="mt-1 text-sm text-[#94A3B8]">{chartSubtitle}</p>
          </div>

          <div className="min-w-[220px]">
            <ThemedSelect
              value={selectedItemId}
              onChange={setSelectedItemId}
              options={itemOptions}
              placeholder="Pilih item"
              disabled={itemOptions.length === 0}
            />
          </div>
        </div>

        <div className="mb-4 grid gap-3 rounded-2xl bg-[#EEF4FF] px-5 py-4 sm:grid-cols-3">
          <MetricTile label="Item Dipilih" value={selectedItemLabel} />
          <MetricTile label="Periode Data" value={periodLabel} />
          <MetricTile
            label="Sumber Data"
            value={periodMode === "MONTHLY" ? "Bulanan + riwayat SPK" : "Tahunan + riwayat SPK"}
          />
        </div>

        <div className="rounded-[14px] bg-white p-2">
          {chartRows.some((row) => row.spk !== 0 || row.incoming !== 0 || row.outgoing !== 0) ? (
            <div className="h-[400px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={chartRows} margin={{ top: 20, right: 24, bottom: 12, left: 8 }}>
                  <CartesianGrid stroke="#E2E8F0" strokeDasharray="4 4" />
                  <XAxis
                    dataKey="label"
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: "#64748B", fontSize: 12, fontWeight: 600 }}
                  />
                  <YAxis
                    axisLine={false}
                    tickLine={false}
                    tick={{ fill: "#94A3B8", fontSize: 12 }}
                    width={48}
                  />
                  <Tooltip
                    formatter={(value, name) => [
                      formatQuantity(Number(value ?? 0)),
                      normalizeChartLegend(String(name)),
                    ]}
                    labelFormatter={(label) => `${label}`}
                    contentStyle={{
                      borderRadius: "14px",
                      border: "1px solid #D7E0EE",
                      boxShadow: "0 12px 32px rgba(15,23,42,0.12)",
                    }}
                  />
                  <Legend
                    verticalAlign="bottom"
                    iconType="circle"
                    formatter={(value) => <span style={{ color: "#64748B" }}>{normalizeChartLegend(value)}</span>}
                  />
                  <Line
                    type="monotone"
                    dataKey="spk"
                    name="SPK"
                    stroke="#EAB308"
                    strokeWidth={3}
                    dot={{ r: 4, strokeWidth: 2, fill: "#FFFFFF" }}
                    activeDot={{ r: 6 }}
                  >
                    <LabelList dataKey="spk" position="top" fill="#EAB308" fontSize={12} />
                  </Line>
                  <Line
                    type="monotone"
                    dataKey="incoming"
                    name="Bahan Masuk"
                    stroke="#2563EB"
                    strokeWidth={3}
                    dot={{ r: 4, strokeWidth: 2, fill: "#FFFFFF" }}
                    activeDot={{ r: 6 }}
                  >
                    <LabelList dataKey="incoming" position="top" fill="#2563EB" fontSize={12} />
                  </Line>
                  <Line
                    type="monotone"
                    dataKey="outgoing"
                    name="Bahan Keluar"
                    stroke="#EF4444"
                    strokeWidth={3}
                    dot={{ r: 4, strokeWidth: 2, fill: "#FFFFFF" }}
                    activeDot={{ r: 6 }}
                  >
                    <LabelList dataKey="outgoing" position="top" fill="#EF4444" fontSize={12} />
                  </Line>
                </LineChart>
              </ResponsiveContainer>
            </div>
          ) : (
            <div className="flex h-[360px] items-center justify-center text-sm text-gray-400">
              Belum ada data grafik untuk item yang dipilih.
            </div>
          )}
        </div>
      </SurfaceCard>
    </div>
  );
}

function MetricTile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl bg-white/70 px-4 py-3">
      <div className="text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">{label}</div>
      <div className="mt-1 text-sm font-semibold text-[#16213E]">{value}</div>
    </div>
  );
}

function diffToneClass(value: number) {
  if (value > 0) return "px-6 py-4 text-[#10B981]";
  if (value < 0) return "px-6 py-4 text-[#EF4444]";
  return "px-6 py-4 text-gray-600";
}

function formatSignedQuantity(value: number, unit?: string) {
  const prefix = value > 0 ? "+" : "";
  return `${prefix}${formatQuantity(value, unit)}`;
}

function normalizeTransactionType(value?: string | null) {
  const normalized = String(value ?? "").trim().toLowerCase();
  if (normalized.includes("masuk") || normalized === "in") return "masuk";
  if (normalized.includes("keluar") || normalized === "out") return "keluar";
  return normalized;
}

function getYearPeriod(year: number) {
  return {
    period_start: `${year}-01-01`,
    period_end: `${year}-12-31`,
  };
}

function getLastFourMonthsPeriod() {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth() - 3, 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);

  return {
    period_start: toIsoDate(start),
    period_end: toIsoDate(end),
  };
}

function buildMonthKeys(periodStart: string, periodEnd: string) {
  const start = new Date(`${periodStart}T00:00:00`);
  const end = new Date(`${periodEnd}T00:00:00`);
  const cursor = new Date(start.getFullYear(), start.getMonth(), 1);
  const rows: ChartPoint[] = [];

  while (cursor <= end) {
    const year = cursor.getFullYear();
    const month = String(cursor.getMonth() + 1).padStart(2, "0");
    const monthKey = `${year}-${month}`;
    const label = new Intl.DateTimeFormat("id-ID", {
      month: "short",
      year: periodStart.slice(0, 4) !== periodEnd.slice(0, 4) ? "numeric" : undefined,
      timeZone: "Asia/Jakarta",
    })
      .format(new Date(`${monthKey}-01T00:00:00`))
      .toUpperCase();

    rows.push({
      monthKey,
      label,
      spk: 0,
      incoming: 0,
      outgoing: 0,
    });

    cursor.setMonth(cursor.getMonth() + 1);
  }

  return rows;
}

function formatMonthKey(date: Date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  return `${year}-${month}`;
}

function buildYearOptions(): SelectOption[] {
  const currentYear = new Date().getFullYear();
  return [currentYear, currentYear - 1, currentYear - 2].map((year) => ({
    value: String(year),
    label: String(year),
  }));
}

function normalizeChartLegend(value: string) {
  if (value === "spk") return "SPK";
  if (value === "incoming") return "Bahan Masuk";
  if (value === "outgoing") return "Bahan Keluar";
  return value;
}

function formatDateRangeLabel(start: string, end: string) {
  const startDate = new Date(`${start}T00:00:00`);
  const endDate = new Date(`${end}T00:00:00`);

  if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
    return "-";
  }

  return `${formatDate(startDate.toISOString())} - ${formatDate(endDate.toISOString())}`;
}

function downloadCsv(rows: Record<string, string>[], filename: string) {
  const headers = Object.keys(rows[0] ?? {});
  const csv = [
    headers.join(","),
    ...rows.map((row) =>
      headers
        .map((header) => {
          const value = row[header] ?? "";
          return `"${String(value).replaceAll('"', '""')}"`;
        })
        .join(","),
    ),
  ].join("\n");

  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}
