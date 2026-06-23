"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { AlertTriangle, ChevronDown, Plus, Trash2, X } from "lucide-react";
import sdk from "@/lib";
import { getErrorMessage, toIsoDate } from "@/lib/admin-utils";
import { listAllItems } from "@/lib/items";
import { refreshStockAdjustmentNotifications } from "@/lib/stock-adjustment-notifications";
import { PrimaryAction, ThemedSelect } from "@/components/admin/ui";
import CommonSearchableItemSelect from "@/components/admin/ui/SearchableItemSelect";
import SuccessModal from "@/components/feedback/SuccessModal";

type ItemRow = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
type CategoryRow = Awaited<ReturnType<typeof sdk.itemCategories.list>>["data"][number];
type SpkHistoryEntry = Awaited<ReturnType<typeof sdk.spk.listBasah>>["data"][number];
type SpkHistoryRow = { id: number; label: string };
type SearchableItemOption = { id: number; label: string; unit: string };
type Row = {
  id: number;
  item_id: number | null;
  qty_spk: number;
  qty_actual: string;
  unit: string;
  locked: boolean;
};
type PrefillDetail = { item_id: number; qty: number };

type AlertState = {
  title: string;
  headline: string;
  message: string;
} | null;

type SearchableItemSelectProps = {
  options: SearchableItemOption[];
  value: number | null;
  placeholder: string;
  disabled?: boolean;
  onChange: (itemId: number | null, unit?: string) => void;
};

function normalizePrefillDetails(source: unknown): PrefillDetail[] {
  const root = source as {
    data?: {
      details?: unknown;
      items?: unknown;
      recommendations?: unknown;
      print_ready?: { recommendations?: unknown };
    };
  };
  const candidates =
    root.data?.details ??
    root.data?.items ??
    root.data?.recommendations ??
    root.data?.print_ready?.recommendations ??
    [];

  if (!Array.isArray(candidates)) return [];

  const normalizedDetails = candidates
    .map((candidate) => {
      const row = candidate as {
        item_id?: unknown;
        item?: { id?: unknown };
        qty?: unknown;
        final_recommended_qty?: unknown;
        system_recommended_qty?: unknown;
        recommended_qty?: unknown;
        required_qty?: unknown;
      };
      const itemId = Number(row.item_id ?? row.item?.id ?? 0);
      const qty = Number(
        row.qty ??
          row.final_recommended_qty ??
          row.system_recommended_qty ??
          row.recommended_qty ??
          row.required_qty ??
          0,
      );
      return { item_id: itemId, qty };
    })
    .filter((detail) => Number.isFinite(detail.item_id) && detail.item_id > 0 && Number.isFinite(detail.qty));

  return Array.from(
    normalizedDetails
      .reduce((map, detail) => {
        const existing = map.get(detail.item_id);
        map.set(detail.item_id, {
          item_id: detail.item_id,
          qty: (existing?.qty ?? 0) + detail.qty,
        });
        return map;
      }, new Map<number, PrefillDetail>())
      .values(),
  );
}

