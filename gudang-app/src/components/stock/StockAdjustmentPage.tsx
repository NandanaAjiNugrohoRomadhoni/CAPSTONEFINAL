"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { X } from "lucide-react";
import sdk from "@/lib";
import {
  AdminPageHeading,
  ExportButton,
  Pagination,
  PrimaryAction,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import SuccessModal from "@/components/feedback/SuccessModal";
import { formatDate, formatNumber, getCurrentMonthPeriod, getErrorMessage, toIsoDate } from "@/lib/admin-utils";
import { listAllItems } from "@/lib/items";
import { useAuthStore } from "@/store/authStore";
import {
  refreshStockAdjustmentNotifications,
  type NotificationRole,
} from "@/lib/stock-adjustment-notifications";
import SearchableItemSelect from "@/components/admin/ui/SearchableItemSelect";

const ITEM_METADATA_CACHE_KEY = "capstone-stock-item-metadata-cache";
const INVALID_STOCK_OPNAME_IDS_CACHE_KEY = "capstone-invalid-stock-opname-ids";

type ItemMetadata = { name: string; categoryName: string };

function readCachedItemMetadata() {
  if (typeof window === "undefined") return {} as Record<number, ItemMetadata>;
  try {
    const raw = window.localStorage.getItem(ITEM_METADATA_CACHE_KEY);
    if (!raw) return {};
    return JSON.parse(raw) as Record<number, ItemMetadata>;
  } catch {
    return {};
  }
}

function writeCachedItemMetadata(items: ItemRow[], stockRows?: StockReportRow[]) {
  if (typeof window === "undefined") return;
  const next = readCachedItemMetadata();
  for (const item of items) {
    next[item.id] = { name: item.name, categoryName: item.category?.name ?? "-" };
  }
  if (stockRows) {
    for (const row of stockRows) {
      const id = Number(row.item_id);
      if (!next[id]) {
        next[id] = { name: row.item_name as string, categoryName: row.category_name as string };
      }
    }
  }
  window.localStorage.setItem(ITEM_METADATA_CACHE_KEY, JSON.stringify(next));
}

type ItemRow = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
type OpnameData = Awaited<ReturnType<typeof sdk.stockOpnames.get>>["data"];
type StockReportRow = Awaited<ReturnType<typeof sdk.reports.getStocks>>["data"]["rows"][number];
type UserRow = Awaited<ReturnType<typeof sdk.users.list>>["data"][number];

type StockAdjustmentPageProps = {
  title: string;
  subtitle: string;
  historyStorageKey: string;
  additionalHistoryStorageKeys?: string[];
  legacyLatestKey?: string;
  autoApplyOnCreate?: boolean;
  allowVerificationAction?: boolean;
  useDraftSubmissionChecklist?: boolean;
};

type StockAdjustmentTableRow = {
  key: string;
  headerId: number;
  createdById: number;
  isFirstDetail: boolean;
  rowSpan: number;
  opnameDate: string;
  state: OpnameData["header"]["state"];
  stateLabel: string;
  createdByLabel: string;
  itemName: string;
  categoryName: string;
  systemQtyLabel: string;
  countedQtyLabel: string;
  variance: number;
  varianceLabel: string;
};

function readHistoryIds(storageKey: string) {
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

function writeHistoryIds(storageKey: string, ids: number[]) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(storageKey, JSON.stringify(Array.from(new Set(ids))));
}

function readInvalidStockOpnameIds() {
  if (typeof window === "undefined") return [] as number[];

  try {
    const raw = window.localStorage.getItem(INVALID_STOCK_OPNAME_IDS_CACHE_KEY);
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

function rememberInvalidStockOpnameIds(ids: number[]) {
  if (typeof window === "undefined" || ids.length === 0) return;
  const next = Array.from(new Set([...readInvalidStockOpnameIds(), ...ids]));
  window.localStorage.setItem(INVALID_STOCK_OPNAME_IDS_CACHE_KEY, JSON.stringify(next));
}

function removeCachedOpnames(storageKey: string, ids: number[]) {
  if (typeof window === "undefined" || ids.length === 0) return;
  const cached = readCachedOpnames(storageKey);
  for (const id of ids) {
    delete cached[id];
  }
  window.localStorage.setItem(getOpnameCacheKey(storageKey), JSON.stringify(cached));
}

function getOpnameCacheKey(storageKey: string) {
  return `${storageKey}-cache`;
}

function readCachedOpnames(storageKey: string) {
  if (typeof window === "undefined") return {} as Record<number, OpnameData>;

  try {
    const raw = window.localStorage.getItem(getOpnameCacheKey(storageKey));
    if (!raw) return {};

    const parsed = JSON.parse(raw) as Record<string, OpnameData>;
    const next: Record<number, OpnameData> = {};

    for (const [key, value] of Object.entries(parsed)) {
      const numericKey = Number(key);
      if (Number.isFinite(numericKey) && value && typeof value === "object") {
        next[numericKey] = value;
      }
    }

    return next;
  } catch {
    return {};
  }
}

function writeCachedOpnames(storageKey: string, opnames: OpnameData[]) {
  if (typeof window === "undefined") return;

  const payload = Object.fromEntries(
    opnames.map((opname) => [String(opname.header.id), opname]),
  );

  window.localStorage.setItem(getOpnameCacheKey(storageKey), JSON.stringify(payload));
}

const USER_NAME_CACHE_KEY = "capstone-stock-adjustment-user-cache";

function readCachedUserNames() {
  if (typeof window === "undefined") return {} as Record<number, string>;

  try {
    const raw = window.localStorage.getItem(USER_NAME_CACHE_KEY);
    if (!raw) return {};

    const parsed = JSON.parse(raw) as Record<string, string>;
    const next: Record<number, string> = {};

    for (const [key, value] of Object.entries(parsed)) {
      const numericKey = Number(key);
      if (Number.isFinite(numericKey) && typeof value === "string" && value.trim()) {
        next[numericKey] = value;
      }
    }

    return next;
  } catch {
    return {};
  }
}

function writeCachedUserNames(entries: Array<{ id: number; name?: string | null }>) {
  if (typeof window === "undefined") return;

  const current = readCachedUserNames();
  const next = { ...current };

  for (const entry of entries) {
    const id = Number(entry.id);
    const name = entry.name?.trim();
    if (Number.isFinite(id) && id > 0 && name) {
      next[id] = name;
    }
  }

  window.localStorage.setItem(USER_NAME_CACHE_KEY, JSON.stringify(next));
}

const USER_ROLE_CACHE_KEY = "capstone-stock-adjustment-user-role-cache";

function readCachedUserRoles() {
  if (typeof window === "undefined") return {} as Record<number, NotificationRole>;

  try {
    const raw = window.localStorage.getItem(USER_ROLE_CACHE_KEY);
    if (!raw) return {};

    const parsed = JSON.parse(raw) as Record<string, NotificationRole>;
    const next: Record<number, NotificationRole> = {};

    for (const [key, value] of Object.entries(parsed)) {
      const numericKey = Number(key);
      if (
        Number.isFinite(numericKey) &&
        (value === "admin" || value === "gudang" || value === "dapur")
      ) {
        next[numericKey] = value;
      }
    }

    return next;
  } catch {
    return {};
  }
}

function writeCachedUserRoles(
  entries: Array<{ id: number; role?: { name?: string | null } | null }>,
) {
  if (typeof window === "undefined") return;

  const current = readCachedUserRoles();
  const next = { ...current };

  for (const entry of entries) {
    const id = Number(entry.id);
    const roleName = entry.role?.name;
    if (
      Number.isFinite(id) &&
      id > 0 &&
      (roleName === "admin" || roleName === "gudang" || roleName === "dapur")
    ) {
      next[id] = roleName;
    }
  }

  window.localStorage.setItem(USER_ROLE_CACHE_KEY, JSON.stringify(next));
}

async function fetchStockOpnamesByIds(ids: number[]) {
  const uniqueIds = Array.from(new Set(ids)).filter((id) => Number.isFinite(id) && id > 0);
  if (uniqueIds.length === 0) return [] as OpnameData[];

  const responses = await Promise.allSettled(uniqueIds.slice(0, 25).map((id) => sdk.stockOpnames.get(id)));
  return responses.flatMap((response) => (response.status === "fulfilled" && response.value.data ? [response.value.data] : []));
}

function sortOpnames(opnames: OpnameData[]) {
  return [...opnames].sort((left, right) => {
    const leftTime = new Date(left.header.opname_date).getTime();
    const rightTime = new Date(right.header.opname_date).getTime();
    if (rightTime !== leftTime) {
      return rightTime - leftTime;
    }
    return right.header.id - left.header.id;
  });
}

function getDefaultUserDisplayName(userId: number) {
  if (userId === 1) {
    return "Admin User";
  }

  return null;
}

function getReadableOpnameState(state: OpnameData["header"]["state"]) {
  switch (state) {
    case "DRAFT":
      return "Belum Diajukan";
    case "SUBMITTED":
      return "Menunggu Verifikasi";
    case "APPROVED":
    case "POSTED":
      return "Disetujui";
    case "REJECTED":
      return "Ditolak";
    default:
      return state;
  }
}

function getApiStatus(error: unknown) {
  if (typeof error === "object" && error !== null && "status" in error) {
    const status = Number((error as { status?: unknown }).status);
    return Number.isFinite(status) ? status : null;
  }

  return null;
}

function normalizeFilterValue(value: string) {
  return value.trim().toUpperCase();
}

function getOpnameStateClasses(state: OpnameData["header"]["state"]) {
  switch (state) {
    case "DRAFT":
      return "bg-[#FFF7ED] text-[#C2410C]";
    case "SUBMITTED":
      return "bg-[#EEF4FF] text-[#2155CD]";
    case "APPROVED":
    case "POSTED":
      return "bg-[#ECFDF3] text-[#16A34A]";
    case "REJECTED":
      return "bg-[#FEF2F2] text-[#DC2626]";
    default:
      return "bg-[#F1F5F9] text-[#475569]";
  }
}

function buildFallbackOpnameData({
  header,
  itemId,
  systemQty,
  countedQty,
}: {
  header: OpnameData["header"];
  itemId: number;
  systemQty: number;
  countedQty: number;
}): OpnameData {
  return {
    header,
    details: [
      {
        id: Number(`${header.id}${itemId}`),
        opname_id: header.id,
        item_id: itemId,
        system_qty: systemQty,
        counted_qty: countedQty,
        variance_qty: countedQty - systemQty,
        notes: null,
        created_at: header.created_at,
        updated_at: header.updated_at,
      },
    ],
  };
}

export default function StockAdjustmentPage({
  title,
  subtitle,
  historyStorageKey,
  additionalHistoryStorageKeys = [],
  legacyLatestKey,
  autoApplyOnCreate = false,
  allowVerificationAction = false,
  useDraftSubmissionChecklist = false,
}: Readonly<StockAdjustmentPageProps>) {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const [items, setItems] = useState<ItemRow[]>([]);
  const [activeItems, setActiveItems] = useState<ItemRow[]>([]);
  const [stockRows, setStockRows] = useState<StockReportRow[]>([]);
  const [users, setUsers] = useState<UserRow[]>([]);
  const [opnames, setOpnames] = useState<OpnameData[]>([]);
  const [selectedItemId, setSelectedItemId] = useState<number | null>(null);
  const [countedQty, setCountedQty] = useState("0");
  const [modalOpen, setModalOpen] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [draftChecklistOpen, setDraftChecklistOpen] = useState(false);
  const [selectedDraftIds, setSelectedDraftIds] = useState<number[]>([]);
  const [rejectionReason, setRejectionReason] = useState("");
  const [verificationTarget, setVerificationTarget] = useState<{
    headerId: number;
    itemName: string;
    categoryName: string;
    systemQtyLabel: string;
    countedQtyLabel: string;
    varianceLabel: string;
    varianceToneClass: string;
    createdByLabel: string;
  } | null>(null);
  const [rejectTarget, setRejectTarget] = useState<{
    headerId: number;
    itemName: string;
  } | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [submittingHeaderId, setSubmittingHeaderId] = useState<number | null>(null);
  const [confirmingHeaderId, setConfirmingHeaderId] = useState<number | null>(null);
  const [rejectingHeaderId, setRejectingHeaderId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [successConfig, setSuccessConfig] = useState<{ headline: string; message: string } | null>(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [dateFromFilter, setDateFromFilter] = useState("");
  const [dateToFilter, setDateToFilter] = useState("");
  const [categoryFilter, setCategoryFilter] = useState("Semua Jenis");
  const [statusFilter, setStatusFilter] = useState("Semua Status");
  const [currentPage, setCurrentPage] = useState(1);
  const additionalHistoryStorageSignature = useMemo(
    () => additionalHistoryStorageKeys.join("|"),
    [additionalHistoryStorageKeys],
  );
  const stableAdditionalHistoryStorageKeys = useMemo(
    () => additionalHistoryStorageKeys,
    [additionalHistoryStorageSignature],
  );

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const cachedUserNames = readCachedUserNames();
        const cachedUserRoles = readCachedUserRoles();
        const nextUsers = [
          ...(user ? [{ ...user }] : []),
          ...Object.entries(cachedUserNames).map(([id, name]) => ({
            id: Number(id),
            name,
            username: name,
            role_id: 0,
            email: null,
            is_active: true,
            created_at: "",
            updated_at: "",
            role: cachedUserRoles[Number(id)]
              ? { id: 0, name: cachedUserRoles[Number(id)] }
              : undefined,
          })),
        ];

        const invalidIds = readInvalidStockOpnameIds();
        const mergedIds = [
          ...readHistoryIds(historyStorageKey),
          ...stableAdditionalHistoryStorageKeys.flatMap((storageKey) => readHistoryIds(storageKey)),
        ].filter((id) => !invalidIds.includes(id));

        if (typeof window !== "undefined" && legacyLatestKey) {
          const legacyId = Number(window.sessionStorage.getItem(legacyLatestKey) ?? 0);
          if (legacyId > 0 && !mergedIds.includes(legacyId)) {
            mergedIds.unshift(legacyId);
          }
          if (legacyId > 0) {
            window.sessionStorage.removeItem(legacyLatestKey);
          }
        }

        const fallbackNotificationIds = mergedIds.length > 0
          ? []
          : (
              await sdk.notifications.list({
                paginate: false,
                sortBy: "created_at",
                sortDir: "DESC",
              }).catch(() => ({ data: [] as Array<{ type?: string; related_id?: number | string | null }> }))
            ).data
              .filter((notification) => String(notification.type ?? "").toUpperCase() === "STOCK_OPNAME")
              .map((notification) => Number(notification.related_id ?? 0))
              .filter((id) => Number.isFinite(id) && id > 0);
        const uniqueIds = Array.from(new Set([...mergedIds, ...fallbackNotificationIds]));
        writeHistoryIds(historyStorageKey, uniqueIds);
        const successOpnames = await fetchStockOpnamesByIds(uniqueIds);

        if (!cancelled) {
          setUsers(nextUsers);
          setOpnames(sortOpnames(successOpnames));
          if (successOpnames.length > 0) {
            writeCachedOpnames(historyStorageKey, successOpnames);
          }
        }
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat halaman penyesuaian stok."));
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
  }, [additionalHistoryStorageSignature, historyStorageKey, legacyLatestKey, stableAdditionalHistoryStorageKeys, user]);

  const [metadataLoading, setMetadataLoading] = useState(false);

  useEffect(() => {
    let cancelled = false;
    async function loadBackground() {
      setMetadataLoading(true);
      try {
        const [itemsResponse, activeItemsResponse, usersResponse] = await Promise.all([
          listAllItems({ sortBy: "name", sortDir: "ASC" }),
          listAllItems({ sortBy: "name", sortDir: "ASC", is_active: true }),
          sdk.users
            .list({ perPage: 100, sortBy: "created_at", sortDir: "DESC" })
            .catch(() => ({ data: [] as UserRow[] })),
        ]);

        if (cancelled) return;

        const nextItems = itemsResponse;
        const nextActiveItems = activeItemsResponse;
        const nextUsers = usersResponse.data ?? [];

        setItems(nextItems);
        setActiveItems(nextActiveItems);
        setUsers((current) => {
          const merged = [...current];
          for (const u of nextUsers) {
            if (!merged.find((m) => m.id === u.id)) {
              merged.push(u);
            }
          }
          return merged;
        });

        writeCachedItemMetadata(nextItems);
        writeCachedUserNames(nextUsers);
        writeCachedUserRoles(nextUsers);
      } catch (err) {
        console.error("Background metadata load failed:", err);
      } finally {
        if (!cancelled) setMetadataLoading(false);
      }
    }

    void loadBackground();
    return () => {
      cancelled = true;
    };
  }, []);

  async function ensureCurrentStock() {
    if (stockRows.length > 0) return;
    try {
      const response = await sdk.reports.getStocks(getCurrentMonthPeriod());
      setStockRows(response.data.rows ?? []);
      writeCachedItemMetadata([], response.data.rows ?? []);
    } catch (err) {
      console.error("Failed to load current stock:", err);
    }
  }
  useEffect(() => {
    let cancelled = false;

    async function syncHistoryFromBackend() {
      const historyKeys = [historyStorageKey, ...stableAdditionalHistoryStorageKeys];
      const invalidIds = readInvalidStockOpnameIds();
      const mergedIds = [
        ...readHistoryIds(historyStorageKey),
        ...stableAdditionalHistoryStorageKeys.flatMap((storageKey) => readHistoryIds(storageKey)),
      ].filter((id) => !invalidIds.includes(id));
      const uniqueIds = Array.from(new Set(mergedIds));
      if (uniqueIds.length === 0) return;

      try {
        const freshOpnames = await fetchStockOpnamesByIds(uniqueIds);
        if (cancelled) return;

        const returnedIds = new Set(freshOpnames.map((opname) => opname.header.id));
        const missingIds = uniqueIds.filter((id) => !returnedIds.has(id));

        if (missingIds.length > 0) {
          rememberInvalidStockOpnameIds(missingIds);
          for (const storageKey of historyKeys) {
            const retainedIds = readHistoryIds(storageKey).filter((id) => !missingIds.includes(id));
            writeHistoryIds(storageKey, retainedIds);
            removeCachedOpnames(storageKey, missingIds);
          }
        }

        if (freshOpnames.length > 0) {
          setOpnames((current) => {
            const byId = new Map(current.map((o) => [o.header.id, o]));
            for (const opname of freshOpnames) {
              byId.set(opname.header.id, opname);
            }
            const next = sortOpnames(Array.from(byId.values()));
            writeCachedOpnames(historyStorageKey, next);
            return next;
          });
        }
      } catch {
        // Ignore
      }
    }

    void syncHistoryFromBackend();
    return () => {
      cancelled = true;
    };
  }, [additionalHistoryStorageSignature, historyStorageKey, stableAdditionalHistoryStorageKeys]);

  const selectedItem = useMemo(
    () => activeItems.find((item) => Number(item.id) === Number(selectedItemId)) ?? null,
    [activeItems, selectedItemId],
  );

  const activeItemOptions = useMemo(
    () =>
      activeItems.map((item) => ({
        id: Number(item.id),
        label: item.name,
        unit: item.unit_base ?? "-",
      })),
    [activeItems],
  );

  const stockRowMap = useMemo(() => new Map(stockRows.map((row) => [Number(row.item_id), row])), [stockRows]);
  const itemMap = useMemo(() => new Map(items.map((item) => [Number(item.id), item])), [items]);
  const userMap = useMemo(() => new Map(users.map((user) => [Number(user.id), user])), [users]);
  const cachedUserNames = useMemo(() => readCachedUserNames(), []);
  const cachedItemMetadata = useMemo(() => readCachedItemMetadata(), []);

  const selectedItemCategory = useMemo<string>(() => {
    if (!selectedItemId) return "-";
    const relatedItem = itemMap.get(Number(selectedItemId));
    const relatedStockRow = stockRowMap.get(Number(selectedItemId));
    const cachedMeta = cachedItemMetadata[Number(selectedItemId)];
    return (
      String(relatedItem?.category?.name ?? "") ||
      String(relatedStockRow?.category_name ?? "") ||
      cachedMeta?.categoryName ||
      "-"
    );
  }, [cachedItemMetadata, itemMap, selectedItemId, stockRowMap]);


  const selectedSystemQty = useMemo(() => {
    if (!selectedItem) return 0;
    return Number(selectedItem.qty ?? 0);
  }, [selectedItem]);

  const countedQtyValue = useMemo(() => Number(countedQty || 0), [countedQty]);
  const previewVariance = countedQtyValue - selectedSystemQty;

  const tableRows = useMemo<StockAdjustmentTableRow[]>(() => {
    return opnames.flatMap((opname) =>
      opname.details.map((detail, index) => {
        const itemId = Number(detail.item_id);
        const createdById = Number(opname.header.created_by);
        const relatedItem = itemMap.get(itemId);
        const relatedStockRow = stockRowMap.get(itemId);
        const cachedMeta = cachedItemMetadata[itemId];

        const variance = Number(detail.variance_qty ?? 0);
        const systemQty = Number(detail.system_qty ?? 0);
        const countedQtyValue = Number(detail.counted_qty ?? 0);
        const createdByLabel =
          (user && Number(user.id) === createdById ? user.name : null) ??
          userMap.get(createdById)?.name ??
          cachedUserNames[createdById] ??
          userMap.get(createdById)?.username ??
          getDefaultUserDisplayName(createdById) ??
          `User #${opname.header.created_by}`;
        return {
          key: `${opname.header.id}-${detail.id}`,
          headerId: opname.header.id,
          createdById,
          isFirstDetail: index === 0,
          rowSpan: opname.details.length,
          opnameDate: opname.header.opname_date,
          state: opname.header.state,
          stateLabel: getReadableOpnameState(opname.header.state),
          createdByLabel,
          itemName:
            String(relatedItem?.name ?? "") ||
            String(relatedStockRow?.item_name ?? "") ||
            cachedMeta?.name ||
            `Item #${detail.item_id}`,
          categoryName:
            String(relatedItem?.category?.name ?? "") ||
            String(relatedStockRow?.category_name ?? "") ||
            cachedMeta?.categoryName ||
            "-",
          systemQtyLabel: formatNumber(systemQty, Number.isInteger(systemQty) ? 0 : 1),
          countedQtyLabel: formatNumber(countedQtyValue, Number.isInteger(countedQtyValue) ? 0 : 1),
          variance,
          varianceLabel: formatNumber(variance, Number.isInteger(variance) ? 0 : 1),
        };
      }),
    );
  }, [cachedItemMetadata, cachedUserNames, itemMap, opnames, stockRowMap, user, userMap]);

  const categoryOptions = useMemo(() => {
    return Array.from(
      new Set(
        tableRows
          .map((row) => row.categoryName)
          .filter((value): value is string => typeof value === "string" && value !== "-"),
      ),
    ).sort((left, right) => left.localeCompare(right, "id-ID"));
  }, [tableRows]);

  const statusOptions = useMemo(() => {
    return Array.from(
      new Set(
        tableRows
          .map((row) => row.stateLabel)
          .filter((value): value is string => typeof value === "string" && value.trim() !== ""),
      ),
    ).sort((left, right) => left.localeCompare(right, "id-ID"));
  }, [tableRows]);

  const filteredRows = useMemo(() => {
    const normalizedSearch = searchTerm.trim().toLowerCase();
    const normalizedCategoryFilter = normalizeFilterValue(categoryFilter);
    const normalizedStatusFilter = normalizeFilterValue(statusFilter);

    return tableRows.filter((row) => {
      const matchesSearch =
        !normalizedSearch ||
        row.itemName.toLowerCase().includes(normalizedSearch) ||
        row.createdByLabel.toLowerCase().includes(normalizedSearch) ||
        row.categoryName.toLowerCase().includes(normalizedSearch) ||
        `PS-${String(row.headerId).padStart(4, "0")}`.toLowerCase().includes(normalizedSearch);
      const matchesDateFrom = !dateFromFilter || row.opnameDate >= dateFromFilter;
      const matchesDateTo = !dateToFilter || row.opnameDate <= dateToFilter;
      const matchesCategory =
        normalizedCategoryFilter === normalizeFilterValue("Semua Jenis") ||
        normalizeFilterValue(row.categoryName) === normalizedCategoryFilter;
      const matchesStatus =
        normalizedStatusFilter === normalizeFilterValue("Semua Status") ||
        normalizeFilterValue(row.stateLabel) === normalizedStatusFilter;

      return matchesSearch && matchesDateFrom && matchesDateTo && matchesCategory && matchesStatus;
    });
  }, [categoryFilter, dateFromFilter, dateToFilter, searchTerm, statusFilter, tableRows]);

  const userOwnedDraftHeaders = useMemo(() => {
    const currentUserId = Number(user?.id ?? 0);
    if (currentUserId <= 0) return [] as OpnameData[];

    return opnames.filter(
      (opname) =>
        opname.header.state === "DRAFT" &&
        Number(opname.header.created_by) === currentUserId,
    );
  }, [opnames, user]);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, dateFromFilter, dateToFilter, categoryFilter, statusFilter]);

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
      setError("Belum ada data yang bisa diexport dari hasil filter saat ini.");
      return;
    }

    const header = [
      "ID Penyesuaian Stok",
      "Tanggal",
      "User",
      "Nama Bahan",
      "Jenis Bahan",
      "Stok Sistem",
      "Stok Fisik",
      "Selisih",
      "Status",
    ];

    const lines = filteredRows.map((row) => [
      `PS-${String(row.headerId).padStart(4, "0")}`,
      formatDate(row.opnameDate),
      row.createdByLabel,
      row.itemName,
      row.categoryName,
      row.systemQtyLabel,
      row.countedQtyLabel,
      `${row.variance > 0 ? "+" : ""}${row.varianceLabel}`,
      row.stateLabel,
    ]);

    const csv = [header, ...lines]
      .map((line) => line.map((value) => `"${String(value).replaceAll('"', '""')}"`).join(","))
      .join("\n");

    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `riwayat-penyesuaian-stok-${toIsoDate(new Date())}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  async function handleCreateOpname() {
    if (!selectedItemId || Number(countedQty) < 0) {
      setError("Pilih barang dan isi stok fisik terlebih dahulu.");
      return;
    }

    setSaving(true);
    setError(null);

    try {
      const response = await sdk.stockOpnames.create({
        opname_date: toIsoDate(new Date()),
        details: [{ item_id: selectedItemId, counted_qty: Number(countedQty) }],
      });

      const headerId = response.data.id;
      if (autoApplyOnCreate) {
        await sdk.stockOpnames.submit(headerId);
        await sdk.stockOpnames.approve(headerId);
        await sdk.stockOpnames.post(headerId);
      }
      let nextOpname: OpnameData;

      try {
        const opnameResponse = await sdk.stockOpnames.get(headerId);
        nextOpname = opnameResponse.data;
      } catch {
        nextOpname = buildFallbackOpnameData({
          header: {
            ...response.data,
            opname_date: toIsoDate(new Date()),
            created_by: response.data.created_by ?? Number(user?.id ?? 0),
            approved_by: response.data.approved_by ?? null,
            rejection_reason: response.data.rejection_reason ?? null,
            notes: response.data.notes ?? null,
            created_at: response.data.created_at ?? new Date().toISOString(),
            updated_at: response.data.updated_at ?? new Date().toISOString(),
          },
          itemId: selectedItemId,
          systemQty: selectedSystemQty,
          countedQty: Number(countedQty),
        });
      }

      const nextOpnames = sortOpnames([nextOpname, ...opnames.filter((entry) => entry.header.id !== nextOpname.header.id)]);
      setOpnames(nextOpnames);
      if (typeof window !== "undefined") {
        writeHistoryIds(
          historyStorageKey,
          nextOpnames.map((entry) => entry.header.id),
        );
        writeCachedOpnames(historyStorageKey, nextOpnames);
      }

      setModalOpen(false);
      setConfirmOpen(false);
      setSelectedItemId(null);
      setCountedQty("0");
      if (autoApplyOnCreate) {
        refreshStockAdjustmentNotifications();
      }
      setSuccessConfig({
        headline: autoApplyOnCreate ? "Penyesuaian Stok Berhasil Diterapkan" : "Penyesuaian Stok Berhasil Disimpan",
        message: autoApplyOnCreate
          ? `Penyesuaian stok PS-${String(headerId).padStart(4, "0")} langsung diterapkan ke stok bahan dan riwayat tetap ditampilkan di halaman ini.`
          : `Draft stock opname PS-${String(headerId).padStart(4, "0")} tersimpan di backend dan riwayat tetap ditampilkan di halaman ini.`,
      });
      if (autoApplyOnCreate) {
        setStockRows([]); // Trigger re-fetch
      }
      router.refresh();
    } catch (saveError) {
      setError(getErrorMessage(saveError, "Gagal menyimpan penyesuaian stok."));
    } finally {
      setSaving(false);
    }
  }

  function handlePrimarySaveClick() {
    if (!selectedItemId || Number(countedQty) < 0) {
      setError("Pilih barang dan isi stok fisik terlebih dahulu.");
      return;
    }

    if (autoApplyOnCreate) {
      setConfirmOpen(true);
      return;
    }

    void handleCreateOpname();
  }

  async function handleSubmitSelectedDrafts() {
    if (selectedDraftIds.length === 0) {
      setError("Pilih minimal satu draft penyesuaian stok untuk diajukan.");
      return;
    }

    setSubmittingHeaderId(-1);
    setError(null);

    try {
      await Promise.all(selectedDraftIds.map((headerId) => sdk.stockOpnames.submit(headerId)));

      setOpnames((current) => {
        const next = sortOpnames(
          current.map((opname) =>
            selectedDraftIds.includes(opname.header.id)
              ? {
                  ...opname,
                  header: {
                    ...opname.header,
                    state: "SUBMITTED",
                  },
                }
              : opname,
          ),
        );
        writeCachedOpnames(historyStorageKey, next);
        return next;
      });

      setSuccessConfig({
        headline: "Draft Berhasil Diajukan",
        message:
          selectedDraftIds.length === 1
            ? `Draft PS-${String(selectedDraftIds[0]).padStart(4, "0")} berhasil diajukan untuk verifikasi admin.`
            : `${selectedDraftIds.length} draft penyesuaian stok berhasil diajukan untuk verifikasi admin.`,
      });
      setDraftChecklistOpen(false);
      setSelectedDraftIds([]);
      refreshStockAdjustmentNotifications();
      router.refresh();
    } catch (submitError) {
      setError(getErrorMessage(submitError, "Gagal mengajukan draft penyesuaian stok."));
    } finally {
      setSubmittingHeaderId(null);
    }
  }

  async function handleConfirmSubmission(headerId: number) {
    setConfirmingHeaderId(headerId);
    setError(null);

    try {
      await sdk.stockOpnames.approve(headerId);
      await sdk.stockOpnames.post(headerId);

      setOpnames((current) => {
        const next = sortOpnames(
          current.map((opname) =>
            opname.header.id === headerId
              ? {
                  ...opname,
                  header: {
                    ...opname.header,
                    state: "POSTED",
                  },
                }
              : opname,
          ),
        );
        writeCachedOpnames(historyStorageKey, next);
        return next;
      });

      refreshStockAdjustmentNotifications();

      setSuccessConfig({
        headline: "Penyesuaian Stok Berhasil Dikonfirmasi",
        message: `Penyesuaian stok PS-${String(headerId).padStart(4, "0")} berhasil diterapkan ke stok bahan.`,
      });
      setVerificationTarget(null);
      setRejectionReason("");
      return true;
    } catch (confirmError) {
      setError(getErrorMessage(confirmError, "Gagal mengonfirmasi penyesuaian stok."));
      return false;
    } finally {
      setConfirmingHeaderId(null);
    }
  }

  async function handleRejectSubmission(headerId: number) {
    const trimmedReason = rejectionReason.trim();
    if (!trimmedReason) {
      setError("Mohon isi alasan penolakan terlebih dahulu.");
      return;
    }

    setRejectingHeaderId(headerId);
    setError(null);

    try {
      await sdk.stockOpnames.reject(headerId, { reason: trimmedReason });

      setOpnames((current) => {
        const next = sortOpnames(
          current.map((opname) =>
            opname.header.id === headerId
              ? {
                  ...opname,
                  header: {
                    ...opname.header,
                    state: "REJECTED",
                    rejection_reason: trimmedReason,
                  },
                }
              : opname,
          ),
        );
        writeCachedOpnames(historyStorageKey, next);
        return next;
      });

      refreshStockAdjustmentNotifications();

      setSuccessConfig({
        headline: "Penyesuaian Stok Ditolak",
        message: `Penyesuaian stok PS-${String(headerId).padStart(4, "0")} berhasil ditolak.`,
      });
      setVerificationTarget(null);
      setRejectTarget(null);
      setRejectionReason("");
      return true;
    } catch (rejectError) {
      setError(getErrorMessage(rejectError, "Gagal menolak penyesuaian stok."));
      return false;
    } finally {
      setRejectingHeaderId(null);
    }
  }

  function openVerificationConfirmation(row: {
    headerId: number;
    itemName: string;
    categoryName: string;
    systemQtyLabel: string;
    countedQtyLabel: string;
    varianceLabel: string;
    variance: number;
    createdByLabel: string;
  }) {
    setVerificationTarget({
      headerId: row.headerId,
      itemName: row.itemName,
      categoryName: row.categoryName,
      systemQtyLabel: row.systemQtyLabel,
      countedQtyLabel: row.countedQtyLabel,
      varianceLabel: row.varianceLabel,
      varianceToneClass:
        row.variance > 0 ? "text-[#10B981]" : row.variance < 0 ? "text-[#EF4444]" : "text-[#475569]",
      createdByLabel: row.createdByLabel,
    });
    setRejectionReason("");
    setRejectTarget(null);
    setError(null);
  }

  return (
    <>
      <div className="space-y-6">
          <AdminPageHeading
            title={title}
            subtitle={subtitle}
            action={
              <PrimaryAction
                disabled={loading || metadataLoading}
                onClick={async () => {
                  setModalOpen(true);
                  await ensureCurrentStock();
                }}
              >
                {metadataLoading ? "Menyiapkan..." : "Input Penyesuaian Stok"}
              </PrimaryAction>
            }
          />

        {error ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
            {error}
          </div>
        ) : null}

        <SurfaceCard className="overflow-hidden">
          <div className="border-b border-[#D7E0EE] bg-[#F8FAFC] px-5 py-4">
            <div className="grid gap-3 xl:grid-cols-[minmax(220px,1.4fr)_170px_170px_190px_210px_auto] xl:items-center">
              <input
                onChange={(event) => setSearchTerm(event.target.value)}
                value={searchTerm}
                placeholder="Cari Bahan"
                className="h-12 w-full rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-base text-[#334155] outline-none placeholder:text-[#94A3B8]"
              />
              <input
                aria-label="Tanggal awal"
                type="date"
                value={dateFromFilter}
                onChange={(event) => setDateFromFilter(event.target.value)}
                onKeyDown={(event) => event.preventDefault()}
                onPaste={(event) => event.preventDefault()}
                className="h-12 w-full rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-base text-[#334155] outline-none"
              />
              <input
                aria-label="Tanggal akhir"
                type="date"
                value={dateToFilter}
                onChange={(event) => setDateToFilter(event.target.value)}
                onKeyDown={(event) => event.preventDefault()}
                onPaste={(event) => event.preventDefault()}
                className="h-12 w-full rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-base text-[#334155] outline-none"
              />
              <ThemedSelect
                className="w-full"
                value={categoryFilter}
                onChange={setCategoryFilter}
                options={[
                  { value: "Semua Jenis", label: "Semua Jenis" },
                  ...categoryOptions.map((category) => ({ value: category, label: category })),
                ]}
              />
              <ThemedSelect
                className="w-full"
                value={statusFilter}
                onChange={setStatusFilter}
                options={[
                  { value: "Semua Status", label: "Semua Status" },
                  ...statusOptions.map((status) => ({ value: status, label: status })),
                ]}
              />
              <div className="flex justify-start xl:justify-end">
                {useDraftSubmissionChecklist ? (
                  <div className="flex flex-wrap gap-3 xl:ml-auto">
                    <PrimaryAction
                      onClick={() => {
                        setSelectedDraftIds(userOwnedDraftHeaders.map((opname) => opname.header.id));
                        setDraftChecklistOpen(true);
                        setError(null);
                      }}
                      disabled={userOwnedDraftHeaders.length === 0}
                      className="w-full sm:w-auto"
                    >
                      Ajukan Draft
                    </PrimaryAction>
                    <ExportButton onClick={handleExport}>Export Riwayat</ExportButton>
                  </div>
                ) : (
                  <ExportButton onClick={handleExport}>Export Riwayat</ExportButton>
                )}
              </div>
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">
                <tr>
                  <th className="px-6 py-3">ID Penyesuaian Stok</th>
                  <th className="px-6 py-3">Tanggal</th>
                  <th className="px-6 py-3">User</th>
                  <th className="px-6 py-3">Nama Bahan</th>
                  <th className="px-6 py-3">Jenis Bahan</th>
                  <th className="px-6 py-3">Stok Awal</th>
                  <th className="px-6 py-3">Selisih</th>
                  <th className="px-6 py-3">Stok Akhir</th>
                  <th className="px-6 py-3">Status</th>
                </tr>
              </thead>
              <tbody className="bg-white text-base text-[#334155]">
                {paginatedRows.map((row) => (
                  <tr key={row.key} className="border-t border-[#E2E8F0] transition hover:bg-[#F8FAFC]">
                    <td className="px-6 py-4 font-semibold text-[#16213E]">
                      PS-{String(row.headerId).padStart(4, "0")}
                    </td>
                    <td className="px-6 py-4 text-[#475569]">{formatDate(row.opnameDate)}</td>
                    <td className="px-6 py-4 text-[#475569]">{row.createdByLabel}</td>
                    <td className="px-6 py-4 font-semibold text-[#16213E]">{row.itemName}</td>
                    <td className="px-6 py-4 text-[#475569]">{row.categoryName}</td>
                    <td className="px-6 py-4 text-[#475569]">{row.systemQtyLabel}</td>
                    <td className={`px-6 py-4 font-semibold ${row.variance > 0 ? "text-[#10B981]" : row.variance < 0 ? "text-[#EF4444]" : "text-[#475569]"}`}>
                      {row.variance > 0 ? "+" : ""}
                      {row.varianceLabel}
                    </td>
                    <td className="px-6 py-4 text-[#475569]">{row.countedQtyLabel}</td>
                      <td className="px-6 py-4">
                        {allowVerificationAction && row.state === "SUBMITTED" ? (
                          <button
                            className="rounded-xl bg-[#2155CD] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#1948B7] disabled:bg-[#AFC4F7]"
                            disabled={confirmingHeaderId === row.headerId}
                            onClick={() => openVerificationConfirmation(row)}
                            type="button"
                          >
                            {confirmingHeaderId === row.headerId ? "Mengonfirmasi..." : "Konfirmasi"}
                          </button>
                        ) : (
                          <span className={`inline-flex rounded-full px-3 py-1 text-sm font-semibold ${getOpnameStateClasses(row.state)}`}>
                            {row.stateLabel}
                          </span>
                        )}
                      </td>
                  </tr>
                ))}
                {!loading && filteredRows.length === 0 ? (
                  <tr>
                    <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={9}>
                      Belum ada riwayat penyesuaian yang cocok dengan filter saat ini.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>

          <div className="border-t border-[#D7E0EE] bg-[#F8FAFC] px-6 py-4">
            <Pagination
              currentPage={currentPage}
              onPageChange={setCurrentPage}
              totalPages={totalPages}
              totalLabel={
                filteredRows.length > 0
                  ? `${(currentPage - 1) * pageSize + 1}-${Math.min(currentPage * pageSize, filteredRows.length)} dari ${filteredRows.length} item`
                  : "0 dari 0 item"
              }
            />
          </div>
        </SurfaceCard>
      </div>

      {modalOpen ? (
        <div className="fixed inset-0 z-[70] flex items-center justify-center px-4">
          <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={() => setModalOpen(false)} />
          <div className="relative w-full max-w-[390px] overflow-hidden rounded-[24px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
            <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
              <div>
                <h2 className="text-[24px] font-semibold text-[#16213E]">Input Penyesuaian Stok</h2>
                <p className="mt-1 text-sm text-[#94A3B8]">Untuk menghitung selisih stok sistem dan stok fisik</p>
              </div>
              <button
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD]"
                onClick={() => setModalOpen(false)}
                type="button"
              >
                <X size={18} />
              </button>
            </div>

            <div className="space-y-4 px-5 py-4">
              <div className="space-y-2">
                <label className="text-sm font-semibold text-[#475569]">
                  Nama Barang <span className="text-red-500">*</span>
                </label>
                <SearchableItemSelect
                  disabled={metadataLoading}
                  options={activeItemOptions}
                  placeholder={metadataLoading ? "Memuat data bahan..." : "Pilih Nama Bahan"}
                  value={selectedItemId}
                  onChange={(itemId) => setSelectedItemId(itemId)}
                />
              </div>

              <div className="space-y-2">
                <label className="text-sm font-semibold text-[#475569]">Jenis Bahan</label>
                <input
                  disabled
                  value={selectedItemCategory}
                  className="h-12 w-full rounded-xl border border-[#D7E0EE] bg-[#F8FAFC] px-4 text-base text-[#475569] outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="relative space-y-2">
                  <label className="text-sm font-semibold text-[#475569]">Stok di Sistem</label>
                  <input
                    disabled
                    value={selectedItem ? formatNumber(Number(selectedItem.qty), Number.isInteger(Number(selectedItem.qty)) ? 0 : 1) : "-"}
                    className="h-12 w-full rounded-xl border border-[#D7E0EE] bg-[#F8FAFC] px-4 text-base text-[#2155CD] outline-none"
                  />
                  <p className="text-xs text-[#94A3B8]">Otomatis dari data stok</p>
                </div>

                <div className="relative space-y-2">
                  <label className="text-sm font-semibold text-[#475569]">
                    Stok Fisik (Aktual) <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="number"
                    min="0"
                    value={countedQty}
                    onChange={(event) => setCountedQty(event.target.value)}
                    className="h-12 w-full rounded-xl border border-[#D7E0EE] px-4 pr-16 text-base text-[#334155] outline-none"
                  />
                  <span className="pointer-events-none absolute right-4 top-[42px] text-sm font-semibold text-[#94A3B8]">
                    {selectedItem?.unit_base ?? "-"}
                  </span>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-[#E2E8F0] px-5 py-4">
              <button
                className="rounded-xl border border-[#CBD5E1] px-5 py-2.5 text-base text-[#475569] transition hover:bg-[#F8FAFC]"
                onClick={() => setModalOpen(false)}
                type="button"
              >
                Batal
              </button>
              <button
                className="rounded-xl bg-[#2155CD] px-5 py-2.5 text-base font-medium text-white transition hover:bg-[#1948B7]"
                onClick={handlePrimarySaveClick}
                type="button"
                disabled={saving}
              >
                {saving ? "Menyimpan..." : "Simpan"}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {confirmOpen ? (
        <div className="fixed inset-0 z-[80] flex items-center justify-center px-4">
          <div className="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]" onClick={() => setConfirmOpen(false)} />
          <div className="relative w-full max-w-[440px] overflow-hidden rounded-[24px] bg-white shadow-[0_24px_70px_rgba(15,23,42,0.2)]">
            <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
              <div>
                <h2 className="text-[22px] font-semibold text-[#16213E]">Verifikasi Penyesuaian Stok</h2>
                <p className="mt-1 text-sm text-[#94A3B8]">Periksa kembali data sebelum stok bahan langsung diperbarui.</p>
              </div>
              <button
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD]"
                onClick={() => setConfirmOpen(false)}
                type="button"
              >
                <X size={18} />
              </button>
            </div>

            <div className="space-y-4 px-5 py-5">
              <div className="rounded-[18px] border border-[#FECACA] bg-[#FEF2F2] p-4">
                <p className="text-center text-sm font-medium text-[#DC2626]">
                  Pastikan data penyesuaian stok ini sudah benar karena stok bahan akan langsung diperbarui.
                </p>
              </div>

              <div className="rounded-[18px] border border-[#D7E0EE] bg-[#F8FBFF] p-4">
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p className="text-[#94A3B8]">Nama Barang</p>
                    <p className="mt-1 text-base font-semibold text-[#16213E]">{selectedItem?.name ?? "-"}</p>
                  </div>
                  <div>
                    <p className="text-[#94A3B8]">Jenis Bahan</p>
                    <div className="mt-1">
                      <span className="inline-flex rounded-full bg-[#E0ECFF] px-3 py-1 text-sm font-semibold text-[#2155CD]">
                        {selectedItemCategory}
                      </span>
                    </div>
                  </div>
                  <div>
                    <p className="text-[#94A3B8]">Stok Sistem</p>
                    <p className="mt-1 text-base font-semibold text-[#16213E]">
                      {formatNumber(selectedSystemQty, Number.isInteger(selectedSystemQty) ? 0 : 1)} {selectedItem?.unit_base ?? ""}
                    </p>
                  </div>
                  <div>
                    <p className="text-[#94A3B8]">Stok Fisik</p>
                    <p className="mt-1 text-base font-semibold text-[#16213E]">
                      {formatNumber(countedQtyValue, Number.isInteger(countedQtyValue) ? 0 : 1)} {selectedItem?.unit_base ?? ""}
                    </p>
                  </div>
                </div>
              </div>

              <div className="rounded-[18px] border border-[#D7E0EE] bg-white p-4">
                <p className="text-sm text-[#94A3B8]">Selisih yang akan diterapkan</p>
                <p
                  className={`mt-2 text-2xl font-semibold ${
                    previewVariance > 0 ? "text-[#10B981]" : previewVariance < 0 ? "text-[#EF4444]" : "text-[#475569]"
                  }`}
                >
                  {previewVariance > 0 ? "+" : ""}
                  {formatNumber(previewVariance, Number.isInteger(previewVariance) ? 0 : 1)} {selectedItem?.unit_base ?? ""}
                </p>
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-[#E2E8F0] px-5 py-4">
              <button
                className="rounded-xl border border-[#CBD5E1] px-5 py-2.5 text-base text-[#475569] transition hover:bg-[#F8FAFC]"
                onClick={() => setConfirmOpen(false)}
                type="button"
              >
                Batal
              </button>
              <button
                className="rounded-xl bg-[#2155CD] px-5 py-2.5 text-base font-medium text-white transition hover:bg-[#1948B7] disabled:bg-[#AFC4F7]"
                onClick={() => void handleCreateOpname()}
                type="button"
                disabled={saving}
              >
                {saving ? "Menyimpan..." : "Ya, Simpan"}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {verificationTarget ? (
        <div className="fixed inset-0 z-[85] flex items-center justify-center px-4 py-6">
          <div
            className="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]"
            onClick={() => confirmingHeaderId === null && setVerificationTarget(null)}
          />
          <div className="relative flex max-h-[calc(100vh-3rem)] w-full max-w-[440px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_24px_70px_rgba(15,23,42,0.2)]">
            <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
              <div>
                <h2 className="text-[22px] font-semibold text-[#16213E]">Konfirmasi Penyesuaian Stok</h2>
                <p className="mt-1 text-sm text-[#94A3B8]">Pastikan data ini siap diterapkan ke stok bahan.</p>
              </div>
              <button
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={() => setVerificationTarget(null)}
                type="button"
                disabled={confirmingHeaderId !== null}
              >
                <X size={18} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-5">
              <div className="space-y-4">
              <div className="rounded-[18px] border border-[#FECACA] bg-[#FEF2F2] p-4">
                <p className="text-center text-sm font-medium text-[#DC2626]">
                  Setelah dikonfirmasi, status akan berubah menjadi disetujui dan stok bahan langsung diperbarui.
                </p>
              </div>

              <div className="rounded-[18px] border border-[#D7E0EE] bg-[#F8FBFF] p-4">
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p className="text-[#94A3B8]">ID Penyesuaian</p>
                    <p className="mt-1 text-base font-semibold text-[#16213E]">
                      PS-{String(verificationTarget.headerId).padStart(4, "0")}
                    </p>
                  </div>
                  <div>
                    <p className="text-[#94A3B8]">User Pengaju</p>
                    <p className="mt-1 text-base font-semibold text-[#16213E]">{verificationTarget.createdByLabel}</p>
                  </div>
                  <div>
                    <p className="text-[#94A3B8]">Nama Barang</p>
                    <p className="mt-1 text-base font-semibold text-[#16213E]">{verificationTarget.itemName}</p>
                  </div>
                  <div>
                    <p className="text-[#94A3B8]">Jenis Bahan</p>
                    <div className="mt-1">
                      <span className="inline-flex rounded-full bg-[#E0ECFF] px-3 py-1 text-sm font-semibold text-[#2155CD]">
                        {verificationTarget.categoryName}
                      </span>
                    </div>
                  </div>
                  <div>
                    <p className="text-[#94A3B8]">Stok Sistem</p>
                    <p className="mt-1 text-base font-semibold text-[#16213E]">{verificationTarget.systemQtyLabel}</p>
                  </div>
                  <div>
                    <p className="text-[#94A3B8]">Stok Fisik</p>
                    <p className="mt-1 text-base font-semibold text-[#16213E]">{verificationTarget.countedQtyLabel}</p>
                  </div>
                </div>
              </div>

              <div className="rounded-[18px] border border-[#D7E0EE] bg-white p-4">
                <p className="text-sm text-[#94A3B8]">Selisih yang akan diterapkan</p>
                <p className={`mt-2 text-2xl font-semibold ${verificationTarget.varianceToneClass}`}>
                  {verificationTarget.varianceLabel}
                </p>
              </div>

              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-[#E2E8F0] px-5 py-4">
              <button
                className="rounded-xl border border-[#CBD5E1] px-5 py-2.5 text-base text-[#475569] transition hover:bg-[#F8FAFC] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={() => setVerificationTarget(null)}
                type="button"
                disabled={confirmingHeaderId !== null || rejectingHeaderId !== null}
              >
                Batal
              </button>
              <button
                className="rounded-xl bg-[#DC2626] px-5 py-2.5 text-base font-medium text-white transition hover:bg-[#B91C1C] disabled:bg-[#FCA5A5]"
                onClick={() => {
                  setRejectTarget({
                    headerId: verificationTarget.headerId,
                    itemName: verificationTarget.itemName,
                  });
                  setVerificationTarget(null);
                  setRejectionReason("");
                }}
                type="button"
                disabled={confirmingHeaderId !== null || rejectingHeaderId !== null}
              >
                Tolak
              </button>
              <button
                className="rounded-xl bg-[#2155CD] px-5 py-2.5 text-base font-medium text-white transition hover:bg-[#1948B7] disabled:bg-[#AFC4F7]"
                onClick={() => void handleConfirmSubmission(verificationTarget.headerId)}
                type="button"
                disabled={confirmingHeaderId !== null || rejectingHeaderId !== null}
              >
                {confirmingHeaderId === verificationTarget.headerId ? "Mengonfirmasi..." : "Ya, Konfirmasi"}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {rejectTarget ? (
        <div className="fixed inset-0 z-[86] flex items-center justify-center px-4 py-6">
          <div
            className="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]"
            onClick={() => rejectingHeaderId === null && setRejectTarget(null)}
          />
          <div className="relative flex max-h-[calc(100vh-3rem)] w-full max-w-[440px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_24px_70px_rgba(15,23,42,0.2)]">
            <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
              <div>
                <h2 className="text-[22px] font-semibold text-[#16213E]">Alasan Penolakan</h2>
                <p className="mt-1 text-sm text-[#94A3B8]">Isi alasan sebelum pengajuan untuk bahan {rejectTarget.itemName} ditolak.</p>
              </div>
              <button
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={() => setRejectTarget(null)}
                type="button"
                disabled={rejectingHeaderId !== null}
              >
                <X size={18} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-5">
              <div className="space-y-4">
                <div className="rounded-[18px] border border-[#FECACA] bg-[#FEF2F2] p-4">
                  <p className="text-sm font-medium text-[#DC2626]">
                    Pengajuan penyesuaian stok akan berubah menjadi <span className="font-semibold">Ditolak</span> setelah alasan dikirim.
                  </p>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-semibold text-[#475569]">
                    Alasan Penolakan <span className="text-[#DC2626]">*</span>
                  </label>
                  <textarea
                    value={rejectionReason}
                    onChange={(event) => setRejectionReason(event.target.value)}
                    className="min-h-[120px] w-full rounded-xl border border-[#D7E0EE] px-4 py-3 text-sm text-[#334155] outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#DBEAFE]"
                    placeholder="Masukkan alasan penolakan jika pengajuan tidak bisa diterima"
                    disabled={rejectingHeaderId !== null}
                  />
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-[#E2E8F0] px-5 py-4">
              <button
                className="rounded-xl border border-[#CBD5E1] px-5 py-2.5 text-base text-[#475569] transition hover:bg-[#F8FAFC] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={() => setRejectTarget(null)}
                type="button"
                disabled={rejectingHeaderId !== null}
              >
                Batal
              </button>
              <button
                className="rounded-xl bg-[#DC2626] px-5 py-2.5 text-base font-medium text-white transition hover:bg-[#B91C1C] disabled:bg-[#FCA5A5]"
                onClick={() => void handleRejectSubmission(rejectTarget.headerId)}
                type="button"
                disabled={rejectingHeaderId !== null}
              >
                {rejectingHeaderId === rejectTarget.headerId ? "Menolak..." : "Kirim Penolakan"}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {draftChecklistOpen ? (
        <div className="fixed inset-0 z-[82] flex items-center justify-center px-4 py-6">
          <div
            className="absolute inset-0 bg-slate-900/45 backdrop-blur-[2px]"
            onClick={() => submittingHeaderId === null && setDraftChecklistOpen(false)}
          />
          <div className="relative flex max-h-[calc(100vh-3rem)] w-full max-w-[520px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_24px_70px_rgba(15,23,42,0.2)]">
            <div className="flex items-start justify-between border-b border-[#E2E8F0] px-5 py-4">
              <div>
                <h2 className="text-[22px] font-semibold text-[#16213E]">Ajukan Draft Penyesuaian Stok</h2>
                <p className="mt-1 text-sm text-[#94A3B8]">Pilih draft yang ingin diajukan ke admin untuk diverifikasi.</p>
              </div>
              <button
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAFC] text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={() => setDraftChecklistOpen(false)}
                type="button"
                disabled={submittingHeaderId !== null}
              >
                <X size={18} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-5">
              <div className="space-y-4">
                <div className="rounded-[18px] border border-[#D7E0EE] bg-[#F8FBFF] p-4">
                  <p className="text-sm text-[#475569]">
                    Draft terpilih akan berubah menjadi <span className="font-semibold text-[#2155CD]">Menunggu Verifikasi</span>.
                  </p>
                </div>

                {userOwnedDraftHeaders.length > 0 ? (
                  <div className="space-y-3">
                    {userOwnedDraftHeaders.map((opname) => {
                      const detail = opname.details[0];
                      const itemId = Number(detail?.item_id ?? 0);
                      const relatedItem = itemMap.get(itemId);
                      const relatedStockRow = stockRowMap.get(itemId);
                      const itemName = String(relatedItem?.name ?? "") || String(relatedStockRow?.item_name ?? "") || `Item #${itemId}`;
                      const categoryName = String(relatedItem?.category?.name ?? "") || String(relatedStockRow?.category_name ?? "") || "-";
                      const variance = Number(detail?.variance_qty ?? 0);
                      const varianceLabel = `${variance > 0 ? "+" : ""}${formatNumber(variance, Number.isInteger(variance) ? 0 : 1)}`;
                      const checked = selectedDraftIds.includes(opname.header.id);

                      return (
                        <label
                          key={opname.header.id}
                          className={`flex cursor-pointer items-start gap-3 rounded-[18px] border p-4 transition ${
                            checked ? "border-[#2155CD] bg-[#EEF4FF]" : "border-[#D7E0EE] bg-white hover:border-[#BFD1F6]"
                          }`}
                        >
                          <input
                            type="checkbox"
                            className="mt-1 h-4 w-4 rounded border-[#CBD5E1] text-[#2155CD] focus:ring-[#DBEAFE]"
                            checked={checked}
                            onChange={(event) => {
                              setSelectedDraftIds((current) =>
                                event.target.checked
                                  ? [...current, opname.header.id]
                                  : current.filter((id) => id !== opname.header.id),
                              );
                            }}
                          />
                          <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                              <p className="text-base font-semibold text-[#16213E]">
                                PS-{String(opname.header.id).padStart(4, "0")}
                              </p>
                              <span className="text-sm text-[#94A3B8]">{formatDate(opname.header.opname_date)}</span>
                            </div>
                            <div className="mt-3 grid gap-3 text-sm text-[#475569] sm:grid-cols-2">
                              <div>
                                <p className="text-[#94A3B8]">Nama Barang</p>
                                <p className="mt-1 font-medium text-[#16213E]">{itemName}</p>
                              </div>
                              <div>
                                <p className="text-[#94A3B8]">Jenis Bahan</p>
                                <p className="mt-1 font-medium text-[#16213E]">{categoryName}</p>
                              </div>
                              <div>
                                <p className="text-[#94A3B8]">Stok Sistem</p>
                                <p className="mt-1 font-medium text-[#16213E]">
                                  {formatNumber(Number(detail?.system_qty ?? 0), Number.isInteger(Number(detail?.system_qty ?? 0)) ? 0 : 1)}
                                </p>
                              </div>
                              <div>
                                <p className="text-[#94A3B8]">Stok Fisik</p>
                                <p className="mt-1 font-medium text-[#16213E]">
                                  {formatNumber(Number(detail?.counted_qty ?? 0), Number.isInteger(Number(detail?.counted_qty ?? 0)) ? 0 : 1)}
                                </p>
                              </div>
                            </div>
                            <div className="mt-3">
                              <span className={`text-base font-semibold ${variance > 0 ? "text-[#10B981]" : variance < 0 ? "text-[#EF4444]" : "text-[#475569]"}`}>
                                Selisih {varianceLabel}
                              </span>
                            </div>
                          </div>
                        </label>
                      );
                    })}
                  </div>
                ) : (
                  <div className="rounded-[18px] border border-dashed border-[#D7E0EE] bg-[#F8FAFC] px-4 py-8 text-center text-sm text-[#94A3B8]">
                    Belum ada draft milik Anda yang bisa diajukan.
                  </div>
                )}
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-[#E2E8F0] px-5 py-4">
              <button
                className="rounded-xl border border-[#CBD5E1] px-5 py-2.5 text-base text-[#475569] transition hover:bg-[#F8FAFC] disabled:cursor-not-allowed disabled:opacity-60"
                onClick={() => setDraftChecklistOpen(false)}
                type="button"
                disabled={submittingHeaderId !== null}
              >
                Batal
              </button>
              <button
                className="rounded-xl bg-[#2155CD] px-5 py-2.5 text-base font-medium text-white transition hover:bg-[#1948B7] disabled:bg-[#AFC4F7]"
                onClick={() => void handleSubmitSelectedDrafts()}
                type="button"
                disabled={submittingHeaderId !== null || selectedDraftIds.length === 0}
              >
                {submittingHeaderId === -1 ? "Mengajukan..." : "Ajukan Draft"}
              </button>
            </div>
          </div>
        </div>
      ) : null}

      <SuccessModal
        open={Boolean(successConfig)}
        title="Berhasil"
        headline={successConfig?.headline ?? ""}
        message={successConfig?.message ?? ""}
        onClose={() => setSuccessConfig(null)}
      />
    </>
  );
}
