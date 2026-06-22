"use client";

import type { ReactNode } from "react";
import { useCallback, useEffect, useMemo, useState } from "react";
import { AlertTriangle, PackageX, Zap } from "lucide-react";
import sdk from "@/lib";
import { formatNumber, formatQuantity, getErrorMessage, getStockTone } from "@/lib/admin-utils";
import { buildExportFilename } from "@/lib/export-filename";
import { clearItemsCache, listAllItems } from "@/lib/items";
import { isItemDeleteConstraintError } from "@/lib/item-delete-guards";
import {
  buildSpreadsheetDocument,
  downloadSpreadsheetHtml,
  escapeSpreadsheetHtml,
  formatSpreadsheetNumber,
} from "@/lib/spreadsheet-export";
import {
  AdminPageHeading,
  ExportButton,
  FilterSearch,
  MiniActionButton,
  Pagination,
  PrimaryAction,
  StatusPill,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import DeleteConfirmModal from "@/components/feedback/DeleteConfirmModal";
import SuccessModal from "@/components/feedback/SuccessModal";
import StockItemModal, { type StockItemFormValue } from "@/components/stock/StockItemModal";

type ItemRecord = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
type ItemCategoryRecord = Awaited<ReturnType<typeof sdk.itemCategories.list>>["data"][number];
type ItemUnitRecord = Awaited<ReturnType<typeof sdk.itemUnits.list>>["data"][number];
type ItemListQuery = NonNullable<Parameters<typeof sdk.items.list>[0]>;
type NoticeState = {
  title: string;
  headline: string;
  message: string;
  tone?: "success" | "danger";
  icon?: ReactNode;
} | null;
type ModalMode = "create" | "edit" | null;

const statCards = [
  {
    key: "warning",
    title: "STOK MENIPIS",
    note: "Bahan kering di bawah minimum",
    accent: "border-[#F59E0B]",
    iconBg: "bg-[#FFF7CC]",
    iconColor: "text-[#92400E]",
    icon: Zap,
  },
  {
    key: "critical",
    title: "STOK KRITIS",
    note: "Bahan kering mendekati habis",
    accent: "border-[#FB7185]",
    iconBg: "bg-[#FFE4E6]",
    iconColor: "text-[#BE123C]",
    icon: AlertTriangle,
  },
  {
    key: "danger",
    title: "STOK HABIS",
    note: "Bahan kering habis",
    accent: "border-[#818CF8]",
    iconBg: "bg-[#E0E7FF]",
    iconColor: "text-[#3730A3]",
    icon: PackageX,
  },
] as const;

function normalizeFilterValue(value: string) {
  return value.trim().toUpperCase();
}

export default function Page() {
  const [items, setItems] = useState<ItemRecord[]>([]);
  const [categories, setCategories] = useState<ItemCategoryRecord[]>([]);
  const [itemUnits, setItemUnits] = useState<ItemUnitRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [modalMode, setModalMode] = useState<ModalMode>(null);
  const [selectedItem, setSelectedItem] = useState<ItemRecord | null>(null);
  const [modalError, setModalError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<ItemRecord | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [deleting, setDeleting] = useState(false);
  const [successState, setSuccessState] = useState<NoticeState>(null);
  const [totalRecords, setTotalRecords] = useState(0);
  const [currentPage, setCurrentPage] = useState(1);
  const [searchTerm, setSearchTerm] = useState("");
  const [categoryFilter, setCategoryFilter] = useState("Semua Jenis");
  const [statusFilter, setStatusFilter] = useState("Semua Status");

  const loadPageData = useCallback(async () => {
    const baseParams: ItemListQuery = {
      perPage: 100,
      sortBy: "id",
      sortDir: "ASC",
    };

    if (searchTerm.trim()) baseParams.q = searchTerm.trim();
    if (categoryFilter !== "Semua Jenis") {
      const cat = categories.find(c => c.name === categoryFilter);
      if (cat) baseParams.item_category_id = cat.id;
    }

    const allItems = (await listAllItems(baseParams)).filter(
      (item) => item.category?.name?.toUpperCase() !== "BASAH",
    );

    setItems(allItems);
    setTotalRecords(allItems.length);
  }, [searchTerm, categoryFilter, categories]);

  async function ensureAuxiliaryData() {
    if (categories.length > 0 && itemUnits.length > 0) return;
    try {
      const [categoryResponse, unitResponse] = await Promise.all([
        sdk.itemCategories.list({ paginate: false, sortBy: "name", sortDir: "ASC" }),
        sdk.itemUnits.list({ paginate: false, sortBy: "name", sortDir: "ASC" }),
      ]);
      setCategories(categoryResponse.data ?? []);
      setItemUnits(unitResponse.data ?? []);
    } catch (err) {
      console.error("Failed to load auxiliary data:", err);
    }
  }

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        await Promise.all([ensureAuxiliaryData(), loadPageData()]);
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat stok kering."));
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
  }, [loadPageData]);

  const categoryOptions = useMemo(
    () =>
      categories
        .filter((category) => category.name?.toUpperCase() !== "BASAH")
        .map((category) => ({
          id: category.id,
          name: category.name,
        })),
    [categories],
  );

  const itemUnitOptions = useMemo(
    () =>
      itemUnits.map((unit) => ({
        id: unit.id,
        name: unit.name,
      })),
    [itemUnits],
  );

  function getUnitConvertName(unitName: string) {
    const normalized = unitName.trim().toLowerCase();

    if (normalized === "gram") {
      return "kg";
    }
    if (normalized === "ml") {
      return "liter";
    }
    if (normalized === "butir") {
      return "pack";
    }

    return {
      gram: "kg",
      ml: "liter",
      butir: "pack",
    }[normalized] ?? unitName;
  }

  async function openCreateModal() {
    setModalError(null);
    setSelectedItem(null);
    setModalMode("create");
    await ensureAuxiliaryData();
  }

  async function openEditModal(item: ItemRecord) {
    setModalError(null);
    setSelectedItem(item);
    setModalMode("edit");
    await ensureAuxiliaryData();
  }

  function closeModal() {
    setModalMode(null);
    setSelectedItem(null);
    setModalError(null);
  }

  function closeDeleteModal() {
    setDeleteTarget(null);
    setDeleteError(null);
    setDeleting(false);
  }

  const initialFormValue = useMemo<StockItemFormValue | null>(() => {
    if (!selectedItem) {
      return null;
    }

    return {
      id: selectedItem.id,
      name: selectedItem.name,
      categoryName: selectedItem.category?.name ?? "",
      minimumStock: String(Number(selectedItem.conversion_base ?? 0) || 0),
      unitName: selectedItem.unit_base ?? "",
      unitConvertName: selectedItem.unit_convert ?? "",
    };
  }, [selectedItem]);

  async function handleSubmit(formValue: StockItemFormValue) {
    const trimmedName = formValue.name.trim();
    const trimmedCategory = formValue.categoryName.trim();
    const trimmedUnitName = formValue.unitName.trim();
    const trimmedUnitConvertName = formValue.unitConvertName?.trim() || getUnitConvertName(trimmedUnitName);
    const minimumStock = Number(formValue.minimumStock);

    if (!trimmedName || !trimmedCategory || !trimmedUnitName || !Number.isFinite(minimumStock) || minimumStock <= 0) {
      setModalError("Mohon lengkapi nama bahan, jenis bahan, satuan item, dan minimal stock dengan benar.");
      return;
    }

    if (
      modalMode === "edit" &&
      selectedItem &&
      trimmedName === selectedItem.name &&
      trimmedCategory === (selectedItem.category?.name ?? "").trim() &&
      trimmedUnitName === (selectedItem.unit_base ?? "").trim() &&
      trimmedUnitConvertName === (selectedItem.unit_convert ?? "").trim() &&
      minimumStock === (Number(selectedItem.conversion_base ?? 0) || 0)
    ) {
      setSuccessState({
        title: "Informasi",
        headline: "Belum ada perubahan",
        message: "Ubah data master barang terlebih dahulu sebelum menyimpan.",
      });
      closeModal();
      return;
    }

    const payload = {
      name: trimmedName,
      item_category_name: trimmedCategory,
      conversion_base: minimumStock,
      unit_base: trimmedUnitName,
      unit_convert: trimmedUnitConvertName,
      is_active: true,
    } as const;

    setSubmitting(true);
    setModalError(null);

    try {
      if (modalMode === "create") {
        await sdk.items.create(payload);
        setSuccessState({
          title: "Berhasil",
          headline: "Master Barang Berhasil Ditambahkan",
          message: `Data bahan ${trimmedName} berhasil disimpan.`,
        });
      } else if (selectedItem) {
        await sdk.items.update(selectedItem.id, payload);
        setSuccessState({
          title: "Berhasil",
          headline: "Master Barang Berhasil Diedit",
          message: `Data bahan ${trimmedName} berhasil diperbarui.`,
        });
      }

      clearItemsCache();
      await loadPageData();
      closeModal();
    } catch (submitError) {
      setModalError(getErrorMessage(submitError, "Gagal menyimpan master barang."));
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!deleteTarget) {
      return;
    }

    setDeleting(true);
    setDeleteError(null);

    try {
      await sdk.items.delete(deleteTarget.id);
      clearItemsCache();
      await loadPageData();
      setSuccessState({
        title: "Berhasil",
        headline: "Master Barang Berhasil Dihapus",
        message: `Data bahan ${deleteTarget.name} berhasil dihapus permanen dari sistem.`,
      });
      closeDeleteModal();
    } catch (deleteFailure) {
      const message = getErrorMessage(deleteFailure, "Gagal menghapus master barang.");

      if (isItemDeleteConstraintError(message)) {
        closeDeleteModal();
        setSuccessState({
          title: "Peringatan",
          headline: "Barang Tidak Bisa Dihapus",
          message: "Bahan masih dipakai oleh sistem, jadi tidak bisa dihapus.",
          tone: "danger",
          icon: <AlertTriangle size={36} strokeWidth={2.1} />,
        });
        return;
      }

      setDeleteError(message);
    } finally {
      setDeleting(false);
    }
  }

  const itemRows = useMemo(() => {
    return items.map((item) => {
      const minimum = Number(item.conversion_base ?? 0) || 0;
      const qty = Number(item.qty ?? 0);
      const stock = getStockTone(qty, minimum || 1);

      return {
        idLabel: `BR-${String(item.id).padStart(4, "0")}`,
        name: item.name,
        category: item.category?.name ?? "-",
        qtyLabel: formatQuantity(qty, item.unit_base),
        minimumLabel: formatQuantity(minimum, item.unit_base),
        tone: stock.tone,
        label: stock.label,
        raw: item,
      };
    });
  }, [items]);

  const filteredItems = useMemo(() => {
    const normalizedStatus = normalizeFilterValue(statusFilter);
    if (normalizedStatus === normalizeFilterValue("Semua Status")) return itemRows;
    return itemRows.filter((item) => normalizeFilterValue(item.label) === normalizedStatus);
  }, [itemRows, statusFilter]);

  function handleExport() {
    if (typeof window === "undefined" || filteredItems.length === 0) return;

    const summaryRows = [
      { label: "Total Item", value: formatSpreadsheetNumber(filteredItems.length, 0) },
      { label: "Stok Menipis", value: formatSpreadsheetNumber(counts.warning, 0) },
      { label: "Stok Kritis", value: formatSpreadsheetNumber(counts.critical, 0) },
      { label: "Stok Habis", value: formatSpreadsheetNumber(counts.danger, 0) },
    ];

    const tableRows = filteredItems
      .map(
        (item, index) => `
          <tr>
            <td class="rank">${index + 1}</td>
            <td class="text-strong">${escapeSpreadsheetHtml(item.idLabel)}</td>
            <td class="text-strong">${escapeSpreadsheetHtml(item.name)}</td>
            <td>${escapeSpreadsheetHtml(item.category)}</td>
            <td class="number">${escapeSpreadsheetHtml(item.qtyLabel)}</td>
            <td class="number">${escapeSpreadsheetHtml(item.minimumLabel)}</td>
            <td class="pill ${item.tone}">${escapeSpreadsheetHtml(item.label)}</td>
          </tr>
        `,
      )
      .join("");

    const html = buildSpreadsheetDocument({
      title: "LAPORAN DATA STOK BAHAN INSTALASI GIZI RSD BALUNG",
      subtitle: "Rekapitulasi data stok bahan kering berdasarkan filter stok bahan saat ini.",
      body: `
        <table class="section-gap">
          <tr class="no-border">
            <td class="title" colspan="7">LAPORAN DATA STOK BAHAN INSTALASI GIZI RSD BALUNG</td>
          </tr>
          <tr class="no-border">
            <td class="subtitle" colspan="7">Rekapitulasi data stok bahan kering berdasarkan filter stok bahan saat ini.</td>
          </tr>
        </table>

        <table class="section-gap">
          <tr><td class="section" colspan="2">RINGKASAN</td></tr>
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
            <th>No</th>
            <th>ID Barang</th>
            <th>Nama Bahan</th>
            <th>Jenis Bahan</th>
            <th>Stok Saat Ini</th>
            <th>Minimal Stok</th>
            <th>Status</th>
          </tr>
          ${tableRows || `<tr><td class="muted" colspan="7">Belum ada data stok kering.</td></tr>`}
        </table>
      `,
    });

    downloadSpreadsheetHtml(buildExportFilename("laporan-data-stok-bahan"), html);
  }

  const totalPages = Math.max(1, Math.ceil(filteredItems.length / 10));
  const visibleItems = useMemo(() => {
    const startIndex = (currentPage - 1) * 10;
    return filteredItems.slice(startIndex, startIndex + 10);
  }, [currentPage, filteredItems]);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, categoryFilter, statusFilter]);

  useEffect(() => {
    if (currentPage > totalPages) setCurrentPage(totalPages);
  }, [currentPage, totalPages]);

  const counts = useMemo(() => {
    return itemRows.reduce(
      (acc, item) => {
        acc[item.tone] += 1;
        return acc;
      },
      { warning: 0, critical: 0, danger: 0, safe: 0 } as Record<
        "warning" | "critical" | "danger" | "safe",
        number
      >,
    );
  }, [itemRows]);

  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Stok Kering"
        subtitle="Pantau stok bahan kering dan pengemas dari data backend"
        action={<PrimaryAction onClick={openCreateModal}>Tambah Master Barang</PrimaryAction>}
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
              <div
                className={`mb-5 flex h-8 w-8 items-center justify-center rounded-[9px] ${card.iconBg}`}
              >
                <Icon size={14} className={card.iconColor} />
              </div>
              <p className="text-[11px] font-semibold tracking-[0.04em] text-[#94A3B8]">
                {card.title}
              </p>
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
                onChange={setSearchTerm}
                placeholder="Cari nama bahan, ID, atau jenis bahan"
                readOnly={false}
                value={searchTerm}
              />
            </div>
            <ThemedSelect
              className="min-w-[180px]"
              value={categoryFilter}
              onChange={setCategoryFilter}
              options={[
                { value: "Semua Jenis", label: "Semua Jenis" },
                ...categoryOptions.map((category) => ({ value: category.name, label: category.name })),
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
          <table className="w-full text-sm text-left">
            <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <th className="px-6 py-3">ID Barang</th>
                <th className="px-6 py-3">Nama Bahan</th>
                <th className="px-6 py-3">Jenis Bahan</th>
                <th className="px-6 py-3">Stok Saat Ini</th>
                <th className="px-6 py-3">Minimal Stok</th>
                <th className="px-6 py-3">Status</th>
                <th className="px-6 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody className="bg-white text-sm text-gray-700">
              {visibleItems.map((item) => (
                <tr
                  key={item.idLabel}
                  className="border-t border-gray-200 transition hover:bg-gray-50"
                >
                  <td className="px-6 py-4 font-medium text-gray-900">{item.idLabel}</td>
                  <td className="px-6 py-4 font-medium text-gray-900">{item.name}</td>
                  <td className="px-6 py-4">{item.category}</td>
                  <td className="px-6 py-4">{item.qtyLabel}</td>
                  <td className="px-6 py-4">{item.minimumLabel}</td>
                  <td className="px-6 py-4">
                    <StatusPill tone={item.tone}>{item.label}</StatusPill>
                  </td>
                      <td className="px-6 py-4">
                        <div className="flex justify-end gap-2">
                          <MiniActionButton onClick={() => openEditModal(item.raw)}>Edit</MiniActionButton>
                          <MiniActionButton
                            onClick={() => {
                              setDeleteTarget(item.raw);
                              setDeleteError(null);
                            }}
                            tone="danger"
                          >
                            Hapus
                          </MiniActionButton>
                        </div>
                  </td>
                </tr>
              ))}
              {!loading && visibleItems.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={7}>
                    Belum ada data stok kering.
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
          totalLabel={`${filteredItems.length === 0 ? 0 : (currentPage - 1) * 10 + 1}-${Math.min(
            currentPage * 10,
            filteredItems.length,
          )} dari ${filteredItems.length} item`}
        />
      </SurfaceCard>

      <StockItemModal
        categories={categoryOptions}
        error={modalError}
        initialValue={initialFormValue}
        itemUnits={itemUnitOptions}
        key={`${modalMode ?? "closed"}-${selectedItem?.id ?? "new"}-${categoryOptions.length}-${itemUnitOptions.length}`}
        mode={modalMode === "edit" ? "edit" : "create"}
        onClose={closeModal}
        onSubmit={handleSubmit}
        open={modalMode !== null}
        submitting={submitting}
      />

      <DeleteConfirmModal
        description={`Data bahan ${deleteTarget?.name ?? ""} akan dihapus permanen dari sistem dan tidak bisa dipulihkan lagi.`}
        error={deleteError}
        headline={`Hapus bahan ${deleteTarget?.name ?? ""}?`}
        onClose={closeDeleteModal}
        onConfirm={handleDelete}
        open={deleteTarget !== null}
        submitting={deleting}
      />

      <SuccessModal
        headline={successState?.headline ?? ""}
        message={successState?.message ?? ""}
        onClose={() => setSuccessState(null)}
        open={successState !== null}
        title={successState?.title ?? "Berhasil"}
        tone={successState?.tone ?? "success"}
        icon={successState?.icon}
      />
    </div>
  );
}
