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
} from "@/components/admin/ui";
import SuccessModal from "@/components/feedback/SuccessModal";
import SearchableItemSelect from "@/components/admin/ui/SearchableItemSelect";
import {
  formatDate,
  getErrorMessage,
  resolveDetailItemCategory,
  resolveDetailItemName,
  resolveDetailUnit,
} from "@/lib/admin-utils";
import { useRouter } from "next/navigation";

type TransactionRow = Awaited<ReturnType<typeof sdk.stockTransactions.list>>["data"][number];
type DetailRow = Awaited<ReturnType<typeof sdk.stockTransactions.details>>["data"][number];
type ItemRow = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
type ItemUnitRow = Awaited<ReturnType<typeof sdk.itemUnits.list>>["data"][number];
type LookupRow = Awaited<ReturnType<typeof sdk.transactionTypes.list>>["data"][number];
type StatusRow = Awaited<ReturnType<typeof sdk.approvalStatuses.list>>["data"][number];
type StockTransactionListQuery = NonNullable<Parameters<typeof sdk.stockTransactions.list>[0]>;
type ItemGetResponse = Awaited<ReturnType<typeof sdk.items.get>>;

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


const PAGE_SIZE = 8;

async function loadAllItemsSortedByName(): Promise<ItemRow[]> {
  const allItems: ItemRow[] = [];
  let page = 1;
  while (page <= 20) {
    const response = await sdk.items.list({ page, perPage: 100, sortBy: "name", sortDir: "ASC" });
    const chunk = response.data ?? [];
    allItems.push(...chunk);
    if (chunk.length < 100) break;
    page += 1;
  }
  return allItems;
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
  const [selectedDate, setSelectedDate] = useState("");
  const [selectedType, setSelectedType] = useState("");
  const [selectedStatus, setSelectedStatus] = useState("");
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
        const params: StockTransactionListQuery = {
          page: currentPage,
          perPage: PAGE_SIZE,
          sortBy: "transaction_date",
          sortDir: "DESC",
        };

        if (search.trim()) params.search = search.trim();
        if (selectedDate) {
          params.transaction_date_from = selectedDate;
          params.transaction_date_to = selectedDate;
        }
        if (selectedType) params.type_id = Number(selectedType);
        if (selectedStatus) params.status_id = Number(selectedStatus);

        const response = await sdk.stockTransactions.list(params);

        if (cancelled) return;

        const normalizedRows = pickLatestTransactionByParent(
          response.data ?? [],
          new Map(statuses.map((status) => [status.id, status.name])),
        );
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
  }, [currentPage, search, selectedDate, selectedType, selectedStatus, statuses]);

  const typeMap = useMemo(() => new Map(types.map((type) => [type.id, type.name])), [types]);
  const statusMap = useMemo(() => new Map(statuses.map((status) => [status.id, status.name])), [statuses]);
  const unitMap = useMemo(() => new Map(units.map((unit) => [unit.id, unit.name])), [units]);

  const derivedRows = useMemo<DerivedRow[]>(() => {
    return transactions.map((transaction) => {
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
      const transactionLabel = normaliseTransactionLabel(typeMap.get(transaction.type_id));

      return {
        transaction,
        transactionLabel,
        categoryLabel,
        userLabel,
        statusLabel,
      };
    });
  }, [transactions, detailMap, statusMap, currentUser?.id, currentUser?.name, typeMap]);

  const totalPages = Math.max(1, Math.ceil(totalRecords / PAGE_SIZE));
  const paginatedRows = derivedRows;

  useEffect(() => {
    setCurrentPage(1);
  }, [search, selectedDate, selectedType, selectedStatus]);

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

      for (const id of missingIds) {
        if (cancelled) break;
        try {
          const detailResponse = await sdk.stockTransactions.details(id);
          if (cancelled) break;
          setDetailMap((prev) => ({ ...prev, [id]: detailResponse.data ?? [] }));
        } catch {
          if (cancelled) break;
          setDetailMap((prev) => ({ ...prev, [id]: [] }));
        }
      }
    }

    void fetchMissingDetails();
    return () => {
      cancelled = true;
    };
  }, [paginatedRows, detailMap]);

  // Fetch detail transaksi on-demand (hanya saat modal dibuka)
  async function getDetails(transactionId: number) {
    const detailResponse = await sdk.stockTransactions.details(transactionId);
    const details = detailResponse.data ?? [];
    setDetailMap((prev) => ({ ...prev, [transactionId]: details }));

    // Fetch hanya item yang dibutuhkan untuk detail ini
    const itemIds = Array.from(new Set(details.map((d) => d.item_id)));
    const itemResponses = await Promise.allSettled(
      itemIds.map((id) => sdk.items.get(id))
    );
    const fetchedItems: ItemRow[] = itemResponses
      .filter((r): r is PromiseFulfilledResult<ItemGetResponse> => r.status === "fulfilled")
      .map((r) => r.value.data)
      .filter(Boolean);

    const resolvedItemMap = new Map(fetchedItems.map((item) => [item.id, item]));

    return { details, resolvedItemMap };
  }

  async function openDetail(transaction: TransactionRow) {
    try {
      const { details, resolvedItemMap } = await getDetails(transaction.id);
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
      console.error("[revision.submit] failed", saveError);
      setError(getErrorMessage(saveError, "Gagal mengajukan revisi transaksi."));
    } finally {
      setSavingRevision(false);
    }
  }

  function handleExport() {
    const lines = [
      ["ID Transaksi", "Tanggal", "User", "Transaksi", "Kategori Bahan", "Status"],
      ...derivedRows.map((row) => [
        `TR-${String(normalizeTransactionId(row.transaction.parent_transaction_id) || normalizeTransactionId(row.transaction.id)).padStart(4, "0")}`,
        formatDate(row.transaction.transaction_date),
        row.userLabel,
        row.transactionLabel,
        row.categoryLabel,
        row.statusLabel,
      ]),
    ];

    const csv = lines
      .map((line) => line.map((cell) => `"${String(cell).replaceAll('"', '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "riwayat-transaksi-gudang.csv";
    link.click();
    URL.revokeObjectURL(url);
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
            <input
              type="date"
              value={selectedDate}
              onChange={(event) => setSelectedDate(event.target.value)}
              className="h-10 min-w-[150px] rounded-lg border border-[#E2E8F0] bg-white px-3 text-base text-[#334155] outline-none"
            />
            <select
              value={selectedType}
              onChange={(event) => setSelectedType(event.target.value)}
              className="h-10 min-w-[140px] rounded-lg border border-[#E2E8F0] bg-white px-3 text-base text-[#334155] outline-none"
            >
              <option value="">Semua Jenis</option>
              {types.map((type) => (
                <option key={type.id} value={type.id}>
                  {type.name}
                </option>
              ))}
            </select>
            <select
              value={selectedStatus}
              onChange={(event) => setSelectedStatus(event.target.value)}
              className="h-10 min-w-[150px] rounded-lg border border-[#E2E8F0] bg-white px-3 text-base text-[#334155] outline-none"
            >
              <option value="">Semua Status</option>
              {statuses.map((status) => (
                <option key={status.id} value={status.id}>
                  {status.name}
                </option>
              ))}
            </select>
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
    <div className="fixed inset-0 z-[70] flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative w-full max-w-[530px] overflow-hidden rounded-[24px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
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

        <div className="space-y-4 px-5 py-4">
          <div className="grid grid-cols-4 gap-3 rounded-2xl bg-[#EEF4FF] px-4 py-3">
            <InfoBlock label="Tanggal" value={formatDate(detailState.transaction.transaction_date)} />
            <InfoBlock label="Kategori Bahan" value={categoryLabel} />
            <InfoBlock label="ID Transaksi" value={`TR-${String(normalizeTransactionId(detailState.transaction.parent_transaction_id) || normalizeTransactionId(detailState.transaction.id)).padStart(4, "0")}`} />
            <InfoBlock label="Total Item" value={`${detailState.details.length} item`} />
          </div>

          <div className="overflow-hidden rounded-2xl border border-[#D7E0EE]">
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

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center px-4 py-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative flex max-h-[calc(100vh-2rem)] w-full max-w-[1180px] flex-col overflow-hidden rounded-[28px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
          <div>
            <h2 className="text-[30px] font-semibold text-[#16213E]">
              Edit {typeLabel === "IN" ? "Barang Masuk" : "Barang Keluar"}
            </h2>
            <p className="mt-2 text-lg text-[#94A3B8]">
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
        <div className="flex-1 space-y-5 overflow-y-auto px-6 py-5">
          <div className="rounded-2xl border border-[#D7E0EE] bg-white shadow-sm">
            <div className="border-b border-[#E2E8F0] bg-[#F8FBFF] px-5 py-4">
              <h3 className="text-[28px] font-semibold text-[#16213E]">Komposisi Bahan</h3>
              <p className="mt-2 text-base text-[#94A3B8]">Sesuaikan bahan dan jumlah revisi sesuai stok yang tersedia.</p>
            </div>
            <div className="p-4">
              <div className="mb-4 grid grid-cols-12 gap-4 px-2 text-sm font-semibold uppercase tracking-wider text-slate-500">
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
                        options={items.map((it) => ({
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
                          const item = items.find((it) => it.id === itemId);
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
                          className="h-12 w-full rounded-xl border border-slate-200 px-3 text-center text-lg outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
                        />
                      </div>
                    </div>
                    <div className="col-span-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-lg font-medium text-slate-600">
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
                className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-200 py-3 text-lg font-medium text-slate-500 transition hover:border-[#2563EB] hover:bg-blue-50/50 hover:text-[#2563EB]"
              >
                <Plus size={16} />
                <span>Tambah Baris Bahan</span>
              </button>
            </div>
          </div>

          <div className="rounded-2xl border border-[#D7E0EE] bg-[#F8FAFC] px-4 py-3 text-lg text-[#64748B]">
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
  const upper = (value ?? "").toUpperCase();
  if (upper.includes("IN") || upper.includes("MASUK")) return "Masuk";
  if (upper.includes("OUT") || upper.includes("KELUAR")) return "Keluar";
  return value ?? "-";
}
