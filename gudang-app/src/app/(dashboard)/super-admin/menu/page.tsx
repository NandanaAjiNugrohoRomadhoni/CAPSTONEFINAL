"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { ChevronDown, ChevronLeft, ChevronRight, Plus, Search, Trash2, X } from "lucide-react";
import sdk from "@/lib";
import { getErrorMessage } from "@/lib/admin-utils";
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
  unit?: string;
};

type FoodMenu = {
  id: number;
  name: string;
  description: string;
  compositionSummary: string;
  ingredients: IngredientRow[];
};

type ModalMode = "create" | "detail" | "edit" | "delete" | null;

type SuccessState = {
  title?: string;
  headline: string;
  message: string;
} | null;

type ItemRecord = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
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

export default function Page() {
  const router = useRouter();
  const [menus, setMenus] = useState<FoodMenu[]>([]);
  const [items, setItems] = useState<ItemRecord[]>([]);
  const [searchTerm, setSearchTerm] = useState("");
  const [currentPage, setCurrentPage] = useState(1);
  const [modalMode, setModalMode] = useState<ModalMode>(null);
  const [selectedMenu, setSelectedMenu] = useState<FoodMenu | null>(null);
  const [menuName, setMenuName] = useState("");
  const [menuDescription, setMenuDescription] = useState("");
  const [ingredients, setIngredients] = useState<IngredientRow[]>([{ localId: 1, item_id: null, qty_per_patient: "0" }]);
  const [successState, setSuccessState] = useState<SuccessState>(null);
  const [loading, setLoading] = useState(true);
  const [totalRecords, setTotalRecords] = useState(0);
  const [itemsLoading, setItemsLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

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
      const params: any = {
        perPage: 8,
        page: currentPage,
        sortBy: "name",
        sortDir: "ASC",
      };

      if (searchTerm.trim()) {
        params.q = searchTerm.trim();
      }

      const dishesResponse = await sdk.dishes.list(params);

      const descriptionMap = readMenuDescriptions();
      const nextMenus = (dishesResponse.data ?? []).map((dish) => {
        const dishId = Number(dish.id);

        return {
          id: dishId,
          name: dish.name,
          description: descriptionMap[String(dishId)] ?? "",
          compositionSummary: "Klik detail untuk melihat komposisi bahan.",
          ingredients: [],
        };
      });

      setMenus(nextMenus);
      const totalFromMeta = dishesResponse.meta?.total;
      const inferredTotal = nextMenus.length === 8 ? currentPage * 8 + 1 : (currentPage - 1) * 8 + nextMenus.length;
      setTotalRecords(totalFromMeta ?? inferredTotal);
    } catch (loadError) {
      setError(getErrorMessage(loadError, "Gagal memuat menu makanan."));
    } finally {
      setLoading(false);
    }
  }, [currentPage, searchTerm]);

  useEffect(() => {
    void loadMenus();
  }, [loadMenus]);

  async function ensureItemsLoaded() {
    if (items.length > 0) return;
    setItemsLoading(true);
    try {
      const itemsResponse = await sdk.items.list({ perPage: 100, sortBy: "name", sortDir: "ASC", is_active: true });
      setItems(itemsResponse.data ?? []);
    } catch (err) {
      console.error("Failed to load item metadata for menu compositions:", err);
    } finally {
      setItemsLoading(false);
    }
  }

  const itemOptions = useMemo(
    () =>
      items.map((item) => ({
        id: item.id,
        label: item.name,
        unit: item.unit_base,
      })),
    [items],
  );
  const itemMap = useMemo(
    () => new Map(items.map((item) => [Number(item.id), item])),
    [items],
  );
  const descriptionPreview = useMemo(
    () => buildCompositionSummary(ingredients, itemMap),
    [ingredients, itemMap],
  );

  const totalPages = Math.max(1, Math.ceil(totalRecords / 8));
  const paginatedMenus = menus;

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  async function openCreateModal() {
    setModalMode("create");
    setSelectedMenu(null);
    setMenuName("");
    setMenuDescription("");
    setIngredients([{ localId: Date.now(), item_id: null, qty_per_patient: "0" }]);
    await ensureItemsLoaded();
  }

  async function fetchCompositionsForDish(dishId: number) {
    try {
      const response = await sdk.dishCompositions.list({ dish_id: dishId, perPage: 100 });
      const compositions = response.data ?? [];
      const itemMap = new Map(items.map((item) => [Number(item.id), item]));

      const nextIngredients: IngredientRow[] = compositions.map((comp) => ({
        localId: comp.id,
        compositionId: comp.id,
        item_id: comp.item_id,
        qty_per_patient: comp.qty_per_patient,
        unit: itemMap.get(Number(comp.item_id))?.unit_base ?? comp.item?.unit_base ?? undefined,
      }));

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

  async function openDetailModal(menu: FoodMenu) {
    setLoading(true);
    const dishIngredients = await fetchCompositionsForDish(menu.id);
    setSelectedMenu({ ...menu, ingredients: dishIngredients });
    setModalMode("detail");
    setLoading(false);
    await ensureItemsLoaded();
  }

  async function openEditModal(menu: FoodMenu) {
    setLoading(true);
    const dishIngredients = await fetchCompositionsForDish(menu.id);
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
    await ensureItemsLoaded();
  }

  function openDeleteModal(menu: FoodMenu) {
    setSelectedMenu(menu);
    setModalMode("delete");
  }

  function closeModal() {
    if (saving) return;
    setModalMode(null);
    setSelectedMenu(null);
  }

  function addIngredientRow() {
    setIngredients((current) => [...current, { localId: Date.now(), item_id: null, qty_per_patient: "0" }]);
  }

  function removeIngredientRow(localId: number) {
    setIngredients((current) => (current.length === 1 ? current : current.filter((row) => row.localId !== localId)));
  }

  async function saveMenu() {
    setSaving(true);
    setError(null);

    try {
      const validRows = ingredients.filter((row) => row.item_id !== null && Number(row.qty_per_patient) > 0);
      if (menuName.trim() === "") {
        setSuccessState({
          title: "Informasi",
          headline: "Nama Menu Wajib Diisi",
          message: "Masukkan nama menu terlebih dahulu sebelum menyimpan.",
        });
        return;
      }
      if (validRows.length === 0) {
        setSuccessState({
          title: "Informasi",
          headline: "Komposisi Bahan Belum Lengkap",
          message: "Minimal satu komposisi bahan harus diisi.",
        });
        return;
      }
      if (isDuplicateMenuName(menus, menuName, selectedMenu?.id ?? null)) {
        setSuccessState({
          title: "Informasi",
          headline: "Nama Menu Sudah Ada",
          message: "Gunakan nama menu lain agar data menu makanan tidak duplikat.",
        });
        return;
      }
      if (getDuplicateIngredientIds(validRows).size > 0) {
        setSuccessState({
          title: "Informasi",
          headline: "Tidak bisa menambahkan 2 komponen yang sama",
          message: "Pilih bahan yang berbeda untuk setiap baris komposisi.",
        });
        return;
      }
      if (modalMode === "create") {
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
      const message = getErrorMessage(saveError, "Gagal menyimpan menu makanan.");
      if (message.toLowerCase().includes("validation failed")) {
        setSuccessState({
          title: "Informasi",
          headline: "Nama Menu Sudah Ada",
          message: "Gunakan nama menu lain agar data menu makanan tidak duplikat.",
        });
      } else {
        setError(message);
      }
    } finally {
      setSaving(false);
    }
  }

  async function deleteMenu() {
    if (!selectedMenu) return;

    setSaving(true);
    setError(null);
    try {
      const slotsResponse = await sdk.menus.slots();
      const linkedPackages = Array.from(
        new Set(
          (slotsResponse.data ?? [])
            .filter((slot) => Number(slot.dish_id) === Number(selectedMenu.id))
            .map((slot) => slot.menu?.name)
            .filter((name): name is string => Boolean(name)),
        ),
      );

      if (linkedPackages.length > 0) {
        setSuccessState({
          title: "Informasi",
          headline: "Menu Tidak Bisa Dihapus",
          message: `Menu tidak bisa dihapus karena masih bagian dari paket menu ${linkedPackages.join(", ")}.`,
        });
        closeModal();
        return;
      }

      for (const ingredient of selectedMenu.ingredients) {
        if (ingredient.compositionId) {
          await sdk.dishCompositions.delete(ingredient.compositionId);
        }
      }

      await sdk.client.request({
        method: "DELETE",
        path: `/dishes/${selectedMenu.id}`,
      });
      removeMenuDescription(selectedMenu.id);

      setSuccessState({
        title: "Berhasil",
        headline: "Menu Makanan Berhasil Dihapus",
        message: `Menu ${selectedMenu.name} berhasil dihapus dari sistem.`,
      });
      await loadMenus();
      router.refresh();
      closeModal();
    } catch (deleteError) {
      setError(getErrorMessage(deleteError, "Gagal menghapus menu makanan."));
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
          <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">{error}</div>
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
            <span className="ml-auto text-sm font-medium text-[#94A3B8]">{totalRecords} menu</span>
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
                          <p className="text-[13px] font-medium text-slate-500">
                            Area gambar menu
                          </p>
                        </div>
                      </div>
                      <div className="inline-flex rounded-full bg-[#EAF2FF] px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-[#2563EB]">
                        Menu
                      </div>
                      <div>
                        <h3 className="text-[20px] font-semibold leading-tight text-slate-900">{menu.name}</h3>
                        <p className="mt-3 line-clamp-4 text-[14px] leading-6 text-slate-600">{menu.compositionSummary}</p>
                      </div>
                    </div>

                    <div className="mt-auto flex flex-wrap gap-2 pt-5">
                      <MiniActionButton onClick={() => openDetailModal(menu)}>Detail</MiniActionButton>
                      <MiniActionButton onClick={() => openEditModal(menu)}>Edit</MiniActionButton>
                      <MiniActionButton onClick={() => openDeleteModal(menu)}>
                        Hapus
                      </MiniActionButton>
                    </div>
                  </article>
                ))}
              </div>
            ) : (
              <div className="rounded-[22px] border-2 border-dashed border-[#D6E3FA] bg-[#F8FBFF] px-6 py-14 text-center text-[15px] text-slate-400">
                Belum ada menu makanan yang cocok dengan pencarian.
              </div>
            )}
          </div>

          <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-[#F8FAFC] px-6 py-4 text-sm text-[#94A3B8]">
            <span>
            <span>
              {totalRecords === 0 ? 0 : (currentPage - 1) * 8 + 1}-
              {Math.min(currentPage * 8, totalRecords)} dari {totalRecords}
            </span>
            </span>

            <div className="ml-auto flex items-center gap-2">
              <button
                className="flex h-9 w-9 items-center justify-center rounded-lg border border-[#D7E3F4] bg-white text-slate-500 transition disabled:cursor-not-allowed disabled:opacity-45"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                type="button"
                disabled={currentPage === 1}
              >
                <ChevronLeft size={16} />
              </button>
              {Array.from({ length: totalPages }, (_, index) => index + 1).map((page) => (
                <button
                  key={page}
                  className={`flex h-9 min-w-[36px] items-center justify-center rounded-lg border text-sm font-semibold transition ${
                    page === currentPage
                      ? "border-[#2563EB] bg-[#2563EB] text-white shadow-[0_10px_24px_rgba(37,99,235,0.26)]"
                      : "border-[#D7E3F4] bg-white text-slate-600 hover:border-[#93B4F7] hover:text-[#2563EB]"
                  }`}
                  onClick={() => setCurrentPage(page)}
                  type="button"
                >
                  {page}
                </button>
              ))}
              <button
                className="flex h-9 w-9 items-center justify-center rounded-lg border border-[#D7E3F4] bg-white text-slate-500 transition disabled:cursor-not-allowed disabled:opacity-45"
                onClick={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
                type="button"
                disabled={currentPage === totalPages}
              >
                <ChevronRight size={16} />
              </button>
            </div>
          </div>
        </SurfaceCard>
      </div>

      {(modalMode === "create" || modalMode === "edit") && (
        <div className="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
          <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={closeModal} />
          <div className="animate-modal-enter relative flex max-h-[calc(100vh-3rem)] w-full max-w-[620px] flex-col overflow-visible rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
            <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
              <div>
                <h2 className="text-[22px] font-semibold text-slate-900">
                  {modalMode === "create" ? "Tambah Menu Makanan" : "Edit Menu Makanan"}
                </h2>
                <p className="mt-2 text-sm text-slate-400">
                  {modalMode === "create" ? "Isi detail menu dan komposisi bahan" : "Ubah nama, deskripsi, dan komposisi bahan"}
                </p>
                {itemsLoading && (
                  <p className="mt-1 text-xs font-semibold text-blue-500 animate-pulse">Memuat metadata bahan...</p>
                )}
              </div>
              <button className="flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-slate-400" onClick={closeModal} type="button">
                <X size={18} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-5">
              <div className="space-y-5">
                <div>
                  <label className="block text-sm font-semibold text-slate-700">
                    Nama Menu <span className="text-red-500">*</span>
                  </label>
                  <input
                    value={menuName}
                    onChange={(event) => setMenuName(event.target.value)}
                    className="mt-2 h-11 w-full rounded-xl border border-slate-200 px-4 text-sm text-slate-700 outline-none"
                    placeholder="Masukkan nama menu"
                  />
                </div>

                <div>
                  <label className="block text-sm font-semibold text-slate-700">Deskripsi</label>
                  <textarea
                    value={menuDescription}
                    onChange={(event) => setMenuDescription(event.target.value)}
                    className="mt-2 min-h-[96px] w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 outline-none"
                    placeholder="Masukkan deskripsi menu"
                  />
                </div>

                <div className="rounded-[12px] bg-[#EDF4FF] px-4 py-3">
                  <p className="text-[11px] font-bold uppercase tracking-wide text-[#2155CD]">Ringkasan Komposisi</p>
                  <p className="mt-1 text-sm text-slate-700">{descriptionPreview}</p>
                </div>

                <div className="overflow-visible rounded-[14px] border border-[#D9E3F2]">
                  <div className="border-b border-[#D9E3F2] bg-[#EDF4FF] px-4 py-3">
                    <h3 className="text-base font-semibold text-[#475569]">KOMPOSISI BAHAN</h3>
                    <p className="mt-1 text-sm text-[#94A3B8]">Pilih bahan dari stok yang tersedia</p>
                  </div>

                  <div className="space-y-4 overflow-visible p-4">
                    {ingredients.map((row) => (
                      <div key={row.localId} className="relative z-10 rounded-[12px] border border-[#D9E3F2] bg-[#F8FAFC] p-4 focus-within:z-40">
                        <div className="grid gap-3 md:grid-cols-[1.3fr_1fr_auto] md:items-end">
                          <div>
                            <label className="mb-2 block text-sm font-semibold text-slate-700">Nama Bahan</label>
                            <SearchableIngredientSelect
                              key={`${row.localId}-${row.item_id ?? "empty"}`}
                              options={itemOptions}
                              value={row.item_id}
                              placeholder="Cari nama bahan"
                              onChange={(nextItemId, unit) =>
                                setIngredients((current) =>
                                  current.map((item) =>
                                    item.localId === row.localId ? { ...item, item_id: nextItemId, unit } : item,
                                  ),
                                )
                              }
                            />
                          </div>

                          <div>
                            <label className="mb-2 block text-sm font-semibold text-slate-700">Standar Porsi</label>
                            <div className="flex items-center gap-2">
                              <input
                                value={row.qty_per_patient}
                                onChange={(event) =>
                                  setIngredients((current) =>
                                    current.map((item) =>
                                      item.localId === row.localId ? { ...item, qty_per_patient: event.target.value } : item,
                                    ),
                                  )
                                }
                                className="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none"
                              />
                              <span className="text-sm text-slate-400">{row.unit ?? "-"}</span>
                            </div>
                          </div>

                          <button
                            className="flex h-11 w-11 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-500 transition hover:bg-red-100 hover:text-red-600"
                            onClick={() => removeIngredientRow(row.localId)}
                            type="button"
                          >
                            <Trash2 size={16} />
                          </button>
                        </div>
                      </div>
                    ))}

                    <button
                      className="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-[#2155CD] px-4 py-3 text-sm font-semibold text-[#2155CD] transition hover:bg-[#EEF4FF]"
                      onClick={addIngredientRow}
                      type="button"
                    >
                      <Plus size={16} />
                      Tambah Baris Bahan
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
              <button className="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600" onClick={closeModal} type="button">
                Batal
              </button>
              <button className="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white" onClick={saveMenu} type="button" disabled={saving}>
                {saving ? "Menyimpan..." : "Simpan"}
              </button>
            </div>
          </div>
        </div>
      )}

      {modalMode === "detail" && selectedMenu && (
        <div className="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
          <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={closeModal} />
          <div className="animate-modal-enter relative flex max-h-[calc(100vh-3rem)] w-full max-w-[520px] flex-col overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
            <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
              <h2 className="text-[22px] font-semibold text-slate-900">{selectedMenu.name}</h2>
              <button className="flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-slate-400" onClick={closeModal} type="button">
                <X size={18} />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-5">
              <div className="space-y-4">
                <div className="rounded-[12px] bg-[#EDF4FF] px-4 py-3">
                  <p className="text-[11px] font-bold uppercase tracking-wide text-[#2155CD]">Deskripsi</p>
                  <p className="mt-1 text-sm text-slate-700">{selectedMenu.description || "Belum ada deskripsi menu."}</p>
                </div>

                <div>
                  <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-500">Komposisi Bahan</h3>
                  <div className="mt-2 overflow-hidden rounded-[12px] border border-[#E2E8F0]">
                    <table className="w-full text-left text-sm">
                      <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                          <th className="px-4 py-3">#</th>
                          <th className="px-4 py-3">Nama Bahan</th>
                          <th className="px-4 py-3">Standar Porsi</th>
                        </tr>
                      </thead>
                      <tbody>
                        {selectedMenu.ingredients.map((item, index) => {
                          const relatedItem = itemOptions.find((option) => option.id === item.item_id);
                          return (
                            <tr key={`${item.localId}-${index}`} className="border-t border-gray-200">
                              <td className="px-4 py-3 text-slate-500">{index + 1}</td>
                              <td className="px-4 py-3 font-medium text-gray-900">{relatedItem?.label ?? "-"}</td>
                              <td className="px-4 py-3 text-slate-600">
                                {item.qty_per_patient} {item.unit ?? relatedItem?.unit ?? ""}
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex justify-end border-t border-slate-200 px-5 py-4">
              <button className="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600" onClick={closeModal} type="button">
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      <DeleteConfirmModal
        open={modalMode === "delete"}
        headline="Hapus menu makanan ini?"
        description={`Menu ${selectedMenu?.name ?? ""} akan dihapus permanen dari sistem.`}
        onClose={closeModal}
        onConfirm={deleteMenu}
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

function buildCompositionSummary(
  rows: IngredientRow[],
  itemMap: Map<number, ItemRecord>,
) {
  if (rows.length === 0) {
    return "Belum ada komposisi bahan.";
  }

  const names = rows
    .map((row) => (row.item_id !== null ? itemMap.get(row.item_id)?.name ?? null : null))
    .filter((name): name is string => Boolean(name));

  if (names.length === 0) {
    return "Belum ada komposisi bahan.";
  }

  if (names.length <= 3) {
    return names.join(", ");
  }

  return `${names.slice(0, 3).join(", ")} +${names.length - 3} lagi`;
}

function getDuplicateIngredientIds(rows: IngredientRow[]) {
  const seen = new Set<number>();
  const duplicates = new Set<number>();

  for (const row of rows) {
    if (row.item_id === null) continue;
    if (seen.has(row.item_id)) {
      duplicates.add(row.item_id);
    } else {
      seen.add(row.item_id);
    }
  }

  return duplicates;
}

function isDuplicateMenuName(menus: FoodMenu[], nextName: string, selectedMenuId?: number | null) {
  const normalizedName = nextName.trim().toLowerCase();
  return menus.some(
    (menu) =>
      menu.id !== selectedMenuId &&
      menu.name.trim().toLowerCase() === normalizedName,
  );
}