export default function BarangMasukPage() {
  const [activeTab, setActiveTab] = useState<"basah" | "kering">("basah");
  const [items, setItems] = useState<ItemRow[]>([]);
  const [categories, setCategories] = useState<CategoryRow[]>([]);
  const [rows, setRows] = useState<Row[]>([createEmptyRow()]);
  const [spkOptions, setSpkOptions] = useState<SpkHistoryRow[]>([]);
  const [selectedSpkId, setSelectedSpkId] = useState<number | null>(null);
  const [prefillModalOpen, setPrefillModalOpen] = useState(false);
  const [confirmSaveOpen, setConfirmSaveOpen] = useState(false);
  const [loadingPrefill, setLoadingPrefill] = useState(false);
  const [saving, setSaving] = useState(false);
  const [alertState, setAlertState] = useState<AlertState>(null);
  const [successOpen, setSuccessOpen] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const [itemResponse, categoryResponse] = await Promise.all([
          listAllItems({ sortBy: "name", sortDir: "ASC", is_active: true }),
          sdk.itemCategories.list({ paginate: false, sortBy: "name", sortDir: "ASC" }),
        ]);

        if (cancelled) return;

        setItems(itemResponse);
        setCategories(categoryResponse.data ?? []);
      } catch (loadError) {
        if (!cancelled) {
          openAlert(
            setAlertState,
            "Gagal Memuat Data",
            "Data barang masuk belum bisa ditampilkan",
            getErrorMessage(loadError, "Terjadi kesalahan saat memuat daftar barang."),
          );
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  const itemMap = useMemo(() => new Map(items.map((item) => [item.id, item])), [items]);

  const activeCategoryIds = useMemo(() => {
    const targets = activeTab === "basah" ? ["basah"] : ["kering", "pengemas"];
    return categories
      .filter((category) =>
        targets.some((keyword) => category.name.trim().toLowerCase().includes(keyword)),
      )
      .map((category) => Number(category.id));
  }, [activeTab, categories]);

  const filteredItems = useMemo(
    () => items.filter((item) => activeCategoryIds.includes(Number(item.category?.id))),
    [activeCategoryIds, items],
  );
  const filteredItemOptions = useMemo<SearchableItemOption[]>(
    () =>
      filteredItems.map((item) => ({
        id: item.id,
        label: item.name,
        unit: item.item_unit_base?.name ?? item.unit_base ?? "-",
      })),
    [filteredItems],
  );

  useEffect(() => {
    let cancelled = false;

    async function loadSpkOptions() {
      setSpkOptions([]);
      setSelectedSpkId(null);

      try {
        if (activeTab === "basah") {
          const response = await sdk.spk.listBasah();
          if (cancelled) return;

          setSpkOptions(mapSpkOptions(response.data ?? []));
          return;
        }

        const response = await sdk.spk.listKeringPengemas();
        if (cancelled) return;

        setSpkOptions(mapSpkOptions(response.data ?? []));
      } catch (loadError) {
        if (!cancelled) {
          openAlert(
            setAlertState,
            "Gagal Memuat SPK",
            "Daftar SPK belum tersedia",
            getErrorMessage(loadError, "Terjadi kesalahan saat memuat daftar SPK."),
          );
        }
      }
    }

    void loadSpkOptions();
    return () => {
      cancelled = true;
    };
  }, [activeTab]);

  function resetRows() {
    setRows([createEmptyRow()]);
  }

  function resetForm() {
    setSelectedSpkId(null);
    resetRows();
  }

  function openPrefillModal() {
    if (spkOptions.length === 0) {
      openAlert(
        setAlertState,
        "SPK Belum Tersedia",
        "Tidak ada ID SPK yang bisa dipilih",
        "Generate atau siapkan data SPK terlebih dahulu sebelum melakukan prefill barang masuk.",
      );
      return;
    }

    setPrefillModalOpen(true);
  }

  async function handleConfirmPrefill() {
    if (!selectedSpkId) {
      openAlert(
        setAlertState,
        "ID SPK Wajib Dipilih",
        "Pilih ID SPK terlebih dahulu",
        "Sistem membutuhkan ID SPK yang sudah di-generate untuk mengisi tabel barang masuk secara otomatis.",
      );
      return;
    }

    setLoadingPrefill(true);

    try {
      let details: PrefillDetail[] = [];
      const detailResponse =
        activeTab === "basah"
          ? await sdk.spk.getBasah(selectedSpkId)
          : await sdk.spk.getKeringPengemas(selectedSpkId);
      details = normalizePrefillDetails(detailResponse);

      if (details.length === 0) {
        throw new Error("SPK yang dipilih belum memiliki detail bahan untuk prefill.");
      }

      setRows(
        details.map((detail, index) => {
          const item = itemMap.get(detail.item_id);
          return {
            id: Date.now() + index,
            item_id: detail.item_id,
            qty_spk: Number(detail.qty),
            qty_actual: String(Number(detail.qty)),
            unit: item?.unit_base ?? "-",
            locked: false,
          };
        }),
      );
      setPrefillModalOpen(false);
    } catch (prefillError) {
      openAlert(
        setAlertState,
        "Isi Otomatis Gagal",
        "Data SPK belum bisa dimasukkan ke tabel",
        getErrorMessage(prefillError, "Gagal mengambil data Isi Otomatis dari SPK."),
      );
    } finally {
      setLoadingPrefill(false);
    }
  }

  function validateBeforeConfirm() {
    const normalizedRows = rows.filter((row) => row.item_id !== null && Number(row.qty_actual) > 0);

    if (normalizedRows.length === 0) {
      openAlert(
        setAlertState,
        "Data Belum Lengkap",
        "Minimal satu barang masuk harus diisi",
        "Pilih bahan dan isi Qty Faktual lebih dari 0 sebelum menyimpan transaksi barang masuk.",
      );
      return false;
    }

    const itemIds = normalizedRows.map((row) => Number(row.item_id));
    const duplicateId = itemIds.find((itemId, index) => itemIds.indexOf(itemId) !== index);

    if (duplicateId) {
      const duplicateName = itemMap.get(duplicateId)?.name ?? `Item #${duplicateId}`;
      openAlert(
        setAlertState,
        "Bahan Ganda Ditemukan",
        "Satu bahan tidak boleh diinput dua kali",
        `Bahan ${duplicateName} muncul lebih dari satu kali. Gabungkan jumlahnya ke satu baris saja.`,
      );
      return false;
    }

    return true;
  }

  async function handleSave() {
    setSaving(true);

    try {
      const details = rows
        .filter((row) => row.item_id !== null && Number(row.qty_actual) > 0)
        .map((row) => ({
          item_id: row.item_id as number,
          qty: Number(row.qty_actual),
          input_unit: "base" as const,
        }));

      await sdk.stockTransactions.create({
        type_name: "IN",
        transaction_date: toIsoDate(new Date()),
        spk_id: selectedSpkId,
        details,
      });
      refreshStockAdjustmentNotifications();

      setConfirmSaveOpen(false);
      setSuccessOpen(true);
      resetForm();
    } catch (saveError) {
      setConfirmSaveOpen(false);
      openAlert(
        setAlertState,
        "Penyimpanan Gagal",
        "Data barang masuk belum tersimpan",
        getErrorMessage(saveError, "Gagal menyimpan barang masuk."),
      );
    } finally {
      setSaving(false);
    }
  }

  const summaryCount = rows.filter((row) => row.item_id !== null && Number(row.qty_actual) > 0).length;

  return (
    <>
      <div className="space-y-6">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Barang Masuk</h1>
          <p className="text-sm text-gray-400">
            Barang masuk dibagi per kategori bahan. Isi Otomatis rekomendasi SPK tersedia langsung dari form aktif.
          </p>
        </div>

        <div className="flex gap-10 border-b border-gray-200">
          <button
            onClick={() => {
              setActiveTab("basah");
              resetForm();
            }}
            className={`relative pb-3 text-sm font-medium ${activeTab === "basah" ? "text-blue-600" : "text-gray-400"}`}
            type="button"
          >
            Input Barang Basah
            {activeTab === "basah" ? (
              <div className="absolute bottom-0 left-0 h-[3px] w-full rounded-full bg-blue-600" />
            ) : null}
          </button>

          <button
            onClick={() => {
              setActiveTab("kering");
              resetForm();
            }}
            className={`relative pb-3 text-sm font-medium ${activeTab === "kering" ? "text-blue-600" : "text-gray-400"}`}
            type="button"
          >
            Input Barang Kering & Pengemas
            {activeTab === "kering" ? (
              <div className="absolute bottom-0 left-0 h-[3px] w-full rounded-full bg-blue-600" />
            ) : null}
          </button>
        </div>

        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div className="border-b border-gray-100 p-5">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <h2 className="font-semibold text-gray-900">
                  {activeTab === "basah" ? "Input Barang Basah" : "Input Barang Kering & Pengemas"}
                </h2>
                <p className="text-xs text-gray-400">
                  {activeTab === "basah"
                    ? "Gunakan prefill dari SPK basah atau lengkapi Qty Faktual secara manual sebelum simpan."
                    : "Gunakan prefill dari SPK bulanan atau lengkapi Qty Faktual secara manual sebelum simpan."}
                </p>
              </div>

              <button
                className="inline-flex h-11 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-4 text-sm font-medium text-blue-700 transition hover:bg-blue-100"
                onClick={openPrefillModal}
                type="button"
              >
                Isi Otomatis via SPK
              </button>
            </div>
          </div>

          <div className="space-y-5 p-5">
            <div className="overflow-visible rounded-xl border border-gray-200">
              <div className="bg-[#F1F5F9] px-4 py-3 text-xs font-semibold text-gray-500">
                DAFTAR BARANG MASUK
              </div>
              <div className="grid grid-cols-11 border-t px-4 py-2 text-xs text-gray-400">
                <div className="col-span-1">ID</div>
                <div className="col-span-4">Nama Bahan</div>
                <div className="col-span-2">Qty SPK</div>
                <div className="col-span-2">Qty Faktual</div>
                <div className="col-span-1">Satuan</div>
                <div className="col-span-1" />
              </div>

              {rows.map((row) => {
                return (
                  <div key={row.id} className="grid grid-cols-11 items-center gap-3 border-t px-4 py-3">
                    <div className="col-span-1 text-sm font-medium text-gray-500">
                      {row.item_id ? `IT-${String(row.item_id).padStart(3, "0")}` : "-"}
                    </div>

                    <CommonSearchableItemSelect
                      className="col-span-4"
                      options={filteredItemOptions}
                      value={row.item_id}
                      placeholder="Pilih Nama Bahan"
                      disabled={row.locked}
                      onChange={(nextId, unit) => {
                        const nextItem = nextId ? itemMap.get(nextId) : null;
                        setRows((current) =>
                          current.map((item) =>
                            item.id === row.id
                              ? {
                                  ...item,
                                  item_id: nextId,
                                  unit: unit ?? nextItem?.item_unit_base?.name ?? nextItem?.unit_base ?? "-",
                                  locked: false,
                                }
                              : item,
                          ),
                        )
                      }}
                    />
                    <div className="col-span-2 text-sm text-gray-600">{row.qty_spk >= 0 ? row.qty_spk : "-"}</div>

                    <div className="col-span-2 flex items-center gap-2">
                      <input
                        type="number"
                        min="0"
                        value={row.qty_actual}
                        onChange={(event) =>
                          setRows((current) =>
                            current.map((item) =>
                              item.id === row.id ? { ...item, qty_actual: event.target.value } : item,
                            ),
                          )
                        }
                        className="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"
                        placeholder="0"
                      />
                    </div>

                    <div className="col-span-1 text-sm text-gray-600">{row.unit}</div>
                    <div className="col-span-1 flex justify-center">
                      <button
                        className="flex justify-center rounded-lg p-2 text-red-500 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:text-red-200 disabled:hover:bg-transparent"
                        disabled={row.locked}
                        onClick={() =>
                          setRows((current) =>
                            current.length === 1 ? current : current.filter((item) => item.id !== row.id),
                          )
                        }
                        type="button"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </div>
                );
              })}

              <div className="border-t p-3">
                <button
                  className="flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-blue-400 py-2 text-sm text-blue-600 transition hover:bg-blue-50"
                  onClick={() => setRows((current) => [...current, createEmptyRow()])}
                  type="button"
                >
                  <Plus size={16} />
                  Tambah Baris Bahan
                </button>
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-3 border-t border-gray-100 bg-[#F9FAFB] p-5">
            <button
              className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-100"
              onClick={resetForm}
              type="button"
            >
              Batal
            </button>

            <PrimaryAction
              onClick={() => {
                if (validateBeforeConfirm()) {
                  setConfirmSaveOpen(true);
                }
              }}
            >
              Simpan
            </PrimaryAction>
          </div>
        </div>
      </div>

      <SelectSpkModal
        open={prefillModalOpen}
        activeTab={activeTab}
        options={spkOptions}
        selectedSpkId={selectedSpkId}
        onChangeSelectedSpkId={setSelectedSpkId}
        onClose={() => setPrefillModalOpen(false)}
        onConfirm={() => void handleConfirmPrefill()}
        loading={loadingPrefill}
      />

      <ConfirmSaveModal
        open={confirmSaveOpen}
        summaryCount={summaryCount}
        selectedSpkId={selectedSpkId}
        onClose={() => setConfirmSaveOpen(false)}
        onConfirm={() => void handleSave()}
        saving={saving}
      />

      <AlertModal open={alertState !== null} config={alertState} onClose={() => setAlertState(null)} />

      <SuccessModal
        open={successOpen}
        title="Berhasil"
        headline="Data barang masuk berhasil disimpan dan stok telah diperbarui"
        message="Transaksi barang masuk sudah tersimpan ke backend dan stok barang aktif telah diperbarui oleh sistem."
        onClose={() => setSuccessOpen(false)}
      />
    </>
  );
}

function mapSpkOptions(entries: SpkHistoryEntry[]) {
  return entries
    .sort((a, b) => b.calculation_date.localeCompare(a.calculation_date))
    .map((entry) => ({
      id: entry.id,
      label: `SPK-${String(entry.id).padStart(4, "0")} • ${entry.calculation_date}`,
    }));
}

function SearchableItemSelect({
  options,
  value,
  placeholder,
  disabled = false,
  onChange,
}: Readonly<SearchableItemSelectProps>) {
  const wrapperRef = useRef<HTMLDivElement | null>(null);
  const selectedOption = options.find((option) => option.id === value) ?? null;
  const [query, setQuery] = useState(selectedOption?.label ?? "");
  const [open, setOpen] = useState(false);

  useEffect(() => {
    function handleOutsideClick(event: MouseEvent) {
      if (!wrapperRef.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);
    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
    };
  }, []);

  const filteredOptions = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) return options;
    return options.filter((option) => option.label.toLowerCase().includes(normalizedQuery));
  }, [options, query]);

  return (
    <div ref={wrapperRef} className={`relative col-span-4 ${open ? "z-50" : "z-20"}`}>
      <div className="relative">
        <input
          value={open ? query : (selectedOption?.label ?? query)}
          disabled={disabled}
          onChange={(event) => {
            const nextValue = event.target.value;
            setQuery(nextValue);
            setOpen(true);
            if (!nextValue.trim()) {
              onChange(null);
            }
          }}
          onFocus={() => {
            if (!disabled) setOpen(true);
          }}
          className={`h-11 w-full rounded-xl border border-slate-200 bg-white px-4 pr-10 text-sm text-slate-700 outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE] ${
            disabled ? "cursor-not-allowed bg-slate-100 text-slate-500" : ""
          }`}
          placeholder={placeholder}
        />
        <button
          className="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:hover:bg-transparent"
          onClick={() => {
            if (!disabled) setOpen((current) => !current);
          }}
          type="button"
          disabled={disabled}
        >
          <ChevronDown size={16} className={`transition-transform duration-200 ${open ? "rotate-180" : ""}`} />
        </button>
      </div>

      {open ? (
        <div className="absolute left-0 right-0 top-[calc(100%+8px)] z-30 max-h-[220px] overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-[0_20px_40px_rgba(15,23,42,0.12)]">
          <button
            className={`flex w-full items-center rounded-lg px-3 py-2 text-left text-sm transition ${
              value === null ? "bg-[#EEF4FF] font-medium text-[#2563EB]" : "text-slate-600 hover:bg-slate-50"
            }`}
            onClick={() => {
              setQuery("");
              onChange(null);
              setOpen(false);
            }}
            type="button"
          >
            Kosongkan pilihan
          </button>

          {filteredOptions.length > 0 ? (
            filteredOptions.map((option) => (
              <button
                key={option.id}
                className={`mt-1 flex w-full items-center rounded-lg px-3 py-2 text-left text-sm transition ${
                  option.id === value
                    ? "bg-[#EEF4FF] font-medium text-[#2563EB]"
                    : "text-slate-700 hover:bg-slate-50"
                }`}
                onClick={() => {
                  setQuery(option.label);
                  onChange(option.id, option.unit);
                  setOpen(false);
                }}
                type="button"
              >
                {option.label}
              </button>
            ))
          ) : (
            <div className="px-3 py-2 text-sm text-slate-400">Tidak ada bahan yang cocok.</div>
          )}
        </div>
      ) : null}
    </div>
  );
}

