"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { ChevronDown, ChevronLeft, ChevronRight, Plus, Search, Trash2, X } from "lucide-react";
import sdk from "@/lib";
import { getErrorMessage } from "@/lib/admin-utils";
import { listAllItems } from "@/lib/items";
import { listAllPaginatedRows } from "@/lib/pagination";
import DeleteConfirmModal from "@/components/feedback/DeleteConfirmModal";
import SuccessModal from "@/components/feedback/SuccessModal";
import {
  AdminPageHeading,
  MiniActionButton,
  PrimaryAction,
  SurfaceCard,
} from "@/components/admin/ui";

type IngredientRow = {
  localId: number;
  compositionId?: number;
  item_id: number | null;
  qty_per_patient: string;
  unit_convert?: string;
  unit?: string;
};

type FoodMenu = {
  id: number;
  name: string;
  description: string;
  compositionSummary: string;
  ingredients: IngredientRow[];
  isActive: boolean;
};

type LinkedPackageInfo = {
  slotId: number;
  packageName: string;
};

type ModalMode = "create" | "detail" | "edit" | "delete" | null;

type SuccessState = {
  headline: string;
  message: string;
} | null;

type ItemRecord = {
  id: number;
  name: string;
  unit_base: string;
  unit_convert: string;
};

const MENU_DESCRIPTION_STORAGE_KEY = "menu-manual-descriptions";

type SearchableIngredientSelectProps = {
  options: Array<{ id: number; label: string; unit: string }>;
  value: number | null;
  placeholder: string;
  onChange: (itemId: number | null, unit?: string) => void;
};

