"use client";

import { useEffect, useMemo, useState } from "react";
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
  conversionBase: string;
  unitName: string;
  unitConvertName?: string;
};

function getDefaultUnitConvert(unitName: string) {
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
    conversionBase: initialValue?.conversionBase ?? "1",
    unitName: initialValue?.unitName ?? itemUnits[0]?.name ?? "",
    unitConvertName: initialValue?.unitConvertName ?? getDefaultUnitConvert(initialValue?.unitName ?? itemUnits[0]?.name ?? ""),
  }));
  const [unitConvertTouched, setUnitConvertTouched] = useState(Boolean(initialValue?.unitConvertName));

  useEffect(() => {
    setForm({
      id: initialValue?.id,
      name: initialValue?.name ?? "",
      categoryName: initialValue?.categoryName ?? categories[0]?.name ?? "",
      minimumStock: initialValue?.minimumStock ?? "",
      conversionBase: initialValue?.conversionBase ?? "1",
      unitName: initialValue?.unitName ?? itemUnits[0]?.name ?? "",
      unitConvertName:
        initialValue?.unitConvertName ??
        getDefaultUnitConvert(initialValue?.unitName ?? itemUnits[0]?.name ?? ""),
    });
    setUnitConvertTouched(Boolean(initialValue?.unitConvertName));
  }, [categories, initialValue, itemUnits]);

  const title = useMemo(
    () => (mode === "create" ? "Tambah Master Barang" : "Edit Master Barang"),
    [mode],
  );

  const availableItemUnits = useMemo(() => {
    const currentUnit = initialValue?.unitName?.trim();

    if (!currentUnit) {
      return itemUnits;
    }

    const hasCurrentUnit = itemUnits.some((unit) => unit.name === currentUnit);

    if (hasCurrentUnit) {
      return itemUnits;
    }

    return [
      ...itemUnits,
      {
        id: 0,
        name: currentUnit,
      },
    ];
  }, [initialValue?.unitName, itemUnits]);

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

                {mode === "create" || mode === "edit" ? (
                  <div className="space-y-2">
                    <span className="text-sm font-medium text-slate-700">
                      Satuan Kecil <span className="text-red-500">*</span>
                    </span>

                    <div className="grid gap-3 md:grid-cols-[1.5fr_1fr] md:items-end">
                      <label className="block space-y-2">
                        <span className="block whitespace-nowrap text-[11px] font-medium uppercase tracking-wide text-slate-500">
                          Nominal konversi dari satuan besar
                        </span>
                        <input
                          className="w-full rounded-xl border border-slate-200 px-4 py-3 text-base text-slate-700 outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#2155CD]/10"
                          min="1"
                          onChange={(event) =>
                            setForm((current) => ({ ...current, conversionBase: event.target.value }))
                          }
                          placeholder="Masukkan nominal konversi"
                          required
                          step="1"
                          type="number"
                          value={form.conversionBase}
                        />
                      </label>

                      <label className="block md:pb-[1px]">
                        <select
                          className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-700 outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#2155CD]/10"
                          onChange={(event) => {
                            const nextUnitName = event.target.value;
                            setForm((current) => ({
                              ...current,
                              unitName: nextUnitName,
                              unitConvertName: unitConvertTouched
                                ? current.unitConvertName
                                : getDefaultUnitConvert(nextUnitName),
                            }));
                          }}
                          required
                          value={form.unitName}
                        >
                          <option value="" disabled>
                            Satuan kecil
                          </option>
                          {availableItemUnits.map((unit) => (
                            <option key={unit.id} value={unit.name}>
                              {unit.name}
                            </option>
                          ))}
                        </select>
                      </label>
                    </div>
                  </div>
                ) : null}

                {mode === "create" || mode === "edit" ? (
                  <label className="block space-y-2">
                    <span className="text-sm font-medium text-slate-700">
                      Satuan Besar <span className="text-red-500">*</span>
                    </span>
                    <select
                      className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base text-slate-700 outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#2155CD]/10"
                      onChange={(event) => {
                        setUnitConvertTouched(true);
                        setForm((current) => ({ ...current, unitConvertName: event.target.value }));
                      }}
                      required
                      value={form.unitConvertName ?? ""}
                    >
                      <option value="" disabled>
                        Pilih satuan besar
                      </option>
                      {availableItemUnits.map((unit) => (
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