function createEmptyRow(): Row {
  return {
    id: Date.now() + Math.floor(Math.random() * 1000),
    item_id: null,
    qty_spk: 0,
    qty_actual: "0",
    unit: "-",
    locked: false,
  };
}

function openAlert(
  setAlertState: React.Dispatch<React.SetStateAction<AlertState>>,
  title: string,
  headline: string,
  message: string,
) {
  setAlertState({ title, headline, message });
}

function SelectSpkModal({
  open,
  activeTab,
  options,
  selectedSpkId,
  onChangeSelectedSpkId,
  onClose,
  onConfirm,
  loading,
}: {
  open: boolean;
  activeTab: "basah" | "kering";
  options: SpkHistoryRow[];
  selectedSpkId: number | null;
  onChangeSelectedSpkId: (value: number | null) => void;
  onClose: () => void;
  onConfirm: () => void;
  loading: boolean;
}) {
  const latestOptions = [...options]
    .sort((left, right) => right.id - left.id)
    .slice(0, 3);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative w-full max-w-[420px] overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 className="text-[22px] font-semibold leading-none text-slate-900">Isi Otomatis via SPK</h2>
            <p className="mt-2 text-sm text-slate-400">
              Pilih ID SPK {activeTab === "basah" ? "basah" : "kering & pengemas"} yang sudah di-generate.
            </p>
          </div>
          <button
            className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-500"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <div className="space-y-4 px-5 py-5">
          <div className="rounded-[18px] border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-slate-600">
            Setelah dikonfirmasi, tabel barang masuk akan otomatis diisi sesuai data yang ada di ID SPK terpilih.
          </div>

          <div>
            <label className="block text-sm font-semibold text-slate-700">ID SPK</label>
            <ThemedSelect
              className="mt-2 h-11 text-sm"
              value={selectedSpkId ? String(selectedSpkId) : ""}
              onChange={(value) => onChangeSelectedSpkId(Number(value) || null)}
              options={[
                { value: "", label: "Pilih ID SPK" },
                ...latestOptions.map((option) => ({ value: String(option.id), label: option.label })),
              ]}
            />
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
          <button
            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
            onClick={onClose}
            type="button"
          >
            Batal
          </button>
          <button
            className="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
            disabled={loading}
            onClick={onConfirm}
            type="button"
          >
            {loading ? "Memuat..." : "Konfirmasi"}
          </button>
        </div>
      </div>
    </div>
  );
}

