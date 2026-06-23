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
import {
  formatDate,
  formatQuantity,
  getErrorMessage,
  toIsoDate,
} from "@/lib/admin-utils";
import {
  buildSpreadsheetDocument,
  downloadSpreadsheetHtml,
  escapeSpreadsheetHtml,
  formatSpreadsheetNumber,
} from "@/lib/spreadsheet-export";
import { buildExportFilename } from "@/lib/export-filename";
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

type ChartRangeMode = "FOUR_MONTHS" | "YEAR";

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
  openingStock: number;
  spkQty: number;
  incomingQty: number;
  outgoingQty: number;
  incomingMinusOutgoing: number;
  closingStock: number;
  accuracy: number;
};

type ChartPoint = {
  monthKey: string;
  label: string;
  openingStock: number;
  spk: number;
  incoming: number;
  outgoing: number;
  closingStock: number;
  unit: string;
};

type ItemSelectOption = {
  value: string;
  label: string;
};

type SelectOption = {
  value: string;
  label: string;
};

const CURRENT_MONTH = new Date().getMonth() + 1;
const CHART_RANGE_OPTIONS: SelectOption[] = [
  { value: "FOUR_MONTHS", label: "4 Bulan Terakhir" },
  { value: "YEAR", label: "1 Tahun Terakhir" },
];

