"use client";

import { useMemo, useState } from "react";
import { X } from "lucide-react";

type CategoryOption = {
  id: number;
  name: string;
};

type ItemUnitOption = {
  id: number;
  name: string;
};

export type StockItemFormValue = {
  id?: number;
  name: string;
  categoryName: string;
  minimumStock: string;
  unitName: string;
  unitConvertName?: string;
};

type StockItemModalProps = {
  open: boolean;
  mode: "create" | "edit";
  categories: CategoryOption[];
  itemUnits: ItemUnitOption[];
  initialValue?: StockItemFormValue | null;
  submitting?: boolean;
  error?: string | null;
  onClose: () => void;
  onSubmit: (value: StockItemFormValue) => void | Promise<void>;
};

export default function StockItemModal({
  open,
  mode,
  categories,
  itemUnits,
  initialValue = null,
  submitting = false,
  error = null,
  onClose,
  onSubmit,
}: Readonly<StockItemModalProps>) {
  const [form, setForm] = useState<StockItemFormValue>(() => ({
    id: initialValue?.id,
    name: initialValue?.name ?? "",
    categoryName: initialValue?.categoryName ?? categories[0]?.name ?? "",
    minimumStock: initialValue?.minimumStock ?? "",
    unitName: initialValue?.unitName ?? itemUnits[0]?.name ?? "",
    unitConvertName: initialValue?.unitConvertName ?? "",
  }));

  const title = useMemo(
    () => (mode === "create" ? "Tambah Master Barang" : "Edit Master Barang"),
    [mode],
  );

  const subtitle = useMemo(
    () =>
      mode === "create"
        ? "Masukkan data master bahan baru."
        : "Perbarui data master bahan yang dipilih.",
    [mode],
  );

  if (!open) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-[55] flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />

      <div className="animate-modal-enter relative flex max-h-[calc(100vh-3rem)] w-full max-w-[560px] flex-col overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)] transition-transform duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_28px_70px_rgba(15,23,42,0.22)]">
        <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 className="text-[22px] font-semibold leading-none text-slate-900">{title}</h2>
            <p className="mt-2 text-sm text-slate-400">{subtitle}</p>
          </div>

          <button
            aria-label="Tutup"
            className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-400 transition-all duration-300 ease-out hover:scale-105 hover:bg-slate-200 hover:text-slate-500"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <form
          className="flex min-h-0 flex-1 flex-col"
          onSubmit={(event) => {
            event.preventDefault();
            void onSubmit(form);
          }}
        >
          <div className="space-y-6 overflow-y-auto px-5 py-5">
            <label className="block space-y-2">
              <span className="text-sm font-medium text-slate-700">
                Nama Bahan <span className="text-red-500">*</span>
              </span>
              <input
                className="w-full rounded-xl border border-slate-200 px-4 py-3 text-base text-slate-700 outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#2155CD]/10"
                onChange={(event) =>
                  setForm((current) => ({ ...current, name: event.target.value }))
                }
                placeholder="Masukkan nama bahan"
                required
                type="text"
                value={form.name}
              />
            </label>

            <div className="overflow-hidden rounded-[14px] border border-[#D9E3F2]">
              <div className="border-b border-[#D9E3F2] bg-[#EDF4FF] px-4 py-3">
                <h3 className="text-base font-semibold text-[#475569]">KONFIGURASI BAHAN</h3>
                <p className="mt-1 text-xs text-[#94A3B8]">
                  Jenis bahan dan minimal stok akan dipakai untuk konfigurasi master barang.
                </p>
              </div>

              <div className="space-y-5 px-4 py-4">
                <label className="block space-y-2">
                  <span className="text-sm font-medium text-slate-700">
                    Jenis Bahan <span className="text-red-500">*</span>
                  </span>
                  <select
                    className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-700 outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#2155CD]/10"
                    onChange={(event) =>
                      setForm((current) => ({ ...current, categoryName: event.target.value }))
                    }
                    required
                    value={form.categoryName}
                  >
                    <option value="" disabled>
                      Pilih jenis bahan
                    </option>
                    {categories.map((category) => (
                      <option key={category.id} value={category.name}>
                        {category.name}
                      </option>
                    ))}
                  </select>
                </label>

                {mode === "create" ? (
                  <label className="block space-y-2">
                    <span className="text-sm font-medium text-slate-700">
                      Satuan Item <span className="text-red-500">*</span>
                    </span>
                    <select
                      className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-700 outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#2155CD]/10"
                      onChange={(event) =>
                        setForm((current) => ({ ...current, unitName: event.target.value }))
                      }
                      required
                      value={form.unitName}
                    >
                      <option value="" disabled>
                        Pilih satuan item
                      </option>
                      {itemUnits.map((unit) => (
                        <option key={unit.id} value={unit.name}>
                          {unit.name}
                        </option>
                      ))}
                    </select>
                  </label>
                ) : null}

                <label className="block space-y-2">
                  <span className="text-sm font-medium text-slate-700">
                    Minimal Stock <span className="text-red-500">*</span>
                  </span>
                  <div className="relative">
                    <input
                      className="w-full rounded-xl border border-slate-200 px-4 py-3 pr-20 text-base text-slate-700 outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#2155CD]/10"
                      min="1"
                      onChange={(event) =>
                        setForm((current) => ({ ...current, minimumStock: event.target.value }))
                      }
                      placeholder="Masukkan minimal stock"
                      required
                      step="1"
                      type="number"
                      value={form.minimumStock}
                    />
                    <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-semibold text-[#94A3B8]">
                      {form.unitName || "-"}
                    </span>
                  </div>
                </label>
              </div>
            </div>

            {error ? (
              <div className="rounded-[16px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
                {error}
              </div>
            ) : null}
          </div>

          <div className="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
            <button
              className="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-50"
              onClick={onClose}
              type="button"
            >
              Batal
            </button>
            <button
              className="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-[0_14px_30px_rgba(37,99,235,0.28)] disabled:cursor-not-allowed disabled:bg-blue-300 disabled:hover:translate-y-0 disabled:hover:shadow-none"
              disabled={submitting}
              type="submit"
            >
              {submitting ? "Menyimpan..." : "Simpan"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
