"use client";

import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, PackageX, Zap } from "lucide-react";
import sdk from "@/lib";
import {
  formatNumber,
  formatQuantity,
  getCurrentMonthPeriod,
  getErrorMessage,
  getStockTone,
} from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  FilterSearch,
  Pagination,
  StatusPill,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";

type ItemRecord = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
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
    iconBg: "bg-[#FEF3C7]",
    iconColor: "text-[#B45309]",
    icon: Zap,
  },
  {
    key: "critical",
    title: "STOK KRITIS",
    note: "Bahan mendekati habis",
    accent: "border-[#FF6B6B]",
    iconBg: "bg-[#FEE2E2]",
    iconColor: "text-[#DC2626]",
    icon: AlertTriangle,
  },
  {
    key: "danger",
    title: "STOK HABIS",
    note: "Bahan habis",
    accent: "border-[#CBD5E1]",
    iconBg: "bg-[#E2E8F0]",
    iconColor: "text-[#334155]",
    icon: PackageX,
  },
] as const;

async function loadAllStockItems() {
  const allItems: ItemRecord[] = [];
  let page = 1;

  while (page <= 20) {
    const response = await sdk.items.list({
      page,
      perPage: 100,
      sortBy: "id",
      sortDir: "ASC",
    });
    const chunk = response.data ?? [];
    allItems.push(...chunk);

    if (chunk.length < 100) break;
    page += 1;
  }

  return allItems;
}

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

export default function StocksReportPage() {
  const [items, setItems] = useState<ItemRecord[]>([]);
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
        const response = await loadAllStockItems();
        if (cancelled) return;
        setItems(response);
        setReportRows([]);
      } catch (loadError) {
        try {
          const reportResponse = await sdk.reports.getStocks(getCurrentMonthPeriod());
          if (cancelled) return;
          setItems([]);
          setReportRows(reportResponse.data.rows ?? []);
        } catch (reportError) {
          if (!cancelled) {
            setError(getErrorMessage(reportError, getErrorMessage(loadError, "Gagal memuat stok bahan.")));
          }
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
    if (items.length > 0) {
      return items.map((item) => {
        const qty = Number(item.qty ?? 0);
        const minimumQty = Number(item.conversion_base ?? 0) || 1;
        const stock = getStockTone(qty, minimumQty);
        const categoryName = item.category?.name ?? "-";
        const itemName = item.name ?? `Item ${item.id}`;
        const unitBase = item.unit_base ?? "";

        return {
          idLabel: `BR-${String(item.id).padStart(4, "0")}`,
          itemId: item.id,
          itemName,
          categoryName,
          qty,
          qtyLabel: formatQuantity(qty, unitBase),
          minimumQty,
          minimumLabel: formatQuantity(minimumQty, unitBase),
          tone: stock.tone,
          label: stock.label,
        };
      });
    }

    return reportRows.map((row, index) => {
      const itemId = firstNumber(row, ["item_id", "id"], index + 1);
      const qty = firstNumber(row, ["qty", "current_stock", "stock", "stock_qty", "quantity"]);
      const minimumQty = firstNumber(row, ["minimum_qty", "minimal_stock", "minimum_stock", "conversion_base"], 1) || 1;
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
  }, [items, reportRows]);

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
    return tableRows.filter((row) => {
      const normalizedSearch = searchTerm.trim().toLowerCase();
      const matchesSearch =
        normalizedSearch === "" ||
        row.itemName.toLowerCase().includes(normalizedSearch) ||
        row.idLabel.toLowerCase().includes(normalizedSearch) ||
        row.categoryName.toLowerCase().includes(normalizedSearch);
      const matchesCategory = categoryFilter === "Semua Jenis" || row.categoryName === categoryFilter;
      const matchesStatus = statusFilter === "Semua Status" || row.label === statusFilter;

      return matchesSearch && matchesCategory && matchesStatus;
    });
  }, [categoryFilter, searchTerm, statusFilter, tableRows]);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, categoryFilter, statusFilter]);

  const counts = useMemo(() => {
    return filteredRows.reduce(
      (acc, row) => {
        acc[row.tone] += 1;
        return acc;
      },
      { warning: 0, critical: 0, danger: 0, safe: 0 } as Record<"warning" | "critical" | "danger" | "safe", number>,
    );
  }, [filteredRows]);

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

    const header = ["ID Barang", "Nama Bahan", "Jenis Bahan", "Stok Saat Ini", "Minimal Stok", "Status"];
    const lines = filteredRows.map((row) =>
      [
        row.idLabel,
        row.itemName,
        row.categoryName,
        row.qtyLabel,
        row.minimumLabel,
        row.label,
      ]
        .map((value) => `"${String(value).replaceAll('"', '""')}"`)
        .join(","),
    );

    const csvContent = [header.join(","), ...lines].join("\n");
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = window.URL.createObjectURL(blob);
    const link = window.document.createElement("a");
    link.href = url;
    link.download = "stok-bahan.csv";
    link.click();
    window.URL.revokeObjectURL(url);
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
