"use client";

import type { FormEvent } from "react";
import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Search, X } from "lucide-react";
import DeleteConfirmModal from "@/components/feedback/DeleteConfirmModal";
import SuccessModal from "@/components/feedback/SuccessModal";
import sdk from "@/lib";

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
} | null;

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

  async function loadCategories() {
    setLoading(true);
    setPageError(null);

    try {
      const response = await sdk.itemCategories.list();
      setItems(response.data as ItemCategory[]);
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
          message: `Jenis bahan ${nextValue} telah ditambahkan ke daftar utama.`,
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
          message: `Jenis bahan ${nextValue} berhasil diperbarui.`,
        });
      }

      await loadCategories();
      router.refresh();
      closeModal();
    } catch (error) {
      setItems(previousItems);
      const message = getErrorMessage(error, "Gagal menyimpan jenis bahan.");
      setModalError(message);

      if (message === "Jenis Bahan Telah Ada") {
        window.alert("Jenis Bahan Telah Ada");
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
      const itemsResponse = await sdk.items.list({ perPage: 100, sortBy: "name", sortDir: "ASC" });
      const usedByItems = (itemsResponse.data ?? []).some(
        (item) => Number(item.item_category_id ?? item.category?.id) === Number(selectedItem.id),
      );

      if (usedByItems) {
        setSuccessState({
          title: "Informasi",
          headline: "Jenis Bahan Dipakai Oleh Sistem",
          message: `Jenis bahan ${deletedName} sedang dipakai oleh sistem dan belum bisa dihapus.`,
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
        message: `Jenis bahan ${deletedName} berhasil dihapus dari sistem.`,
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
            <h1 className="text-[22px] font-semibold text-gray-900">Data Jenis Bahan</h1>
            <p className="mt-1 text-sm text-gray-400">
              Untuk melihat dan mengelola data jenis bahan
            </p>
          </div>

          <button
            className="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white shadow-sm transition hover:bg-blue-700"
            onClick={openAddModal}
            type="button"
          >
            Tambah Jenis Bahan
          </button>
        </div>

        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-[#F8FAFC] px-5 py-4">
            <div className="flex h-10 w-64 items-center rounded-lg border border-gray-200 bg-white px-3">
              <Search size={16} className="mr-2 text-gray-400" />
              <input
                placeholder="Cari nama jenis"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                className="w-full bg-transparent text-sm text-gray-700 outline-none placeholder-gray-400"
              />
            </div>

            <p className="text-xs text-gray-400">{filteredItems.length} jenis bahan</p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F1F5F9] text-[11px] uppercase tracking-wide text-gray-500">
                  <th className="px-6 py-3 text-left">Nama Jenis Bahan</th>
                  <th className="px-6 text-right">Aksi</th>
                </tr>
              </thead>

              <tbody className="text-gray-700">
                {loading ? (
                  <tr>
                    <td className="px-6 py-10 text-center text-gray-500" colSpan={2}>
                      Memuat data jenis bahan...
                    </td>
                  </tr>
                ) : filteredItems.length === 0 ? (
                  <tr>
                    <td className="px-6 py-10 text-center text-gray-500" colSpan={2}>
                      Tidak ada data jenis bahan.
                    </td>
                  </tr>
                ) : (
                  filteredItems.map((item) => (
                    <tr
                      key={item.id}
                      className="border-t border-gray-200 transition hover:bg-[#F9FAFB]"
                    >
                      <td className="px-6 py-4 font-medium text-gray-900">{item.name}</td>

                      <td className="px-6">
                        <div className="flex justify-end gap-2">
                          <button
                            className="rounded-lg bg-gray-100 px-3 py-[6px] text-xs text-gray-600 transition hover:bg-gray-200"
                            onClick={() => openEditModal(item)}
                            type="button"
                          >
                            Edit
                          </button>
                          <button
                            className="rounded-lg bg-red-50 px-3 py-[6px] text-xs text-red-600 transition hover:bg-red-100"
                            onClick={() => openDeleteModal(item)}
                            type="button"
                          >
                            Hapus
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>

          <div className="flex items-center justify-between border-t bg-[#F8FAFC] px-6 py-3 text-xs text-gray-400">
            <span>
              {filteredItems.length === 0 ? "0" : `1-${filteredItems.length}`} dari{" "}
              {filteredItems.length}
            </span>

            {pageError ? (
              <span className="text-red-600">{pageError}</span>
            ) : (
              <div className="flex gap-2">
                <button className="rounded-lg bg-gray-100 px-2 py-1 text-gray-600" type="button">
                  {"<"}
                </button>
                <button className="rounded-lg bg-blue-600 px-3 py-1 text-white" type="button">
                  1
                </button>
                <button className="rounded-lg bg-gray-100 px-2 py-1 text-gray-600" type="button">
                  {">"}
                </button>
              </div>
            )}
          </div>
        </div>
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
                    {modalMode === "add" ? "Masukkan jenis bahan" : "Ubah jenis bahan"}
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
        description={`Jenis bahan ${selectedItem?.name ?? ""} akan dipindahkan ke arsip dan tidak tampil di daftar utama.`}
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
        onClose={() => setSuccessState(null)}
      />
    </>
  );
}