function SearchableIngredientSelect({
  options,
  value,
  placeholder,
  onChange,
}: Readonly<SearchableIngredientSelectProps>) {
  const wrapperRef = useRef<HTMLDivElement | null>(null);
  const selectedOption = options.find((option) => option.id === value) ?? null;
  const [query, setQuery] = useState(selectedOption?.label ?? "");
  const [open, setOpen] = useState(false);

  useEffect(() => {
    setQuery(selectedOption?.label ?? "");
  }, [selectedOption]);

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
    if (!normalizedQuery) {
      return options;
    }

    return options.filter((option) => option.label.toLowerCase().includes(normalizedQuery));
  }, [options, query]);

  return (
    <div ref={wrapperRef} className={`relative ${open ? "z-50" : "z-20"}`}>
      <div className="relative">
        <input
          value={query}
          onChange={(event) => {
            const nextValue = event.target.value;
            setQuery(nextValue);
            setOpen(true);
            if (!nextValue.trim()) {
              onChange(null);
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
          <ChevronDown size={16} className={`transition-transform duration-200 ${open ? "rotate-180" : ""}`} />
        </button>
      </div>

      {open ? (
        <div className="absolute left-0 right-0 top-[calc(100%+8px)] z-30 max-h-[220px] overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-[0_20px_40px_rgba(15,23,42,0.12)]">
          <button
            className={`flex w-full items-center rounded-lg px-3 py-2 text-left text-sm transition ${value === null ? "bg-[#EEF4FF] font-medium text-[#2563EB]" : "text-slate-600 hover:bg-slate-50"
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
                className={`mt-1 flex w-full items-center rounded-lg px-3 py-2 text-left text-sm transition ${option.id === value
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

async function loadAvailableItems() {
  const itemsResponse = await listAllItems({
    sortBy: "name",
    sortDir: "ASC",
    is_active: true,
  });

  return itemsResponse.map((item) => ({
    id: Number(item.id),
    name: item.name,
    unit_base: item.unit_base,
    unit_convert: item.unit_convert ?? item.unit_base,
  })) satisfies ItemRecord[];
}

function buildCompositionSummary(
  ingredients: IngredientRow[],
  itemMap: Map<number, { name: string }>,
) {
  const names = ingredients
    .map((row) => (row.item_id ? itemMap.get(Number(row.item_id))?.name : undefined))
    .filter((name): name is string => Boolean(name));

  if (names.length === 0) {
    return "Belum ada komposisi bahan.";
  }

  if (names.length <= 3) {
    return names.join(", ");
  }

  return `${names.slice(0, 3).join(", ")} +${names.length - 3} lagi`;
}

export default function AdminMenuManagementPage() {
  const router = useRouter();
  const [menus, setMenus] = useState<FoodMenu[]>([]);
  const [items, setItems] = useState<ItemRecord[]>([]);
  const [searchTerm, setSearchTerm] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [modalMode, setModalMode] = useState<ModalMode>(null);
  const [selectedMenu, setSelectedMenu] = useState<FoodMenu | null>(null);
  const [menuName, setMenuName] = useState("");
  const [menuDescription, setMenuDescription] = useState("");
  const [ingredients, setIngredients] = useState<IngredientRow[]>([
    { localId: 1, item_id: null, qty_per_patient: "0" },
  ]);
  const [successState, setSuccessState] = useState<SuccessState>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [linkedPackages, setLinkedPackages] = useState<LinkedPackageInfo[]>([]);
  const [loadingLinkedPackages, setLoadingLinkedPackages] = useState(false);

  function sortMenusByStatusAndName(nextMenus: FoodMenu[]) {
    return [...nextMenus].sort((left, right) => {
      const activeDiff = Number(right.isActive) - Number(left.isActive);
      if (activeDiff !== 0) return activeDiff;
      return left.name.localeCompare(right.name, "id-ID", { sensitivity: "base" });
    });
  }

  function readMenuDescriptions() {
    if (typeof window === "undefined") {
      return {} as Record<string, string>;
    }

    try {
      const raw = window.localStorage.getItem(MENU_DESCRIPTION_STORAGE_KEY);
      if (!raw) return {};
      const parsed = JSON.parse(raw) as Record<string, string>;
      return parsed && typeof parsed === "object" ? parsed : {};
    } catch {
      return {};
    }
  }

  function writeMenuDescription(dishId: number, description: string) {
    if (typeof window === "undefined") return;
    const next = { ...readMenuDescriptions(), [String(dishId)]: description.trim() };
    window.localStorage.setItem(MENU_DESCRIPTION_STORAGE_KEY, JSON.stringify(next));
  }

  function removeMenuDescription(dishId: number) {
    if (typeof window === "undefined") return;
    const next = { ...readMenuDescriptions() };
    delete next[String(dishId)];
    window.localStorage.setItem(MENU_DESCRIPTION_STORAGE_KEY, JSON.stringify(next));
  }

  const loadMenus = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const [dishesResponse, allCompositions, availableItems] = await Promise.all([
        listAllPaginatedRows(sdk.dishes.list.bind(sdk.dishes), {
          sortBy: "name",
          sortDir: "ASC",
        }),
        listAllPaginatedRows(sdk.dishCompositions.list.bind(sdk.dishCompositions), {
          sortBy: "id",
          sortDir: "ASC",
        }),
        loadAvailableItems(),
      ]);

      const itemMap = new Map(availableItems.map((item) => [Number(item.id), item]));
      const compositionMap = new Map<number, IngredientRow[]>();

      for (const composition of allCompositions) {
        const dishId = Number(composition.dish_id);
        const itemId = Number(composition.item_id);
        const group = compositionMap.get(dishId) ?? [];
        const compositionItem = composition.item as
          | { unit_convert?: string; unit_base?: string }
          | null
          | undefined;
        const relatedItem = itemMap.get(itemId);

        group.push({
          localId: composition.id,
          compositionId: composition.id,
          item_id: itemId,
          qty_per_patient: composition.qty_per_patient,
          unit_convert:
            relatedItem?.unit_convert ??
            compositionItem?.unit_convert ??
            relatedItem?.unit_base ??
            compositionItem?.unit_base ??
            undefined,
          unit:
            relatedItem?.unit_convert ??
            compositionItem?.unit_convert ??
            relatedItem?.unit_base ??
            compositionItem?.unit_base ??
            undefined,
        });
        compositionMap.set(dishId, group);
      }

      const descriptionMap = readMenuDescriptions();
      const nextMenus = dishesResponse.map((dish) => {
        const dishId = Number(dish.id);
        const isActive = (dish as { is_active?: boolean | null }).is_active !== false;
        const menuIngredients = compositionMap.get(dishId) ?? [];

        return {
          id: dishId,
          name: dish.name,
          description: descriptionMap[String(dishId)] ?? "",
          compositionSummary: buildCompositionSummary(menuIngredients, itemMap),
          ingredients: menuIngredients,
          isActive,
        };
      });

      setItems(availableItems);
      setMenus(sortMenusByStatusAndName(nextMenus));
    } catch (loadError) {
      setError(getErrorMessage(loadError, "Gagal memuat menu makanan."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void loadMenus();
  }, [loadMenus]);

  const itemOptions = useMemo(
    () =>
      items.map((item) => ({
        id: item.id,
        label: item.name,
        unit: item.unit_convert,
      })),
    [items],
  );
  const itemMap = useMemo(() => new Map(items.map((item) => [Number(item.id), item])), [items]);

  const filteredMenus = useMemo(() => {
    const query = searchTerm.trim().toLowerCase();
    const filtered = !query ? menus : menus.filter((menu) => menu.name.toLowerCase().includes(query));
    return sortMenusByStatusAndName(filtered);
  }, [menus, searchTerm]);

  const totalPages = Math.max(1, Math.ceil(filteredMenus.length / 8));
  const paginatedMenus = useMemo(() => {
    const start = (currentPage - 1) * 8;
    return filteredMenus.slice(start, start + 8);
  }, [currentPage, filteredMenus]);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  async function fetchCompositionsForDish(dishId: number) {
    try {
      const existingMenu = menus.find((menu) => menu.id === dishId);
      if (existingMenu?.ingredients?.length) {
        return existingMenu.ingredients;
      }

      const compositions = await listAllPaginatedRows(sdk.dishCompositions.list.bind(sdk.dishCompositions), {
        dish_id: dishId,
        sortBy: "id",
        sortDir: "ASC",
      });
      const itemMap = new Map(items.map((item) => [Number(item.id), item]));

      const nextIngredients: IngredientRow[] = compositions.map((comp) => {
        const compositionItem = comp.item as
          | { unit_convert?: string; unit_base?: string }
          | null
          | undefined;
        const relatedItem = itemMap.get(Number(comp.item_id));

        return {
          localId: comp.id,
          compositionId: comp.id,
          item_id: comp.item_id,
          qty_per_patient: comp.qty_per_patient,
          // Backend item payload can include unit_convert even when the generated type omits it.
          // Use a narrow local cast so the UI can prefer the smallest unit without changing API shape.
          unit_convert:
            relatedItem?.unit_convert ??
            compositionItem?.unit_convert ??
            relatedItem?.unit_base ??
            compositionItem?.unit_base ??
            undefined,
          unit:
            relatedItem?.unit_convert ??
            compositionItem?.unit_convert ??
            relatedItem?.unit_base ??
            compositionItem?.unit_base ??
            undefined,
        };
      });

      setMenus((current) =>
        current.map((menu) =>
          menu.id === dishId
            ? {
                ...menu,
                ingredients: nextIngredients,
                compositionSummary: buildCompositionSummary(nextIngredients, itemMap),
              }
            : menu,
        ),
      );

      return nextIngredients;
    } catch {
      return [];
    }
  }

  function openCreateModal() {
    setModalMode("create");
    setSelectedMenu(null);
    setMenuName("");
    setMenuDescription("");
    setIngredients([{ localId: Date.now(), item_id: null, qty_per_patient: "0" }]);
  }

  async function openDetailModal(menu: FoodMenu) {
    setLoading(true);
    const dishIngredients = menu.ingredients.length > 0 ? menu.ingredients : await fetchCompositionsForDish(menu.id);
    setSelectedMenu({ ...menu, ingredients: dishIngredients });
    setModalMode("detail");
    setLoading(false);
  }

  async function openEditModal(menu: FoodMenu) {
    setLoading(true);
    const dishIngredients = menu.ingredients.length > 0 ? menu.ingredients : await fetchCompositionsForDish(menu.id);
    setSelectedMenu({ ...menu, ingredients: dishIngredients });
    setMenuName(menu.name);
    setMenuDescription(menu.description);
    setIngredients(
      dishIngredients.length > 0
        ? dishIngredients.map((item, index) => ({ ...item, localId: item.localId || index + 1 }))
        : [{ localId: Date.now(), item_id: null, qty_per_patient: "0" }],
    );
    setModalMode("edit");
    setLoading(false);
  }

  async function openDeleteModal(menu: FoodMenu) {
    setSelectedMenu(menu);
    setLinkedPackages([]);
    setLoadingLinkedPackages(true);
    try {
      const slotsResponse = await sdk.menus.slots();
      const relatedSlots = (slotsResponse.data ?? []).filter((slot) => Number(slot.dish_id) === Number(menu.id));
      const nextLinkedPackages = relatedSlots
        .map((slot) => ({
          slotId: Number(slot.id),
          packageName: slot.menu?.name?.trim() ?? "",
        }))
        .filter((entry) => Number.isFinite(entry.slotId) && entry.slotId > 0 && entry.packageName)
        .sort((left, right) => left.packageName.localeCompare(right.packageName, "id-ID", { sensitivity: "base" }));
      setLinkedPackages(nextLinkedPackages);
    } catch {
      setLinkedPackages([]);
    } finally {
      setLoadingLinkedPackages(false);
      setModalMode("delete");
    }
  }

  function closeModal() {
    if (saving) return;
    setModalMode(null);
    setSelectedMenu(null);
  }

  function addIngredientRow() {
    setIngredients((current) => [
      ...current,
      { localId: Date.now(), item_id: null, qty_per_patient: "0" },
    ]);
  }

  function removeIngredientRow(localId: number) {
    setIngredients((current) =>
      current.length === 1 ? current : current.filter((row) => row.localId !== localId),
    );
  }

  async function saveMenu() {
    setSaving(true);
    setError(null);
    const previousMenus = menus;

    try {
      const selectedRows = ingredients.filter((row) => row.item_id !== null);
      if (selectedRows.some((row) => Number(row.qty_per_patient) <= 0)) {
        throw new Error("Qty bahan tidak boleh 0. Silakan ubah qty bahan terlebih dahulu.");
      }

      const validRows = selectedRows.filter((row) => Number(row.qty_per_patient) > 0);

      if (menuName.trim() === "") throw new Error("Nama menu wajib diisi.");
      if (validRows.length === 0) throw new Error("Minimal satu komposisi bahan harus diisi.");

      if (modalMode === "create") {
        setMenus((current) => [
          ...current,
          {
            id: Date.now(),
            name: menuName.trim(),
            description: menuDescription.trim(),
            compositionSummary: buildCompositionSummary(validRows, itemMap),
            ingredients: validRows,
            isActive: true,
          },
        ]);
        const createdDish = await sdk.dishes.create({ name: menuName.trim() });
        const dishId = createdDish.data.id;
        writeMenuDescription(dishId, menuDescription);

        await Promise.all(
          validRows.map((row) =>
            sdk.dishCompositions.create({
              dish_id: dishId,
              item_id: row.item_id as number,
              qty_per_patient: row.qty_per_patient,
            }),
          ),
        );

        setSuccessState({
          headline: "Menu Makanan Berhasil Ditambahkan",
          message: `Menu ${menuName.trim()} berhasil ditambahkan.`,
        });
      }

      if (modalMode === "edit" && selectedMenu) {
        setMenus((current) =>
          sortMenusByStatusAndName(
            current.map((menu) =>
              menu.id === selectedMenu.id
                ? {
                    ...menu,
                    name: menuName.trim(),
                    description: menuDescription.trim(),
                    compositionSummary: buildCompositionSummary(validRows, itemMap),
                    ingredients: validRows,
                  }
                : menu,
            ),
          ),
        );
        await sdk.dishes.update(selectedMenu.id, { name: menuName.trim() });
        writeMenuDescription(selectedMenu.id, menuDescription);

        const existing = menus.find((menu) => menu.id === selectedMenu.id)?.ingredients ?? [];
        const existingIds = new Set(existing.map((row) => row.compositionId).filter(Boolean) as number[]);
        const nextIds = new Set(validRows.map((row) => row.compositionId).filter(Boolean) as number[]);

        const toDelete = [...existingIds].filter((id) => !nextIds.has(id));
        await Promise.all(toDelete.map((id) => sdk.dishCompositions.delete(id)));

        for (const row of validRows) {
          if (row.compositionId) {
            await sdk.dishCompositions.update(row.compositionId, {
              dish_id: selectedMenu.id,
              item_id: row.item_id as number,
              qty_per_patient: row.qty_per_patient,
            });
          } else {
            await sdk.dishCompositions.create({
              dish_id: selectedMenu.id,
              item_id: row.item_id as number,
              qty_per_patient: row.qty_per_patient,
            });
          }
        }

        setSuccessState({
          headline: "Menu Makanan Berhasil Diedit",
          message: `Menu ${menuName.trim()} berhasil diperbarui.`,
        });
      }

      await loadMenus();
      router.refresh();
      closeModal();
    } catch (saveError) {
      setMenus(previousMenus);
      setError(getErrorMessage(saveError, "Gagal menyimpan menu makanan."));
    } finally {
      setSaving(false);
    }
  }

  async function toggleMenuActive() {
    if (!selectedMenu) return;

    setSaving(true);
    setError(null);
    const previousMenus = menus;
    try {
      const nextIsActive = !selectedMenu.isActive;
      const linkedSlots = linkedPackages;

      if (nextIsActive) {
        await sdk.client.request({
          method: "PATCH",
          path: `/dishes/${selectedMenu.id}/reactivate`,
        });
      } else {
        await sdk.client.request({
          method: "PATCH",
          path: `/dishes/${selectedMenu.id}/deactivate`,
        });
      }

      setSuccessState({
        headline: nextIsActive ? "Menu Makanan Berhasil Diaktifkan" : "Menu Makanan Berhasil Dinonaktifkan",
        message: nextIsActive
          ? `Menu ${selectedMenu.name} kembali aktif di daftar menu.`
          : linkedSlots.length > 0
            ? `Menu ${selectedMenu.name} dinonaktifkan dan ${linkedSlots.length} slot paket yang memakai menu ini ikut dinonaktifkan.`
            : `Menu ${selectedMenu.name} telah dinonaktifkan dari daftar menu.`,
      });
      await loadMenus();
      router.refresh();
      closeModal();
    } catch (deleteError) {
      setMenus(previousMenus);
      setError(getErrorMessage(deleteError, "Gagal memperbarui status menu makanan."));
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <div className="space-y-5">
        <AdminPageHeading
          title="Menu Makanan"
          subtitle="Kelola daftar menu makanan"
          action={<PrimaryAction onClick={openCreateModal}>Tambah Menu Makanan</PrimaryAction>}
        />

        {error ? (
          <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
            {error}
          </div>
        ) : null}

        <SurfaceCard className="overflow-hidden">
          <div className="flex flex-wrap items-center gap-4 border-b bg-[#F8FAFC] px-5 py-4">
            <label className="relative w-full lg:w-[420px]">
              <Search className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[#94A3B8]" size={18} />
              <input
                value={searchTerm}
                onChange={(event) => setSearchTerm(event.target.value)}
                className="h-12 w-full rounded-xl border-2 border-[#D8E3F8] bg-white pl-11 pr-4 text-[15px] text-slate-700 outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
                placeholder="Cari nama menu"
              />
            </label>
            <span className="ml-auto text-sm font-medium text-[#94A3B8]">{filteredMenus.length} menu</span>
          </div>

          <div className="bg-white px-5 py-5">
            {loading ? (
              <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {Array.from({ length: 8 }, (_, index) => (
                  <div
                    key={index}
                    className="h-[220px] animate-pulse rounded-[22px] border-2 border-[#D6E3FA] bg-[#F8FBFF]"
                  />
                ))}
              </div>
            ) : paginatedMenus.length > 0 ? (
              <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                {paginatedMenus.map((menu) => (
                  <article
                    key={menu.id}
                    className="flex min-h-[320px] cursor-pointer flex-col rounded-[22px] border-2 border-[#C7D8F8] bg-white p-5 shadow-[0_14px_36px_rgba(37,99,235,0.08)] transition duration-200 hover:-translate-y-1 hover:border-[#93B4F7] hover:shadow-[0_18px_42px_rgba(37,99,235,0.14)]"
                    onClick={(event) => {
                      if ((event.target as HTMLElement).closest("button")) return;
                      openDetailModal(menu);
                    }}
                  >
                    <div className="space-y-4">
                      <div className="flex h-[132px] items-center justify-center rounded-[18px] border-2 border-dashed border-[#C7D8F8] bg-[linear-gradient(135deg,#F8FBFF_0%,#EEF4FF_100%)] px-4 text-center">
                        <div className="space-y-2">
                          <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white text-[11px] font-bold uppercase tracking-[0.18em] text-[#2563EB] shadow-[0_8px_18px_rgba(37,99,235,0.12)]">
                            Img
                          </div>
                          <p className="text-[13px] font-medium text-slate-500">Area gambar menu</p>
                        </div>
                      </div>

                      <div className="space-y-2">
                        <span className="inline-flex rounded-full bg-[#EEF4FF] px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-[#2563EB]">
                          Menu
                        </span>
                        <span className={`inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] ${menu.isActive ? "bg-[#ECFDF3] text-[#16A34A]" : "bg-[#F1F5F9] text-[#64748B]"}`}>
                          {menu.isActive ? "Aktif" : "Nonaktif"}
                        </span>
                        <h3 className="line-clamp-2 text-xl font-semibold leading-tight text-[#16213E]">
                          {menu.name}
                        </h3>
                        <p className="min-h-[48px] text-sm leading-6 text-slate-500">
                          {menu.compositionSummary}
                        </p>
                      </div>
                    </div>

                    <div className="mt-auto flex flex-wrap items-center gap-3 pt-5 pr-2">
                      <MiniActionButton
                        className="min-w-[82px] justify-center"
                        onClick={(event) => {
                          event.stopPropagation();
                          openEditModal(menu);
                        }}
                      >
                        Edit
                      </MiniActionButton>
                      <MiniActionButton
                        className="min-w-[120px] justify-center"
                        onClick={(event) => {
                          event.stopPropagation();
                          void openDeleteModal(menu);
                        }}
                      >
                        {menu.isActive ? "Nonaktifkan" : "Aktifkan"}
                      </MiniActionButton>
                    </div>
                  </article>
                ))}
              </div>
            ) : (
              <div className="rounded-[22px] border-2 border-dashed border-[#D6E3FA] bg-[#F8FBFF] px-6 py-12 text-center text-sm text-slate-400">
                Belum ada menu makanan.
              </div>
            )}
          </div>

          <div className="flex items-center justify-between border-t bg-[#F8FAFC] px-5 py-4 text-sm text-[#94A3B8]">
            <span>
              {filteredMenus.length === 0
                ? "0 menu"
                : `${(currentPage - 1) * 8 + 1}-${Math.min(currentPage * 8, filteredMenus.length)} dari ${filteredMenus.length} menu`}
            </span>
            <div className="flex items-center gap-2">
              <button
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-[#D8E3F8] bg-white text-[#94A3B8] transition hover:border-[#2563EB] hover:text-[#2563EB] disabled:cursor-not-allowed disabled:opacity-50"
                disabled={currentPage === 1}
                onClick={() => setCurrentPage((current) => Math.max(1, current - 1))}
                type="button"
              >
                <ChevronLeft size={16} />
              </button>
              {Array.from({ length: totalPages }, (_, index) => index + 1).map((page) => (
                <button
                  key={page}
                  className={`flex h-9 min-w-9 items-center justify-center rounded-xl px-3 text-sm font-semibold transition ${
                    currentPage === page
                      ? "bg-[#2563EB] text-white shadow-[0_10px_24px_rgba(37,99,235,0.22)]"
                      : "border border-[#D8E3F8] bg-white text-[#94A3B8] hover:border-[#2563EB] hover:text-[#2563EB]"
                  }`}
                  onClick={() => setCurrentPage(page)}
                  type="button"
                >
                  {page}
                </button>
              ))}
              <button
                className="flex h-9 w-9 items-center justify-center rounded-xl border border-[#D8E3F8] bg-white text-[#94A3B8] transition hover:border-[#2563EB] hover:text-[#2563EB] disabled:cursor-not-allowed disabled:opacity-50"
                disabled={currentPage === totalPages}
                onClick={() => setCurrentPage((current) => Math.min(totalPages, current + 1))}
                type="button"
              >
                <ChevronRight size={16} />
              </button>
            </div>
          </div>
        </SurfaceCard>
      </div>

      {modalMode && modalMode !== "delete" ? (
        <div className="fixed inset-0 z-[90] flex items-center justify-center bg-[rgba(15,23,42,0.28)] px-4 py-6 backdrop-blur-sm">
          <div className="flex max-h-[92vh] w-full max-w-[780px] flex-col overflow-hidden rounded-[28px] bg-white shadow-[0_32px_90px_rgba(15,23,42,0.28)]">
            <div className="flex items-start justify-between border-b border-[#E8EEF8] px-6 py-5">
              <div>
                <h2 className="text-[20px] font-semibold text-[#16213E]">
                  {modalMode === "create"
                    ? "Tambah Menu Makanan"
                    : modalMode === "edit"
                      ? "Edit Menu Makanan"
                      : selectedMenu?.name}
                </h2>
                <p className="mt-1 text-sm text-[#94A3B8]">
                  {modalMode === "detail"
                    ? "Detail menu dan komposisi bahan"
                    : "Ubah nama, deskripsi, dan komposisi bahan"}
                </p>
              </div>
              <button
                className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#EFF4FB] text-[#7A869A] transition hover:bg-[#E2EAF7] hover:text-[#16213E]"
                onClick={closeModal}
                type="button"
              >
                <X size={22} />
              </button>
            </div>

            {modalMode !== "detail" && error ? (
              <div className="mx-6 mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
                {error}
              </div>
            ) : null}

            <div className="space-y-5 overflow-y-auto px-6 py-6">
              <div className="space-y-2">
                <label className="text-sm font-semibold text-[#334155]">Nama Menu</label>
                {modalMode === "detail" ? (
                  <div className="rounded-2xl border border-[#D8E3F8] bg-[#F8FBFF] px-4 py-3 text-sm text-[#16213E]">
                    {selectedMenu?.name}
                  </div>
                ) : (
                  <input
                    value={menuName}
                    onChange={(event) => setMenuName(event.target.value)}
                    className="h-14 w-full rounded-2xl border border-[#D8E3F8] bg-white px-4 text-[15px] text-[#16213E] outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
                    placeholder="Masukkan nama menu"
                  />
                )}
              </div>

              <div className="space-y-2">
                <label className="text-sm font-semibold text-[#334155]">Deskripsi</label>
                {modalMode === "detail" ? (
                  <div className="rounded-2xl border border-[#D8E3F8] bg-[#F8FBFF] px-4 py-3 text-sm text-[#475569]">
                    {selectedMenu?.description || "Belum ada deskripsi menu."}
                  </div>
                ) : (
                  <textarea
                    value={menuDescription}
                    onChange={(event) => setMenuDescription(event.target.value)}
                    className="min-h-[92px] w-full rounded-2xl border border-[#D8E3F8] bg-white px-4 py-3 text-[15px] text-[#16213E] outline-none transition focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
                    placeholder="Masukkan deskripsi menu"
                  />
                )}
              </div>

              <div className="rounded-[20px] border border-[#D8E3F8] bg-[#F2F7FF] p-5">
                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#2155CD]">Ringkasan Komposisi</p>
                <p className="mt-2 text-sm text-[#475569]">
                  {modalMode === "detail"
                    ? selectedMenu?.compositionSummary || "Belum ada komposisi bahan."
                    : buildCompositionSummary(ingredients, itemMap)}
                </p>
              </div>

              <div className="overflow-visible rounded-[22px] border border-[#D8E3F8] bg-[#F8FBFF]">
                <div className="border-b border-[#D8E3F8] bg-[#EDF5FF] px-5 py-4">
                  <h3 className="text-[18px] font-semibold text-[#475569]">Komposisi Bahan</h3>
                  <p className="mt-1 text-sm text-[#94A3B8]">Pilih bahan dari stok yang tersedia</p>
                </div>

                <div className="space-y-4 p-5 overflow-visible">
                  {(modalMode === "detail" ? selectedMenu?.ingredients ?? [] : ingredients).map((row) => (
                    <div
                      key={row.localId}
                      className="relative z-10 rounded-[18px] border border-[#D8E3F8] bg-white p-4 transition focus-within:z-40"
                    >
                      <div className="grid gap-3 md:grid-cols-[1.5fr_1fr_auto] md:items-end">
                        <div className="space-y-2">
                          <label className="text-sm font-semibold text-[#334155]">Nama Bahan</label>
                          {modalMode === "detail" ? (
                            <div className="rounded-2xl border border-[#D8E3F8] bg-[#F8FBFF] px-4 py-3 text-sm text-[#16213E]">
                              {row.item_id ? itemMap.get(Number(row.item_id))?.name ?? "-" : "-"}
                            </div>
                          ) : (
                            <SearchableIngredientSelect
                              options={itemOptions}
                              placeholder="Cari nama bahan"
                              value={row.item_id}
                              onChange={(itemId, unit) =>
                                setIngredients((current) =>
                                  current.map((entry) =>
                                    entry.localId === row.localId ? { ...entry, item_id: itemId, unit } : entry,
                                  ),
                                )
                              }
                            />
                          )}
                        </div>

                        <div className="space-y-2">
                          <label className="text-sm font-semibold text-[#334155]">Standar Porsi</label>
                          {modalMode === "detail" ? (
                            <div className="rounded-2xl border border-[#D8E3F8] bg-[#F8FBFF] px-4 py-3 text-sm text-[#16213E]">
                              {row.qty_per_patient} {row.unit ?? ""}
                            </div>
                          ) : (
                            <div className="flex items-center gap-2">
                              <input
                                min="0"
                                step="0.01"
                                type="number"
                                value={row.qty_per_patient}
                                onChange={(event) =>
                                  setIngredients((current) =>
                                    current.map((entry) =>
                                      entry.localId === row.localId
                                        ? { ...entry, qty_per_patient: event.target.value }
                                        : entry,
                                    ),
                                  )
                                }
                                className="h-11 w-full rounded-xl border border-[#D8E3F8] bg-white px-4 text-sm text-[#16213E] outline-none transition focus:border-[#2563EB]"
                                placeholder="0"
                              />
                              <span className="min-w-[40px] text-sm text-[#64748B]">{row.unit ?? ""}</span>
                            </div>
                          )}
                        </div>

                        {modalMode !== "detail" && (
                          <button
                            className="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FFF1F2] text-[#F43F5E] transition hover:bg-[#FFE4E6]"
                            onClick={() => removeIngredientRow(row.localId)}
                            type="button"
                          >
                            <Trash2 size={18} />
                          </button>
                        )}
                      </div>
                    </div>
                  ))}

                  {modalMode !== "detail" && (
                    <button
                      className="flex w-full items-center justify-center gap-2 rounded-[18px] border-2 border-dashed border-[#C7D8F8] py-4 text-sm font-semibold text-[#2563EB] transition hover:border-[#2563EB] hover:bg-[#F2F7FF]"
                      onClick={addIngredientRow}
                      type="button"
                    >
                      <Plus size={18} />
                      Tambah Bahan Komposisi
                    </button>
                  )}
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-[#E8EEF8] bg-[#F8FAFC] px-6 py-5">
              <button
                className="rounded-2xl border border-[#D8E3F8] bg-white px-6 py-2.5 text-sm font-semibold text-[#64748B] transition hover:bg-[#F1F5F9] hover:text-[#16213E]"
                onClick={closeModal}
                type="button"
              >
                {modalMode === "detail" ? "Tutup" : "Batal"}
              </button>
              {modalMode !== "detail" && (
                <button
                  className="rounded-2xl bg-[#2563EB] px-8 py-2.5 text-sm font-semibold text-white shadow-[0_10px_20px_rgba(37,99,235,0.18)] transition hover:-translate-y-0.5 hover:bg-[#1D4ED8] hover:shadow-[0_14px_24px_rgba(37,99,235,0.24)] disabled:cursor-not-allowed disabled:bg-[#94A3B8]"
                  disabled={saving}
                  onClick={saveMenu}
                  type="button"
                >
                  {saving ? "Menyimpan..." : "Simpan Perubahan"}
                </button>
              )}
            </div>
          </div>
        </div>
      ) : null}

      <DeleteConfirmModal
        open={modalMode === "delete"}
        purpose="toggle"
        title={selectedMenu?.isActive ? "Nonaktifkan Menu Makanan" : "Aktifkan Menu Makanan"}
        headline={selectedMenu?.isActive ? "Menu akan dinonaktifkan" : "Menu akan diaktifkan kembali"}
        description={
          selectedMenu?.isActive
            ? loadingLinkedPackages
              ? `Mengecek paket menu yang memakai ${selectedMenu?.name}...`
              : linkedPackages.length > 0
                ? `${selectedMenu?.name} sedang dipakai pada paket menu berikut: ${linkedPackages.map((item) => item.packageName).join(", ")}. Menonaktifkan menu ini akan memutus slot paket yang memakai menu tersebut.`
                : `Apakah Anda yakin ingin menonaktifkan menu ${selectedMenu?.name}? Menu ini akan tetap ada di sistem dan bisa diaktifkan kembali.`
            : `Apakah Anda yakin ingin mengaktifkan kembali menu ${selectedMenu?.name}?`
        }
        onClose={closeModal}
        onConfirm={toggleMenuActive}
        submitting={saving || loadingLinkedPackages}
        confirmLabel={selectedMenu?.isActive ? "Nonaktifkan" : "Aktifkan"}
        tone={selectedMenu?.isActive ? "danger" : "neutral"}
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
