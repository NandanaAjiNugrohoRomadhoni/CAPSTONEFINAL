"use client";

import { useEffect, useMemo, useRef, useState, type Dispatch, type SetStateAction } from "react";
import { AlertTriangle, ChevronDown, Plus, Trash2, X } from "lucide-react";
import sdk from "@/lib";
import { getErrorMessage } from "@/lib/admin-utils";
import SuccessModal from "@/components/feedback/SuccessModal";

type ItemRow = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
type MealTimeRow = Awaited<ReturnType<typeof sdk.mealTimes.list>>["data"][number];
type PreviewItem = Awaited<
  ReturnType<typeof sdk.spk.operationalStockPreview>
>["data"]["items"][number];
type SearchableItemOption = { id: number; label: string; unit: string };

type ManualRow = {
  id: number;
  item_id: number | null;
  qty: string;
  unit: string;
};

type BasahValidatedRow = {
  id: number;
  item_id: number;
  item_name: string;
  unit: string;
  qty_spk: number;
  qty_actual: string;
  locked?: boolean;
};

type SavedRecommendation = {
  serviceDate: string;
  patientCount: number;
  menuName: string;
  totalItems: number;
  rows: BasahValidatedRow[];
  submitted?: boolean;
};

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
  className?: string;
  onChange: (itemId: number | null, unit?: string) => void;
};

const MEAL_PRIORITY = ["SIANG", "SORE", "PAGI"];
const RECOMMENDATION_STORAGE_KEY = "barang-keluar-basah-rekomendasi";