function ConfirmSaveModal({
  open,
  summaryCount,
  selectedSpkId,
  onClose,
  onConfirm,
  saving,
}: {
  open: boolean;
  summaryCount: number;
  selectedSpkId: number | null;
  onClose: () => void;
  onConfirm: () => void;
  saving: boolean;
}) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[72] flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative w-full max-w-[420px] overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 className="text-[22px] font-semibold leading-none text-slate-900">Konfirmasi Simpan</h2>
            <p className="mt-2 text-sm text-slate-400">Pastikan data barang masuk sudah benar sebelum disimpan.</p>
          </div>
          <button
            className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-500"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <div className="space-y-4 px-5 py-5">
          <div className="rounded-[18px] border border-blue-200 bg-blue-50 px-5 py-4 text-sm text-slate-600">
            Sistem akan menyimpan <span className="font-semibold text-slate-900">{summaryCount} bahan</span>
            {selectedSpkId ? (
              <>
                {" "}dengan referensi <span className="font-semibold text-slate-900">SPK-{String(selectedSpkId).padStart(4, "0")}</span>.
              </>
            ) : (
              "."
            )}
          </div>

          <div className="rounded-[18px] border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-slate-600">
            Setelah dikonfirmasi, data barang masuk akan dikirim ke backend dan stok akan diperbarui oleh sistem.
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
          <button
            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
            onClick={onClose}
            type="button"
          >
            Batal
          </button>
          <button
            className="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
            disabled={saving}
            onClick={onConfirm}
            type="button"
          >
            {saving ? "Menyimpan..." : "Ya, Konfirmasi"}
          </button>
        </div>
      </div>
    </div>
  );
}

function AlertModal({
  open,
  config,
  onClose,
}: {
  open: boolean;
  config: AlertState;
  onClose: () => void;
}) {
  if (!open || !config) return null;

  return (
    <div className="fixed inset-0 z-[74] flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative w-full max-w-[400px] overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <h2 className="text-[22px] font-semibold leading-none text-slate-900">{config.title}</h2>
          <button
            className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-500"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <div className="px-5 py-5">
          <div className="rounded-[18px] border border-red-200 bg-red-50 px-5 py-6 text-center">
            <div className="flex justify-center text-slate-700">
              <AlertTriangle size={36} strokeWidth={1.9} />
            </div>

            <p className="mt-3 text-base font-semibold text-red-600">{config.headline}</p>
            <p className="mt-2 text-sm leading-7 text-slate-500">{config.message}</p>
          </div>
        </div>

        <div className="flex justify-end border-t border-slate-200 px-5 py-4">
          <button
            className="rounded-xl bg-red-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-red-600"
            onClick={onClose}
            type="button"
          >
            Oke
          </button>
        </div>
      </div>
    </div>
  );
}