export default function LaporanEvaluationPage() {
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
  const [selectedMonth, setSelectedMonth] = useState(CURRENT_MONTH);
  const [chartRangeMode, setChartRangeMode] = useState<ChartRangeMode>("FOUR_MONTHS");
  const [searchTerm, setSearchTerm] = useState("");
  const [categoryFilter, setCategoryFilter] = useState("all");
  const [selectedItemId, setSelectedItemId] = useState("");
  const [stockRows, setStockRows] = useState<StockReportRow[]>([]);
  const [tableTransactions, setTableTransactions] = useState<TransactionReportRow[]>([]);
  const [tableSpkHistory, setTableSpkHistory] = useState<SpkHistoryReportRow[]>([]);
  const [chartTransactions, setChartTransactions] = useState<TransactionReportRow[]>([]);
  const [chartSpkHistory, setChartSpkHistory] = useState<SpkHistoryReportRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const tablePeriod = useMemo(() => getMonthPeriod(selectedYear, selectedMonth), [selectedYear, selectedMonth]);
  const chartPeriod = useMemo(
    () => (chartRangeMode === "FOUR_MONTHS" ? getLastFourMonthsPeriod(selectedYear, selectedMonth) : getYearPeriod(selectedYear)),
    [chartRangeMode, selectedMonth, selectedYear],
  );

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const [
          stocksResult,
          tableTransactionsResult,
          tableSpkResult,
          chartTransactionsResult,
          chartSpkResult,
        ] =
          await Promise.allSettled([
            sdk.reports.getStocks(ALL_STOCK_REPORT_PERIOD),
            sdk.reports.getTransactions(tablePeriod),
            sdk.reports.getSpkHistory(tablePeriod),
            chartRangeMode === "FOUR_MONTHS"
              ? sdk.reports.getTransactions(chartPeriod)
              : Promise.resolve(null),
            chartRangeMode === "FOUR_MONTHS"
              ? sdk.reports.getSpkHistory(chartPeriod)
              : Promise.resolve(null),
          ]);

        if (cancelled) return;

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
          chartRangeMode === "FOUR_MONTHS"
            ? chartTransactionsResult.status === "fulfilled" && chartTransactionsResult.value
              ? ((chartTransactionsResult.value.data.rows as TransactionReportRow[]) ?? [])
              : []
            : nextTableTransactions;
        const nextChartSpkHistory =
          chartRangeMode === "FOUR_MONTHS"
            ? chartSpkResult.status === "fulfilled" && chartSpkResult.value
              ? ((chartSpkResult.value.data.rows as SpkHistoryReportRow[]) ?? [])
              : []
            : nextTableSpkHistory;

        setStockRows(nextStockRows);
        setTableTransactions(nextTableTransactions);
        setTableSpkHistory(nextTableSpkHistory);
        setChartTransactions(nextChartTransactions);
        setChartSpkHistory(nextChartSpkHistory);

        const failureMessages = [
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
  }, [chartPeriod, chartRangeMode, selectedMonth, selectedYear, tablePeriod]);

  const categoryOptions = useMemo(() => {
    return [
      { value: "all", label: "Semua Jenis" },
      ...Array.from(
        new Set(
          stockRows
            .map((row) => String(row.category_name ?? "").trim())
            .filter((value) => value !== ""),
        ),
      )
        .sort((left, right) => left.localeCompare(right, "id-ID"))
        .map((value) => ({ value, label: value })),
    ];
  }, [stockRows]);

  const monthOptions = useMemo(() => buildMonthOptions(selectedYear), [selectedYear]);
  const yearOptions = useMemo(() => buildYearOptions(), []);

  const stockQtyByItemId = useMemo(() => {
    const map = new Map<number, number>();

    stockRows.forEach((row, index) => {
      const itemId = Number(row.item_id ?? 0);
      if (!itemId) return;

      const qty = Number(row.qty ?? 0) || 0;
      map.set(itemId, qty);
    });

    return map;
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

    stockRows.forEach((row) => {
      const itemId = Number(row.item_id ?? 0);
      if (!itemId) return;

      itemMap.set(itemId, {
        itemId,
        itemName: String(row.item_name ?? `Item #${itemId}`),
        categoryName: String(row.category_name ?? "-"),
        unit: String(row.unit_base ?? ""),
        spkQty: 0,
        incomingQty: 0,
        outgoingQty: 0,
      });
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
        openingStock: Math.max((stockQtyByItemId.get(row.itemId) ?? 0) - row.incomingQty + row.outgoingQty, 0),
        incomingMinusOutgoing: row.incomingQty - row.outgoingQty,
        closingStock: stockQtyByItemId.get(row.itemId) ?? 0,
        accuracy: calculateEvaluationAccuracy(row.spkQty, row.outgoingQty),
      }))
      .sort((left, right) =>
        left.categoryName.localeCompare(right.categoryName, "id-ID") ||
        left.itemName.localeCompare(right.itemName, "id-ID"),
      );
  }, [stockQtyByItemId, stockRows, tableTransactions, tableSpkHistory]);

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
  }, [searchTerm, categoryFilter, chartRangeMode, selectedMonth, selectedYear]);

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

  const selectedItemRow = useMemo(() => {
    return tableRows.find((row) => String(row.itemId) === selectedItemId) ?? null;
  }, [selectedItemId, tableRows]);

  const selectedItemUnit = selectedItemRow?.unit ?? "";

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
    const monthKeys = buildMonthKeys(chartPeriod.period_start, chartPeriod.period_end);
    const monthLookup = new Map(
      monthKeys.map((month) => [
        month.monthKey,
        { ...month, openingStock: 0, spk: 0, incoming: 0, outgoing: 0, closingStock: 0, unit: "" },
      ]),
    );
    const selectedId = Number(selectedItemId || 0);
    const selectedCurrentStock = stockQtyByItemId.get(selectedId) ?? 0;

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

    const orderedRows = [...monthLookup.values()];
    const totalIncoming = orderedRows.reduce((sum, row) => sum + row.incoming, 0);
    const totalOutgoing = orderedRows.reduce((sum, row) => sum + row.outgoing, 0);
    let runningStock = Math.max(selectedCurrentStock - totalIncoming + totalOutgoing, 0);

    return orderedRows.map((row) => {
      const openingStock = runningStock;
      const closingStock = Math.max(openingStock + row.incoming - row.outgoing, 0);
      runningStock = closingStock;

      return {
        ...row,
        openingStock,
        closingStock,
        unit: selectedItemUnit,
      };
    });
  }, [chartTransactions, chartSpkHistory, chartPeriod, selectedItemId, selectedItemUnit, stockQtyByItemId]);

  const selectedItemLabel =
    itemOptions.find((option) => option.value === selectedItemId)?.label ?? "Pilih item";

  const periodLabel = formatReportPeriodLabel(chartPeriod.period_start, chartPeriod.period_end);

  const totalLabel = `${filteredRows.length === 0 ? 0 : pageStartIndex + 1}-${Math.min(filteredRows.length, pageStartIndex + pageSize)} dari ${filteredRows.length} item`;
  const chartTitle =
    chartRangeMode === "FOUR_MONTHS"
      ? "Grafik Evaluasi 4 Bulan Terakhir"
      : `Grafik Evaluasi 12 Bulan Tahun ${selectedYear}`;
  const chartSubtitle =
    chartRangeMode === "FOUR_MONTHS"
      ? "Menampilkan tren stok awal, SPK, barang masuk, barang keluar, dan stok akhir untuk item terpilih."
      : "Menampilkan tren 12 bulan untuk item yang dipilih.";

  const exportDisabled = filteredRows.length === 0;

  function handleExport() {
    if (exportDisabled) return;

    const filename = buildExportFilename(
      `laporan-evaluasi-spk-${chartRangeMode === "FOUR_MONTHS" ? "4-bulan" : "12-bulan"}`,
    );
    const printedAt = formatDate(new Date().toISOString());
    const reportRows = filteredRows.map((row, index) => {
      const accuracy = calculateEvaluationAccuracy(row.spkQty, row.outgoingQty);

      return {
        no: index + 1,
        itemName: row.itemName,
        categoryName: row.categoryName,
        openingStock: row.openingStock,
        recommendationQty: row.spkQty,
        realizationQty: row.incomingQty,
        usageQty: row.outgoingQty,
        diffRealizationVsUsage: row.outgoingQty - row.incomingQty,
        closingStock: row.closingStock,
        accuracy,
        unit: row.unit,
      };
    });

    const totalRecommended = reportRows.reduce((sum, row) => sum + row.recommendationQty, 0);
    const totalRealization = reportRows.reduce((sum, row) => sum + row.realizationQty, 0);
    const totalUsage = reportRows.reduce((sum, row) => sum + row.usageQty, 0);
    const totalOpeningStock = reportRows.reduce((sum, row) => sum + row.openingStock, 0);
    const totalClosingStock = reportRows.reduce((sum, row) => sum + row.closingStock, 0);
    const averageAccuracy =
      reportRows.length === 0
        ? 0
        : reportRows.reduce((sum, row) => sum + row.accuracy, 0) / reportRows.length;

    const analysisRows = Array.from(new Set(reportRows.map((row) => row.categoryName || "-")))
      .sort((left, right) => left.localeCompare(right, "id-ID"))
      .map((categoryName) => {
        const rows = reportRows.filter((row) => row.categoryName === categoryName);
        const categoryRecommendation = rows.reduce((sum, row) => sum + row.recommendationQty, 0);
        const categoryRealization = rows.reduce((sum, row) => sum + row.realizationQty, 0);
        const categoryUsage = rows.reduce((sum, row) => sum + row.usageQty, 0);
        const categoryAccuracy = rows.length === 0 ? 0 : rows.reduce((sum, row) => sum + row.accuracy, 0) / rows.length;

        return {
          categoryName,
          categoryRecommendation,
          categoryRealization,
          categoryUsage,
          categoryAccuracy,
        };
      });

    const summaryRows = [
      { label: "Mode Grafik", value: chartRangeMode === "FOUR_MONTHS" ? "4 Bulan Terakhir" : "12 Bulan Terakhir" },
      { label: "Periode", value: periodLabel },
      { label: "Tanggal Cetak", value: printedAt },
      { label: "Total Bahan Dievaluasi", value: formatSpreadsheetNumber(reportRows.length, 0) },
      { label: "Total Stok Awal", value: formatQuantity(totalOpeningStock) },
      { label: "Total SPK", value: formatQuantity(totalRecommended) },
      { label: "Total Barang Masuk", value: formatQuantity(totalRealization) },
      { label: "Total Barang Keluar", value: formatQuantity(totalUsage) },
      { label: "Total Stok Akhir", value: formatQuantity(totalClosingStock) },
      { label: "Rata-rata Tingkat Akurasi", value: `${formatSpreadsheetNumber(averageAccuracy, 2)}%` },
    ];

    const filterRows = [
      { label: "Bulan", value: formatMonthLabel(selectedMonth, selectedYear) },
      { label: "Tahun", value: String(selectedYear) },
      { label: "Periode Grafik", value: periodLabel },
      { label: "Kategori Bahan", value: categoryFilter === "all" ? "Semua Jenis" : categoryFilter },
      { label: "Nama Bahan", value: searchTerm.trim() || "Semua Bahan" },
      { label: "Item Grafik", value: selectedItemLabel },
    ];

    const htmlRows = reportRows
      .map(
        (row) => `
          <tr>
            <td class="rank">${row.no}</td>
            <td class="text-strong">${escapeSpreadsheetHtml(row.itemName)}</td>
            <td>${escapeSpreadsheetHtml(row.categoryName)}</td>
            <td class="number">${escapeSpreadsheetHtml(formatQuantity(row.openingStock, row.unit))}</td>
            <td class="number">${escapeSpreadsheetHtml(formatQuantity(row.recommendationQty, row.unit))}</td>
            <td class="number">${escapeSpreadsheetHtml(formatQuantity(row.realizationQty, row.unit))}</td>
            <td class="number">${escapeSpreadsheetHtml(formatQuantity(row.usageQty, row.unit))}</td>
            <td class="number ${row.diffRealizationVsUsage > 0 ? "safe" : row.diffRealizationVsUsage < 0 ? "danger" : ""}">${escapeSpreadsheetHtml(formatSignedQuantity(row.diffRealizationVsUsage, row.unit))}</td>
            <td class="number">${escapeSpreadsheetHtml(formatQuantity(row.closingStock, row.unit))}</td>
            <td class="number ${row.accuracy >= 95 ? "safe" : row.accuracy >= 85 ? "warning" : "danger"}">${escapeSpreadsheetHtml(`${formatSpreadsheetNumber(row.accuracy, 2)}%`)}</td>
          </tr>
        `,
      )
      .join("");

    const analysisHtmlRows = analysisRows
      .map(
        (row) => `
          <tr>
            <td class="text-strong">${escapeSpreadsheetHtml(row.categoryName)}</td>
            <td class="number">${escapeSpreadsheetHtml(formatQuantity(row.categoryRecommendation))}</td>
            <td class="number">${escapeSpreadsheetHtml(formatQuantity(row.categoryRealization))}</td>
            <td class="number">${escapeSpreadsheetHtml(formatQuantity(row.categoryUsage))}</td>
            <td class="number ${row.categoryAccuracy >= 95 ? "safe" : row.categoryAccuracy >= 85 ? "warning" : "danger"}">${escapeSpreadsheetHtml(`${formatSpreadsheetNumber(row.categoryAccuracy, 2)}%`)}</td>
          </tr>
        `,
      )
      .join("");

    const html = buildSpreadsheetDocument({
      title: "LAPORAN EVALUASI SPK VS PEMBELIAN VS PENGGUNAAN BAHAN INSTALASI GIZI RSD BALUNG",
      subtitle: "Dokumen evaluasi SPK berdasarkan perbandingan rekomendasi sistem, realisasi pembelian, dan penggunaan aktual bahan.",
      body: `
        <table class="section-gap">
          <tr class="no-border">
            <td class="title" colspan="10">LAPORAN EVALUASI SPK VS PEMBELIAN VS PENGGUNAAN BAHAN INSTALASI GIZI RSD BALUNG</td>
          </tr>
          <tr class="no-border">
            <td class="subtitle" colspan="10">Periode : ${escapeSpreadsheetHtml(periodLabel)} | Tanggal Cetak : ${escapeSpreadsheetHtml(printedAt)}</td>
          </tr>
          <tr class="no-border">
            <td class="subtitle" colspan="10">Tujuan: Laporan ini digunakan untuk mengevaluasi tingkat akurasi rekomendasi SPK dengan membandingkan rekomendasi pembelian, realisasi pembelian bahan, dan penggunaan aktual bahan makanan selama periode tertentu.</td>
          </tr>
        </table>

        <table class="section-gap">
          <tr>
            <td class="section" colspan="2">FILTER LAPORAN</td>
          </tr>
          ${filterRows
            .map(
              (row) => `<tr class="summary">
                <td class="summary-label">${escapeSpreadsheetHtml(row.label)}</td>
                <td class="summary-value">${escapeSpreadsheetHtml(row.value)}</td>
              </tr>`,
            )
            .join("")}
        </table>

        <table class="section-gap">
          <tr>
            <td class="section" colspan="10">DATA EVALUASI</td>
          </tr>
          <tr class="head">
            <th>No</th>
            <th>Nama Bahan</th>
            <th>Kategori Bahan</th>
            <th>Stok Awal</th>
            <th>SPK</th>
            <th>Bahan Masuk</th>
            <th>Bahan Keluar</th>
            <th>Bahan Masuk-Keluar</th>
            <th>Stok Akhir</th>
            <th>Tingkat Akurasi (%)</th>
          </tr>
          ${htmlRows || `<tr><td class="muted" colspan="10">Belum ada data laporan pada periode ini.</td></tr>`}
        </table>

        <table class="section-gap">
          <tr>
            <td class="section" colspan="2">RINGKASAN EVALUASI</td>
          </tr>
          ${summaryRows
            .map(
              (row) => `<tr class="summary">
                <td class="summary-label">${escapeSpreadsheetHtml(row.label)}</td>
                <td class="summary-value">${escapeSpreadsheetHtml(row.value)}</td>
              </tr>`,
            )
            .join("")}
        </table>

        <table class="section-gap">
          <tr>
            <td class="section" colspan="5">ANALISIS EFISIENSI</td>
          </tr>
          <tr class="head">
            <th>Kategori</th>
            <th>Total Rekomendasi</th>
            <th>Total Pembelian</th>
            <th>Total Penggunaan</th>
            <th>Akurasi</th>
          </tr>
          ${analysisHtmlRows || `<tr><td class="muted" colspan="5">Belum ada data analisis pada periode ini.</td></tr>`}
        </table>

        <table class="section-gap">
          <tr>
            <td class="section">KETERANGAN</td>
          </tr>
          <tr>
            <td class="muted">Rekomendasi SPK diperoleh dari hasil generate sistem berdasarkan rumus yang berlaku.</td>
          </tr>
          <tr>
            <td class="muted">Realisasi Pembelian diperoleh dari transaksi barang masuk.</td>
          </tr>
          <tr>
            <td class="muted">Penggunaan Aktual diperoleh dari transaksi barang keluar.</td>
          </tr>
          <tr>
            <td class="muted">Tingkat Akurasi digunakan untuk mengukur kesesuaian antara hasil rekomendasi sistem dengan kebutuhan aktual di lapangan.</td>
          </tr>
          <tr>
            <td class="muted">Semakin kecil selisih antara rekomendasi, pembelian, dan penggunaan, maka semakin baik performa sistem dalam membantu perencanaan kebutuhan bahan makanan.</td>
          </tr>
        </table>
      `,
      extraStyles: `
        .title { font-size: 24px; text-transform: uppercase; }
        .subtitle { font-size: 13px; }
        .section { background: #DFF7E6; color: #166534; font-size: 14px; }
        .head th { text-align: center; font-size: 12px; }
        .summary-label { width: 270px; }
        .summary-value { font-weight: 700; }
      `,
    });

    downloadSpreadsheetHtml(filename, html);
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
                value={String(selectedMonth)}
                onChange={(value) => setSelectedMonth(Number(value) || CURRENT_MONTH)}
                options={monthOptions}
                placeholder="Pilih Bulan"
              />
            </div>
            <div className="min-w-[130px]">
              <ThemedSelect
                value={String(selectedYear)}
                onChange={(value) => setSelectedYear(Number(value) || new Date().getFullYear())}
                options={yearOptions}
                placeholder="Pilih Tahun"
              />
            </div>
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
            <th className="px-6 py-3">Kategori Bahan</th>
            <th className="px-6 py-3">Stok Awal</th>
            <th className="px-6 py-3">SPK</th>
            <th className="px-6 py-3">Bahan Masuk</th>
            <th className="px-6 py-3">Bahan Keluar</th>
            <th className="px-6 py-3">Bahan Masuk-Keluar</th>
            <th className="px-6 py-3">Stok Akhir</th>
            <th className="px-6 py-3">Tingkat Akurasi</th>
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
                  <td className="px-6 py-4">{formatQuantity(row.openingStock, row.unit)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.spkQty, row.unit)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.incomingQty, row.unit)}</td>
                  <td className="px-6 py-4">{formatQuantity(row.outgoingQty, row.unit)}</td>
                  <td className={diffToneClass(row.incomingMinusOutgoing)}>
                    {formatSignedQuantity(row.incomingMinusOutgoing, row.unit)}
                  </td>
                  <td className="px-6 py-4">{formatQuantity(row.closingStock, row.unit)}</td>
                  <td className="px-6 py-4 font-semibold text-gray-700">
                    {formatSpreadsheetNumber(row.accuracy, 2)}%
                  </td>
                </tr>
              ))}

              {!loading && filteredRows.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={9}>
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

          <div className="flex flex-wrap items-center gap-3">
            <div className="min-w-[190px]">
              <ThemedSelect
                value={chartRangeMode}
                onChange={(value) => setChartRangeMode(value === "YEAR" ? "YEAR" : "FOUR_MONTHS")}
                options={CHART_RANGE_OPTIONS}
                placeholder="4 Bulan Terakhir"
              />
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
        </div>

        <div className="mb-4 grid gap-3 rounded-2xl bg-[#EEF4FF] px-5 py-4 sm:grid-cols-3">
          <MetricTile label="Item Dipilih" value={selectedItemLabel} />
          <MetricTile label="Periode Data" value={periodLabel} />
          <MetricTile
            label="Sumber Data"
            value={chartRangeMode === "FOUR_MONTHS" ? "4 Bulan Terakhir + riwayat SPK" : "12 Bulan Terakhir + riwayat SPK"}
          />
        </div>

        <div className="rounded-[14px] bg-white p-2">
          {chartRows.some((row) => row.openingStock !== 0 || row.spk !== 0 || row.incoming !== 0 || row.outgoing !== 0 || row.closingStock !== 0) ? (
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
                  <Tooltip content={EvaluationTooltip as never} />
                  <Legend
                    verticalAlign="bottom"
                    iconType="circle"
                    formatter={(value) => <span style={{ color: "#64748B" }}>{normalizeChartLegend(value)}</span>}
                  />
                  <Line
                    type="monotone"
                    dataKey="openingStock"
                    name="Stok Awal"
                    stroke="#10B981"
                    strokeWidth={2.5}
                    strokeDasharray="8 6"
                    dot={{ r: 4, strokeWidth: 2, fill: "#FFFFFF" }}
                    activeDot={{ r: 6 }}
                  >
                    <LabelList dataKey="openingStock" position="top" fill="#10B981" fontSize={12} />
                  </Line>
                  <Line
                    type="monotone"
                    dataKey="spk"
                    name="SPK"
                    stroke="#EAB308"
                    strokeWidth={3}
                    strokeDasharray="3 3"
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
                    strokeDasharray="12 4"
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
                    strokeDasharray="2 4"
                    dot={{ r: 4, strokeWidth: 2, fill: "#FFFFFF" }}
                    activeDot={{ r: 6 }}
                  >
                    <LabelList dataKey="outgoing" position="top" fill="#EF4444" fontSize={12} />
                  </Line>
                  <Line
                    type="monotone"
                    dataKey="closingStock"
                    name="Stok Akhir"
                    stroke="#7C3AED"
                    strokeWidth={3}
                    dot={{ r: 4, strokeWidth: 2, fill: "#FFFFFF" }}
                    activeDot={{ r: 6 }}
                  >
                    <LabelList dataKey="closingStock" position="top" fill="#7C3AED" fontSize={12} />
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

function EvaluationTooltip({ active, payload, label }: any) {
  if (!active || !payload || payload.length === 0) return null;

  const row = payload[0]?.payload as ChartPoint | undefined;
  if (!row) return null;

  return (
    <div className="rounded-2xl border border-[#D7E0EE] bg-white px-4 py-3 shadow-[0_12px_32px_rgba(15,23,42,0.12)]">
      <div className="text-sm font-semibold text-[#16213E]">{label}</div>
      <div className="mt-2 space-y-1 text-sm text-[#475569]">
        <div>Stok awal: {formatQuantity(row.openingStock, row.unit)}</div>
        <div>Bahan masuk: {formatQuantity(row.incoming, row.unit)}</div>
        <div>Bahan keluar: {formatQuantity(row.outgoing, row.unit)}</div>
        <div>SPK: {formatQuantity(row.spk, row.unit)}</div>
        <div>Stok akhir: {formatQuantity(row.closingStock, row.unit)}</div>
      </div>
    </div>
  );
}

function calculateEvaluationAccuracy(recommendationQty: number, realizationQty: number) {
  const left = Math.abs(Number(recommendationQty) || 0);
  const right = Math.abs(Number(realizationQty) || 0);

  if (left === 0 && right === 0) {
    return 100;
  }

  const larger = Math.max(left, right);
  const smaller = Math.min(left, right);

  if (larger === 0) {
    return 0;
  }

  return (smaller / larger) * 100;
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

function getLastFourMonthsPeriod(year: number, month: number) {
  const start = new Date(year, month - 4, 1);
  const end = new Date(year, month, 0);

  return {
    period_start: toIsoDate(start),
    period_end: toIsoDate(end),
  };
}

function getMonthPeriod(year: number, month: number) {
  const start = new Date(year, month - 1, 1);
  const end = new Date(year, month, 0);

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
      openingStock: 0,
      spk: 0,
      incoming: 0,
      outgoing: 0,
      closingStock: 0,
      unit: "",
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

function buildMonthOptions(year: number): SelectOption[] {
  return Array.from({ length: 12 }, (_, index) => {
    const month = index + 1;
    const date = new Date(year, index, 1);
    const label = new Intl.DateTimeFormat("id-ID", {
      month: "long",
      year: "numeric",
      timeZone: "Asia/Jakarta",
    }).format(date);

    return {
      value: String(month),
      label,
    };
  });
}

function buildYearOptions(): SelectOption[] {
  const currentYear = new Date().getFullYear();
  return [currentYear, currentYear + 1].map((year) => ({
    value: String(year),
    label: String(year),
  }));
}

function formatMonthLabel(month: number, year = new Date().getFullYear()) {
  const date = new Date(year, month - 1, 1);
  return new Intl.DateTimeFormat("id-ID", {
    month: "long",
    timeZone: "Asia/Jakarta",
  }).format(date);
}

function formatReportPeriodLabel(start: string, end: string) {
  const startDate = new Date(`${start}T00:00:00`);
  const endDate = new Date(`${end}T00:00:00`);

  if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
    return "-";
  }

  const startLabel = new Intl.DateTimeFormat("id-ID", {
    month: "long",
    year: "numeric",
    timeZone: "Asia/Jakarta",
  }).format(startDate);
  const endLabel = new Intl.DateTimeFormat("id-ID", {
    month: "long",
    year: "numeric",
    timeZone: "Asia/Jakarta",
  }).format(endDate);

  return `${startLabel} - ${endLabel}`;
}

function normalizeChartLegend(value: string) {
  if (value === "spk") return "SPK";
  if (value === "incoming") return "Bahan Masuk";
  if (value === "outgoing") return "Bahan Keluar";
  if (value === "openingStock") return "Stok Awal";
  if (value === "closingStock") return "Stok Akhir";
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