export default function BarangKeluarPage() {
  const [activeTab, setActiveTab] = useState<"basah" | "kering">("basah");
  const [items, setItems] = useState<ItemRow[]>([]);
  const [mealTimes, setMealTimes] = useState<MealTimeRow[]>([]);
  const [patientCount, setPatientCount] = useState("0");
  const [validatedRows, setValidatedRows] = useState<BasahValidatedRow[]>([]);
  const [validatedMeta, setValidatedMeta] = useState<{ totalItems: number; menuName: string } | null>(null);
  const [savedRecommendation, setSavedRecommendation] = useState<SavedRecommendation | null>(null);
  const [confirmSaveOpen, setConfirmSaveOpen] = useState(false);
  const [recommendationPreviewOpen, setRecommendationPreviewOpen] = useState(false);
  const [recommendationRows, setRecommendationRows] = useState<BasahValidatedRow[]>([]);
  const [recommendationMeta, setRecommendationMeta] = useState<{ totalItems: number; menuName: string } | null>(null);
  const [rows, setRows] = useState<ManualRow[]>([createManualRow()]);
  const [saving, setSaving] = useState(false);
  const [validating, setValidating] = useState(false);
  const [alertState, setAlertState] = useState<AlertState>(null);
  const [successState, setSuccessState] = useState<{ headline: string; message: string } | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      try {
        const [itemResponse, mealTimeResponse] = await Promise.all([
          sdk.items.list({ perPage: 100, sortBy: "name", sortDir: "ASC", is_active: true }),
          sdk.mealTimes.list({ paginate: false, sortBy: "id", sortDir: "ASC" }),
        ]);

        if (cancelled) return;

        setItems(itemResponse.data ?? []);
        setMealTimes(sortMealTimes(mealTimeResponse.data ?? []));
      } catch (loadError) {
        if (!cancelled) {
          openAlert(
            setAlertState,
            "Gagal Memuat Data",
            "Form barang keluar belum bisa ditampilkan",
            getErrorMessage(loadError, "Terjadi kesalahan saat memuat daftar bahan atau waktu makan."),
          );
        }
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, []);

  const serviceDate = useMemo(() => new Date().toISOString().slice(0, 10), []);

  useEffect(() => {
    const saved = readSavedRecommendation(serviceDate);
    if (saved) {
      setSavedRecommendation(saved);
      setPatientCount(String(saved.patientCount));
      setValidatedMeta({ totalItems: saved.totalItems, menuName: saved.menuName });
    }
  }, [serviceDate]);

  const itemMap = useMemo(() => new Map(items.map((item) => [item.id, item])), [items]);

  const basahItems = useMemo(
    () => items.filter((item) => includesCategoryKeyword(item.category?.name, ["basah"])),
    [items],
  );
  const basahItemOptions = useMemo<SearchableItemOption[]>(
    () =>
      basahItems.map((item) => ({
        id: item.id,
        label: item.name,
        unit: item.item_unit_base?.name ?? item.unit_base ?? "-",
      })),
    [basahItems],
  );

  const keringItems = useMemo(
    () => items.filter((item) => includesCategoryKeyword(item.category?.name, ["kering", "pengemas"])),
    [items],
  );
  const keringItemOptions = useMemo<SearchableItemOption[]>(
    () =>
      keringItems.map((item) => ({
        id: item.id,
        label: item.name,
        unit: item.item_unit_base?.name ?? item.unit_base ?? "-",
      })),
    [keringItems],
  );

  async function prepareRecommendationPreview() {
    const totalPatients = Number(patientCount);

    if (totalPatients <= 0) {
      openAlert(
        setAlertState,
        "Jumlah Pasien Belum Valid",
        "Isi jumlah pasien lebih dari 0",
        "Sistem membutuhkan jumlah pasien hari ini untuk menghitung kebutuhan bahan basah.",
      );
      return;
    }

    if (mealTimes.length === 0) {
      openAlert(
        setAlertState,
        "Waktu Makan Belum Tersedia",
        "Data waktu makan belum bisa dipakai",
        "Silakan muat ulang halaman atau periksa data waktu makan terlebih dahulu.",
      );
      return;
    }

    setValidating(true);

    try {
      const serviceDate = new Date().toISOString().slice(0, 10);
      const previews = await Promise.all(
        mealTimes.map(async (mealTime) => {
          const response = await sdk.spk.operationalStockPreview({
            service_date: serviceDate,
            meal_time: String(mealTime.name ?? "").toUpperCase(),
            total_patients: totalPatients,
          });

          return {
            mealTime,
            response: response.data,
          };
        }),
      );

      const previewItems = previews.flatMap(({ response }) => response.items ?? []);
      if (previewItems.length === 0) {
        throw new Error("Sistem belum menemukan bahan basah untuk jumlah pasien yang diinput.");
      }

      const aggregated = aggregatePreviewItems(previewItems, itemMap);
      const menuNames = Array.from(
        new Set(previews.map(({ response }) => response.menu?.name ?? "-").filter((menuName) => menuName && menuName !== "-")),
      );

      const nextMeta = {
        totalItems: aggregated.length,
        menuName: menuNames.length > 0 ? menuNames.join(", ") : "-",
      };

      await ensureDailyPatientForDate(serviceDate, totalPatients);

      const nextRecommendation: SavedRecommendation = {
        serviceDate,
        patientCount: totalPatients,
        menuName: nextMeta.menuName,
        totalItems: nextMeta.totalItems,
        rows: aggregated.map((row) => ({ ...row, locked: true })),
      };

      writeSavedRecommendation(nextRecommendation);
      setSavedRecommendation(nextRecommendation);
      setValidatedMeta({ totalItems: nextRecommendation.totalItems, menuName: nextRecommendation.menuName });
      setValidatedRows([]);
      setRecommendationRows(aggregated);
      setRecommendationMeta(nextMeta);
      setRecommendationPreviewOpen(true);
    } catch (validationError) {
      openAlert(
        setAlertState,
        "Rekomendasi Gagal",
        "Data rekomendasi belum bisa dibuat",
        getErrorMessage(validationError, "Gagal menyiapkan data pengeluaran bahan basah."),
      );
    } finally {
      setValidating(false);
    }
  }

  function loadValidatedBasahFromSavedRecommendation() {
    const saved = readSavedRecommendation(serviceDate) ?? savedRecommendation;
    if (!saved) {
      openAlert(
        setAlertState,
        "Rekomendasi Belum Disimpan",
        "Simpan rekomendasi harian terlebih dahulu",
        "Masukkan jumlah pasien lalu tekan tombol Simpan untuk membuat rekomendasi bahan basah hari ini.",
      );
      return;
    }

    setSavedRecommendation(saved);
    setPatientCount(String(saved.patientCount));
    setValidatedRows(saved.rows.map((row) => ({ ...row })));
    setValidatedMeta({ totalItems: saved.totalItems, menuName: saved.menuName });
  }

  async function saveBasahOutput() {
    setSaving(true);

    try {
      const totalPatients = Number(patientCount);
      if (totalPatients <= 0) {
        throw new Error("Jumlah pasien harus lebih dari 0.");
      }

      const details = validatedRows
        .filter((row) => row.item_id !== 0 && Number(row.qty_actual) > 0)
        .map((row) => ({
          item_id: row.item_id,
          qty: Number(row.qty_actual),
          input_unit: "base" as const,
        }));

      if (details.length === 0) {
        throw new Error("Klik Validasi terlebih dahulu lalu pastikan ada bahan yang siap disimpan.");
      }

      const itemIds = details.map((detail) => detail.item_id);
      const duplicateId = itemIds.find((itemId, index) => itemIds.indexOf(itemId) !== index);
      if (duplicateId) {
        throw new Error(`Bahan ${itemMap.get(duplicateId)?.name ?? `Item #${duplicateId}`} tidak boleh diinput dua kali.`);
      }

      const serviceDate = new Date().toISOString().slice(0, 10);
      await ensureDailyPatientForDate(serviceDate, totalPatients);

      await sdk.stockTransactions.create({
        type_name: "OUT",
        transaction_date: serviceDate,
        details,
      });

      setConfirmSaveOpen(false);
      const nextSavedRecommendation = savedRecommendation
        ? { ...savedRecommendation, submitted: true }
        : null;
      if (nextSavedRecommendation) {
        writeSavedRecommendation(nextSavedRecommendation);
      }
      setSavedRecommendation(nextSavedRecommendation);
      setSuccessState({
        headline: "Barang keluar bahan basah berhasil disimpan",
        message: "Data pengeluaran bahan basah sudah tersimpan ke backend dan stok telah diperbarui oleh sistem.",
      });
      setValidatedRows([]);
      setValidatedMeta(
        nextSavedRecommendation
          ? { totalItems: nextSavedRecommendation.totalItems, menuName: nextSavedRecommendation.menuName }
          : null,
      );
    } catch (saveError) {
      setConfirmSaveOpen(false);
      openAlert(
        setAlertState,
        "Penyimpanan Gagal",
        "Data barang keluar belum tersimpan",
        getErrorMessage(saveError, "Gagal menyimpan barang keluar bahan basah."),
      );
    } finally {
      setSaving(false);
    }
  }

  async function saveDryOutput() {
    setSaving(true);
    try {
      const details = rows
        .filter((row) => row.item_id !== null && Number(row.qty) > 0)
        .map((row) => ({ item_id: row.item_id as number, qty: Number(row.qty), input_unit: "base" as const }));

      if (details.length === 0) {
        throw new Error("Minimal satu bahan harus dipilih dan memiliki jumlah lebih dari 0.");
      }

      const itemIds = details.map((detail) => detail.item_id);
      const duplicateId = itemIds.find((itemId, index) => itemIds.indexOf(itemId) !== index);
      if (duplicateId) {
        throw new Error(`Bahan ${itemMap.get(duplicateId)?.name ?? `Item #${duplicateId}`} tidak boleh diinput dua kali.`);
      }

      await sdk.stockTransactions.create({
        type_name: "OUT",
        transaction_date: new Date().toISOString().slice(0, 10),
        details,
      });

      setSuccessState({
        headline: "Barang keluar berhasil disimpan",
        message: "Transaksi bahan kering & pengemas telah tersimpan ke backend.",
      });
      setRows([createManualRow()]);
    } catch (saveError) {
      openAlert(
        setAlertState,
        "Penyimpanan Gagal",
        "Data barang keluar belum tersimpan",
        getErrorMessage(saveError, "Gagal menyimpan bahan kering & pengemas."),
      );
    } finally {
      setSaving(false);
    }
  }

  function resetBasahForm() {
    setValidatedRows([]);
    setRecommendationPreviewOpen(false);
    setRecommendationRows([]);
    setRecommendationMeta(null);
    if (!savedRecommendation) {
      setPatientCount("0");
      setValidatedMeta(null);
    }
  }

  return (
    <>
      <div className="space-y-6">
        <div>
          <h1 className="text-[22px] font-semibold text-gray-900">Barang Keluar</h1>
          <p className="text-sm text-gray-400">
            Input barang keluar khusus jenis bahan kering & pengemas. Bahan basah disiapkan melalui validasi jumlah pasien hari ini.
          </p>
        </div>

        <div className="flex gap-10 border-b border-gray-200">
          <button
            onClick={() => setActiveTab("basah")}
            className={`relative pb-3 text-sm font-medium ${activeTab === "basah" ? "text-blue-600" : "text-gray-400"}`}
            type="button"
          >
            Input Bahan Basah
            {activeTab === "basah" ? <div className="absolute bottom-0 left-0 h-[3px] w-full rounded-full bg-blue-600" /> : null}
          </button>

          <button
            onClick={() => setActiveTab("kering")}
            className={`relative pb-3 text-sm font-medium ${activeTab === "kering" ? "text-blue-600" : "text-gray-400"}`}
            type="button"
          >
            Input Bahan Kering & Pengemas
            {activeTab === "kering" ? <div className="absolute bottom-0 left-0 h-[3px] w-full rounded-full bg-blue-600" /> : null}
          </button>
        </div>

        {activeTab === "basah" ? (
          <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div className="border-b border-gray-100 p-5">
              <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                  <h2 className="font-semibold text-gray-900">Input Pasien Hari Ini</h2>
                  <p className="text-xs text-gray-400">Simpan rekomendasi harian terlebih dahulu, lalu gunakan tombol validasi untuk mengisi tabel bahan basah.</p>
                </div>
                <button
                  className="inline-flex h-11 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-5 text-sm font-medium text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:bg-blue-100/60"
                  disabled={validating || !savedRecommendation}
                  onClick={loadValidatedBasahFromSavedRecommendation}
                  type="button"
                >
                  {validating ? "Menyiapkan..." : "Validasi"}
                </button>
              </div>
            </div>

            <div className="space-y-5 p-5">
              <div>
                <label className="text-sm font-medium text-gray-700">
                  Jumlah Pasien Total <span className="text-red-500">*</span>
                </label>
                <input
                  type="number"
                  value={patientCount}
                  onChange={(event) => setPatientCount(event.target.value)}
                  disabled={savedRecommendation !== null}
                  placeholder="0"
                  className={`mt-3 h-12 w-full rounded-xl border border-gray-200 px-4 text-base font-semibold text-gray-700 outline-none focus:ring-2 focus:ring-blue-500 ${
                    savedRecommendation ? "cursor-not-allowed bg-slate-100 text-slate-500" : ""
                  }`}
                />
              </div>

              <div className="overflow-visible rounded-2xl border border-gray-200">
                <div className="bg-[#F1F5F9] px-4 py-3 text-xs font-semibold text-gray-500">DAFTAR BAHAN BASAH</div>
                <div className="grid grid-cols-12 border-t px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                  <div className="col-span-2">ID</div>
                  <div className="col-span-4">Nama Bahan</div>
                  <div className="col-span-2">Qty SPK</div>
                  <div className="col-span-2">Qty Faktual</div>
                  <div className="col-span-1">Satuan</div>
                  <div className="col-span-1" />
                </div>

                {validatedRows.length > 0 ? (
                  validatedRows.map((row) => (
                    <div key={row.id} className="grid grid-cols-12 items-center gap-4 border-t px-5 py-4">
                      <div className="col-span-2 text-base font-medium text-gray-500">
                        {row.item_id ? `IT-${String(row.item_id).padStart(3, "0")}` : "-"}
                      </div>
                      {row.locked ? (
                        <div className="col-span-4 text-lg font-semibold text-gray-900">{row.item_name}</div>
                      ) : (
                        <SearchableItemSelect
                          options={basahItemOptions}
                          value={row.item_id || null}
                          placeholder="Pilih Nama Bahan"
                          className="col-span-4"
                          onChange={(nextId, unit) =>
                            setValidatedRows((current) =>
                              current.map((item) =>
                                item.id === row.id
                                  ? {
                                      ...item,
                                      item_id: nextId ?? 0,
                                      item_name: nextId ? basahItemOptions.find((option) => option.id === nextId)?.label ?? "" : "",
                                      unit: nextId ? unit ?? "-" : "-",
                                    }
                                  : item,
                              ),
                            )
                          }
                        />
                      )}
                      <div className="col-span-2 text-base font-medium text-gray-600">{row.qty_spk}</div>
                      <input
                        type="number"
                        min="0"
                        value={row.qty_actual}
                        onChange={(event) =>
                          setValidatedRows((current) =>
                            current.map((item) =>
                              item.id === row.id ? { ...item, qty_actual: event.target.value } : item,
                            ),
                          )
                        }
                        className="col-span-2 h-12 rounded-xl border border-gray-200 px-4 py-2 text-base"
                      />
                      <div className="col-span-1 text-base font-medium text-gray-600">{row.unit}</div>
                      <button
                        className="col-span-1 flex justify-center rounded-xl p-3 text-red-500 transition hover:bg-red-50"
                        onClick={() =>
                          setValidatedRows((current) =>
                            current.length === 1 ? current : current.filter((item) => item.id !== row.id),
                          )
                        }
                        type="button"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  ))
                ) : (
                  <div className="px-4 py-8 text-center text-sm text-gray-400">
                    {savedRecommendation
                      ? "Klik tombol Validasi untuk memuat rekomendasi bahan basah yang sudah tersimpan."
                      : "Masukkan jumlah pasien lalu tekan tombol Simpan untuk membuat rekomendasi bahan basah hari ini."}
                  </div>
                )}

                {validatedRows.length > 0 ? (
                  <div className="border-t p-3">
                    <button
                      className="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-blue-400 py-3 text-base text-blue-600 transition hover:bg-blue-50"
                      onClick={() =>
                        setValidatedRows((current) => [
                          ...current,
                          {
                            id: Date.now() + Math.floor(Math.random() * 1000),
                            item_id: 0,
                            item_name: "",
                            unit: "-",
                            qty_spk: 0,
                            qty_actual: "0",
                            locked: false,
                          },
                        ])
                      }
                      type="button"
                    >
                      <Plus size={16} />
                      Tambah Bahan
                    </button>
                  </div>
                ) : null}
              </div>

              {validatedMeta ? (
                <div className="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                  Paket menu: <span className="font-semibold">{validatedMeta.menuName}</span> | Total bahan tervalidasi:{" "}
                  <span className="font-semibold">{validatedMeta.totalItems}</span>
                </div>
              ) : null}
            </div>

            <div className="flex justify-end gap-3 border-t border-gray-100 bg-[#F9FAFB] p-5">
              <button
                className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-100"
                onClick={resetBasahForm}
                type="button"
              >
                Batal
              </button>
              <button
                className="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
                onClick={() => {
                  if (!savedRecommendation) {
                    void prepareRecommendationPreview();
                    return;
                  }
                  if (validatedRows.length === 0) {
                    openAlert(
                      setAlertState,
                      "Data Belum Divalidasi",
                      "Validasi bahan basah terlebih dahulu",
                      "Klik tombol Validasi setelah rekomendasi harian tersimpan agar tabel bahan basah bisa disimpan ke riwayat transaksi.",
                    );
                    return;
                  }
                  setConfirmSaveOpen(true);
                }}
                type="button"
                disabled={saving}
              >
                {saving ? "Menyimpan..." : "Simpan"}
              </button>
            </div>
          </div>
        ) : (
          <div className="rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div className="border-b border-gray-100 p-5">
              <h2 className="font-semibold text-gray-900">Input Bahan Kering & Pengemas</h2>
              <p className="text-xs text-gray-400">Tambahkan bahan kering atau pengemas secara manual.</p>
            </div>

            <div className="p-5">
              <div className="overflow-visible rounded-xl border border-gray-200">
                <div className="bg-[#F1F5F9] px-4 py-3 text-xs font-semibold text-gray-500">DAFTAR BARANG</div>
                <div className="grid grid-cols-12 border-t px-4 py-2 text-xs text-gray-400">
                  <div className="col-span-5">Nama Bahan</div>
                  <div className="col-span-3">Jumlah</div>
                  <div className="col-span-3">Satuan</div>
                  <div className="col-span-1" />
                </div>

                {rows.map((row) => (
                  <div key={row.id} className="grid grid-cols-12 items-center gap-3 border-t px-4 py-3">
                    <SearchableItemSelect
                      options={keringItemOptions}
                      value={row.item_id}
                      placeholder="Pilih Nama Bahan"
                      onChange={(nextId, unit) => {
                        setRows((current) =>
                          current.map((item) =>
                            item.id === row.id ? { ...item, item_id: nextId, unit: unit ?? "-" } : item,
                          ),
                        );
                      }}
                    />

                    <input
                      type="number"
                      min="0"
                      value={row.qty}
                      onChange={(event) =>
                        setRows((current) =>
                          current.map((item) => (item.id === row.id ? { ...item, qty: event.target.value } : item)),
                        )
                      }
                      className="col-span-3 rounded-lg border border-gray-200 px-3 py-2 text-sm"
                      placeholder="0"
                    />

                    <input
                      disabled
                      className="col-span-3 rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-sm"
                      value={row.unit}
                    />

                    <button
                      className="col-span-1 flex justify-center rounded-lg p-2 text-red-500 transition hover:bg-red-50"
                      onClick={() =>
                        setRows((current) => (current.length === 1 ? current : current.filter((item) => item.id !== row.id)))
                      }
                      type="button"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                ))}

                <div className="border-t p-3">
                  <button
                    className="w-full rounded-lg border border-dashed border-blue-400 py-2 text-sm text-blue-600 transition hover:bg-blue-50"
                    onClick={() => setRows((current) => [...current, createManualRow()])}
                    type="button"
                  >
                    <span className="inline-flex items-center gap-2">
                      <Plus size={16} />
                      Tambah Baris Bahan
                    </span>
                  </button>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-gray-100 bg-[#F9FAFB] p-5">
              <button
                className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-100"
                onClick={() => setRows([createManualRow()])}
                type="button"
              >
                Batal
              </button>
              <button
                className="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-300"
                onClick={() => void saveDryOutput()}
                type="button"
                disabled={saving}
              >
                {saving ? "Menyimpan..." : "Simpan"}
              </button>
            </div>
          </div>
        )}
      </div>

      <RecommendationPreviewModal
        open={recommendationPreviewOpen}
        rows={recommendationRows}
        patientCount={patientCount}
        menuName={recommendationMeta?.menuName ?? "-"}
        onClose={() => setRecommendationPreviewOpen(false)}
      />

      <ConfirmBasahSaveModal
        open={confirmSaveOpen}
        patientCount={patientCount}
        totalRows={validatedRows.length}
        onClose={() => setConfirmSaveOpen(false)}
        onConfirm={() => void saveBasahOutput()}
        saving={saving}
      />

      <AlertModal open={alertState !== null} config={alertState} onClose={() => setAlertState(null)} />

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

async function ensureDailyPatientForDate(serviceDate: string, totalPatients: number) {
  const existingResponse = await sdk.dailyPatients.list();
  const existing = (existingResponse.data ?? []).find((row) => row.service_date === serviceDate);

  if (existing) {
    return existing;
  }

  try {
    const created = await sdk.dailyPatients.create({
      service_date: serviceDate,
      total_patients: totalPatients,
    });
    return created.data;
  } catch (error) {
    const message = getErrorMessage(error, "");
    if (message.toLowerCase().includes("service_date already exists")) {
      return null;
    }
    throw error;
  }
}

function RecommendationPreviewModal({
  open,
  rows,
  patientCount,
  menuName,
  onClose,
}: {
  open: boolean;
  rows: BasahValidatedRow[];
  patientCount: string;
  menuName: string;
  onClose: () => void;
}) {
  if (!open) return null;

  const totalRows = rows.length;

  return (
    <div className="fixed inset-0 z-[75] flex items-center justify-center px-4 py-6">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative flex max-h-[calc(100vh-3rem)] w-full max-w-[1120px] flex-col overflow-hidden rounded-[24px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 className="text-[24px] font-semibold text-slate-900">Bahan Basah Keluar Hari Ini</h2>
            <p className="mt-1 text-sm text-slate-400">Rekomendasi bahan keluar sudah tersimpan otomatis dan input pasien hari ini sudah dikunci.</p>
          </div>
          <button
            className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-500"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-5 py-5">
          <div className="grid gap-3 rounded-2xl bg-[#EEF4FF] px-4 py-3 md:grid-cols-4">
            <InfoBlock label="Tanggal" value={new Date().toLocaleDateString("id-ID")} />
            <InfoBlock label="Jumlah Pasien" value={`${patientCount} orang`} />
            <InfoBlock label="Paket Menu" value={menuName} />
            <InfoBlock label="Total Bahan" value={`${totalRows} bahan`} />
          </div>

          <div className="mt-5 overflow-hidden rounded-2xl border border-[#D7E0EE]">
            <div className="grid grid-cols-12 border-b bg-[#F8FAFC] px-5 py-3 text-xs font-semibold uppercase tracking-wide text-[#94A3B8]">
              <div className="col-span-2">ID</div>
              <div className="col-span-5">Nama Bahan</div>
              <div className="col-span-2">Qty SPK</div>
              <div className="col-span-2">Qty Rekomendasi</div>
              <div className="col-span-1">Satuan</div>
            </div>
            {rows.map((row) => (
              <div key={row.id} className="grid grid-cols-12 items-center gap-4 border-t px-5 py-4">
                <div className="col-span-2 text-base text-slate-500">
                  {row.item_id ? `IT-${String(row.item_id).padStart(3, "0")}` : "-"}
                </div>
                <div className="col-span-5 text-lg font-semibold text-slate-900">{row.item_name}</div>
                <div className="col-span-2 text-base text-slate-600">{row.qty_spk}</div>
                <div className="col-span-2 text-base text-slate-600">{row.qty_spk}</div>
                <div className="col-span-1 text-base text-slate-600">{row.unit}</div>
              </div>
            ))}
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
          <button
            className="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50"
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

function ConfirmBasahSaveModal({
  open,
  patientCount,
  totalRows,
  onClose,
  onConfirm,
  saving,
}: {
  open: boolean;
  patientCount: string;
  totalRows: number;
  onClose: () => void;
  onConfirm: () => void;
  saving: boolean;
}) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[76] flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={onClose} />
      <div className="relative w-full max-w-[420px] overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
        <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <div>
            <h2 className="text-[22px] font-semibold leading-none text-slate-900">Konfirmasi Simpan</h2>
            <p className="mt-2 text-sm text-slate-400">Pastikan data pengeluaran bahan basah sudah benar.</p>
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
            Sistem akan menyimpan data pengeluaran untuk <span className="font-semibold text-slate-900">{patientCount} pasien</span> dengan <span className="font-semibold text-slate-900">{totalRows} bahan</span>.
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
    <div className="fixed inset-0 z-[78] flex items-center justify-center px-4">
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

function InfoBlock({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <div className="text-[11px] font-semibold uppercase tracking-wide text-[#2155CD]">{label}</div>
      <div className="mt-1 text-base font-semibold text-[#16213E]">{value}</div>
    </div>
  );
}

function SearchableItemSelect({
  options,
  value,
  placeholder,
  disabled = false,
  className = "col-span-5",
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
    <div ref={wrapperRef} className={`relative ${className} ${open ? "z-50" : "z-20"}`}>
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

function createManualRow(): ManualRow {
  return {
    id: Date.now() + Math.floor(Math.random() * 1000),
    item_id: null,
    qty: "0",
    unit: "-",
  };
}

function sortMealTimes(rows: MealTimeRow[]) {
  return [...rows].sort((left, right) => {
    const leftIndex = MEAL_PRIORITY.indexOf(String(left.name ?? "").toUpperCase());
    const rightIndex = MEAL_PRIORITY.indexOf(String(right.name ?? "").toUpperCase());
    return (leftIndex === -1 ? 99 : leftIndex) - (rightIndex === -1 ? 99 : rightIndex);
  });
}

function includesCategoryKeyword(name: string | null | undefined, keywords: string[]) {
  const normalized = String(name ?? "").trim().toLowerCase();
  return keywords.some((keyword) => normalized.includes(keyword));
}

function openAlert(
  setAlertState: Dispatch<SetStateAction<AlertState>>,
  title: string,
  headline: string,
  message: string,
) {
  setAlertState({ title, headline, message });
}

function aggregatePreviewItems(items: PreviewItem[], itemMap: Map<number, ItemRow>) {
  const aggregated = new Map<number, BasahValidatedRow>();

  for (const item of items) {
    const existing = aggregated.get(item.item_id);
    const qty = Number(item.projected_stock_out_qty ?? item.required_qty ?? 0);
    const fallbackItem = itemMap.get(item.item_id);
    const unit =
      item.item_unit_base ??
      fallbackItem?.item_unit_base?.name ??
      fallbackItem?.unit_base ??
      "-";

    if (existing) {
      existing.qty_spk += qty;
      existing.qty_actual = String(existing.qty_spk);
      continue;
    }

    aggregated.set(item.item_id, {
      id: Date.now() + aggregated.size,
      item_id: item.item_id,
      item_name: item.item_name ?? fallbackItem?.name ?? `Item #${item.item_id}`,
      unit,
      qty_spk: qty,
      qty_actual: String(qty),
      locked: true,
    });
  }

  return Array.from(aggregated.values());
}

function getRecommendationStorageKey(serviceDate: string) {
  return `${RECOMMENDATION_STORAGE_KEY}:${serviceDate}`;
}

function readSavedRecommendation(serviceDate: string) {
  if (typeof window === "undefined") return null;

  try {
    const raw = window.localStorage.getItem(getRecommendationStorageKey(serviceDate));
    if (!raw) return null;
    return JSON.parse(raw) as SavedRecommendation;
  } catch {
    return null;
  }
}

function writeSavedRecommendation(payload: SavedRecommendation) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(getRecommendationStorageKey(payload.serviceDate), JSON.stringify(payload));
}
