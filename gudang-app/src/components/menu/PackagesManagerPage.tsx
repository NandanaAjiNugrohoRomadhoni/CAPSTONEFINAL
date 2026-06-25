"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { ChevronDown, X } from "lucide-react";
import { createPortal } from "react-dom";
import sdk from "@/lib";
import { getErrorMessage } from "@/lib/admin-utils";
import { listAllPaginatedRows } from "@/lib/pagination";
import { buildCsvPackageCards, getCsvMenuPackageLabel } from "@/lib/menu-csv-plan";
import DeleteConfirmModal from "@/components/feedback/DeleteConfirmModal";
import SuccessModal from "@/components/feedback/SuccessModal";
import {
  AdminPageHeading,
  MiniActionButton,
  SurfaceCard,
} from "@/components/admin/ui";

type ModalMode = "edit" | null;

type NoticeState = {
  title: string;
  headline: string;
  message: string;
} | null;

type MealRowState = {
  rowId: string;
  slotId: number | null;
  value: string;
  deleted: boolean;
};

type DishOption = {
  id: number;
  name: string;
  isActive: boolean;
};

type MenuRow = Awaited<ReturnType<typeof sdk.menus.list>>["data"][number];

type MealTimeOption = {
  id: number;
  name: string;
};

type MealKey = "siang" | "sore" | "pagi";

type PackageCard = {
  id: number;
  title: string;
  active: boolean;
  meals: Record<MealKey, string[]>;
};

type SlotState = {
  id: number | null;
  mealTimeId: number | null;
  dishId: number | null;
  dishName: string | null;
};

type SearchableMealSelectProps = {
  label: string;
  placeholder: string;
  options: DishOption[];
  value: string;
  onChange: (value: string, option?: DishOption) => void;
};

const mealTone: Record<MealKey, string> = {
  siang: "bg-[#DCEAFE] text-[#0A6DDE]",
  sore: "bg-[#ECE8FF] text-[#7C3AED]",
  pagi: "bg-[#FFF4C7] text-[#D97706]",
};

const mealLabel: Record<MealKey, string> = {
  siang: "SIANG",
  sore: "SORE",
  pagi: "PAGI",
};

const mealOrder: MealKey[] = ["siang", "sore", "pagi"];
const emptyMealValues = (): Record<MealKey, MealRowState[]> => ({
  siang: [createMealRowState()],
  sore: [createMealRowState()],
  pagi: [createMealRowState()],
});

function createMealRowState(slotId: number | null = null, value = ""): MealRowState {
  return {
    rowId: typeof crypto !== "undefined" && typeof crypto.randomUUID === "function"
      ? crypto.randomUUID()
      : `meal-row-${Date.now()}-${Math.random().toString(36).slice(2)}`,
    slotId,
    value,
    deleted: false,
  };
}

function normalizeMealKey(name: string | null | undefined): MealKey | null {
  const normalized = (name ?? "").trim().toLowerCase();

  if (normalized === "siang") {
    return "siang";
  }

  if (normalized === "sore") {
    return "sore";
  }

  if (normalized === "pagi") {
    return "pagi";
  }

  return null;
}

function buildPackageCards(
  menus: Array<{ id: number; name: string }>,
  slots: Array<{
    menu_id: number;
    dish_id: number;
    menu?: { id: number; name: string };
    meal_time?: { id: number; name: string | null };
    dish?: { id: number; name: string | null };
  }>,
  labels: string[] = [],
): PackageCard[] {
  const groupedSlots = new Map<number, Record<MealKey, string[]>>();

  for (const slot of slots) {
    const mealKey = normalizeMealKey(slot.meal_time?.name);
    if (!mealKey) {
      continue;
    }

    const current = groupedSlots.get(slot.menu_id) ?? {
      siang: [],
      sore: [],
      pagi: [],
    };

    const dishName = slot.dish?.name ?? null;
    if (dishName) {
      current[mealKey].push(dishName);
    }
    groupedSlots.set(slot.menu_id, current);
  }

  return [...menus]
    .sort((left, right) => left.id - right.id)
    .map((menu, index) => ({
      id: menu.id,
      title: labels[index] ?? menu.name,
      meals: groupedSlots.get(menu.id) ?? {
        siang: [],
        sore: [],
        pagi: [],
      },
      active: Object.values(
        groupedSlots.get(menu.id) ?? {
          siang: [],
          sore: [],
          pagi: [],
        },
      ).some((items) => items.length > 0),
    }));
}

