"use client";

import { useEffect, useMemo, useState } from "react";
import { X, Trash2, Plus } from "lucide-react";
import sdk from "@/lib";
import { useAuthStore } from "@/store/authStore";
import {
  AdminPageHeading,
  ExportButton,
  MiniActionButton,
  Pagination,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import DateRangePicker from "@/components/filters/DateRangePicker";
import SuccessModal from "@/components/feedback/SuccessModal";
import SearchableItemSelect from "@/components/admin/ui/SearchableItemSelect";
import {
  formatDate,
  getErrorMessage,
  resolveDetailItemCategory,
  resolveDetailItemName,
  resolveDetailUnit,
} from "@/lib/admin-utils";
import {
  buildSpreadsheetDocument,
  downloadSpreadsheetHtml,
  escapeSpreadsheetHtml,
  formatSpreadsheetNumber,
} from "@/lib/spreadsheet-export";
import { listAllItems } from "@/lib/items";
import { getDateRangeQuery } from "@/lib/date-range";
import { listAllPaginatedRows } from "@/lib/pagination";
import { useRouter } from "next/navigation";

type TransactionRow = Awaited<ReturnType<typeof sdk.stockTransactions.list>>["data"][number];
type DetailRow = Awaited<ReturnType<typeof sdk.stockTransactions.details>>["data"][number];
type ItemRow = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
type ItemUnitRow = Awaited<ReturnType<typeof sdk.itemUnits.list>>["data"][number];
type LookupRow = Awaited<ReturnType<typeof sdk.transactionTypes.list>>["data"][number];
type StatusRow = Awaited<ReturnType<typeof sdk.approvalStatuses.list>>["data"][number];
type StockTransactionListQuery = NonNullable<Parameters<typeof sdk.stockTransactions.list>[0]>;

type DetailState = {
  transaction: TransactionRow;
  details: DetailRow[];
  resolvedItemMap: Map<number, ItemRow>;
};

type RevisionRow = {
  id: number;
  item_id: number;
  qty: string;
  unit: string;
  input_unit: "base" | "convert";
  item_name: string;
  category_name: string;
  isPersisted: boolean;
  originalQty: number;
};

type DerivedRow = {
  transaction: TransactionRow;
  transactionLabel: string;
  categoryLabel: string;
  userLabel: string;
  statusLabel: string;
};

function normalizeTransactionId(value: unknown): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

function isApprovedRevision(row: TransactionRow, statusMap: Map<number, string>) {
  if (!row.is_revision) return true;
  const statusName = (statusMap.get(row.approval_status_id) ?? "").toLowerCase();
  return row.approval_status_id === 1 || statusName.includes("approved") || statusName.includes("disetujui");
}

function pickLatestTransactionByParent(rows: TransactionRow[], statusMap: Map<number, string>): TransactionRow[] {
  const byParent = new Map<number, TransactionRow>();
  for (const row of rows.filter((transaction) => isApprovedRevision(transaction, statusMap))) {
    const parentId =
      normalizeTransactionId(row.parent_transaction_id) || normalizeTransactionId(row.id);
    const existing = byParent.get(parentId);
    if (!existing) {
      byParent.set(parentId, row);
      continue;
    }
    const rowTime = new Date(row.updated_at || row.created_at || row.transaction_date).getTime();
    const existingTime = new Date(existing.updated_at || existing.created_at || existing.transaction_date).getTime();
    if (rowTime > existingTime || (rowTime === existingTime && row.id > existing.id)) {
      byParent.set(parentId, row);
    }
  }
  return Array.from(byParent.values());
}

function isLegacyTransactionRow(row: TransactionRow) {
  const transactionId = normalizeTransactionId(row.parent_transaction_id) || normalizeTransactionId(row.id);
  return transactionId === 1;
}

function normalizeTransactionDirection(label: string) {
  const normalized = label.trim().toUpperCase();
  if (normalized.includes("MASUK") || normalized === "IN") return "IN";
  if (normalized.includes("KELUAR") || normalized === "OUT") return "OUT";
  return "MIXED";
}


const PAGE_SIZE = 10;

async function loadAllTransactions(query: Omit<StockTransactionListQuery, "page" | "perPage">) {
  return listAllPaginatedRows<TransactionRow>(
    sdk.stockTransactions.list.bind(sdk.stockTransactions),
    query,
    100,
  );
}

async function loadAllItemsSortedByName(): Promise<ItemRow[]> {
  return listAllItems({ sortBy: "name", sortDir: "ASC" });
}

export default function GudangTransactionHistoryPage() {
  const router = useRouter();
  const currentUser = useAuthStore((state) => state.user);
  const [transactions, setTransactions] = useState<TransactionRow[]>([]);
  const [types, setTypes] = useState<LookupRow[]>([]);
  const [statuses, setStatuses] = useState<StatusRow[]>([]);
  const [units, setUnits] = useState<ItemUnitRow[]>([]);
  const [detailMap, setDetailMap] = useState<Record<number, DetailRow[]>>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [dateRange, setDateRange] = useState({ startDate: "", endDate: "" });
  const [selectedType, setSelectedType] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [detailState, setDetailState] = useState<DetailState | null>(null);
  const [revisionState, setRevisionState] = useState<DetailState | null>(null);
  const [revisionRows, setRevisionRows] = useState<RevisionRow[]>([]);
  const [savingRevision, setSavingRevision] = useState(false);
  const [totalRecords, setTotalRecords] = useState(0);
  const [successOpen, setSuccessOpen] = useState(false);
  // Items dimuat on-demand saat revision modal dibuka, untuk SearchableItemSelect
  const [modalItems, setModalItems] = useState<ItemRow[]>([]);
  const [modalItemsLoaded, setModalItemsLoaded] = useState(false);

  // Hanya fetch lookup statis (jenis transaksi & status) — tidak fetch items/units secara background
  useEffect(() => {
    let cancelled = false;
    async function loadLookups() {
      try {
        const [typeResponse, statusResponse, unitResponse] = await Promise.all([
          sdk.transactionTypes.list({ paginate: false }),
          sdk.approvalStatuses.list({ paginate: false }),
          sdk.itemUnits.list({ paginate: false, sortBy: "name", sortDir: "ASC" }),
        ]);
        if (cancelled) return;
        setTypes(typeResponse.data ?? []);
        setStatuses(statusResponse.data ?? []);
        setUnits(unitResponse.data ?? []);
      } catch (err) {
        console.error("Failed to load transaction lookups:", err);
      }
    }
    void loadLookups();
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const params: Omit<StockTransactionListQuery, "page" | "perPage"> = {
          sortBy: "transaction_date",
          sortDir: "DESC",
        };

        Object.assign(params, getDateRangeQuery(dateRange));
        if (selectedType) params.type_id = Number(selectedType);

        const response = await loadAllTransactions(params);

        if (cancelled) return;

        const normalizedRows = pickLatestTransactionByParent(
          response,
          new Map(statuses.map((status) => [status.id, status.name])),
        )
          .filter((row) => !isLegacyTransactionRow(row))
          .sort((left, right) => {
            const rightTime = new Date(right.updated_at || right.created_at || right.transaction_date).getTime();
            const leftTime = new Date(left.updated_at || left.created_at || left.transaction_date).getTime();
            if (rightTime !== leftTime) {
              return rightTime - leftTime;
            }

            const rightParentId =
              normalizeTransactionId(right.parent_transaction_id) || normalizeTransactionId(right.id);
            const leftParentId =
              normalizeTransactionId(left.parent_transaction_id) || normalizeTransactionId(left.id);

            return rightParentId - leftParentId;
          });
        setTransactions(normalizedRows);
        setTotalRecords(normalizedRows.length);
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat riwayat transaksi gudang."));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [dateRange, selectedType, statuses]);

  const typeMap = useMemo(() => new Map(types.map((type) => [type.id, type.name])), [types]);
  const statusMap = useMemo(() => new Map(statuses.map((status) => [status.id, status.name])), [statuses]);
  const unitMap = useMemo(() => new Map(units.map((unit) => [unit.id, unit.name])), [units]);

  const derivedRows = useMemo<DerivedRow[]>(() => {
    return transactions.flatMap((transaction) => {
      // categoryLabel tidak di-render di tabel — hanya diisi saat buka modal
      const details = detailMap[transaction.id] ?? [];
      const categoryLabel =
        Array.from(
          new Set(
            details
              .map((detail) => resolveDetailItemCategory(detail, undefined))
              .filter((value) => value && value !== "-"),
          ),
        ).join(", ") || "-";
      const statusLabel = statusMap.get(transaction.approval_status_id) ?? "Menunggu";
      const userLabel = getGudangUserLabel(
        transaction.user_id,
        (transaction as { user_name?: string | null }).user_name,
        currentUser?.id,
        currentUser?.name,
      );
      const transactionLabel = getStockMovementTypeLabel(typeMap.get(transaction.type_id));
      if (!transactionLabel) return [];

      return [{
        transaction,
        transactionLabel,
        categoryLabel,
        userLabel,
        statusLabel,
      }];
    });
  }, [transactions, detailMap, statusMap, currentUser?.id, currentUser?.name, typeMap]);

  const filteredRows = useMemo(() => {
    const query = search.trim().toLowerCase();
    return derivedRows.filter((row) => {
      if (query.length === 0) return true;

      const transactionId = `TR-${String(
        normalizeTransactionId(row.transaction.parent_transaction_id) || normalizeTransactionId(row.transaction.id),
      ).padStart(4, "0")}`.toLowerCase();
      const isoDate = String(row.transaction.transaction_date ?? "").slice(0, 10).toLowerCase();
      const prettyDate = formatDate(row.transaction.transaction_date).toLowerCase();
      const transactionLabel = row.transactionLabel.toLowerCase();
      const categoryLabel = row.categoryLabel.toLowerCase();
      const userLabel = row.userLabel.toLowerCase();
      const statusLabel = row.statusLabel.toLowerCase();

      return (
        transactionId.includes(query) ||
        isoDate.includes(query) ||
        prettyDate.includes(query) ||
        transactionLabel.includes(query) ||
        categoryLabel.includes(query) ||
        userLabel.includes(query) ||
        statusLabel.includes(query)
      );
    });
  }, [derivedRows, search]);

  const totalPages = Math.max(1, Math.ceil(filteredRows.length / PAGE_SIZE));
  const paginatedRows = useMemo(() => {
    const startIndex = (currentPage - 1) * PAGE_SIZE;
    return filteredRows.slice(startIndex, startIndex + PAGE_SIZE);
  }, [currentPage, filteredRows]);

  useEffect(() => {
    setCurrentPage(1);
  }, [dateRange, search, selectedType]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  useEffect(() => {
    let cancelled = false;

    async function fetchMissingDetails() {
      const missingIds = paginatedRows
        .map((row) => row.transaction.id)
        .filter((id) => !detailMap[id]);

      const nextEntries = await Promise.allSettled(
        missingIds.map(async (id) => [id, (await sdk.stockTransactions.details(id)).data ?? []] as const),
      );

      if (cancelled) {
        return;
      }

      const resolvedEntries = nextEntries
        .map((entry) => (entry.status === "fulfilled" ? entry.value : null))
        .filter((entry): entry is readonly [number, DetailRow[]] => entry !== null);

      if (resolvedEntries.length === 0) {
        return;
      }

      setDetailMap((prev) => {
        const next = { ...prev };
        for (const [id, details] of resolvedEntries) {
          next[id] = details;
        }
        return next;
      });
    }

    void fetchMissingDetails();
    return () => {
      cancelled = true;
    };
  }, [paginatedRows.map((row) => row.transaction.id).join(",")]);

  // Fetch detail transaksi on-demand (hanya saat modal dibuka)
  async function getDetails(transactionId: number) {
    const detailResponse = await sdk.stockTransactions.details(transactionId);
    const details = detailResponse.data ?? [];
    return { details, resolvedItemMap: new Map<number, ItemRow>() };
  }

  async function openDetail(transaction: TransactionRow) {
    try {
      const { details, resolvedItemMap } = await getDetails(transaction.id);
      setDetailMap((prev) => ({ ...prev, [transaction.id]: details }));
      setDetailState({ transaction, details, resolvedItemMap });
    } catch (loadError) {
      setError(getErrorMessage(loadError, "Gagal memuat detail transaksi."));
    }
  }

  async function openRevision(transaction: TransactionRow) {
    try {
      const { details, resolvedItemMap } = await getDetails(transaction.id);
      setRevisionRows(
        details.map((detail, index) => ({
          id: index + 1,
          item_id: detail.item_id,
          qty: String(Number(detail.input_qty ?? detail.qty ?? 0)),
          unit: resolveDetailUnit(detail, resolvedItemMap.get(detail.item_id)),
          input_unit: detail.input_unit === "convert" ? "convert" : "base",
          item_name: resolveDetailItemName(detail, resolvedItemMap.get(detail.item_id)),
          category_name: resolveDetailItemCategory(detail, resolvedItemMap.get(detail.item_id)),
          isPersisted: true,
          originalQty: Number(detail.input_qty ?? detail.qty ?? 0),
        })),
      );
      setDetailMap((prev) => ({ ...prev, [transaction.id]: details }));
      setRevisionState({ transaction, details, resolvedItemMap });

      // Muat daftar semua item untuk SearchableItemSelect (hanya sekali)
      if (!modalItemsLoaded) {
        loadAllItemsSortedByName()
          .then((res) => {
            setModalItems(res ?? []);
            setModalItemsLoaded(true);
          })
          .catch(console.error);
      }
    } catch (loadError) {
      setError(getErrorMessage(loadError, "Gagal memuat form revisi transaksi."));
    }
  }

  function addRevisionRow() {
    setRevisionRows((current) => [
      ...current,
      {
        id: current.length > 0 ? Math.max(...current.map((r) => r.id)) + 1 : 1,
        item_id: 0,
        qty: "0",
        unit: "",
        input_unit: "base",
        item_name: "",
        category_name: "",
        isPersisted: false,
        originalQty: 0,
      },
    ]);
  }

  function removeRevisionRow(id: number) {
    setRevisionRows((current) => current.filter((r) => r.id !== id));
  }

  function updateRevisionRow(id: number, updates: Partial<RevisionRow>) {
    setRevisionRows((current) => current.map((r) => (r.id === id ? { ...r, ...updates } : r)));
  }

  async function submitRevision() {
    if (!revisionState) return;

    setSavingRevision(true);
    setError(null);

    try {
      const details = revisionRows
        .filter((row) => row.item_id > 0 && Number(row.qty) > 0)
        .map((row) => ({ item_id: row.item_id, qty: Number(row.qty), input_unit: row.input_unit }));

      if (details.length === 0) {
        throw new Error("Minimal satu item harus memiliki jumlah lebih dari 0.");
      }

      const duplicateId = details.find((detail, index) => details.findIndex((item) => item.item_id === detail.item_id) !== index)?.item_id;
      if (duplicateId) {
        throw new Error(`Bahan ${modalItems.find((item) => item.id === duplicateId)?.name ?? `Item #${duplicateId}`} tidak boleh dipilih dua kali.`);
      }
      const payload: {
        transaction_date: string;
        spk_id?: number | null;
        details: { item_id: number; qty: number; input_unit?: "base" | "convert" }[];
      } = {
        transaction_date: revisionState.transaction.transaction_date,
        details,
      };
      if (typeof revisionState.transaction.spk_id === "number") {
        payload.spk_id = revisionState.transaction.spk_id;
      }

      const targetTransactionId =
        normalizeTransactionId(revisionState.transaction.parent_transaction_id) ||
        normalizeTransactionId(revisionState.transaction.id);

      console.info("[revision.submit] request", {
        targetTransactionId,
        payload,
      });
      await sdk.stockTransactions.submitRevision(targetTransactionId, payload);

      setRevisionState(null);
      setSuccessOpen(true);
      
      setTimeout(() => {
        router.push("/gudang/transaksi/pengajuan-revisi");
      }, 1500);
    } catch (saveError) {
      setError(getErrorMessage(saveError, "Gagal menyimpan pembaruan revisi transaksi."));
    } finally {
      setSavingRevision(false);
    }
  }

  async function handleExport() {
    if (typeof window === "undefined" || filteredRows.length === 0) {
      setError("Belum ada data riwayat transaksi yang bisa diexport dari hasil filter saat ini.");
      return;
    }

    const exportRows = filteredRows;
    const exportMode =
      exportRows.every((row) => row.transactionLabel === "Masuk")
        ? "IN"
        : exportRows.every((row) => row.transactionLabel === "Keluar")
          ? "OUT"
          : "MIXED";

    const missingIds = exportRows
      .map((row) => row.transaction.id)
      .filter((id) => !detailMap[id]);

    const resolvedEntries = await Promise.allSettled(
      missingIds.map(async (id) => [id, (await sdk.stockTransactions.details(id)).data ?? []] as const),
    );
    const fetchedDetails = resolvedEntries
      .map((entry) => (entry.status === "fulfilled" ? entry.value : null))
      .filter((entry): entry is readonly [number, DetailRow[]] => entry !== null);
    const nextDetailMap = { ...detailMap };
    for (const [id, details] of fetchedDetails) {
      nextDetailMap[id] = details;
    }

    const allItems = await loadAllItemsSortedByName();
    const resolvedItemMap = new Map<number, ItemRow>(allItems.map((item) => [item.id, item]));

    const detailRows = exportRows.flatMap((row) => {
      const details = nextDetailMap[row.transaction.id] ?? [];
      const transactionMeta = row.transaction as {
        reason?: string | null;
        notes?: string | null;
        description?: string | null;
        patient_count?: unknown;
        jumlah_pasien?: unknown;
      };
      const keterangan = transactionMeta.reason ?? transactionMeta.notes ?? transactionMeta.description ?? "-";
      const patientCountRaw = transactionMeta.patient_count ?? transactionMeta.jumlah_pasien ?? null;
      const patientCount =
        patientCountRaw === null || patientCountRaw === undefined || patientCountRaw === ""
          ? "-"
          : formatSpreadsheetNumber(Number(patientCountRaw), 0);

      if (details.length === 0) {
        return [{
          id: `TR-${String(normalizeTransactionId(row.transaction.parent_transaction_id) || normalizeTransactionId(row.transaction.id)).padStart(4, "0")}`,
          date: formatDate(row.transaction.transaction_date),
          name: "-",
          category: row.categoryLabel,
          unit: "-",
          inQty: "-",
          outQty: "-",
          patientCount,
          keterangan,
          petugas: row.userLabel,
        }];
      }

      return details.map((detail) => {
        const resolvedItem = resolvedItemMap.get(detail.item_id);
        const unit = resolveDetailUnit(detail, resolvedItem, unitMap);
        const qty = Number(detail.input_qty ?? detail.qty ?? 0);
        const rowDirection = normalizeTransactionDirection(row.transactionLabel);
        return {
          id: `TR-${String(normalizeTransactionId(row.transaction.parent_transaction_id) || normalizeTransactionId(row.transaction.id)).padStart(4, "0")}`,
          date: formatDate(row.transaction.transaction_date),
          name: resolveDetailItemName(detail, resolvedItem),
          category: resolveDetailItemCategory(detail, resolvedItem),
          unit,
          inQty: rowDirection === "IN" ? `${formatSpreadsheetNumber(qty, 2)} ${unit}` : "-",
          outQty: rowDirection === "OUT" ? `${formatSpreadsheetNumber(qty, 2)} ${unit}` : "-",
          patientCount: rowDirection === "OUT" ? patientCount : "-",
          keterangan,
          petugas: row.userLabel,
        };
      });
    });

    const summaryRows = [
      { label: "Total Transaksi", value: formatSpreadsheetNumber(exportRows.length, 0) },
      { label: "Total Detail Bahan", value: formatSpreadsheetNumber(detailRows.length, 0) },
      { label: "Jenis Ekspor", value: exportMode === "IN" ? "Barang Masuk" : exportMode === "OUT" ? "Barang Keluar" : "Gabungan" },
    ];

    const title =
      exportMode === "OUT"
        ? "LAPORAN BARANG KELUAR INSTALASI GIZI RSD BALUNG"
        : exportMode === "IN"
          ? "LAPORAN BARANG MASUK INSTALASI GIZI RSD BALUNG"
          : "LAPORAN RIWAYAT TRANSAKSI BARANG INSTALASI GIZI RSD BALUNG";

    const subtitle =
      exportMode === "OUT"
        ? "Rekapitulasi transaksi barang keluar berdasarkan filter riwayat transaksi saat ini."
        : exportMode === "IN"
          ? "Rekapitulasi transaksi barang masuk berdasarkan filter riwayat transaksi saat ini."
          : "Rekapitulasi seluruh transaksi barang berdasarkan filter riwayat transaksi saat ini.";

    const headerColumns =
      exportMode === "OUT"
        ? ["No", "Tanggal", "ID Transaksi", "Nama Bahan", "Kategori", "Satuan", "Jumlah Keluar", "Jumlah Pasien", "Keterangan", "Petugas"]
        : exportMode === "IN"
          ? ["No", "Tanggal", "ID Transaksi", "Nama Bahan", "Kategori", "Satuan", "Jumlah Masuk", "Keterangan", "Petugas"]
          : ["No", "Tanggal", "ID Transaksi", "Nama Bahan", "Kategori", "Satuan", "Jumlah Masuk", "Jumlah Keluar", "Keterangan", "Petugas"];

    const bodyRows = detailRows.map((row, index) => {
      const cells =
        exportMode === "OUT"
          ? [
              index + 1,
              row.date,
              row.id,
              row.name,
              row.category,
              row.unit,
              row.outQty,
              row.patientCount,
              row.keterangan,
              row.petugas,
            ]
          : exportMode === "IN"
            ? [
                index + 1,
                row.date,
                row.id,
                row.name,
                row.category,
                row.unit,
                row.inQty,
                row.keterangan,
                row.petugas,
              ]
            : [
                index + 1,
                row.date,
                row.id,
                row.name,
                row.category,
                row.unit,
                row.inQty,
                row.outQty,
                row.keterangan,
                row.petugas,
              ];

      return `<tr>${cells
        .map((cell, cellIndex) => `<td${cellIndex === 0 ? ' class="rank"' : ''}>${escapeSpreadsheetHtml(cell)}</td>`)
        .join("")}</tr>`;
    }).join("");

    const html = buildSpreadsheetDocument({
      title,
      subtitle,
      body: `
        <table class="section-gap">
          <tr class="no-border">
            <td class="title" colspan="4">${escapeSpreadsheetHtml(title)}</td>
          </tr>
          <tr class="no-border">
            <td class="subtitle" colspan="4">${escapeSpreadsheetHtml(subtitle)}</td>
          </tr>
        </table>

        <table class="section-gap">
          <tr>
            <td class="section" colspan="2">RINGKASAN</td>
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

        <table>
          <tr class="head">
            ${headerColumns.map((column) => `<th>${escapeSpreadsheetHtml(column)}</th>`).join("")}
          </tr>
          ${bodyRows || `<tr><td colspan="${headerColumns.length}" class="muted">Belum ada data untuk diexport.</td></tr>`}
        </table>
      `,
    });

    const filename =
      exportMode === "OUT"
        ? "laporan-barang-keluar.xls"
        : exportMode === "IN"
          ? "laporan-barang-masuk.xls"
          : "riwayat-transaksi-barang.xls";

    downloadSpreadsheetHtml(filename, html);
  }

  return (
    <>
      <div className="space-y-6">
        <AdminPageHeading
          title="Riwayat Transaksi Barang"
          subtitle="Riwayat transaksi barang masuk & keluar"
        />

        {error ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
            {error}
          </div>
        ) : null}

        <SurfaceCard className="overflow-hidden">
          <div className="flex flex-wrap items-center gap-3 border-b border-[#D7E0EE] bg-[#F8FAFC] px-5 py-4">
            <div className="w-full max-w-[220px]">
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Cari transaksi"
                className="h-10 w-full rounded-[9px] border border-[#E2E8F0] bg-white px-3 text-base text-[#334155] outline-none placeholder:text-[#94A3B8]"
              />
            </div>
            <DateRangePicker
              ariaLabel="Rentang tanggal riwayat transaksi gudang"
              className="min-w-[240px]"
              endDate={dateRange.endDate}
              onChange={setDateRange}
              placeholder="dd/mm/yyyy"
              startDate={dateRange.startDate}
            />
            <ThemedSelect
              className="h-10 min-w-[140px] rounded-lg"
              value={selectedType}
              onChange={setSelectedType}
              options={[
                { value: "", label: "Semua Jenis" },
                ...types
                  .flatMap((type) => {
                    const label = getStockMovementTypeLabel(type.name);
                    return label ? [{ value: String(type.id), label }] : [];
                  }),
              ]}
            />
            <div className="ml-auto">
              <ExportButton onClick={handleExport}>Export Riwayat</ExportButton>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">
                <tr>
                  <th className="px-6 py-3">ID Transaksi</th>
                  <th className="px-6 py-3">Tanggal</th>
                  <th className="px-6 py-3">User</th>
                  <th className="px-6 py-3">Transaksi</th>
                  <th className="px-6 py-3">Kategori Bahan</th>
                  <th className="px-6 py-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className="bg-white text-base text-[#334155]">
                {paginatedRows.map((row) => (
                  <tr key={row.transaction.id} className="border-t border-[#E2E8F0] transition hover:bg-[#F8FAFC]">
                    <td className="px-6 py-4 font-semibold text-[#16213E]">
                      TR-{String(normalizeTransactionId(row.transaction.parent_transaction_id) || normalizeTransactionId(row.transaction.id)).padStart(4, "0")}
                    </td>
                    <td className="px-6 py-4 text-[#475569]">{formatDate(row.transaction.transaction_date)}</td>
                    <td className="px-6 py-4 text-[#475569]">{row.userLabel}</td>
                    <td className="px-6 py-4 font-semibold text-[#16213E]">{row.transactionLabel}</td>
                    <td className="px-6 py-4 text-[#475569]">{row.categoryLabel}</td>
                    <td className="px-6 py-4">
                      <div className="flex justify-center gap-2">
                        <MiniActionButton onClick={() => void openDetail(row.transaction)}>Detail</MiniActionButton>
                        <MiniActionButton onClick={() => void openRevision(row.transaction)}>Edit</MiniActionButton>
                      </div>
                    </td>
                  </tr>
                ))}
                {!loading && paginatedRows.length === 0 ? (
                  <tr>
                    <td className="px-6 py-8 text-center text-[#94A3B8]" colSpan={6}>
                      Belum ada riwayat transaksi.
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
            totalLabel={`${totalRecords === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1}-${Math.min(
              currentPage * PAGE_SIZE,
              totalRecords,
            )} dari ${totalRecords} item`}
          />
        </SurfaceCard>
      </div>

        {detailState ? (
          <TransactionDetailModal
            detailState={detailState}
            typeMap={typeMap}
            unitMap={unitMap}
            onClose={() => setDetailState(null)}
          />
        ) : null}

        {revisionState ? (
          <TransactionRevisionModal
            transaction={revisionState.transaction}
            rows={revisionRows}
            items={modalItems}
            typeMap={typeMap}
            unitMap={unitMap}
            updateRevisionRow={updateRevisionRow}
            addRevisionRow={addRevisionRow}
            removeRevisionRow={removeRevisionRow}
            onClose={() => setRevisionState(null)}
            onSubmit={() => void submitRevision()}
            saving={savingRevision}
          />
        ) : null}

      <SuccessModal
        open={successOpen}
        title="Berhasil"
        headline="Revisi Transaksi Berhasil Diajukan"
        message="Perubahan transaksi telah dikirim ke backend sebagai revisi."
        onClose={() => setSuccessOpen(false)}
      />
    </>
  );
}

function TransactionDetailModal({
  detailState,
  typeMap,
  unitMap,
  onClose,
}: {
  detailState: DetailState;
  typeMap: Map<number, string>;
  unitMap: Map<number, string>;
  onClose: () => void;
}) {
  const categoryLabel =
    Array.from(
      new Set(
        detailState.details
          .map((detail) => resolveDetailItemCategory(detail, detailState.resolvedItemMap.get(detail.item_id)))
          .filter((value) => value && value !== "-"),
      ),
    ).join(", ") || "-";
  const typeLabel = typeMap.get(detailState.transaction.type_id) ?? "Transaksi";

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center px-4 py-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative flex max-h-[calc(100vh-2rem)] w-full max-w-[560px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
          <div>
            <h2 className="text-[24px] font-semibold text-[#16213E]">
              Detail {typeLabel === "IN" ? "Barang Masuk" : "Barang Keluar"}
            </h2>
            <p className="mt-1 text-sm text-[#94A3B8]">1 riwayat dapat menampung banyak bahan</p>
          </div>
          <button
            className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD]"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
          <div className="grid grid-cols-4 gap-3 rounded-2xl bg-[#EEF4FF] px-4 py-3">
            <InfoBlock label="Tanggal" value={formatDate(detailState.transaction.transaction_date)} />
            <InfoBlock label="Kategori Bahan" value={categoryLabel} />
            <InfoBlock label="ID Transaksi" value={`TR-${String(normalizeTransactionId(detailState.transaction.parent_transaction_id) || normalizeTransactionId(detailState.transaction.id)).padStart(4, "0")}`} />
            <InfoBlock label="Total Item" value={`${detailState.details.length} item`} />
          </div>

          <div className="max-h-[52vh] overflow-y-auto overflow-hidden rounded-2xl border border-[#D7E0EE]">
            <div className="grid grid-cols-12 bg-[#F1F5F9] px-4 py-3 text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">
              <div className="col-span-1">#</div>
              <div className="col-span-5">Nama Bahan</div>
              <div className="col-span-3">Jumlah</div>
              <div className="col-span-3">Satuan</div>
            </div>
            {detailState.details.map((detail, index) => {
              const item = detailState.resolvedItemMap.get(detail.item_id);
              return (
                <div
                  key={`${detail.transaction_id}-${detail.item_id}-${index}`}
                  className="grid grid-cols-12 border-t border-[#E2E8F0] px-4 py-3 text-base text-[#334155]"
                >
                  <div className="col-span-1">{index + 1}</div>
                  <div className="col-span-5 font-semibold text-[#16213E]">
                    {resolveDetailItemName(detail, item)}
                  </div>
                  <div className="col-span-3">{Number(detail.input_qty ?? detail.qty ?? 0)}</div>
                  <div className="col-span-3">{resolveDetailUnit(detail, item, unitMap)}</div>
                </div>
              );
            })}
          </div>
        </div>

        <div className="flex justify-end border-t border-[#E2E8F0] px-5 py-4">
          <button
            className="rounded-xl border border-[#2155CD] bg-white px-5 py-2.5 text-base font-medium text-[#2155CD] transition hover:bg-[#EEF4FF]"
            onClick={onClose}
            type="button"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  );
}

function TransactionRevisionModal({
  transaction,
  rows,
  items,
  typeMap,
  unitMap,
  updateRevisionRow,
  addRevisionRow,
  removeRevisionRow,
  onClose,
  onSubmit,
  saving,
}: {
  transaction: TransactionRow;
  rows: RevisionRow[];
  items: ItemRow[];
  typeMap: Map<number, string>;
  unitMap: Map<number, string>;
  updateRevisionRow: (id: number, updates: Partial<RevisionRow>) => void;
  addRevisionRow: () => void;
  removeRevisionRow: (id: number) => void;
  onClose: () => void;
  onSubmit: () => void;
  saving: boolean;
}) {
  const typeLabel = typeMap.get(transaction.type_id) ?? "Transaksi";
  const allowedCategoryMode = useMemo(() => {
    const sourceCategories = rows
      .map((row) => row.category_name.toUpperCase())
      .filter(Boolean);
    const hasBasah = sourceCategories.some((category) => category.includes("BASAH"));
    const hasDry = sourceCategories.some(
      (category) => category.includes("KERING") || category.includes("PENGEMAS"),
    );

    if (hasBasah && !hasDry) return "BASAH";
    if (hasDry && !hasBasah) return "DRY";
    return "ALL";
  }, [rows]);

  const selectableItems = useMemo(() => {
    if (allowedCategoryMode === "BASAH") {
      return items.filter((item) => (item.category?.name ?? "").toUpperCase().includes("BASAH"));
    }

    if (allowedCategoryMode === "DRY") {
      return items.filter((item) => {
        const category = (item.category?.name ?? "").toUpperCase();
        return category.includes("KERING") || category.includes("PENGEMAS");
      });
    }

    return items;
  }, [allowedCategoryMode, items]);

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center px-4 py-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative flex max-h-[calc(100vh-2rem)] w-full max-w-[1100px] flex-col overflow-hidden rounded-[28px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
          <div>
            <h2 className="text-[24px] font-semibold text-[#16213E]">
              Edit {typeLabel === "IN" ? "Barang Masuk" : "Barang Keluar"}
            </h2>
            <p className="mt-1 text-sm text-[#94A3B8]">
              Perubahan akan diajukan ke backend sebagai revisi transaksi
            </p>
          </div>
          <button
            className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD]"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>
        <div className="flex-1 space-y-5 overflow-y-auto px-5 py-4">
          <div className="rounded-2xl border border-[#D7E0EE] bg-white shadow-sm">
            <div className="border-b border-[#E2E8F0] bg-[#F8FBFF] px-5 py-4">
              <h3 className="text-[22px] font-semibold text-[#16213E]">Komposisi Bahan</h3>
              <p className="mt-1 text-sm text-[#94A3B8]">Sesuaikan bahan dan jumlah revisi sesuai stok yang tersedia.</p>
            </div>
            <div className="p-4">
              <div className="mb-3 grid grid-cols-12 gap-4 px-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                <div className="col-span-5">Nama Bahan</div>
                <div className="col-span-4 text-center">Jumlah Revisi</div>
                <div className="col-span-2">Satuan</div>
                <div className="col-span-1"></div>
              </div>

              <div className="space-y-3">
                {rows.map((row) => (
                  <div
                    key={row.id}
                    className={`grid grid-cols-12 items-center gap-4 rounded-2xl border p-3 ${
                      row.isPersisted
                        ? "border-[#BFDBFE] bg-[#EFF6FF]"
                        : "border-[#E2E8F0] bg-[#FCFDFE]"
                    }`}
                  >
                    <div className="col-span-5">
                      <SearchableItemSelect
                        options={selectableItems.map((it) => ({
                          id: it.id,
                          label: it.name,
                          unit:
                            it.item_unit_base?.name ??
                            (it.item_unit_base_id ? unitMap.get(it.item_unit_base_id) : undefined) ??
                            it.unit_base,
                        }))}
                        value={row.item_id || null}
                        displayValue={row.item_name}
                        placeholder="Cari bahan..."
                        disabled={row.isPersisted}
                        className={row.isPersisted ? "pointer-events-none opacity-80" : ""}
                        onChange={(itemId) => {
                          if (row.isPersisted) return;
                          if (!itemId) return;
                          const duplicateExists = rows.some(
                            (existingRow) => existingRow.id !== row.id && existingRow.item_id === itemId,
                          );
                          if (duplicateExists) return;
                          const item =
                            selectableItems.find((it) => it.id === itemId) ??
                            items.find((it) => it.id === itemId);
                          updateRevisionRow(row.id, {
                            item_id: itemId || 0,
                            item_name: item?.name || "",
                            category_name: item?.category?.name || "",
                            unit:
                              item?.item_unit_base?.name ??
                              (item?.item_unit_base_id ? unitMap.get(item.item_unit_base_id) : undefined) ??
                              item?.unit_base ??
                              "",
                            input_unit: "base",
                          });
                        }}
                      />
                      <div className="px-2 pt-2 text-[10px] uppercase tracking-wide text-slate-400">
                        {row.category_name || "Kategori belum dipilih"} {row.isPersisted ? "• data tersimpan" : ""}
                      </div>
                    </div>
                    <div className="col-span-4">
                      <div className="flex items-center gap-2">
                        <input
                          type="number"
                          value={row.qty}
                          min={0}
                          onChange={(e) => {
                            updateRevisionRow(row.id, { qty: e.target.value });
                          }}
                          className="h-11 w-full rounded-xl border border-slate-200 px-3 text-center text-base outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
                        />
                      </div>
                    </div>
                    <div className="col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-base font-medium text-slate-600">
                      {row.unit || "-"}
                    </div>
                    <div className="col-span-1 flex justify-end">
                      <button
                        onClick={() => removeRevisionRow(row.id)}
                        className={`flex h-11 w-11 items-center justify-center rounded-xl border transition ${
                          row.isPersisted
                            ? "cursor-not-allowed border-slate-200 bg-slate-100 text-slate-300"
                            : "border-red-100 bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-500"
                        }`}
                        title="Hapus baris"
                        type="button"
                        disabled={row.isPersisted}
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>

              <button
                onClick={addRevisionRow}
                className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 py-3 text-base font-medium text-slate-500 transition hover:border-[#2563EB] hover:bg-blue-50/50 hover:text-[#2563EB]"
              >
                <Plus size={16} />
                <span>Tambah Baris Bahan</span>
              </button>
            </div>
          </div>

          <div className="rounded-2xl border border-[#D7E0EE] bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
            ID transaksi:{" "}
            <span className="font-semibold text-[#16213E]">
              TR-{String(normalizeTransactionId(transaction.parent_transaction_id) || normalizeTransactionId(transaction.id)).padStart(4, "0")}
            </span>{" "}
            |
            {" "}Tanggal {formatDate(transaction.transaction_date)}
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-[#E2E8F0] px-5 py-4">
          <button
            className="rounded-xl border border-[#CBD5E1] px-5 py-2.5 text-base text-[#475569] transition hover:bg-[#F8FAFC]"
            onClick={onClose}
            type="button"
          >
            Batal
          </button>
          <button
            className="rounded-xl bg-[#2155CD] px-5 py-2.5 text-base font-medium text-white transition hover:bg-[#1948B7]"
            onClick={onSubmit}
            type="button"
            disabled={saving}
          >
            {saving ? "Menyimpan..." : "Simpan"}
          </button>
        </div>
      </div>
    </div>
  );
}

function InfoBlock({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">{label}</div>
      <div className="mt-1 text-base font-semibold text-[#16213E]">{value}</div>
    </div>
  );
}

function getGudangUserLabel(
  userId: number | null | undefined,
  userName?: string | null,
  currentUserId?: number,
  currentUserName?: string,
) {
  if (userId == null) return "-";
  if (currentUserId === userId && currentUserName) return currentUserName;
  if (userName && userName.trim()) return userName;
  return `User #${userId}`;
}

function normaliseTransactionLabel(value?: string | null) {
  const movementLabel = getStockMovementTypeLabel(value);
  if (movementLabel) return movementLabel;
  return value ?? "-";
}

function getStockMovementTypeLabel(value?: string | null): "Masuk" | "Keluar" | null {
  const upper = (value ?? "").trim().toUpperCase();
  if (upper === "IN" || upper === "MASUK") return "Masuk";
  if (upper === "OUT" || upper === "KELUAR") return "Keluar";
  return null;
}
