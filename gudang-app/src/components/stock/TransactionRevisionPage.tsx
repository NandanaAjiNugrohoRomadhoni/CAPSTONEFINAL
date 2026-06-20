"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import sdk from "@/lib";
import {
  AdminPageHeading,
  ExportButton,
  Pagination,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import DateRangePicker from "@/components/filters/DateRangePicker";
import { formatDate, getErrorMessage, resolveDetailItemName, resolveDetailUnit } from "@/lib/admin-utils";
import { isIsoDateInRange } from "@/lib/date-range";
import {
  buildSpreadsheetDocument,
  downloadSpreadsheetHtml,
  escapeSpreadsheetHtml,
  formatSpreadsheetNumber,
} from "@/lib/spreadsheet-export";
import { buildExportFilename } from "@/lib/export-filename";
import { X } from "lucide-react";

type TransactionRow = Awaited<ReturnType<typeof sdk.stockTransactions.list>>["data"][number];
type DetailRow = Awaited<ReturnType<typeof sdk.stockTransactions.details>>["data"][number];
type ItemUnitRow = Awaited<ReturnType<typeof sdk.itemUnits.list>>["data"][number];
type TransactionTypeRow = Awaited<ReturnType<typeof sdk.transactionTypes.list>>["data"][number];
type StatusRow = Awaited<ReturnType<typeof sdk.approvalStatuses.list>>["data"][number];
type TransactionRevisionRow = TransactionRow & {
  user_name?: string | null;
  user?: { name?: string | null; username?: string | null } | null;
  notes?: string | null;
  description?: string | null;
  revision_reason?: string | null;
  rejection_reason?: string | null;
};

type TransactionRevisionPageProps = {
  title: string;
  subtitle: string;
  role: "admin" | "gudang";
};

export default function TransactionRevisionPage({
  title,
  subtitle,
  role,
}: Readonly<TransactionRevisionPageProps>) {
  const [revisions, setRevisions] = useState<TransactionRevisionRow[]>([]);
  const [currentPage, setCurrentPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [dateRange, setDateRange] = useState({ startDate: "", endDate: "" });
  const [selectedStatus, setSelectedStatus] = useState("");

  const [detailTransaction, setDetailTransaction] = useState<TransactionRevisionRow | null>(null);
  const [detailRows, setDetailRows] = useState<
    Array<{
      itemId: number;
      itemName: string;
      unit: string;
      previousQty: number;
      revisedQty: number;
      changed: boolean;
    }>
  >([]);
  const [unitMap, setUnitMap] = useState<Map<number, string>>(new Map());
  const [typeMap, setTypeMap] = useState<Map<number, string>>(new Map());
  const [statuses, setStatuses] = useState<StatusRow[]>([]);
  const [categoryMap, setCategoryMap] = useState<Map<number, string>>(new Map());
  const [loadingDetails, setLoadingDetails] = useState(false);
  const [confirmRevision, setConfirmRevision] = useState<TransactionRevisionRow | null>(null);
  const [confirmRows, setConfirmRows] = useState<
    Array<{
      itemId: number;
      itemName: string;
      unit: string;
      previousQty: number;
      revisedQty: number;
    }>
  >([]);
  const [loadingConfirmRows, setLoadingConfirmRows] = useState(false);
  const [confirmingRevision, setConfirmingRevision] = useState(false);
  const [rejectingRevision, setRejectingRevision] = useState(false);
  const [rejectReasonOpen, setRejectReasonOpen] = useState(false);
  const [rejectReason, setRejectReason] = useState("");

  const PAGE_SIZE = 10;

  const loadRevisions = useCallback(async () => {
    setLoading(true);
    try {
      const response = await sdk.stockTransactions.list({
        perPage: 100,
        sortBy: "created_at",
        sortDir: "DESC",
      });
      const revisionRows = (response.data ?? []).filter((row) => {
        const isRevision = row.parent_transaction_id !== null;
        if (!isRevision) return false;
        return true;
      });
      setRevisions(revisionRows as TransactionRevisionRow[]);
    } catch (err) {
      setError(getErrorMessage(err, "Gagal memuat daftar pengajuan revisi."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadRevisions();
  }, [loadRevisions]);

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
        setUnitMap(new Map((unitResponse.data ?? []).map((unit: ItemUnitRow) => [unit.id, unit.name])));
        setTypeMap(new Map((typeResponse.data ?? []).map((type: TransactionTypeRow) => [type.id, type.name])));
        setStatuses(statusResponse.data ?? []);
      } catch {
        if (!cancelled) {
          setUnitMap(new Map());
          setTypeMap(new Map());
          setStatuses([]);
        }
      }
    }
    void loadLookups();
    return () => {
      cancelled = true;
    };
  }, []);

  const statusMap = useMemo(
    () => new Map(statuses.map((status) => [status.id, status.name])),
    [statuses],
  );

  const getStatusLabel = useCallback((statusId: number): string => {
    return statusMap.get(statusId) ?? `Status #${statusId}`;
  }, [statusMap]);

  function localizeStatusLabel(statusLabel: string): string {
    const normalized = statusLabel.trim().toUpperCase();
    if (normalized === "APPROVED") return "Disetujui";
    if (normalized === "REJECTED") return "Ditolak";
    if (normalized === "PENDING") return "Menunggu Konfirmasi";
    return statusLabel;
  }

  function getStatusTone(statusLabel: string): string {
    const normalized = statusLabel.toLowerCase();
    if (normalized.includes("setujui") || normalized.includes("approve")) return "approved";
    if (normalized.includes("tolak") || normalized.includes("reject")) return "rejected";
    return "pending";
  }

  function isPendingStatus(statusLabel: string): boolean {
    const normalized = statusLabel.trim().toLowerCase();
    return normalized.includes("pending") || normalized.includes("menunggu");
  }

  function formatCategoryLabel(values: Array<string | null | undefined>) {
    const normalizedValues = Array.from(
      new Set(
        values
          .map((value) => value?.trim())
          .filter((value): value is string => Boolean(value))
          .map((value) => value.toLowerCase()),
      ),
    );

    if (normalizedValues.length === 0) return "-";

    const labelValues = normalizedValues.map((value) =>
      value
        .split(/\s+/)
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" "),
    );

    if (labelValues.length === 1) return labelValues[0];
    if (labelValues.length === 2) return `${labelValues[0]} dan ${labelValues[1]}`;
    return `${labelValues.slice(0, -1).join(", ")} dan ${labelValues[labelValues.length - 1]}`;
  }

  function getRevisionUserLabel(revision: TransactionRevisionRow) {
    return revision.user_name || revision.user?.name || revision.user?.username || `User #${revision.user_id}`;
  }

  function getRevisionReasonLabel(revision: TransactionRevisionRow) {
    const reason = revision.reason ?? revision.notes ?? revision.description ?? revision.revision_reason ?? "";
    return reason.trim() || "Alasan revisi belum tersedia.";
  }

  function getRevisionRejectionReasonLabel(revision: TransactionRevisionRow) {
    const reason = revision.rejection_reason ?? "";
    return reason.trim() || "Alasan penolakan belum tersedia.";
  }

  function getRevisionStatusLabel(revision: TransactionRevisionRow) {
    return localizeStatusLabel(getStatusLabel(revision.approval_status_id));
  }

  function getRevisionChangeStatus(previousQty: number, revisedQty: number) {
    return previousQty !== revisedQty ? "Ada Perubahan" : "Tidak Ada";
  }

  function getRevisionComparisonBaseline(revision: TransactionRevisionRow) {
    if (revision.parent_transaction_id == null) return null;
    const previousRevision = revisions
      .filter((row) => row.parent_transaction_id === revision.parent_transaction_id && row.id !== revision.id)
      .sort((left, right) => new Date(right.created_at).getTime() - new Date(left.created_at).getTime())[0];
    return previousRevision ?? null;
  }

  async function openDetails(transaction: TransactionRevisionRow) {
    setDetailTransaction(transaction);
    setLoadingDetails(true);
    try {
      const baselineRevision = getRevisionComparisonBaseline(transaction);
      const [revisionResponse, baselineResponse] = await Promise.all([
        sdk.stockTransactions.details(transaction.id),
        baselineRevision
          ? sdk.stockTransactions.details(baselineRevision.id)
          : transaction.parent_transaction_id
            ? sdk.stockTransactions.details(transaction.parent_transaction_id)
          : Promise.resolve({ data: [] as DetailRow[] }),
      ]);
      const rows = revisionResponse.data ?? [];
      const parentRows = baselineResponse.data ?? [];

      const parentQtyMap = new Map<number, number>();
      parentRows.forEach((detail) => {
        parentQtyMap.set(detail.item_id, Number(detail.input_qty ?? detail.qty ?? 0));
      });

      const comparisonRows = rows.map((detail) => {
        const previousQty = parentQtyMap.get(detail.item_id) ?? 0;
        const revisedQty = Number(detail.input_qty ?? detail.qty ?? 0);
        return {
          itemId: detail.item_id,
          itemName: resolveDetailItemName(detail),
          unit: resolveDetailUnit(detail, undefined, unitMap),
          previousQty,
          revisedQty,
          changed: previousQty !== revisedQty,
        };
      });
      setDetailRows(comparisonRows);
    } catch (err) {
      setError(getErrorMessage(err, "Gagal memuat detail revisi."));
    } finally {
      setLoadingDetails(false);
    }
  }

  async function openConfirmModal(revision: TransactionRevisionRow) {
    if (revision.parent_transaction_id == null) return;
    setConfirmRevision(revision);
    setLoadingConfirmRows(true);
    try {
      const baselineRevision = getRevisionComparisonBaseline(revision);
      const [revisionDetailResponse, parentDetailResponse] = await Promise.all([
        sdk.stockTransactions.details(revision.id),
        baselineRevision
          ? sdk.stockTransactions.details(baselineRevision.id)
          : sdk.stockTransactions.details(revision.parent_transaction_id),
      ]);

      const revisionDetails = revisionDetailResponse.data ?? [];
      const parentDetails = parentDetailResponse.data ?? [];

      const parentQtyMap = new Map<number, number>();
      parentDetails.forEach((detail) => {
        parentQtyMap.set(detail.item_id, Number(detail.input_qty ?? detail.qty ?? 0));
      });

      const rows = revisionDetails.map((detail) => {
        return {
          itemId: detail.item_id,
          itemName: resolveDetailItemName(detail),
          unit: resolveDetailUnit(detail, undefined, unitMap),
          previousQty: parentQtyMap.get(detail.item_id) ?? 0,
          revisedQty: Number(detail.input_qty ?? detail.qty ?? 0),
        };
      });
      setConfirmRows(rows);
    } catch (err) {
      setError(getErrorMessage(err, "Gagal memuat detail konfirmasi revisi."));
      setConfirmRevision(null);
      setConfirmRows([]);
    } finally {
      setLoadingConfirmRows(false);
    }
  }

  async function confirmRevisionApproval() {
    if (!confirmRevision) return;
    setConfirmingRevision(true);
    try {
      await sdk.stockTransactions.approve(confirmRevision.id);
      setConfirmRevision(null);
      setConfirmRows([]);
      await loadRevisions();
    } catch (err) {
      setError(getErrorMessage(err, "Gagal mengonfirmasi revisi."));
    } finally {
      setConfirmingRevision(false);
    }
  }

  function openRejectReasonModal() {
    setRejectReason("");
    setRejectReasonOpen(true);
  }

  async function confirmRevisionRejection() {
    if (!confirmRevision) return;
    const trimmedReason = rejectReason.trim();
    if (!trimmedReason) {
      setError("Alasan penolakan wajib diisi sebelum revisi ditolak.");
      return;
    }

    setRejectingRevision(true);
    try {
      await sdk.stockTransactions.reject(confirmRevision.id, { reason: trimmedReason });
      setConfirmRevision(null);
      setRejectReasonOpen(false);
      setRejectReason("");
      setConfirmRows([]);
      await loadRevisions();
    } catch (err) {
      setError(getErrorMessage(err, "Gagal menolak revisi."));
    } finally {
      setRejectingRevision(false);
    }
  }

  const visibleRevisions = useMemo(() => {
    const query = search.trim().toLowerCase();
    return revisions.filter((revision) => {
      const revisionId = `rev-${String(revision.id).padStart(4, "0")}`.toLowerCase();
      const parentId = `tr-${String(revision.parent_transaction_id).padStart(4, "0")}`.toLowerCase();
      const userLabel = getRevisionUserLabel(revision).toLowerCase();
      const statusLabel = localizeStatusLabel(getStatusLabel(revision.approval_status_id)).toLowerCase();

      const matchesSearch =
        query.length === 0 ||
        revisionId.includes(query) ||
        parentId.includes(query) ||
        userLabel.includes(query) ||
        statusLabel.includes(query);
      const matchesDate = isIsoDateInRange(revision.transaction_date.slice(0, 10), dateRange);
      const matchesStatus = !selectedStatus || String(revision.approval_status_id) === selectedStatus;

      return matchesSearch && matchesDate && matchesStatus;
    });
  }, [dateRange, revisions, search, selectedStatus, getStatusLabel]);

  const filteredTotalPages = Math.max(1, Math.ceil(visibleRevisions.length / PAGE_SIZE));
  const paginatedRevisions = useMemo(() => {
    const start = (currentPage - 1) * PAGE_SIZE;
    return visibleRevisions.slice(start, start + PAGE_SIZE);
  }, [currentPage, visibleRevisions]);

  const detailStatusLabel = detailTransaction ? getRevisionStatusLabel(detailTransaction) : "";
  const isRejectedRevision = detailStatusLabel === "Ditolak";

  useEffect(() => {
    let cancelled = false;

    async function loadCategories() {
      const missingRows = paginatedRevisions.filter((revision) => !categoryMap.has(revision.id));
      if (missingRows.length === 0) return;

      const nextEntries = await Promise.all(
        missingRows.map(async (revision) => {
          try {
            const response = await sdk.stockTransactions.details(revision.id);
            const categories = (response.data ?? []).map((detail) => detail.item_category_name);
            return [revision.id, formatCategoryLabel(categories)] as const;
          } catch {
            return [revision.id, "-"] as const;
          }
        }),
      );

      if (cancelled) return;
      setCategoryMap((prev) => {
        const next = new Map(prev);
        nextEntries.forEach(([id, label]) => next.set(id, label));
        return next;
      });
    }

    void loadCategories();
    return () => {
      cancelled = true;
    };
  }, [categoryMap, paginatedRevisions]);

  useEffect(() => {
    setCurrentPage(1);
  }, [dateRange, search, selectedStatus]);

  useEffect(() => {
    if (currentPage > filteredTotalPages) {
      setCurrentPage(filteredTotalPages);
    }
  }, [currentPage, filteredTotalPages]);

  async function handleExport() {
    if (typeof window === "undefined" || visibleRevisions.length === 0) {
      setError("Belum ada data pengajuan revisi yang bisa diexport dari hasil filter saat ini.");
      return;
    }

    const detailCache = new Map<number, DetailRow[]>();
    await Promise.all(
      visibleRevisions.map(async (revision) => {
        const baselineRevision = getRevisionComparisonBaseline(revision);
        const [revisionResponse, baselineResponse] = await Promise.all([
          sdk.stockTransactions.details(revision.id),
          baselineRevision
            ? sdk.stockTransactions.details(baselineRevision.id)
            : revision.parent_transaction_id
              ? sdk.stockTransactions.details(revision.parent_transaction_id)
              : Promise.resolve({ data: [] as DetailRow[] }),
        ]);

        detailCache.set(revision.id, revisionResponse.data ?? []);
        if (baselineRevision) {
          detailCache.set(baselineRevision.id, baselineResponse.data ?? []);
        } else if (revision.parent_transaction_id != null) {
          detailCache.set(revision.parent_transaction_id, baselineResponse.data ?? []);
        }
      }),
    );

    const rowGroups = visibleRevisions.flatMap((revision) => {
      const revisionDetails = detailCache.get(revision.id) ?? [];
      const baselineRevision = getRevisionComparisonBaseline(revision);
      const baselineDetails = baselineRevision
        ? detailCache.get(baselineRevision.id) ?? []
        : revision.parent_transaction_id
          ? detailCache.get(revision.parent_transaction_id) ?? []
          : [];

      const baselineQtyMap = new Map<number, number>();
      baselineDetails.forEach((detail) => {
        baselineQtyMap.set(detail.item_id, Number(detail.input_qty ?? detail.qty ?? 0));
      });
      const categoryLabel = formatCategoryLabel(revisionDetails.map((detail) => detail.item_category_name));

      return revisionDetails.map((detail, index) => {
        const revisedQty = Number(detail.input_qty ?? detail.qty ?? 0);
        const previousQty = baselineQtyMap.get(detail.item_id) ?? 0;
        return {
          revision,
          detail,
          rowIndex: index,
          totalRows: revisionDetails.length,
          previousQty,
          revisedQty,
          changeStatus: getRevisionChangeStatus(previousQty, revisedQty),
          categoryLabel,
        };
      });
    });

    const periodLabel =
      dateRange.startDate && dateRange.endDate
        ? `${formatDate(dateRange.startDate)} s/d ${formatDate(dateRange.endDate)}`
        : "Semua tanggal";
    const printedAt = formatDate(new Date().toISOString().slice(0, 10));

    const statusCounts = visibleRevisions.reduce(
      (accumulator, revision) => {
        const status = getRevisionStatusLabel(revision);
        if (status === "Disetujui") accumulator.approved += 1;
        else if (status === "Ditolak") accumulator.rejected += 1;
        else accumulator.pending += 1;
        return accumulator;
      },
      { approved: 0, rejected: 0, pending: 0 },
    );

    const summaryHtml = `
      <table class="summary">
        <tr><td class="summary-label">Total Revisi</td><td class="summary-value">${formatSpreadsheetNumber(visibleRevisions.length, 0)} dokumen</td></tr>
        <tr><td class="summary-label">Total Item</td><td class="summary-value">${formatSpreadsheetNumber(rowGroups.length, 0)} item</td></tr>
        <tr><td class="summary-label">Disetujui</td><td class="summary-value">${formatSpreadsheetNumber(statusCounts.approved, 0)} item</td></tr>
        <tr><td class="summary-label">Ditolak</td><td class="summary-value">${formatSpreadsheetNumber(statusCounts.rejected, 0)} item</td></tr>
        <tr><td class="summary-label">Menunggu</td><td class="summary-value">${formatSpreadsheetNumber(statusCounts.pending, 0)} item</td></tr>
      </table>
    `;

    const rowsHtml = rowGroups
      .map((row, index) => {
        const revisionLabel = `REV-${String(row.revision.id).padStart(4, "0")}`;
        const transactionLabel = `TR-${String(row.revision.parent_transaction_id ?? row.revision.id).padStart(4, "0")}`;
        const dateLabel = formatDate(row.revision.transaction_date);
        const unitLabel = row.detail.satuan ?? "-";
        const reasonLabel = getRevisionReasonLabel(row.revision);
        const statusLabel = getRevisionStatusLabel(row.revision);
        const petugasLabel = getRevisionUserLabel(row.revision);
        const rowspan = row.totalRows;
        const isFirstRow = row.rowIndex === 0;

        return `
          <tr>
            ${isFirstRow ? `<td class="text-center" rowspan="${rowspan}">${formatSpreadsheetNumber(index + 1, 0)}</td>` : ""}
            ${isFirstRow ? `<td rowspan="${rowspan}">${escapeSpreadsheetHtml(dateLabel)}</td>` : ""}
            ${isFirstRow ? `<td class="text-strong" rowspan="${rowspan}">${escapeSpreadsheetHtml(revisionLabel)}</td>` : ""}
            ${isFirstRow ? `<td class="text-strong" rowspan="${rowspan}">${escapeSpreadsheetHtml(transactionLabel)}</td>` : ""}
            <td class="text-strong">${escapeSpreadsheetHtml(row.detail.item_name ?? "-")}</td>
            ${isFirstRow ? `<td class="${row.changeStatus === "Ada Perubahan" ? "danger" : "safe"}" rowspan="${rowspan}">${escapeSpreadsheetHtml(row.changeStatus)}</td>` : ""}
            ${isFirstRow ? `<td rowspan="${rowspan}">${escapeSpreadsheetHtml(row.categoryLabel)}</td>` : ""}
            <td>${escapeSpreadsheetHtml(unitLabel)}</td>
            <td class="number">${escapeSpreadsheetHtml(formatSpreadsheetNumber(row.previousQty, 0))}</td>
            <td class="number">${escapeSpreadsheetHtml(formatSpreadsheetNumber(row.revisedQty, 0))}</td>
            ${isFirstRow ? `<td rowspan="${rowspan}">${escapeSpreadsheetHtml(reasonLabel)}</td>` : ""}
            ${isFirstRow ? `<td class="${statusLabel === "Disetujui" ? "safe" : statusLabel === "Ditolak" ? "danger" : "warning"}" rowspan="${rowspan}">${escapeSpreadsheetHtml(statusLabel)}</td>` : ""}
            ${isFirstRow ? `<td rowspan="${rowspan}">${escapeSpreadsheetHtml(petugasLabel)}</td>` : ""}
          </tr>
        `;
      })
      .join("");

    const html = buildSpreadsheetDocument({
      title: "LAPORAN PENGAJUAN REVISI TRANSAKSI BARANG INSTALASI GIZI RSD BALUNG",
      subtitle: `Periode: ${periodLabel} | Tanggal Cetak: ${printedAt}`,
      body: `
        <div class="title">LAPORAN PENGAJUAN REVISI TRANSAKSI BARANG INSTALASI GIZI RSD BALUNG</div>
        <div class="subtitle">Rekap pengajuan revisi transaksi barang sebelum proses persetujuan.</div>

        <table class="no-border section-gap">
          <tr>
            <td style="width: 40%; padding: 0 12px 12px 0;">${summaryHtml}</td>
            <td style="width: 60%; padding: 0 0 12px 0;">
              <table>
                <tr><td class="section" colspan="2">RINGKASAN STATUS PENGAJUAN</td></tr>
                <tr class="head"><th>Status</th><th>Jumlah</th></tr>
                <tr><td>Disetujui</td><td class="rank">${formatSpreadsheetNumber(statusCounts.approved, 0)}</td></tr>
                <tr><td>Ditolak</td><td class="rank">${formatSpreadsheetNumber(statusCounts.rejected, 0)}</td></tr>
                <tr><td>Menunggu Konfirmasi</td><td class="rank">${formatSpreadsheetNumber(statusCounts.pending, 0)}</td></tr>
              </table>
            </td>
          </tr>
        </table>

        <table>
          <tr class="head">
            <th>No</th>
            <th>Tanggal</th>
            <th>ID Revisi</th>
            <th>ID Transaksi</th>
            <th>Nama Bahan</th>
            <th>Status Perubahan</th>
            <th>Kategori</th>
            <th>Satuan</th>
            <th>Qty Sebelum</th>
            <th>Qty Perubahan</th>
            <th>Keterangan</th>
            <th>Status Pengajuan Revisi</th>
            <th>Petugas</th>
          </tr>
          ${rowsHtml}
        </table>
      `,
    });

    downloadSpreadsheetHtml(
      buildExportFilename(role === "admin" ? "moderasi-revisi-transaksi" : "pengajuan-revisi-transaksi"),
      html,
    );
  }

  return (
    <div className="flex flex-col gap-6 p-6">
      <AdminPageHeading title={title} subtitle={subtitle} />

      {error && (
        <div className="rounded-xl bg-red-50 p-4 text-sm text-red-600 border border-red-100 flex justify-between items-center">
          <span>{error}</span>
          <button onClick={() => setError(null)} className="text-red-400 hover:text-red-600">
            <X size={16} />
          </button>
        </div>
      )}

      <SurfaceCard className="overflow-hidden rounded-[28px] border border-[#D7E0EE] bg-white shadow-[0_16px_40px_rgba(15,23,42,0.08)]">
        <div className="flex flex-wrap items-center gap-3 border-b border-[#D7E0EE] bg-gradient-to-r from-[#F8FBFF] to-[#F8FAFC] px-5 py-4">
          <div className="w-full max-w-[260px]">
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Cari revisi"
              className="h-12 w-full rounded-xl border border-[#D7E0EE] bg-white px-4 text-base text-[#334155] outline-none placeholder:text-[#94A3B8] focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
            />
          </div>
          <DateRangePicker
            ariaLabel="Rentang tanggal pengajuan revisi"
            className="min-w-[240px]"
            endDate={dateRange.endDate}
            onChange={setDateRange}
            placeholder="dd/mm/yyyy"
            startDate={dateRange.startDate}
          />
          <ThemedSelect
            className="min-w-[210px]"
            value={selectedStatus}
            onChange={setSelectedStatus}
            options={[
              { value: "", label: "Semua Status" },
              ...statuses.map((status) => ({
                value: String(status.id),
                label: localizeStatusLabel(status.name),
              })),
            ]}
          />
          <div className="ml-auto">
            <ExportButton onClick={handleExport}>Export Riwayat</ExportButton>
          </div>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-left">
            <thead>
              <tr className="border-b border-slate-100 bg-[#F8FAFC]">
                <th className="px-6 py-4 text-sm font-semibold uppercase tracking-wider text-slate-500">ID Revisi</th>
                <th className="px-6 py-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Tanggal</th>
                <th className="px-6 py-4 text-sm font-semibold uppercase tracking-wider text-slate-500">User</th>
                <th className="px-6 py-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Transaksi</th>
                <th className="px-6 py-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Kategori Bahan</th>
                <th className="px-6 py-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Status</th>
                <th className="px-6 py-4 text-sm font-semibold uppercase tracking-wider text-slate-500 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-base text-slate-600">
              {loading ? (
                <tr>
                  <td colSpan={7} className="px-6 py-10 text-center text-slate-400">Memuat data...</td>
                </tr>
              ) : paginatedRevisions.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-6 py-10 text-center text-slate-400">Tidak ada pengajuan revisi.</td>
                </tr>
              ) : (
                paginatedRevisions.map((rev) => (
                  <tr
                    key={rev.id}
                    className="transition hover:bg-[#F8FBFF]"
                  >
                    <td className="px-6 py-5 font-medium text-slate-900">
                      REV-{String(rev.id).padStart(4, "0")}
                      <p className="mt-1 text-xs uppercase tracking-wide text-slate-400">
                        Transaksi: TR-{String(rev.parent_transaction_id ?? rev.id).padStart(4, "0")}
                      </p>
                    </td>
                    <td className="px-6 py-5">{formatDate(rev.transaction_date)}</td>
                    <td className="px-6 py-5">{getRevisionUserLabel(rev)}</td>
                    <td className="px-6 py-5 font-semibold text-[#16213E]">{normaliseTransactionLabel(typeMap.get(rev.type_id))}</td>
                    <td className="px-6 py-5">{categoryMap.get(rev.id) ?? "-"}</td>
                    <td className="px-6 py-5">
                      {role === "admin" && isPendingStatus(localizeStatusLabel(getStatusLabel(rev.approval_status_id))) ? (
                        <button
                          type="button"
                          className="inline-flex rounded-lg bg-[#2155CD] px-4 py-2 text-sm font-semibold text-white shadow-[0_8px_18px_rgba(33,85,205,0.22)] transition hover:bg-[#1D4ED8]"
                          onClick={() => void openConfirmModal(rev)}
                        >
                          Konfirmasi
                        </button>
                      ) : (
                        <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${
                          getStatusTone(localizeStatusLabel(getStatusLabel(rev.approval_status_id))) === "approved"
                            ? "bg-emerald-50 text-emerald-600"
                            : getStatusTone(localizeStatusLabel(getStatusLabel(rev.approval_status_id))) === "rejected"
                              ? "bg-red-50 text-red-600"
                              : "bg-amber-50 text-amber-600"
                        }`}>
                          {localizeStatusLabel(getStatusLabel(rev.approval_status_id))}
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-5">
                      <div className="flex items-center justify-center gap-2">
                        <button
                          type="button"
                          className="inline-flex rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                          onClick={() => void openDetails(rev)}
                        >
                          Detail
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        <Pagination
          currentPage={currentPage}
          totalPages={filteredTotalPages}
          onPageChange={setCurrentPage}
          totalLabel={`${visibleRevisions.length === 0 ? 0 : (currentPage - 1) * PAGE_SIZE + 1}-${Math.min(currentPage * PAGE_SIZE, visibleRevisions.length)} dari ${visibleRevisions.length} pengajuan`}
        />
      </SurfaceCard>

      {detailTransaction && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center px-4 py-4">
          <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" onClick={() => setDetailTransaction(null)} />
          <div className="relative flex max-h-[calc(100vh-2rem)] w-full max-w-[560px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.22)]">
            {/* Header */}
            <div className="flex items-start justify-between border-b border-[#CBD5E1] px-6 py-5">
              <div>
                <h2 className="text-[24px] font-bold text-[#0F172A]">Detail Revisi</h2>
                <p className="mt-1 text-sm font-medium text-[#475569]">
                  Perbandingan data sebelum dan sesudah revisi
                </p>
              </div>
              <button
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F1F5F9] text-[#64748B] transition hover:bg-[#DBEAFE] hover:text-[#1D4ED8]"
                onClick={() => setDetailTransaction(null)}
                type="button"
              >
                <X size={20} />
              </button>
            </div>

            {/* Content */}
            <div className="flex-1 space-y-4 overflow-y-auto px-5 py-4">
              <div className="rounded-xl border border-[#CBD5E1] bg-[#F8FAFC] p-4">
                {/* Column headers */}
                <div className="mb-3 grid grid-cols-12 gap-4 px-2 text-[12px] font-bold uppercase tracking-wider text-[#475569]">
                  <div className="col-span-4">Nama Bahan</div>
                  <div className="col-span-3 text-center">Qty Sebelumnya</div>
                  <div className="col-span-3 text-center">Qty Perubahan</div>
                  <div className="col-span-2">Satuan</div>
                </div>

                {/* Rows */}
                <div className="max-h-[42vh] space-y-2 overflow-y-auto pr-1">
                  {loadingDetails ? (
                    <div className="rounded-lg bg-white p-5 text-center text-sm font-medium text-[#64748B] shadow-sm">
                      Memuat detail...
                    </div>
                  ) : detailRows.length === 0 ? (
                    <div className="rounded-lg bg-white p-5 text-center text-sm font-medium text-[#64748B] shadow-sm">
                      Tidak ada detail bahan.
                    </div>
                  ) : (
                    detailRows.map((row, index) => {
                      const changed = row.changed;
                      return (
                        <div
                          key={`${row.itemId}-${index}`}
                          className={`grid grid-cols-12 items-center gap-3 rounded-lg px-3 py-3 shadow-sm border ${
                            changed ? "border-[#93C5FD] bg-[#EFF6FF]" : "border-[#E2E8F0] bg-white"
                          }`}
                        >
                          <div className="col-span-4">
                            <p className="text-[14px] font-semibold text-[#0F172A]">{row.itemName}</p>
                          </div>
                          <div className="col-span-3">
                            <div className="flex h-10 items-center justify-center rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] text-sm font-semibold text-[#0F172A]">
                              {row.previousQty}
                            </div>
                          </div>
                          <div className="col-span-3">
                            <div
                              className={`flex h-10 items-center justify-center rounded-lg text-sm ${
                                changed
                                  ? "border border-[#93C5FD] bg-[#EFF6FF] font-bold text-[#1D4ED8]"
                                  : "border border-[#CBD5E1] bg-[#F8FAFC] font-semibold text-[#0F172A]"
                              }`}
                            >
                              {row.revisedQty}
                            </div>
                          </div>
                          <div className="col-span-2 text-sm font-semibold text-[#334155]">
                            {row.unit}
                          </div>
                        </div>
                      );
                    })
                  )}
                </div>
              </div>

              {isRejectedRevision ? (
                <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700">
                  <div className="mb-1 text-xs font-bold uppercase tracking-wider text-red-500">
                    Alasan Penolakan Admin
                  </div>
                  <div className="leading-6">
                    {getRevisionRejectionReasonLabel(detailTransaction)}
                  </div>
                </div>
              ) : null}

              {/* Info bar */}
              <div className="rounded-2xl border border-[#CBD5E1] bg-[#EFF6FF] px-4 py-3 text-sm font-medium text-[#334155]">
                ID revisi:{" "}
                <span className="font-bold text-[#1E40AF]">REV-{String(detailTransaction.id).padStart(4, "0")}</span>
                {" | "}ID transaksi:{" "}
                <span className="font-bold text-[#1E40AF]">TR-{String(detailTransaction.parent_transaction_id).padStart(4, "0")}</span>
                {" "}| Tanggal{" "}
                <span className="font-semibold text-[#0F172A]">{formatDate(detailTransaction.transaction_date)}</span>
              </div>

              <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700">
                <div className="mb-1 text-xs font-bold uppercase tracking-wider text-red-500">
                  Alasan Revisi
                </div>
                <div className="leading-6">
                  {getRevisionReasonLabel(detailTransaction)}
                </div>
              </div>
            </div>

            {/* Footer */}
            <div className="flex justify-end border-t border-[#CBD5E1] px-5 py-4">
              <button
                className="rounded-xl border-2 border-[#2155CD] bg-white px-6 py-2.5 text-base font-semibold text-[#2155CD] transition hover:bg-[#EEF4FF]"
                onClick={() => setDetailTransaction(null)}
                type="button"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {confirmRevision && role === "admin" ? (
        <div className="fixed inset-0 z-[110] flex items-center justify-center px-4 py-4">
          <div className="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" onClick={() => setConfirmRevision(null)} />
          <div className="relative flex max-h-[calc(100vh-2rem)] w-full max-w-[760px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.22)]">
            <div className="flex items-start justify-between border-b border-[#CBD5E1] px-6 py-5">
              <div>
                <h2 className="text-[22px] font-bold text-[#0F172A]">Konfirmasi Revisi</h2>
                <p className="mt-1 text-sm font-medium text-[#475569]">
                  Tinjau perubahan qty sebelum menyetujui revisi transaksi.
                </p>
              </div>
              <button
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F1F5F9] text-[#64748B] transition hover:bg-[#DBEAFE] hover:text-[#1D4ED8]"
                onClick={() => setConfirmRevision(null)}
                type="button"
              >
                <X size={20} />
              </button>
            </div>

            <div className="flex-1 space-y-4 overflow-y-auto px-6 py-5">
              <div className="rounded-xl border border-[#CBD5E1] bg-[#F8FAFC] p-4">
                <div className="mb-3 grid grid-cols-12 gap-4 px-2 text-[12px] font-bold uppercase tracking-wider text-[#475569]">
                  <div className="col-span-4">Nama Bahan</div>
                  <div className="col-span-3 text-center">Qty Sebelumnya</div>
                  <div className="col-span-3 text-center">Qty Perubahan</div>
                  <div className="col-span-2">Satuan</div>
                </div>

                <div className="max-h-[46vh] space-y-2 overflow-y-auto pr-1">
                  {loadingConfirmRows ? (
                    <div className="rounded-lg bg-white p-6 text-center text-base font-medium text-[#64748B] shadow-sm">
                      Memuat perbandingan...
                    </div>
                  ) : confirmRows.length === 0 ? (
                    <div className="rounded-lg bg-white p-6 text-center text-base font-medium text-[#64748B] shadow-sm">
                      Tidak ada perubahan qty.
                    </div>
                  ) : (
                    confirmRows.map((row, index) => {
                      const changed = row.previousQty !== row.revisedQty;
                      return (
                      <div
                        key={`${row.itemId}-${index}`}
                        className={`grid grid-cols-12 items-center gap-3 rounded-lg border bg-white px-3 py-3 shadow-sm ${
                          changed ? "border-[#93C5FD] bg-[#EFF6FF]" : "border-[#E2E8F0]"
                        }`}
                      >
                        <div className="col-span-4 text-[14px] font-semibold text-[#0F172A]">{row.itemName}</div>
                        <div className="col-span-3">
                          <div className="flex h-10 items-center justify-center rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] text-sm font-semibold text-[#0F172A]">
                            {row.previousQty}
                          </div>
                        </div>
                        <div className="col-span-3">
                          <div className={`flex h-10 items-center justify-center rounded-lg text-sm ${
                            changed
                              ? "border border-[#93C5FD] bg-[#EFF6FF] font-bold text-[#1D4ED8]"
                              : "border border-[#CBD5E1] bg-[#F8FAFC] font-semibold text-[#0F172A]"
                          }`}>
                            {row.revisedQty}
                          </div>
                        </div>
                        <div className="col-span-2 text-sm font-semibold text-[#334155]">{row.unit}</div>
                      </div>
                    );
                    })
                  )}
                </div>
              </div>

              <div className="rounded-2xl border border-[#CBD5E1] bg-[#EFF6FF] px-4 py-3 text-sm font-medium text-[#334155]">
                Revisi: <span className="font-bold text-[#1E40AF]">REV-{String(confirmRevision.id).padStart(4, "0")}</span>
                {" | "}Parent: <span className="font-bold text-[#1E40AF]">TR-{String(confirmRevision.parent_transaction_id).padStart(4, "0")}</span>
                {" | "}Tanggal <span className="font-semibold text-[#0F172A]">{formatDate(confirmRevision.transaction_date)}</span>
              </div>

              <div className="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700">
                <div className="mb-1 text-xs font-bold uppercase tracking-wider text-red-500">
                  Alasan Revisi
                </div>
                <div className="leading-6">
                  {getRevisionReasonLabel(confirmRevision)}
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-[#CBD5E1] px-6 py-4">
              <button
                className="rounded-xl border-2 border-[#CBD5E1] bg-white px-6 py-2.5 text-base font-semibold text-[#475569] transition hover:bg-[#F8FAFC]"
                onClick={() => setConfirmRevision(null)}
                type="button"
                disabled={confirmingRevision || rejectingRevision}
              >
                Batal
              </button>
              <button
                className="rounded-xl bg-[#DC2626] px-6 py-2.5 text-base font-semibold text-white transition hover:bg-[#B91C1C] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={openRejectReasonModal}
                type="button"
                disabled={rejectingRevision || confirmingRevision || loadingConfirmRows || confirmRows.length === 0}
              >
                Tolak
              </button>
              <button
                className="rounded-xl bg-[#2155CD] px-6 py-2.5 text-base font-semibold text-white transition hover:bg-[#1D4ED8] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={() => void confirmRevisionApproval()}
                type="button"
                disabled={confirmingRevision || rejectingRevision || loadingConfirmRows || confirmRows.length === 0}
              >
                {confirmingRevision ? "Memproses..." : "Konfirmasi"}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {rejectReasonOpen && confirmRevision && role === "admin" ? (
        <div className="fixed inset-0 z-[120] flex items-center justify-center px-4">
          <div className="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" onClick={() => setRejectReasonOpen(false)} />
          <div
            className="relative w-full max-w-[540px] overflow-hidden rounded-[24px] bg-white shadow-[0_24px_70px_rgba(15,23,42,0.24)]"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="flex items-start justify-between border-b border-[#E2E8F0] px-6 py-5">
              <div>
                <h2 className="text-[24px] font-bold text-[#0F172A]">Alasan Penolakan</h2>
                <p className="mt-1 text-sm font-medium text-[#64748B]">
                  Isi alasan sebelum revisi REV-{String(confirmRevision.id).padStart(4, "0")} ditolak.
                </p>
              </div>
              <button
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#FEE2E2] hover:text-[#DC2626]"
                onClick={() => setRejectReasonOpen(false)}
                type="button"
                disabled={rejectingRevision}
              >
                <X size={20} />
              </button>
            </div>

            <div className="space-y-4 px-6 py-5">
              <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold leading-6 text-red-600">
                Revisi akan berubah menjadi Ditolak setelah alasan dikirim.
              </div>
              <div>
                <label className="text-sm font-semibold text-[#334155]">
                  Alasan Penolakan <span className="text-red-500">*</span>
                </label>
                <textarea
                  value={rejectReason}
                  onChange={(event) => setRejectReason(event.target.value)}
                  className="mt-2 min-h-[130px] w-full resize-none rounded-2xl border border-[#D7E0EE] bg-white px-4 py-3 text-base text-[#16213E] outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#DBEAFE]"
                  placeholder="Masukkan alasan penolakan revisi transaksi"
                  disabled={rejectingRevision}
                />
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-[#E2E8F0] px-6 py-4">
              <button
                className="rounded-xl border border-[#CBD5E1] bg-white px-5 py-2.5 text-base font-semibold text-[#475569] transition hover:bg-[#F8FAFC]"
                onClick={() => setRejectReasonOpen(false)}
                type="button"
                disabled={rejectingRevision}
              >
                Batal
              </button>
              <button
                className="rounded-xl bg-[#DC2626] px-6 py-2.5 text-base font-semibold text-white transition hover:bg-[#B91C1C] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={() => void confirmRevisionRejection()}
                type="button"
                disabled={rejectingRevision}
              >
                {rejectingRevision ? "Mengirim..." : "Kirim Penolakan"}
              </button>
            </div>
          </div>
        </div>
      ) : null}

    </div>
  );
}

function normaliseTransactionLabel(value?: string | null) {
  const upper = (value ?? "").toUpperCase();
  if (upper.includes("IN") || upper.includes("MASUK")) return "Masuk";
  if (upper.includes("OUT") || upper.includes("KELUAR")) return "Keluar";
  if (upper.includes("RETURN")) return "Retur";
  return value ?? "-";
}
