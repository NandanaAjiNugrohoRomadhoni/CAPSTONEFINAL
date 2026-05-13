"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { AlertTriangle, PackageX, Zap } from "lucide-react";
import sdk from "@/lib";
import { formatNumber, formatQuantity, getErrorMessage, getStockTone } from "@/lib/admin-utils";
import {
  AdminPageHeading,
  ExportButton,
  FilterSearch,
  MiniActionButton,
  Pagination,
  PrimaryAction,
  StatusPill,
  SurfaceCard,
} from "@/components/admin/ui";
import DeleteConfirmModal from "@/components/feedback/DeleteConfirmModal";
import SuccessModal from "@/components/feedback/SuccessModal";
import StockItemModal, { type StockItemFormValue } from "@/components/stock/StockItemModal";

type ItemRecord = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
type ItemCategoryRecord = Awaited<ReturnType<typeof sdk.itemCategories.list>>["data"][number];
type ItemUnitRecord = Awaited<ReturnType<typeof sdk.itemUnits.list>>["data"][number];
type NoticeState = { title: string; headline: string; message: string } | null;
type ModalMode = "create" | "edit" | null;

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
    const params: any = {
      perPage: 10,
      page: currentPage,
      sortBy: "id",
      sortDir: "ASC",
    };

    if (searchTerm.trim()) params.q = searchTerm.trim();
    if (categoryFilter !== "Semua Jenis") {
      const cat = categories.find(c => c.name === categoryFilter);
      if (cat) params.category_id = cat.id;
    }
    if (statusFilter !== "Semua Status") {
      params.is_active = statusFilter === "Aktif";
    }

    const itemResponse = await sdk.items.list(params);
    setItems(itemResponse.data ?? []);
    setTotalRecords(itemResponse.meta?.total ?? itemResponse.data?.length ?? 0);
  }, [currentPage, searchTerm, categoryFilter, statusFilter, categories]);

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
        await loadPageData();
      } catch (loadError) {
        if (!cancelled) {
          setError(getErrorMessage(loadError, "Gagal memuat stok bahan."));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [loadPageData]);

  const categoryOptions = useMemo(
    () =>
      categories.map((category) => ({
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

    const unitConvertName = formValue.unitConvertName?.trim() || getUnitConvertName(trimmedUnitName);
    const payload = {
      name: trimmedName,
      item_category_name: trimmedCategory,
      conversion_base: minimumStock,
      unit_base: trimmedUnitName,
      unit_convert: unitConvertName,
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

      await loadPageData();
      setSubmitting(false);
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
      await loadPageData();
      setSuccessState({
        title: "Berhasil",
        headline: "Master Barang Berhasil Dihapus",
        message: `Data bahan ${deleteTarget.name} berhasil dihapus permanen dari sistem.`,
      });
      setDeleteTarget(null);
    } catch (deleteFailure) {
      const message = getErrorMessage(deleteFailure, "Gagal menghapus master barang.");

      if (message.startsWith("Barang Dipakai Pada Menu")) {
        setDeleteTarget(null);
        setDeleteError(null);
        setSuccessState({
          title: "Peringatan",
          headline: "Barang Tidak Bisa Dihapus",
          message,
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


  const totalPages = Math.max(1, Math.ceil(totalRecords / 10));
  const visibleItems = itemRows;

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, categoryFilter, statusFilter]);

  const counts = useMemo(() => {
    return itemRows.reduce(
      (acc, item) => {
        acc[item.tone] += 1;
        return acc;
      },
      { warning: 0, critical: 0, danger: 0, safe: 0 } as Record<"warning" | "critical" | "danger" | "safe", number>,
    );
  }, [itemRows]);

  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Stok Bahan"
        subtitle="Pantau ketersediaan bahan secara langsung (real-time)"
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
                onChange={setSearchTerm}
                placeholder="Cari nama bahan, ID, atau jenis bahan"
                readOnly={false}
                value={searchTerm}
              />
            </div>
            <select
              className="h-12 min-w-[180px] rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-base text-[#334155] outline-none"
              onChange={(event) => setCategoryFilter(event.target.value)}
              value={categoryFilter}
            >
              <option>Semua Jenis</option>
              {categoryOptions.map((category) => (
                <option key={category.id} value={category.name}>
                  {category.name}
                </option>
              ))}
            </select>
            <select
              className="h-12 min-w-[180px] rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-base text-[#334155] outline-none"
              onChange={(event) => setStatusFilter(event.target.value)}
              value={statusFilter}
            >
              <option>Semua Status</option>
              <option>Aman</option>
              <option>Menipis</option>
              <option>Kritis</option>
              <option>Habis</option>
            </select>
          </div>
          <div className="ml-auto">
            <ExportButton />
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
                <tr key={item.idLabel} className="border-t border-gray-200 transition hover:bg-gray-50">
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
                      <MiniActionButton onClick={() => {
                        setDeleteError(null);
                        setDeleteTarget(item.raw);
                      }} tone="danger">
                        Hapus
                      </MiniActionButton>
                    </div>
                  </td>
                </tr>
              ))}
              {!loading && visibleItems.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={7}>
                    Belum ada data stok bahan.
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <Pagination totalLabel={`${itemRows.length === 0 ? 0 : 1}-${itemRows.length} dari ${itemRows.length} item`} />
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
        onClose={() => {
          if (deleting) return;
          setDeleteTarget(null);
          setDeleteError(null);
        }}
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
      />
    </div>
  );
}
