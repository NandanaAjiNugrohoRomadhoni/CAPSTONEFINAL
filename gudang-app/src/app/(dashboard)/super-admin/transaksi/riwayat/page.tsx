"use client";

import { useEffect, useMemo, useState } from "react";
import { AlertTriangle, X, Trash2, Plus } from "lucide-react";
import sdk from "@/lib";
import { useAuthStore } from "@/store/authStore";
import {
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
import { buildExportFilename } from "@/lib/export-filename";
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
type UserRow = Awaited<ReturnType<typeof sdk.users.list>>["data"][number];
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

type ExportDetailRow = {
  rawDate: string;
  dateLabel: string;
  transactionId: string;
  itemName: string;
  categoryName: string;
  unit: string;
  incomingQty: string;
  outgoingQty: string;
  petugas: string;
  patientLabel: string;
  directionLabel: string;
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

function getAllowedItemCategoryMode(rows: RevisionRow[]) {
  for (const row of rows) {
    const category = row.category_name.trim().toUpperCase();
    if (!category) continue;
    if (category.includes("BASAH")) return "BASAH";
    if (category.includes("KERING") || category.includes("PENGEMAS")) return "DRY";
  }

  return "ALL";
}

function matchesAllowedItemCategory(categoryName: string | null | undefined, allowedMode: string) {
  const normalizedCategory = String(categoryName ?? "").trim().toUpperCase();
  if (!normalizedCategory) return false;

  if (allowedMode === "BASAH") {
    return normalizedCategory.includes("BASAH");
  }

  if (allowedMode === "DRY") {
    return normalizedCategory.includes("KERING") || normalizedCategory.includes("PENGEMAS");
  }

  return normalizedCategory.includes("BASAH") || normalizedCategory.includes("KERING") || normalizedCategory.includes("PENGEMAS");
}

function formatExportCategoryLabel(categoryName: string | null | undefined) {
  const normalizedCategory = String(categoryName ?? "").trim().toUpperCase();
  if (normalizedCategory.includes("KERING") || normalizedCategory.includes("PENGEMAS")) {
    return "Kering dan Pengemas";
  }
  if (normalizedCategory.includes("BASAH")) {
    return "Basah";
  }
  return String(categoryName ?? "-");
}

function getExportDateRangeLabel(rows: Array<{ rawDate: string }>) {
  const timestamps = rows
    .map((row) => new Date(row.rawDate).getTime())
    .filter((value) => Number.isFinite(value));

  if (timestamps.length === 0) {
    return "-";
  }

  const startDate = new Date(Math.min(...timestamps));
  const endDate = new Date(Math.max(...timestamps));
  const startLabel = formatDate(startDate.toISOString());
  const endLabel = formatDate(endDate.toISOString());

  return startLabel === endLabel ? startLabel : `${startLabel} s/d ${endLabel}`;
}

function groupExportDetailRows(rows: ExportDetailRow[]) {
  const grouped = new Map<string, ExportDetailRow[]>();

  for (const row of rows) {
    const key = row.transactionId;
    const current = grouped.get(key);
    if (current) {
      current.push(row);
      continue;
    }
    grouped.set(key, [row]);
  }

  return Array.from(grouped.values());
}

function parseExportNumber(value: string) {
  const normalized = String(value ?? "").replace(/[^\d,.-]/g, "").replace(",", ".");
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? parsed : 0;
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

async function loadAllUsersSortedByCreatedAt(): Promise<UserRow[]> {
  const response = await sdk.users.list({
    page: 1,
    perPage: 25,
    sortBy: "created_at",
    sortDir: "DESC",
  });

  return response.data ?? [];
}

export default function Page() {
  const router = useRouter();
  const currentUser = useAuthStore((state) => state.user);
  const [transactions, setTransactions] = useState<TransactionRow[]>([]);
  const [items, setItems] = useState<ItemRow[]>([]);
  const [units, setUnits] = useState<ItemUnitRow[]>([]);
  const [types, setTypes] = useState<LookupRow[]>([]);
  const [statuses, setStatuses] = useState<StatusRow[]>([]);
  const [users, setUsers] = useState<UserRow[]>([]);
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
  const [revisionReason, setRevisionReason] = useState("");
  const [savingRevision, setSavingRevision] = useState(false);
  const [stockShortageMessage, setStockShortageMessage] = useState("");
  const [reasonWarningMessage, setReasonWarningMessage] = useState("");
  const [totalRecords, setTotalRecords] = useState(0);
  const [successOpen, setSuccessOpen] = useState(false);

  useEffect(() => {
    let cancelled = false;
    async function loadLookups() {
      try {
        const [unitResponse, typeResponse, statusResponse] = await Promise.all([
          sdk.itemUnits.list({ paginate: false, sortBy: "name", sortDir: "ASC" }),
          sdk.transactionTypes.list({ paginate: false }),
          sdk.approvalStatuses.list({ paginate: false }),
        ]);
        if (cancelled) return;
        setUnits(unitResponse.data ?? []);
        setTypes(typeResponse.data ?? []);
        setStatuses(statusResponse.data ?? []);
      } catch (err) {
        console.error("Failed to load transaction lookups:", err);
      }
    }
    void loadLookups();
    return () => { cancelled = true; };
  }, []);

  useEffect(() => {
    let cancelled = false;
    async function loadMetadata() {
      try {
        const [itemResponse, usersResponse] = await Promise.all([
          loadAllItemsSortedByName(),
          loadAllUsersSortedByCreatedAt(),
        ]);
        if (cancelled) return;
        setItems(itemResponse ?? []);
        setUsers(usersResponse ?? []);
      } catch (err) {
        console.error("Failed to load transaction metadata:", err);
      }
    }
    void loadMetadata();
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
          setError(getErrorMessage(loadError, "Gagal memuat riwayat transaksi."));
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

  const unitMap = useMemo(() => new Map(units.map((unit) => [unit.id, unit.name])), [units]);
  const typeMap = useMemo(() => new Map(types.map((type) => [type.id, type.name])), [types]);
  const statusMap = useMemo(() => new Map(statuses.map((status) => [status.id, status.name])), [statuses]);
  const userMap = useMemo(() => new Map(users.map((user) => [user.id, user.username || user.name])), [users]);

  const derivedRows = useMemo<DerivedRow[]>(() => {
    return transactions.flatMap((transaction) => {
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
      const userLabel = getUserLabel(transaction.user_id, userMap, currentUser?.id, currentUser?.username);
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
  }, [transactions, detailMap, statusMap, userMap, currentUser?.id, currentUser?.name, typeMap]);

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

      const settled = await Promise.allSettled(
        missingIds.map(async (id) => [id, (await sdk.stockTransactions.details(id)).data ?? []] as const),
      );

      if (cancelled) {
        return;
      }

      const resolvedEntries = settled
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
    return () => { cancelled = true; };
  }, [paginatedRows.map((r) => r.transaction.id).join(",")]);


  async function getDetails(transactionId: number) {
    const cachedDetails = detailMap[transactionId];
    const details =
      cachedDetails ??
      (await sdk.stockTransactions.details(transactionId).then((response) => response.data ?? []));

    if (!cachedDetails) {
      setDetailMap((current) => ({ ...current, [transactionId]: details }));
    }

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
          originalQty: Number(detail.qty ?? detail.input_qty ?? 0),
        })),
      );
      setDetailMap((prev) => ({ ...prev, [transaction.id]: details }));
      setRevisionState({ transaction, details, resolvedItemMap });
      setRevisionReason("");
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

    const trimmedReason = revisionReason.trim();
    if (!trimmedReason) {
      setReasonWarningMessage("Alasan revisi harus diisi sebelum menyimpan.");
      return;
    }

    const isOutgoingTransaction = normaliseTransactionLabel(typeMap.get(revisionState.transaction.type_id)) === "Keluar";
    const stockShortageMessage = getRevisionStockShortageMessage(revisionRows, items, isOutgoingTransaction);
    if (stockShortageMessage) {
      setStockShortageMessage(stockShortageMessage);
      return;
    }

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
        throw new Error(`Bahan ${items.find((item) => item.id === duplicateId)?.name ?? `Item #${duplicateId}`} tidak boleh dipilih dua kali.`);
      }
      const payload: {
        transaction_date: string;
        spk_id?: number | null;
        reason: string;
        details: { item_id: number; qty: number; input_unit?: "base" | "convert" }[];
      } = {
        transaction_date: revisionState.transaction.transaction_date,
        reason: trimmedReason,
        details,
      };
      if (typeof revisionState.transaction.spk_id === "number") {
        payload.spk_id = revisionState.transaction.spk_id;
      }

      const targetTransactionId =
        normalizeTransactionId(revisionState.transaction.parent_transaction_id) ||
        normalizeTransactionId(revisionState.transaction.id);
      console.info("[revision.submit.admin] request", {
        targetTransactionId,
        payload,
      });
      const revisionResult = await sdk.stockTransactions.submitRevision(targetTransactionId, payload);
      const revisionId = revisionResult.data?.id;
      if (typeof revisionId !== "number") {
        throw new Error("ID revisi tidak ditemukan setelah submit.");
      }

      await sdk.stockTransactions.approve(revisionId);

      setRevisionState(null);
      setRevisionReason("");
      setStockShortageMessage("");
      setReasonWarningMessage("");
      setSuccessOpen(true);

      // Delay redirection to allow user to see success modal
      setTimeout(() => {
        router.push("/super-admin/transaksi/pengajuan-revisi");
      }, 1500);
    } catch (saveError) {
      console.error("[revision.submit.admin] failed", saveError);
      setError(getErrorMessage(saveError, "Gagal mengajukan revisi transaksi."));
    } finally {
      setSavingRevision(false);
    }
  }

  async function handleExport() {
    if (typeof window === "undefined" || filteredRows.length === 0) {
      setError("Belum ada data riwayat transaksi yang bisa diexport dari hasil filter saat ini.");
      return;
    }

    const exportSource = filteredRows;
    const exportDetailMap = new Map<number, DetailRow[]>();
    for (const [id, details] of Object.entries(detailMap)) {
      exportDetailMap.set(Number(id), details);
    }

    const missingIds = exportSource
      .map((row) => row.transaction.id)
      .filter((id) => !exportDetailMap.has(id));

    if (missingIds.length > 0) {
      const settled = await Promise.allSettled(
        missingIds.map(async (id) => [id, (await sdk.stockTransactions.details(id)).data ?? []] as const),
      );

      settled
        .map((entry) => (entry.status === "fulfilled" ? entry.value : null))
        .filter((entry): entry is readonly [number, DetailRow[]] => entry !== null)
        .forEach(([id, details]) => {
          exportDetailMap.set(id, details);
        });
    }

    const directionLabels = new Set(
      exportSource.map((row) => normalizeTransactionDirection(row.transactionLabel)),
    );
    const selectedTypeLabel = selectedType ? typeMap.get(Number(selectedType)) ?? "" : "";
    const selectedDirection = selectedTypeLabel ? normalizeTransactionDirection(selectedTypeLabel) : "MIXED";
    const exportMode =
      selectedDirection === "IN" || selectedDirection === "OUT"
        ? selectedDirection
        : directionLabels.size === 1
          ? [...directionLabels][0]
          : "MIXED";

    const exportRows = exportSource.flatMap((row) => {
      const details = exportDetailMap.get(row.transaction.id) ?? [];
      const transactionId = `TR-${String(
        normalizeTransactionId(row.transaction.parent_transaction_id) || normalizeTransactionId(row.transaction.id),
      ).padStart(4, "0")}`;
      const directionLabel = normalizeTransactionDirection(row.transactionLabel);
      const petugas = row.userLabel;
      const patientCountRaw = (row.transaction as { patient_count?: unknown; jumlah_pasien?: unknown }).patient_count
        ?? (row.transaction as { patient_count?: unknown; jumlah_pasien?: unknown }).jumlah_pasien
        ?? null;
      const patientCount =
        typeof patientCountRaw === "number"
          ? patientCountRaw
          : Number(patientCountRaw ?? NaN);
      const patientLabel = Number.isFinite(patientCount) && patientCount > 0
        ? `${formatSpreadsheetNumber(patientCount, 0)} orang`
        : "-";

      if (details.length === 0) {
        return [{
          rawDate: String(row.transaction.transaction_date ?? ""),
          dateLabel: formatDate(row.transaction.transaction_date),
          transactionId,
          itemName: "-",
          categoryName: row.categoryLabel,
          unit: "-",
          incomingQty: "-",
          outgoingQty: "-",
          petugas,
          patientLabel,
          directionLabel,
        }];
      }

      return details.map((detail) => {
        const quantity = Number(detail.input_qty ?? detail.qty ?? 0);
        return {
          rawDate: String(row.transaction.transaction_date ?? ""),
          dateLabel: formatDate(row.transaction.transaction_date),
          transactionId,
          itemName: resolveDetailItemName(detail),
          categoryName: resolveDetailItemCategory(detail),
          unit: resolveDetailUnit(detail, undefined, unitMap),
          incomingQty: directionLabel === "IN" ? `${formatSpreadsheetNumber(quantity, Number.isInteger(quantity) ? 0 : 1)}` : "-",
          outgoingQty: directionLabel === "OUT" ? `${formatSpreadsheetNumber(quantity, Number.isInteger(quantity) ? 0 : 1)}` : "-",
          petugas,
          patientLabel,
          directionLabel,
        };
      });
    });

    const totalTransactions = exportSource.length;
    const totalItems = exportRows.length;
    const totalIncomingTransactions = exportSource.filter((row) => normalizeTransactionDirection(row.transactionLabel) === "IN").length;
    const totalOutgoingTransactions = exportSource.filter((row) => normalizeTransactionDirection(row.transactionLabel) === "OUT").length;
    const groupedRows = groupExportDetailRows(exportRows);
    const periodLabel = getExportDateRangeLabel(exportRows);

    const summaryHtml = `
      <table class="summary">
        <tr><td class="summary-label">Total Transaksi</td><td class="summary-value">${totalTransactions} transaksi</td></tr>
        <tr><td class="summary-label">Total Item</td><td class="summary-value">${totalItems} item</td></tr>
        <tr><td class="summary-label">Total Barang Masuk</td><td class="summary-value">${formatSpreadsheetNumber(totalIncomingTransactions, 0)} transaksi</td></tr>
        <tr><td class="summary-label">Total Barang Keluar</td><td class="summary-value">${formatSpreadsheetNumber(totalOutgoingTransactions, 0)} transaksi</td></tr>
      </table>
    `;

    const bodyRows = groupedRows
      .map((group) =>
        group
          .map((row, rowIndex) => {
            const sharedCells = rowIndex === 0
              ? `
                <td class="text-strong" rowspan="${group.length}">${escapeSpreadsheetHtml(row.dateLabel)}</td>
                <td class="text-strong" rowspan="${group.length}">${escapeSpreadsheetHtml(row.transactionId)}</td>
              `
              : "";
            const categoryCell = rowIndex === 0
              ? `<td rowspan="${group.length}">${escapeSpreadsheetHtml(formatExportCategoryLabel(row.categoryName))}</td>`
              : "";
            const petugasCell = rowIndex === 0
              ? `<td rowspan="${group.length}">${escapeSpreadsheetHtml(row.petugas)}</td>`
              : "";
            const quantityCells =
              exportMode === "OUT"
                ? `<td class="number">${escapeSpreadsheetHtml(row.outgoingQty)}</td><td>${escapeSpreadsheetHtml(row.patientLabel)}</td>`
                : exportMode === "IN"
                  ? `<td class="number">${escapeSpreadsheetHtml(row.incomingQty)}</td>`
                  : `<td class="number">${escapeSpreadsheetHtml(row.incomingQty)}</td><td class="number">${escapeSpreadsheetHtml(row.outgoingQty)}</td>`;

            return `
          <tr>
            ${sharedCells}
            <td class="text-strong">${escapeSpreadsheetHtml(row.itemName)}</td>
            ${categoryCell}
            ${quantityCells}
            <td>${escapeSpreadsheetHtml(row.unit)}</td>
            ${petugasCell}
          </tr>`;
          })
          .join(""),
      )
      .join("");

    const html = buildSpreadsheetDocument({
      title: exportMode === "OUT"
        ? "LAPORAN BARANG KELUAR INSTALASI GIZI RSD BALUNG"
        : exportMode === "IN"
          ? "LAPORAN BARANG MASUK INSTALASI GIZI RSD BALUNG"
          : "LAPORAN RIWAYAT TRANSAKSI BARANG INSTALASI GIZI RSD BALUNG",
      subtitle: exportMode === "OUT"
        ? "Rekap barang keluar berdasarkan data transaksi pada sistem."
        : exportMode === "IN"
          ? "Rekap barang masuk berdasarkan data transaksi pada sistem."
          : "Rekap riwayat transaksi barang masuk dan keluar berdasarkan data pada sistem.",
      body: `
        <div class="title">
          ${
            exportMode === "OUT"
              ? "LAPORAN BARANG KELUAR INSTALASI GIZI RSD BALUNG"
              : exportMode === "IN"
                ? "LAPORAN BARANG MASUK INSTALASI GIZI RSD BALUNG"
                : "LAPORAN RIWAYAT TRANSAKSI BARANG INSTALASI GIZI RSD BALUNG"
          }
        </div>
        <div class="subtitle">
          ${
            exportMode === "OUT"
              ? "Rekap barang keluar berdasarkan data transaksi pada sistem."
              : exportMode === "IN"
                ? "Rekap barang masuk berdasarkan data transaksi pada sistem."
                : "Rekap riwayat transaksi barang masuk dan keluar berdasarkan data pada sistem."
          }
        </div>

        <table class="no-border section-gap">
          <tr>
            <td style="width: 34%; padding: 0 12px 12px 0;">${summaryHtml}</td>
            <td style="width: 66%; padding: 0 0 12px 0;">
              <table>
                <tr><td class="section" colspan="2">RINGKASAN FILTER</td></tr>
                <tr class="head"><th>Jenis</th><th>Keterangan</th></tr>
                <tr><td>Periode</td><td>${escapeSpreadsheetHtml(periodLabel)}</td></tr>
                <tr><td>Jenis Transaksi</td><td>${escapeSpreadsheetHtml(exportMode === "MIXED" ? "Campuran" : "Satu jenis transaksi")}</td></tr>
                <tr><td>Petugas</td><td>${escapeSpreadsheetHtml(`${new Set(exportSource.map((row) => row.userLabel)).size} user penginput`)}</td></tr>
              </table>
            </td>
          </tr>
        </table>

        <table>
          <tr class="head">
            <th>Tanggal</th>
            <th>ID Transaksi</th>
            <th>Nama Bahan</th>
            <th>Kategori</th>
            ${
              exportMode === "OUT"
                ? "<th>Jumlah Keluar</th><th>Satuan</th><th>Jumlah Pasien</th>"
                : exportMode === "IN"
                  ? "<th>Jumlah Masuk</th><th>Satuan</th>"
                  : "<th>Jumlah Masuk</th><th>Jumlah Keluar</th>"
            }
            ${exportMode === "OUT" ? "" : "<th>Satuan</th>"}
            <th>Petugas</th>
          </tr>
          ${bodyRows}
        </table>
      `,
    });

    const filename = buildExportFilename(
      exportMode === "OUT"
        ? "laporan-barang-keluar"
        : exportMode === "IN"
          ? "laporan-barang-masuk"
          : "riwayat-transaksi-barang",
    );

    downloadSpreadsheetHtml(filename, html);
  }

  return (
    <>
      <div className="space-y-6">
        <div>
          <h1 className="text-[22px] font-semibold text-gray-900">Riwayat Transaksi Barang</h1>
        </div>

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
            ariaLabel="Rentang tanggal riwayat transaksi"
            className="min-w-[240px]"
            endDate={dateRange.endDate}
            onChange={setDateRange}
            placeholder="dd/mm/yyyy"
            startDate={dateRange.startDate}
          />
            <ThemedSelect
              className="h-10 min-w-[150px] text-sm"
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
            items={items}
            reason={revisionReason}
            typeMap={typeMap}
            unitMap={unitMap}
            updateRevisionRow={updateRevisionRow}
            updateReason={setRevisionReason}
            addRevisionRow={addRevisionRow}
            removeRevisionRow={removeRevisionRow}
            onClose={() => {
              setRevisionState(null);
              setRevisionReason("");
              setStockShortageMessage("");
              setReasonWarningMessage("");
            }}
            onSubmit={() => void submitRevision()}
            saving={savingRevision}
          />
        ) : null}
      </div>

      <SuccessModal
        open={successOpen}
        title="Berhasil"
        headline="Revisi Transaksi Berhasil Diajukan"
        message="Perubahan transaksi telah dikirim ke backend sebagai revisi."
        onClose={() => setSuccessOpen(false)}
      />

      <SuccessModal
        open={Boolean(stockShortageMessage)}
        title="Stok Kurang"
        headline="Stok Bahan Kurang"
        message={stockShortageMessage || ""}
        tone="danger"
        icon={<AlertTriangle size={36} strokeWidth={2.1} />}
        onClose={() => setStockShortageMessage("")}
      />

      <SuccessModal
        open={Boolean(reasonWarningMessage)}
        title="Peringatan"
        headline="Alasan Revisi Wajib Diisi"
        message={reasonWarningMessage || ""}
        tone="danger"
        icon={<AlertTriangle size={36} strokeWidth={2.1} />}
        onClose={() => setReasonWarningMessage("")}
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
  reason,
  typeMap,
  unitMap,
  updateRevisionRow,
  updateReason,
  addRevisionRow,
  removeRevisionRow,
  onClose,
  onSubmit,
  saving,
}: {
  transaction: TransactionRow;
  rows: RevisionRow[];
  items: ItemRow[];
  reason: string;
  typeMap: Map<number, string>;
  unitMap: Map<number, string>;
  updateRevisionRow: (id: number, updates: Partial<RevisionRow>) => void;
  updateReason: (value: string) => void;
  addRevisionRow: () => void;
  removeRevisionRow: (id: number) => void;
  onClose: () => void;
  onSubmit: () => void;
  saving: boolean;
}) {
  const typeLabel = typeMap.get(transaction.type_id) ?? "Transaksi";
  const allowedCategoryMode = useMemo(() => getAllowedItemCategoryMode(rows), [rows]);
  const selectableItems = useMemo(() => {
    if (allowedCategoryMode === "ALL") return items;
    return items.filter((item) => matchesAllowedItemCategory(item.category?.name, allowedCategoryMode));
  }, [allowedCategoryMode, items]);
  const selectedItemIds = useMemo(
    () => new Set(rows.filter((row) => row.item_id > 0).map((row) => row.item_id)),
    [rows],
  );

  function getRowItemOptions(currentRow: RevisionRow) {
    return selectableItems
      .filter((item) => item.id === currentRow.item_id || !selectedItemIds.has(item.id))
      .map((item) => ({
        id: item.id,
        label: item.name,
        unit:
          item.item_unit_base?.name ??
          (item.item_unit_base_id ? unitMap.get(item.item_unit_base_id) : undefined) ??
          item.unit_base,
      }));
  }

  function getRowHighlightClass(currentRow: RevisionRow) {
    const isHighlighted = currentRow.isPersisted
      ? Number(currentRow.qty) !== currentRow.originalQty
      : currentRow.item_id > 0 || Number(currentRow.qty) > 0 || currentRow.item_name.trim().length > 0;
    return isHighlighted ? "border-[#BFDBFE] bg-[#EFF6FF]" : "border-[#E2E8F0] bg-[#FCFDFE]";
  }

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
          <div className="rounded-2xl border border-[#D7E0EE] bg-white p-4 shadow-sm">
            <label className="mb-2 block text-sm font-semibold text-[#16213E]">
              Alasan Revisi <span className="text-red-500">*</span>
            </label>
            <textarea
              value={reason}
              onChange={(e) => updateReason(e.target.value)}
              placeholder="Tuliskan alasan revisi transaksi..."
              className="min-h-[96px] w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
            />
          </div>

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
                    className={`grid grid-cols-12 items-center gap-4 rounded-2xl border p-3 ${getRowHighlightClass(row)}`}
                  >
                    <div className="col-span-5">
                      <SearchableItemSelect
                        options={getRowItemOptions(row)}
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
                          const item = selectableItems.find((it) => it.id === itemId);
                          if (!item) return;
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
                          onChange={(e) => updateRevisionRow(row.id, { qty: e.target.value })}
                          className="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-center text-base outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
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
            <span className="font-semibold text-[#16213E]">TR-{String(transaction.id).padStart(4, "0")}</span> |
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

function getUserLabel(
  userId: number | null | undefined,
  userMap: Map<number, string>,
  currentUserId?: number,
  currentUserUsername?: string,
) {
  if (userId == null) return "-";
  if (currentUserId === userId && currentUserUsername) return currentUserUsername;
  return userMap.get(userId) ?? `User #${userId}`;
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

function getRevisionStockShortageMessage(rows: RevisionRow[], items: ItemRow[], isOutgoing: boolean) {
  if (!isOutgoing) return "";

  const itemMap = new Map(items.map((item) => [item.id, item]));
  const shortages = rows
    .map((row) => {
      const item = itemMap.get(row.item_id);
      if (!item) return null;

      const currentStock = Number(item.qty ?? 0) || 0;
      const conversionBase = Number(item.conversion_base ?? 1) || 1;
      const revisedBaseQty = Number(row.qty ?? 0) * (row.input_unit === "convert" ? conversionBase : 1);
      const originalBaseQty = Number(row.originalQty ?? 0) || 0;
      const additionalQty = Math.max(0, revisedBaseQty - originalBaseQty);

      if (additionalQty > currentStock) {
        return item.name;
      }
      return null;
    })
    .filter((value): value is string => Boolean(value));

  if (shortages.length === 0) return "";
  const uniqueNames = Array.from(new Set(shortages));
  return `Stok bahan kurang untuk bahan ini: ${uniqueNames.join(", ")}`;
}
