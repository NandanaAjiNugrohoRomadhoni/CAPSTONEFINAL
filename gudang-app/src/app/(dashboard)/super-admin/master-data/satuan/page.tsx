"use client";

import type { FormEvent } from "react";
import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Search, X } from "lucide-react";
import DeleteConfirmModal from "@/components/feedback/DeleteConfirmModal";
import SuccessModal from "@/components/feedback/SuccessModal";
import sdk from "@/lib";

type ModalMode = "add" | "edit" | "delete" | null;

type Unit = {
  id: number;
  name: string;
  created_at: string | null;
  updated_at: string | null;
};

type SuccessState = {
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

export default function SatuanPage() {
  const router = useRouter();
  const [items, setItems] = useState<Unit[]>([]);
  const [loading, setLoading] = useState(true);
  const [pageError, setPageError] = useState<string | null>(null);
  const [search, setSearch] = useState("");
  const [modalMode, setModalMode] = useState<ModalMode>(null);
  const [satuan, setSatuan] = useState("");
  const [selectedItem, setSelectedItem] = useState<Unit | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [modalError, setModalError] = useState<string | null>(null);
  const [successState, setSuccessState] = useState<SuccessState>(null);

  async function loadUnits() {
    setLoading(true);
    setPageError(null);

    try {
      const response = await sdk.itemUnits.list();
      setItems(response.data as Unit[]);
    } catch (error) {
      setPageError(getErrorMessage(error, "Gagal memuat data satuan."));
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadUnits();
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
    setSatuan("");
    setSelectedItem(null);
    setModalError(null);
    setSubmitting(false);
  }

  function openAddModal() {
    setSatuan("");
    setSelectedItem(null);
    setModalError(null);
    setModalMode("add");
  }

  function openEditModal(item: Unit) {
    setSelectedItem(item);
    setSatuan(item.name);
    setModalError(null);
    setModalMode("edit");
  }

  function openDeleteModal(item: Unit) {
    setSelectedItem(item);
    setModalError(null);
    setModalMode("delete");
  }

  async function handleSubmitSatuan(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const nextValue = satuan.trim();
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
        await sdk.itemUnits.create({ name: nextValue });
        setSuccessState({
          headline: "Satuan Berhasil Ditambahkan",
          message: `Satuan ${nextValue} telah ditambahkan ke daftar utama.`,
        });
      }

      if (modalMode === "edit" && selectedItem) {
        setItems((current) =>
          current.map((item) =>
            item.id === selectedItem.id ? { ...item, name: nextValue } : item,
          ),
        );
        await sdk.itemUnits.update(selectedItem.id, { name: nextValue });
        setSuccessState({
          headline: "Satuan Berhasil Diedit",
          message: `Satuan ${nextValue} berhasil diperbarui.`,
        });
      }

      await loadUnits();
      router.refresh();
      closeModal();
    } catch (error) {
      setItems(previousItems);
      const message = getErrorMessage(error, "Gagal menyimpan satuan.");
      setModalError(message);

      if (message === "Satuan Telah Ada") {
        window.alert("Satuan Telah Ada");
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
      await sdk.itemUnits.delete(selectedItem.id);
      await loadUnits();
      router.refresh();
      setSuccessState({
        headline: "Satuan Berhasil Dihapus",
        message: `Satuan ${deletedName} telah dipindahkan ke arsip.`,
      });
      closeModal();
    } catch (error) {
      setItems(previousItems);
      setModalError(getErrorMessage(error, "Gagal menghapus satuan."));
      setSubmitting(false);
    }
  }

  const isFormModal = modalMode === "add" || modalMode === "edit";

  return (
    <>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-[22px] font-semibold text-gray-900">Data Satuan</h1>
            <p className="mt-1 text-sm text-gray-400">
              Untuk melihat dan mengelola data satuan
            </p>
          </div>

          <button
            className="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white shadow-sm transition hover:bg-blue-700"
            onClick={openAddModal}
            type="button"
          >
            Tambah Satuan
          </button>
        </div>

        <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-[#F8FAFC] px-5 py-4">
            <div className="flex h-10 w-64 items-center rounded-lg border border-gray-200 bg-white px-3">
              <Search size={16} className="mr-2 text-gray-400" />
              <input
                placeholder="Cari nama satuan"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                className="w-full bg-transparent text-sm text-gray-700 outline-none placeholder-gray-400"
              />
            </div>

            <p className="text-xs text-gray-400">{filteredItems.length} satuan</p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-[#F1F5F9] text-[11px] uppercase tracking-wide text-gray-500">
                  <th className="px-6 py-3 text-left">Nama Satuan</th>
                  <th className="px-6 text-right">Aksi</th>
                </tr>
              </thead>

              <tbody className="text-gray-700">
                {loading ? (
                  <tr>
                    <td className="px-6 py-10 text-center text-gray-500" colSpan={2}>
                      Memuat data satuan...
                    </td>
                  </tr>
                ) : filteredItems.length === 0 ? (
                  <tr>
                    <td className="px-6 py-10 text-center text-gray-500" colSpan={2}>
                      Tidak ada data satuan.
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
                    {modalMode === "add" ? "Tambah Satuan" : "Edit Satuan"}
                  </h2>
                  <p className="mt-2 text-sm text-slate-400">
                    {modalMode === "add" ? "Masukkan satuan" : "Ubah satuan"}
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

              <form onSubmit={handleSubmitSatuan}>
                <div className="px-4 py-5 sm:px-5">
                  <label className="block text-sm font-semibold text-slate-700">
                    Nama Satuan <span className="text-red-500">*</span>
                  </label>
                  <input
                    value={satuan}
                    onChange={(event) => setSatuan(event.target.value)}
                    placeholder="Masukkan nama satuan"
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
        headline="Hapus satuan ini?"
        description={`Satuan ${selectedItem?.name ?? ""} akan dipindahkan ke arsip dan tidak tampil di daftar utama.`}
        submitting={submitting}
        error={modalError}
        onClose={closeModal}
        onConfirm={handleDelete}
      />

      <SuccessModal
        open={successState !== null}
        title="Berhasil"
        headline={successState?.headline ?? ""}
        message={successState?.message ?? ""}
        onClose={() => setSuccessState(null)}
      />
    </>
  );
}
