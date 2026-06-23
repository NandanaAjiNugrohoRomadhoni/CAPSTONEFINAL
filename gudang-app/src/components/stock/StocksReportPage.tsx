"use client";

import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, PackageX, Zap } from "lucide-react";
import sdk from "@/lib";
import { listAllItems } from "@/lib/items";
import {
  formatNumber,
  formatQuantity,
  getErrorMessage,
  getStockTone,
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
  StatusPill,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";

type StockReportRow = Awaited<ReturnType<typeof sdk.reports.getStocks>>["data"]["rows"][number];

type StockTableRow = {
  idLabel: string;
  itemId: number;
  itemName: string;
  categoryName: string;
  qty: number;
  qtyLabel: string;
  minimumQty: number;
  minimumLabel: string;
  tone: "safe" | "warning" | "critical" | "danger";
  label: string;
};

const statCards = [
  {
    key: "warning",
    title: "STOK MENIPIS",
    note: "Bahan di bawah minimum",
    accent: "border-[#F59E0B]",
    iconBg: "bg-[#FFF7CC]",
    iconColor: "text-[#92400E]",
    icon: Zap,
  },
  {
    key: "critical",
    title: "STOK KRITIS",
    note: "Bahan mendekati habis",
    accent: "border-[#FB7185]",
    iconBg: "bg-[#FFE4E6]",
    iconColor: "text-[#BE123C]",
    icon: AlertTriangle,
  },
  {
    key: "danger",
    title: "STOK HABIS",
    note: "Bahan habis",
    accent: "border-[#818CF8]",
    iconBg: "bg-[#E0E7FF]",
    iconColor: "text-[#3730A3]",
    icon: PackageX,
  },
] as const;

const ALL_STOCK_REPORT_PERIOD = {
  period_start: "2000-01-01",
  period_end: "2099-12-31",
} as const;

function firstString(row: StockReportRow, keys: string[], fallback = "-") {
  for (const key of keys) {
    const value = row[key];
    if (typeof value === "string" && value.trim()) return value;
    if (typeof value === "number" && Number.isFinite(value)) return String(value);
  }
  return fallback;
}

function firstNumber(row: StockReportRow, keys: string[], fallback = 0) {
  for (const key of keys) {
    const value = Number(row[key] ?? NaN);
    if (Number.isFinite(value)) return value;
  }
  return fallback;
}

function normalizeFilterValue(value: string) {
  return value.trim().toUpperCase();
}

export default function StocksReportPage() {
  const [reportRows, setReportRows] = useState<StockReportRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [categoryFilter, setCategoryFilter] = useState("Semua Jenis");
  const [statusFilter, setStatusFilter] = useState("Semua Status");
  const [currentPage, setCurrentPage] = useState(1);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const [reportResponse, itemsResponse] = await Promise.allSettled([
          sdk.reports.getStocks(ALL_STOCK_REPORT_PERIOD),
          listAllItems(),
        ]);

        if (cancelled) return;

        const nextReportRows =
          reportResponse.status === "fulfilled" ? (reportResponse.value.data.rows ?? []) : [];
        const nextItems =
          itemsResponse.status === "fulfilled" ? itemsResponse.value : [];

        const itemsMap = new Map(nextItems.map((item) => [item.id, item]));

        const enrichedReportRows = nextReportRows.map((row) => {
          const itemId = firstNumber(row, ["item_id", "id"]);
          const item = itemsMap.get(itemId);
          if (item) {
            return {
              ...row,
              min_stock: item.min_stock,
            };
          }
          return row;
        });

        setReportRows(enrichedReportRows);

        if (nextReportRows.length === 0) {
          const loadError =
            reportResponse.status === "rejected" ? reportResponse.reason : null;

          if (loadError) {
            setError(getErrorMessage(loadError, "Gagal memuat stok bahan."));
          }
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat stok bahan."));
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

  const tableRows = useMemo<StockTableRow[]>(() => {
    const reportRowMap = new Map(
      reportRows.map((row, index) => [
        firstNumber(row, ["item_id", "id"], index + 1),
        row,
      ]),
    );

    return reportRows.map((row, index) => {
      const itemId = firstNumber(row, ["item_id", "id"], index + 1);
      const qty = firstNumber(row, ["qty", "current_stock", "stock", "stock_qty", "quantity"]);
      const minimumQty = firstNumber(row, ["min_stock", "minimum_qty", "minimal_stock", "minimum_stock", "conversion_base"], 1);
      const unit = firstString(row, ["unit", "satuan", "unit_base"], "");
      const stock = getStockTone(qty, minimumQty);

      return {
        idLabel: `BR-${String(itemId).padStart(4, "0")}`,
        itemId,
        itemName: firstString(row, ["item_name", "name", "nama_bahan"], `Item ${itemId}`),
        categoryName: firstString(row, ["category_name", "jenis_bahan", "kategori_bahan"]),
        qty,
        qtyLabel: formatQuantity(qty, unit),
        minimumQty,
        minimumLabel: formatQuantity(minimumQty, unit),
        tone: stock.tone,
        label: stock.label,
      };
    });
  }, [reportRows]);

  const categoryOptions = useMemo(
    () =>
      Array.from(
        new Set(
          tableRows
            .map((row) => row.categoryName)
            .filter((value) => value && value !== "-"),
        ),
      ).sort((left, right) => left.localeCompare(right, "id-ID")),
    [tableRows],
  );

  const filteredRows = useMemo(() => {
    const normalizedCategoryFilter = normalizeFilterValue(categoryFilter);
    const normalizedStatusFilter = normalizeFilterValue(statusFilter);

    return tableRows.filter((row) => {
      const normalizedSearch = searchTerm.trim().toLowerCase();
      const matchesSearch =
        normalizedSearch === "" ||
        row.itemName.toLowerCase().includes(normalizedSearch) ||
        row.idLabel.toLowerCase().includes(normalizedSearch) ||
        row.categoryName.toLowerCase().includes(normalizedSearch);
      const matchesCategory =
        normalizedCategoryFilter === normalizeFilterValue("Semua Jenis") ||
        normalizeFilterValue(row.categoryName) === normalizedCategoryFilter;
      const matchesStatus =
        normalizedStatusFilter === normalizeFilterValue("Semua Status") ||
        normalizeFilterValue(row.label) === normalizedStatusFilter;

      return matchesSearch && matchesCategory && matchesStatus;
    });
  }, [categoryFilter, searchTerm, statusFilter, tableRows]);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, categoryFilter, statusFilter]);

  const counts = useMemo(() => {
    return tableRows.reduce(
      (acc, row) => {
        acc[row.tone] += 1;
        return acc;
      },
      { warning: 0, critical: 0, danger: 0, safe: 0 } as Record<"warning" | "critical" | "danger" | "safe", number>,
    );
  }, [tableRows]);

  const pageSize = 10;
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));

  const paginatedRows = useMemo(() => {
    const startIndex = (currentPage - 1) * pageSize;
    return filteredRows.slice(startIndex, startIndex + pageSize);
  }, [currentPage, filteredRows]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  function handleExport() {
    if (typeof window === "undefined" || filteredRows.length === 0) {
      setError("Belum ada data stok yang bisa diexport dari hasil filter saat ini.");
      return;
    }

    const summaryHtml = `
      <table class="summary">
        <tr><td class="summary-label">Total Item Ditampilkan</td><td class="summary-value">${filteredRows.length} item</td></tr>
        <tr><td class="summary-label">Stok Aman</td><td class="summary-value">${formatSpreadsheetNumber(counts.safe, 0)} item</td></tr>
        <tr><td class="summary-label">Stok Menipis</td><td class="summary-value">${formatSpreadsheetNumber(counts.warning, 0)} item</td></tr>
        <tr><td class="summary-label">Stok Kritis</td><td class="summary-value">${formatSpreadsheetNumber(counts.critical, 0)} item</td></tr>
        <tr><td class="summary-label">Stok Habis</td><td class="summary-value">${formatSpreadsheetNumber(counts.danger, 0)} item</td></tr>
      </table>
    `;

    const rowsHtml = filteredRows
      .map(
        (row, index) => `
        <tr>
          <td class="rank">${index + 1}</td>
          <td class="text-strong">${escapeSpreadsheetHtml(row.idLabel)}</td>
          <td class="text-strong">${escapeSpreadsheetHtml(row.itemName)}</td>
          <td>${escapeSpreadsheetHtml(row.categoryName)}</td>
          <td class="number">${escapeSpreadsheetHtml(row.qtyLabel)}</td>
          <td class="number">${escapeSpreadsheetHtml(row.minimumLabel)}</td>
          <td>${escapeSpreadsheetHtml(row.idLabel ? row.qtyLabel.split(" ").slice(1).join(" ") || "-" : "-")}</td>
          <td class="pill ${row.tone}">${escapeSpreadsheetHtml(row.label)}</td>
        </tr>`,
      )
      .join("");

    const html = buildSpreadsheetDocument({
      title: "LAPORAN DATA STOK BAHAN INSTALASI GIZI RSD BALUNG",
      subtitle: "Detail data stok bahan berdasarkan filter aktif pada sistem.",
      body: `
        <div class="title">LAPORAN DATA STOK BAHAN INSTALASI GIZI RSD BALUNG</div>
        <div class="subtitle">Laporan stok bahan, minimal stok, dan status ketersediaan bahan pada sistem.</div>

        <table class="no-border section-gap">
          <tr>
            <td style="width: 36%; padding: 0 12px 12px 0;">${summaryHtml}</td>
            <td style="width: 64%; padding: 0 0 12px 0;">
              <table>
                <tr><td class="section" colspan="4">RINGKASAN STATUS STOK</td></tr>
                <tr class="head">
                  <th>Status</th>
                  <th>Jumlah</th>
                  <th>Keterangan</th>
                  <th>Nilai</th>
                </tr>
                <tr><td>Aman</td><td class="rank">${counts.safe}</td><td>Bahan masih aman</td><td class="safe">${counts.safe}</td></tr>
                <tr><td>Menipis</td><td class="rank">${counts.warning}</td><td>Perlu pemantauan</td><td class="warning">${counts.warning}</td></tr>
                <tr><td>Kritis</td><td class="rank">${counts.critical}</td><td>Perlu restock cepat</td><td class="danger">${counts.critical}</td></tr>
                <tr><td>Habis</td><td class="rank">${counts.danger}</td><td>Stok kosong</td><td class="danger">${counts.danger}</td></tr>
              </table>
            </td>
          </tr>
        </table>

        <table>
          <tr class="head">
            <th>No</th>
            <th>ID Barang</th>
            <th>Nama Bahan</th>
            <th>Jenis Bahan</th>
            <th>Stok Saat Ini</th>
            <th>Minimal Stok</th>
            <th>Satuan</th>
            <th>Status</th>
          </tr>
          ${rowsHtml}
        </table>
      `,
    });

    downloadSpreadsheetHtml(buildExportFilename("laporan-data-stok-bahan"), html);
  }

  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Stok Bahan"
        subtitle="Pantau ketersediaan bahan dari laporan stok backend"
      />

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <div className="grid gap-4 xl:grid-cols-3">
        {statCards.map((card) => {
          const Icon = card.icon;
          const count = counts[card.key];

          return (
            <SurfaceCard
              key={card.title}
              className={`border-t-[3px] ${card.accent} p-4 transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_16px_34px_rgba(15,23,42,0.08)]`}
            >
              <div className={`mb-5 flex h-8 w-8 items-center justify-center rounded-[9px] ${card.iconBg}`}>
                <Icon size={14} className={card.iconColor} />
              </div>
              <p className="text-[11px] font-semibold tracking-[0.04em] text-[#94A3B8]">{card.title}</p>
              <p className="mt-1 text-[18px] font-bold text-[#16213E]">
                {loading ? "..." : formatNumber(count)}
              </p>
              <p className="mt-2 text-[11px] text-[#94A3B8]">{card.note}</p>
            </SurfaceCard>
          );
        })}
      </div>

      <SurfaceCard className="overflow-hidden">
        <div className="flex flex-wrap items-center gap-3 border-b bg-[#F8FAFC] px-5 py-4">
          <div className="flex flex-col gap-3 lg:flex-row">
            <div className="w-full lg:w-[380px]">
              <FilterSearch
                placeholder="Cari nama bahan, ID, atau jenis bahan"
                value={searchTerm}
                onChange={setSearchTerm}
                readOnly={false}
              />
            </div>
            <ThemedSelect
              className="min-w-[180px]"
              value={categoryFilter}
              onChange={setCategoryFilter}
              options={[
                { value: "Semua Jenis", label: "Semua Jenis" },
                ...categoryOptions.map((category) => ({ value: category, label: category })),
              ]}
            />
            <ThemedSelect
              className="min-w-[180px]"
              value={statusFilter}
              onChange={setStatusFilter}
              options={[
                { value: "Semua Status", label: "Semua Status" },
                { value: "Aman", label: "Aman" },
                { value: "Menipis", label: "Menipis" },
                { value: "Kritis", label: "Kritis" },
                { value: "Habis", label: "Habis" },
              ]}
            />
          </div>
          <div className="ml-auto">
            <ExportButton onClick={handleExport}>Export Data</ExportButton>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <th className="px-6 py-3">ID Barang</th>
                <th className="px-6 py-3">Nama Bahan</th>
                <th className="px-6 py-3">Jenis Bahan</th>
                <th className="px-6 py-3">Stok Saat Ini</th>
                <th className="px-6 py-3">Minimal Stok</th>
                <th className="px-6 py-3">Status</th>
              </tr>
            </thead>
            <tbody className="bg-white text-sm text-gray-700">
              {paginatedRows.map((row) => (
                <tr key={row.itemId} className="border-t border-gray-200 transition hover:bg-gray-50">
                  <td className="px-6 py-4 font-medium text-gray-900">{row.idLabel}</td>
                  <td className="px-6 py-4 font-medium text-gray-900">{row.itemName}</td>
                  <td className="px-6 py-4">{row.categoryName}</td>
                  <td className="px-6 py-4">{row.qtyLabel}</td>
                  <td className="px-6 py-4">{row.minimumLabel}</td>
                  <td className="px-6 py-4">
                    <StatusPill tone={row.tone}>{row.label}</StatusPill>
                  </td>
                </tr>
              ))}
              {!loading && filteredRows.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={6}>
                    Belum ada data stok bahan.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <Pagination
          currentPage={currentPage}
          totalPages={totalPages}
          onPageChange={setCurrentPage}
          totalLabel={
            filteredRows.length > 0
              ? `${(currentPage - 1) * pageSize + 1}-${Math.min(currentPage * pageSize, filteredRows.length)} dari ${filteredRows.length} item`
              : "0 dari 0 item"
          }
        />
      </SurfaceCard>
    </div>
  );
}
