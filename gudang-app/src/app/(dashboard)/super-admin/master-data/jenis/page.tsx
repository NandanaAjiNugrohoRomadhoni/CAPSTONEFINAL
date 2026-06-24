"use client";

import type { FormEvent, ReactNode } from "react";
import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { AlertTriangle, Trash2, X } from "lucide-react";
import DeleteConfirmModal from "@/components/feedback/DeleteConfirmModal";
import SuccessModal from "@/components/feedback/SuccessModal";
import {
  FilterSearch,
  MiniActionButton,
  Pagination,
  PrimaryAction,
  SurfaceCard,
} from "@/components/admin/ui";
import sdk from "@/lib";
import { listAllItems } from "@/lib/items";
import { listAllPaginatedRows } from "@/lib/pagination";

type ModalMode = "add" | "edit" | "delete" | null;

type ItemCategory = {
  id: number;
  name: string;
  created_at: string | null;
  updated_at: string | null;
};

type SuccessState = {
  title?: string;
  headline: string;
  message: string;
  tone?: "success" | "danger";
  icon?: ReactNode;
} | null;

type ItemCategoryRow = Awaited<ReturnType<typeof sdk.itemCategories.list>>["data"][number];

function getErrorMessage(error: unknown, fallback: string): string {
  if (
    typeof error === "object" &&
    error !== null &&
    "body" in error &&
    typeof (error as { body?: unknown }).body === "object" &&
    (error as { body?: { message?: unknown } }).body !== null &&
    typeof (error as { body?: { message?: unknown } }).body?.message === "string"
  ) {
    return (error as { body: { message: string } }).body.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export default function JenisBahanPage() {
  const router = useRouter();
  const [items, setItems] = useState<ItemCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [modalMode, setModalMode] = useState<ModalMode>(null);
  const [jenisBahan, setJenisBahan] = useState("");
  const [selectedItem, setSelectedItem] = useState<ItemCategory | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [modalError, setModalError] = useState<string | null>(null);
  const [successState, setSuccessState] = useState<SuccessState>(null);
  const [currentPage, setCurrentPage] = useState(1);

  async function loadCategories() {
    setLoading(true);
    setPageError(null);

    try {
      const response = await listAllPaginatedRows<ItemCategoryRow>(
        sdk.itemCategories.list.bind(sdk.itemCategories),
        {
          sortBy: "name",
          sortDir: "ASC",
        },
        100,
      );
      setItems(response as ItemCategory[]);
    } catch (error) {
      setPageError(getErrorMessage(error, "Gagal memuat data jenis bahan."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadCategories();
  }, []);

  const filteredItems = useMemo(() => {
    const keyword = search.trim().toLowerCase();

    if (!keyword) {
      return items;
    }

    return items.filter((item) => item.name.toLowerCase().includes(keyword));
  }, [items, search]);

  useEffect(() => {
    setCurrentPage(1);
  }, [search]);

  const pageSize = 10;
  const totalPages = Math.max(1, Math.ceil(filteredItems.length / pageSize));
  const paginatedItems = useMemo(() => {
    const startIndex = (currentPage - 1) * pageSize;
    return filteredItems.slice(startIndex, startIndex + pageSize);
  }, [currentPage, filteredItems]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  function closeModal() {
    setModalMode(null);
    setJenisBahan("");
    setSelectedItem(null);
    setModalError(null);
    setSubmitting(false);
  }

  function openAddModal() {
    setJenisBahan("");
    setSelectedItem(null);
    setModalError(null);
    setModalMode("add");
  }

  function openEditModal(item: ItemCategory) {
    setSelectedItem(item);
    setJenisBahan(item.name);
    setModalError(null);
    setModalMode("edit");
  }

  function openDeleteModal(item: ItemCategory) {
    setSelectedItem(item);
    setModalError(null);
    setModalMode("delete");
  }

  async function handleSubmitJenisBahan(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const nextValue = jenisBahan.trim().toUpperCase();
    if (!nextValue) {
      return;
    }

    setSubmitting(true);
    setModalError(null);
    const previousItems = items;

    try {
      if (modalMode === "add") {
        setItems((current) => [
          ...current,
          {
            id: Date.now(),
            name: nextValue,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
          },
        ]);
        await sdk.itemCategories.create({ name: nextValue });
          setSuccessState({
            headline: "Jenis Bahan Berhasil Ditambahkan",
            message: "",
          });
      }

      if (modalMode === "edit" && selectedItem) {
        setItems((current) =>
          current.map((item) =>
            item.id === selectedItem.id ? { ...item, name: nextValue } : item,
          ),
        );
        await sdk.itemCategories.update(selectedItem.id, { name: nextValue });
        setSuccessState({
          headline: "Jenis Bahan Berhasil Diedit",
          message: "",
        });
      }

      await loadCategories();
      router.refresh();
      closeModal();
    } catch (error) {
      setItems(previousItems);
      const message = getErrorMessage(error, "Gagal menyimpan jenis bahan.");

      if (message.toLowerCase().includes("validation failed") || message === "Jenis Bahan Telah Ada") {
        setModalError("Nama data jenis bahan sudah ada");
      } else {
        setModalError(message);
      }

      setSubmitting(false);
    }
  }

  async function handleDelete() {
    if (!selectedItem) {
      return;
    }

    setSubmitting(true);
    setModalError(null);
    const previousItems = items;
    setItems((current) => current.filter((item) => item.id !== selectedItem.id));

    try {
      const deletedName = selectedItem.name;
      const itemsResponse = await listAllItems({ sortBy: "name", sortDir: "ASC" });
      const usedByItems = itemsResponse.some(
        (item) => Number(item.item_category_id ?? item.category?.id) === Number(selectedItem.id),
      );

      if (usedByItems) {
        setSuccessState({
          title: "Informasi",
          headline: "Jenis Bahan Dipakai Oleh Sistem",
          message: `Jenis bahan ${deletedName} sedang dipakai oleh sistem dan belum bisa dihapus.`,
          tone: "danger",
          icon: <AlertTriangle size={36} strokeWidth={2.1} />,
        });
        setItems(previousItems);
        closeModal();
        return;
      }

      await sdk.itemCategories.delete(selectedItem.id);
      await loadCategories();
      router.refresh();
      setSuccessState({
        title: "Berhasil",
        headline: "Jenis Bahan Berhasil Dihapus",
        message: "",
        tone: "danger",
        icon: <Trash2 size={36} strokeWidth={2.1} />,
      });
      closeModal();
    } catch (error) {
      setItems(previousItems);
      const message = getErrorMessage(error, "Gagal menghapus jenis bahan.");
      if (message.toLowerCase().includes("validation failed")) {
        setSuccessState({
          title: "Informasi",
          headline: "Jenis Bahan Dipakai Oleh Sistem",
          message: `Jenis bahan ${selectedItem.name} sedang dipakai oleh sistem dan belum bisa dihapus.`,
          tone: "danger",
          icon: <AlertTriangle size={36} strokeWidth={2.1} />,
        });
        closeModal();
      } else {
        setModalError(message);
      }
      setSubmitting(false);
    }
  }

  const isFormModal = modalMode === "add" || modalMode === "edit";

  return (
    <>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-semibold text-[#16213E]">Data Jenis Bahan</h1>
          </div>

          <PrimaryAction onClick={openAddModal}>
            Tambah Jenis Bahan
          </PrimaryAction>
        </div>

        <SurfaceCard className="overflow-hidden">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-[#F8FAFC] px-5 py-4">
            <div className="w-full max-w-[340px]">
              <FilterSearch
                placeholder="Cari nama jenis"
                value={search}
                onChange={setSearch}
                readOnly={false}
              />
            </div>

            <p className="text-sm text-[#94A3B8]">{filteredItems.length} jenis bahan</p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-[#64748B]">
                  <th className="px-6 py-3 text-left">Nama Jenis Bahan</th>
                  <th className="px-6 py-3 text-right">Aksi</th>
                </tr>
              </thead>

              <tbody className="bg-white text-base text-[#334155]">
                {loading ? (
                  <tr>
                    <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={2}>
                      Memuat data jenis bahan...
                    </td>
                  </tr>
                ) : paginatedItems.length === 0 ? (
                  <tr>
                    <td className="px-6 py-10 text-center text-[#94A3B8]" colSpan={2}>
                      Tidak ada data jenis bahan.
                    </td>
                  </tr>
                ) : (
                  paginatedItems.map((item) => (
                    <tr
                      key={item.id}
                      className="border-t border-[#E2E8F0] transition hover:bg-[#F8FAFC]"
                    >
                      <td className="px-6 py-4 font-semibold text-[#16213E]">{item.name}</td>

                      <td className="px-6 py-4">
                        <div className="flex justify-end gap-2">
                          <MiniActionButton onClick={() => openEditModal(item)}>
                            Edit
                          </MiniActionButton>
                          <MiniActionButton onClick={() => openDeleteModal(item)} tone="danger">
                            Hapus
                          </MiniActionButton>
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
            totalPages={totalPages}
            onPageChange={setCurrentPage}
            totalLabel={
              filteredItems.length > 0
                ? `${(currentPage - 1) * pageSize + 1}-${Math.min(currentPage * pageSize, filteredItems.length)} dari ${filteredItems.length} item`
                : "0 dari 0 item"
            }
          />
          {pageError ? (
            <div className="border-t border-[#E2E8F0] bg-[#FFF7ED] px-6 py-3 text-sm text-red-600">
              {pageError}
            </div>
          ) : null}
        </SurfaceCard>
      </div>

      {modalMode && (
        <div className="fixed inset-0 z-50 flex items-center justify-center px-4">
          <div
            className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]"
            onClick={closeModal}
          />

          {isFormModal ? (
            <div className="animate-modal-enter relative w-full max-w-[528px] overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)] transition-transform duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_28px_70px_rgba(15,23,42,0.22)]">
              <div className="flex items-start justify-between border-b border-slate-200 px-4 py-4 sm:px-5">
                <div>
                  <h2 className="text-[22px] font-semibold leading-none text-slate-900">
                    {modalMode === "add" ? "Tambah Jenis Bahan" : "Edit Jenis Bahan"}
                  </h2>
                  <p className="mt-2 text-sm text-slate-400">
                    {modalMode === "add" ? "Masukkan jenis bahan" : ""}
                  </p>
                </div>

                <button
                  className="flex h-7 w-7 items-center justify-center rounded-md bg-slate-100 text-slate-400 transition-all duration-300 ease-out hover:scale-105 hover:bg-slate-200 hover:text-slate-500"
                  onClick={closeModal}
                  type="button"
                >
                  <X size={16} />
                </button>
              </div>

              <form onSubmit={handleSubmitJenisBahan}>
                <div className="px-4 py-5 sm:px-5">
                  <label className="block text-sm font-semibold text-slate-700">
                    Nama Jenis Bahan <span className="text-red-500">*</span>
                  </label>
                  <input
                    value={jenisBahan}
                    onChange={(event) => setJenisBahan(event.target.value)}
                    placeholder="Masukkan nama jenis bahan"
                    className="mt-2 h-11 w-full rounded-xl border border-slate-200 px-4 text-sm text-slate-700 outline-none transition placeholder:text-slate-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    required
                  />
                  {modalError && (
                    <p className="mt-3 text-sm text-red-600">{modalError}</p>
                  )}
                </div>

                <div className="flex justify-end gap-3 border-t border-slate-200 px-4 py-4 sm:px-5">
                  <button
                    className="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-50"
                    onClick={closeModal}
                    type="button"
                  >
                    Batal
                  </button>
                  <button
                    className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-[0_14px_30px_rgba(37,99,235,0.28)] disabled:cursor-not-allowed disabled:bg-blue-300 disabled:hover:translate-y-0 disabled:hover:shadow-none"
                    disabled={submitting}
                    type="submit"
                  >
                    {submitting ? "Menyimpan..." : "Simpan"}
                  </button>
                </div>
              </form>
            </div>
          ) : null}
        </div>
      )}

      <DeleteConfirmModal
        open={modalMode === "delete"}
        headline="Hapus jenis bahan ini?"
        description="Apakah anda yakin untuk menghapus jenis bahan ini?"
        submitting={submitting}
        error={modalError}
        onClose={closeModal}
        onConfirm={handleDelete}
      />

      <SuccessModal
        open={successState !== null}
        title={successState?.title ?? "Berhasil"}
        headline={successState?.headline ?? ""}
        message={successState?.message ?? ""}
        tone={successState?.tone ?? "success"}
        icon={successState?.icon}
        onClose={() => setSuccessState(null)}
      />
    </>
  );
}