function getMealRowLabel(rowIndex: number) {
  return `Menu ${rowIndex + 1}`;
}

function SearchableMealSelect({
  label,
  placeholder,
  options,
  value,
  onChange,
}: Readonly<SearchableMealSelectProps>) {
  const [query, setQuery] = useState(value);
  const [open, setOpen] = useState(false);
  const wrapperRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);
  const dropdownRef = useRef<HTMLDivElement | null>(null);
  const [dropdownStyle, setDropdownStyle] = useState<{
    top?: number;
    bottom?: number;
    left: number;
    width: number;
    maxHeight: number;
  } | null>(null);

  useEffect(() => {
    setQuery(value);
  }, [value]);

  useEffect(() => {
    function handleOutsideClick(event: MouseEvent) {
      const target = event.target as Node;
      const clickedInsideInput = wrapperRef.current?.contains(target);
      const clickedInsideDropdown = dropdownRef.current?.contains(target);

      if (!clickedInsideInput && !clickedInsideDropdown) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);
    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
    };
  }, []);

  useEffect(() => {
    if (!open) return;

    function updatePosition() {
      const rect = inputRef.current?.getBoundingClientRect();
      if (!rect) return;

      const viewportHeight = window.innerHeight;
      const spaceBelow = viewportHeight - rect.bottom - 16;
      const spaceAbove = rect.top - 16;
      const shouldOpenUpward = spaceBelow < 240 && spaceAbove > spaceBelow;
      const maxHeight = Math.max(160, Math.min(220, shouldOpenUpward ? spaceAbove - 8 : spaceBelow - 8));

      setDropdownStyle({
        left: rect.left,
        width: rect.width,
        maxHeight,
        ...(shouldOpenUpward
          ? { bottom: viewportHeight - rect.top + 8 }
          : { top: rect.bottom + 8 }),
      });
    }

    updatePosition();
    window.addEventListener("resize", updatePosition);
    window.addEventListener("scroll", updatePosition, true);

    return () => {
      window.removeEventListener("resize", updatePosition);
      window.removeEventListener("scroll", updatePosition, true);
    };
  }, [open]);

  const filteredOptions = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) {
      return options;
    }

    return options.filter((option) =>
      option.name.toLowerCase().includes(normalizedQuery),
    );
  }, [options, query]);

  return (
    <div ref={wrapperRef} className={`relative ${open ? "z-50" : "z-20"}`}>
      <label className="mb-2 block text-sm font-semibold text-slate-700">
        {label}
      </label>
      <div className="relative">
        <input
          ref={inputRef}
          value={query}
          onChange={(event) => {
            setQuery(event.target.value);
            setOpen(true);
            if (event.target.value.trim() === "") {
              onChange("");
            }
          }}
          onFocus={() => setOpen(true)}
          className="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 pr-10 text-sm text-slate-700 outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
          placeholder={placeholder}
        />
        <button
          className="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100"
          onClick={() => setOpen((current) => !current)}
          type="button"
        >
          <ChevronDown
            size={16}
            className={`transition-transform duration-200 ${open ? "rotate-180" : ""}`}
          />
        </button>

        {open && dropdownStyle
          ? createPortal(
              <div
                ref={dropdownRef}
                className="fixed z-[120] max-h-[220px] overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-[0_20px_40px_rgba(15,23,42,0.12)]"
                style={{
                  top: dropdownStyle.top,
                  bottom: dropdownStyle.bottom,
                  left: dropdownStyle.left,
                  width: dropdownStyle.width,
                  maxHeight: dropdownStyle.maxHeight,
                }}
              >
            <button
              className={`flex w-full items-center rounded-lg px-3 py-2 text-left text-sm transition ${
                value === ""
                  ? "bg-[#EEF4FF] font-medium text-[#2563EB]"
                  : "text-slate-600 hover:bg-slate-50"
              }`}
              onClick={() => {
                setQuery("");
                onChange("");
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
                    option.name === value
                      ? "bg-[#EEF4FF] font-medium text-[#2563EB]"
                      : "text-slate-700 hover:bg-slate-50"
                  }`}
                  onClick={() => {
                    setQuery(option.name);
                    onChange(option.name, option);
                    setOpen(false);
                  }}
                  type="button"
                >
                  <span className="flex-1">{option.name}</span>
                  {!option.isActive ? (
                    <span className="ml-3 rounded-full bg-[#FEE2E2] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.12em] text-[#B91C1C]">
                      Nonaktif
                    </span>
                  ) : null}
                </button>
              ))
            ) : (
              <div className="px-3 py-2 text-sm text-slate-400">
                Tidak ada menu yang cocok.
              </div>
            )}
              </div>,
              document.body,
            )
          : null}
      </div>
    </div>
  );
}

export default function PackagesManagerPage() {
  const router = useRouter();
  const [packages, setPackages] = useState<PackageCard[]>([]);
  const [menuOptions, setMenuOptions] = useState<DishOption[]>([]);
  const [mealTimeOptions, setMealTimeOptions] = useState<MealTimeOption[]>([]);
  const [pendingInactiveSelection, setPendingInactiveSelection] = useState<{
    mealKey: MealKey;
    rowId: string;
    dish: DishOption;
  } | null>(null);
  const [menuSlots, setMenuSlots] = useState<
    Array<{
      id: number;
      menu_id: number;
      meal_time_id: number;
      dish_id: number;
      menu?: { id: number; name: string };
      meal_time?: { id: number; name: string | null };
      dish?: { id: number; name: string | null };
    }>
  >([]);
  const [modalMode, setModalMode] = useState<ModalMode>(null);
  const [selectedPackageId, setSelectedPackageId] = useState<number | null>(null);
  const [selectedPackage, setSelectedPackage] = useState<PackageCard | null>(null);
  const [mealValues, setMealValues] = useState<Record<MealKey, MealRowState[]>>(emptyMealValues);
  const [successState, setSuccessState] = useState<NoticeState>(null);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const menuNameToId = useMemo(
    () => new Map(menuOptions.map((option) => [option.name, option.id])),
    [menuOptions],
  );

  const mealTimeNameToId = useMemo(
    () =>
      new Map(
        mealTimeOptions.map((option) => [normalizeMealKey(option.name), option.id] as const).filter(
          (entry): entry is [MealKey, number] => entry[0] !== null,
        ),
      ),
    [mealTimeOptions],
  );

  async function loadPackages() {
    setLoading(true);
    try {
      const menusResponse = await listAllPaginatedRows<MenuRow>(sdk.menus.list.bind(sdk.menus), {
        sortBy: "id",
        sortDir: "ASC",
      });
      const menusData = menusResponse;
      if (menusData.length === 0) {
        setPackages(buildCsvPackageCards());
        return;
      }

      const packageLabels = menusData.map((_, index) => getCsvMenuPackageLabel(index));
      setPackages(buildPackageCards(menusData, [], packageLabels));
    } catch (loadError) {
      setError(getErrorMessage(loadError, "Gagal memuat data paket menu."));
    } finally {
      setLoading(false);
    }
  }

  async function loadMealTimes() {
    try {
      const mealTimesResponse = await sdk.mealTimes.list({
        paginate: false,
        sortBy: "id",
        sortDir: "ASC",
      });

      setMealTimeOptions(
        (mealTimesResponse.data ?? []).map((mealTime) => ({
          id: Number(mealTime.id),
          name: mealTime.name,
        })),
      );
    } catch (err) {
      console.error("Failed to load meal time metadata:", err);
    }
  }

  const loadSlots = useCallback(async () => {
    try {
      const slotsResponse = await sdk.menus.slots();
      const slotsData = slotsResponse.data ?? [];
      setMenuSlots(slotsData);

      const groupedSlots = new Map<number, Record<MealKey, string[]>>();
      for (const slot of slotsData) {
        const mealKey = normalizeMealKey(slot.meal_time?.name);
        if (!mealKey) continue;

        const current = groupedSlots.get(slot.menu_id) ?? {
          siang: [],
          sore: [],
          pagi: [],
        };
        const dishName = slot.dish?.name ?? null;
        if (dishName) {
          current[mealKey].push(dishName);
        }
        groupedSlots.set(slot.menu_id, current);
      }

      setPackages((current) =>
        current.map((pkg) => ({
          ...pkg,
          meals: groupedSlots.get(pkg.id) ?? { siang: [], sore: [], pagi: [] },
        })),
      );
    } catch (err) {
      console.error("Failed to load slots background:", err);
    }
  }, []);

  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    async function run() {
      await Promise.all([loadPackages(), loadMealTimes()]);
      if (!cancelled) {
        void loadSlots();
      }
    }
    void run();
    return () => {
      cancelled = true;
    };
  }, [loadSlots]);

  async function ensureMenuOptions() {
    if (menuOptions.length > 0) return;

    try {
      const dishesResponse = await listAllPaginatedRows(sdk.dishes.list.bind(sdk.dishes), {
        sortBy: "name",
        sortDir: "ASC",
      });

      setMenuOptions(
        dishesResponse
          .map((dish) => ({
            id: Number(dish.id),
            name: dish.name,
            isActive: (dish as { is_active?: boolean | null }).is_active !== false,
          }))
          .sort((left, right) => left.name.localeCompare(right.name)),
      );
    } catch (err) {
      console.error("Failed to load dish options:", err);
    }
  }

  function closeModal() {
    setModalMode(null);
    setSelectedPackage(null);
    setSelectedPackageId(null);
    setIsSubmitting(false);
  }

  async function openEditModal(item: PackageCard) {
    setSelectedPackage(item);
    setSelectedPackageId(item.id);
    const siangRows = getSlotStates(item.id, "siang");
    const soreRows = getSlotStates(item.id, "sore");
    const pagiRows = getSlotStates(item.id, "pagi");
    setMealValues({
      siang: siangRows.length > 0
        ? siangRows.map((slot) => createMealRowState(slot.id, slot.dishName ?? ""))
        : [createMealRowState()],
      sore: soreRows.length > 0
        ? soreRows.map((slot) => createMealRowState(slot.id, slot.dishName ?? ""))
        : [createMealRowState()],
      pagi: pagiRows.length > 0
        ? pagiRows.map((slot) => createMealRowState(slot.id, slot.dishName ?? ""))
        : [createMealRowState()],
    });
    setModalMode("edit");
    await ensureMenuOptions();
  }

  function confirmInactiveDishSelection() {
    if (!pendingInactiveSelection) return;

    const { mealKey, rowId, dish } = pendingInactiveSelection;
    setMealValues((current) => ({
      ...current,
      [mealKey]: current[mealKey].map((row) =>
        row.rowId === rowId ? { ...row, value: dish.name, deleted: false } : row,
      ),
    }));
    setPendingInactiveSelection(null);
  }

  function getSlotStates(menuId: number, mealKey: MealKey): SlotState[] {
    return menuSlots
      .filter((item) => item.menu_id === menuId && normalizeMealKey(item.meal_time?.name) === mealKey)
      .sort((left, right) => left.id - right.id)
      .map((slot) => ({
        id: slot?.id ?? null,
        mealTimeId: slot?.meal_time_id ?? null,
        dishId: slot?.dish_id ?? null,
        dishName: slot?.dish?.name ?? null,
      }));
  }

  function addMealRow(mealKey: MealKey) {
    setMealValues((current) => ({
      ...current,
      [mealKey]: [...current[mealKey], createMealRowState()],
    }));
  }

  function updateMealRow(mealKey: MealKey, rowId: string, nextValue: string) {
    setMealValues((current) => ({
      ...current,
      [mealKey]: current[mealKey].map((row) =>
        row.rowId === rowId ? { ...row, value: nextValue, deleted: false } : row,
      ),
    }));
  }

  function removeMealRow(mealKey: MealKey, rowId: string) {
    setMealValues((current) => {
      const nextValues = current[mealKey].map((row) =>
        row.rowId === rowId ? { ...row, deleted: true } : row,
      );
      const visibleRows = nextValues.filter((row) => !row.deleted);
      return {
        ...current,
        [mealKey]: visibleRows.length > 0 ? nextValues : [...nextValues, createMealRowState()],
      };
    });
  }

  async function savePackage() {
    if (!selectedPackageId) {
      setSuccessState({
        title: "Informasi",
        headline: "Pilih paket terlebih dahulu",
        message: "Backend saat ini hanya mendukung pengaturan paket yang sudah ada.",
      });
      return;
    }

    setIsSubmitting(true);
    setError(null);
    const previousPackages = packages;

    try {
      const requests: Promise<unknown>[] = [];

      for (const mealKey of mealOrder) {
        const mealRows = mealValues[mealKey];
        const activeRows = mealRows.filter((row) => !row.deleted);
        const existingSlots = getSlotStates(selectedPackageId, mealKey);
        const mealTimeId =
          existingSlots[0]?.mealTimeId ?? mealTimeNameToId.get(mealKey) ?? null;

        if (!mealTimeId) {
          continue;
        }

        const nextMealNames = activeRows.map((row) => row.value.trim()).filter((value) => value.length > 0);
        setPackages((current) =>
          current.map((item) => {
            if (item.id !== selectedPackageId) {
              return item;
            }

            return {
              ...item,
              meals: {
                ...item.meals,
                [mealKey]: nextMealNames,
              },
            };
          }),
        );

        const existingSlotsById = new Map(existingSlots.map((slot) => [slot.id, slot]));

        for (const row of mealRows) {
          const selectedDishName = row.value.trim();
          const selectedDishId = selectedDishName.length > 0 ? menuNameToId.get(selectedDishName) ?? null : null;

          if (row.deleted) {
            if (row.slotId) {
              requests.push(sdk.menus.deleteSlot(row.slotId));
            }
            continue;
          }

          if (!selectedDishId) {
            if (row.slotId) {
              requests.push(sdk.menus.deleteSlot(row.slotId));
            }
            continue;
          }

          if (!row.slotId) {
            requests.push(
              sdk.menus.assignSlot({
                menu_id: selectedPackageId,
                meal_time_id: mealTimeId,
                dish_id: selectedDishId,
              }),
            );
            continue;
          }

          const existingSlot = existingSlotsById.get(row.slotId) ?? null;
          if (existingSlot?.dishId !== selectedDishId) {
            requests.push(
              sdk.menus.updateSlot(row.slotId, {
                dish_id: selectedDishId,
              }),
            );
          }
        }
      }

      if (requests.length === 0) {
        setSuccessState({
          title: "Informasi",
          headline: "Belum ada perubahan",
          message: "Pilih perubahan paket menu yang ingin disimpan terlebih dahulu.",
        });
        closeModal();
        return;
      }

      await Promise.all(requests);

      await loadPackages();
      await loadSlots();
      router.refresh();

      setSuccessState({
        title: "Berhasil",
        headline:
          "Paket Menu Berhasil Diperbarui",
        message:
          `Slot paket pada ${selectedPackage?.title ?? "paket menu"} berhasil diperbarui dari backend.`,
      });
      closeModal();
    } catch (saveError) {
      setPackages(previousPackages);
      setError(getErrorMessage(saveError, "Gagal menyimpan paket menu."));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <>
      <div className="space-y-5">
      <AdminPageHeading
        title="Paket Menu"
        subtitle="Klik tanggal untuk melihat jadwal menu harian"
      />

        {error ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
            {error}
          </div>
        ) : null}

        {loading ? (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {Array.from({ length: 4 }, (_, index) => (
              <div
                key={index}
                className="h-[220px] animate-pulse rounded-[22px] border-2 border-[#D6E3FA] bg-[#F8FBFF]"
              />
            ))}
          </div>
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {packages.map((item) => (
              <SurfaceCard
                key={item.id}
                className={`overflow-hidden p-0 transition-all duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_18px_36px_rgba(15,23,42,0.09)] ${
                  item.active ? "border-[#3B82F6] shadow-[0_0_0_1px_rgba(59,130,246,0.2)]" : ""
                }`}
              >
                <div className="flex items-center justify-between border-b bg-[#F8FAFC] px-5 py-4">
                  <div className="flex items-center gap-2">
                    <h3 className="text-base font-semibold text-[#16213E]">{item.title}</h3>
                    {item.active ? (
                      <span className="rounded-full bg-[#2155CD] px-2 py-0.5 text-[9px] font-bold text-white">
                        AKTIF
                      </span>
                    ) : null}
                  </div>
                  <div className="flex gap-2">
                    <MiniActionButton onClick={() => openEditModal(item)}>Edit</MiniActionButton>
                  </div>
                </div>
                <div className="space-y-2 p-5">
                  {mealOrder.map((mealKey) => (
                    <div
                      key={`${item.id}-${mealKey}`}
                      className={`rounded-[8px] px-3 py-2 ${mealTone[mealKey]}`}
                    >
                      <p className="text-[10px] font-bold">{mealLabel[mealKey]}</p>
                      <p className="mt-1 text-sm font-medium text-[#16213E]">
                        {item.meals[mealKey].length > 0
                          ? item.meals[mealKey].join(", ")
                          : menuSlots.length === 0
                            ? "Memuat..."
                            : "Belum diatur"}
                      </p>
                    </div>
                  ))}
                </div>
              </SurfaceCard>
            ))}
          </div>
        )}
      </div>

      {modalMode === "edit" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
          <div
            className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]"
            onClick={closeModal}
          />

          <div className="animate-modal-enter relative flex max-h-[calc(100vh-3rem)] w-full max-w-[620px] flex-col overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)] transition-transform duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_28px_70px_rgba(15,23,42,0.22)]">
            <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
              <div>
                <h2 className="text-[22px] font-semibold leading-none text-slate-900">
                  Edit Paket Menu
                </h2>
              </div>

              <button
                className="flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-slate-400 transition-all duration-300 ease-out hover:scale-105 hover:bg-slate-200 hover:text-slate-500"
                onClick={closeModal}
                type="button"
              >
                <X size={18} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-5">
              <div className="space-y-5">
                <div>
                  <label className="block text-sm font-semibold text-slate-700">
                    Nama Paket
                  </label>
                  <input
                    value={selectedPackage?.title ?? ""}
                    readOnly
                    className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-500 outline-none"
                  />
                </div>

                <div className="rounded-[14px] border border-[#D9E3F2]">
                  <div className="border-b border-[#D9E3F2] bg-[#EDF4FF] px-4 py-3">
                    <h3 className="text-base font-semibold text-[#475569]">
                      KOMPOSISI MENU PER SESI
                    </h3>
                  </div>

                  <div className="space-y-4 p-4">
                    {mealOrder.map((mealKey) => {
                      const slotStates = selectedPackageId ? getSlotStates(selectedPackageId, mealKey) : [];
                      const visibleRows = mealValues[mealKey].filter((row) => !row.deleted);

                      return (
                        <div key={mealKey} className="rounded-[12px] border border-[#E2EAF5] bg-[#FBFDFF] p-4">
                          <div className="mb-3 flex items-center justify-between">
                            <h4 className="text-sm font-semibold text-slate-700">
                              {mealLabel[mealKey]}
                            </h4>
                            <button
                              className="rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50"
                              onClick={() => addMealRow(mealKey)}
                              type="button"
                            >
                              + Tambah Menu
                            </button>
                          </div>

                          <div className="space-y-3">
                            {visibleRows.map((row, rowIndex) => {
                              const slotState = slotStates[rowIndex] ?? null;

                              return (
                                <div key={row.rowId} className="flex items-start gap-3">
                                  <div className="min-w-0 flex-1">
                                    <SearchableMealSelect
                                      label={getMealRowLabel(rowIndex)}
                                      options={menuOptions}
                                      placeholder={row.value || slotState?.dishName || `Cari menu ${mealLabel[mealKey].toLowerCase()}`}
                                      value={row.value}
                                      onChange={(nextValue, option) => {
                                        if (option && !option.isActive) {
                                          setPendingInactiveSelection({ mealKey, rowId: row.rowId, dish: option });
                                          return;
                                        }

                                        updateMealRow(mealKey, row.rowId, nextValue);
                                      }}
                                    />
                                  </div>

                                  <button
                                    className="mt-7 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100"
                                    onClick={() => removeMealRow(mealKey, row.rowId)}
                                    type="button"
                                  >
                                    Hapus
                                  </button>
                                </div>
                              );
                            })}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
              <button
                className="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-50"
                onClick={closeModal}
                type="button"
              >
                Batal
              </button>
              <button
                className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-[0_14px_30px_rgba(37,99,235,0.28)] disabled:cursor-not-allowed disabled:bg-blue-300 disabled:shadow-none"
                disabled={isSubmitting}
                onClick={savePackage}
                type="button"
              >
                {isSubmitting ? "Menyimpan..." : "Simpan"}
              </button>
            </div>
          </div>
        </div>
      )}

      <SuccessModal
        open={successState !== null}
        title={successState?.title ?? "Informasi"}
        headline={successState?.headline ?? ""}
        message={successState?.message ?? ""}
        onClose={() => setSuccessState(null)}
      />

      <DeleteConfirmModal
        open={pendingInactiveSelection !== null}
        title="Menu Nonaktif"
        headline="Menu ini sedang nonaktif"
        description={
          pendingInactiveSelection
            ? `${pendingInactiveSelection.dish.name} sedang berstatus nonaktif. Tetap gunakan menu ini pada paket?`
            : "Menu ini sedang berstatus nonaktif. Tetap gunakan menu ini pada paket?"
        }
        confirmLabel="Tetap Gunakan"
        cancelLabel="Batal"
        onClose={() => setPendingInactiveSelection(null)}
        onConfirm={confirmInactiveDishSelection}
      />
    </>
  );
}
