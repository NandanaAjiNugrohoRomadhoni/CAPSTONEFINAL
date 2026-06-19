"use client";

import sdk from "@/lib";
import { listAllItems } from "@/lib/items";
import { listAllPaginatedRows } from "@/lib/pagination";
import {
  CSV_MENU_PACKAGES,
  CSV_MENU_RECIPES,
  getCsvMenuPackageLabel,
  normalizeCsvMenuName,
} from "@/lib/menu-csv-plan";

type DishRow = Awaited<ReturnType<typeof sdk.dishes.list>>["data"][number];
type ItemRow = Awaited<ReturnType<typeof sdk.items.list>>["data"][number];
type DishCompositionRow = Awaited<ReturnType<typeof sdk.dishCompositions.list>>["data"][number];
type MenuRow = Awaited<ReturnType<typeof sdk.menus.list>>["data"][number];
type MenuSlotRow = Awaited<ReturnType<typeof sdk.menus.slots>>["data"][number];
type MealTimeRow = Awaited<ReturnType<typeof sdk.mealTimes.list>>["data"][number];

type MenuCsvSyncSummary = {
  dishesCreated: number;
  compositionsCreated: number;
  compositionsUpdated: number;
  slotsUpdated: number;
  slotsCreated: number;
  slotsDeleted: number;
  skippedItems: string[];
};

const MENU_CSV_SYNC_VERSION = "menu-csv-plan-v1";
const MENU_CSV_SYNC_STORAGE_KEY = "menu-csv-sync-version";

let syncPromise: Promise<MenuCsvSyncSummary | null> | null = null;

function getStoredSyncVersion() {
  if (typeof window === "undefined") return null;
  try {
    return window.localStorage.getItem(MENU_CSV_SYNC_STORAGE_KEY);
  } catch {
    return null;
  }
}

function setStoredSyncVersion(version: string) {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(MENU_CSV_SYNC_STORAGE_KEY, version);
  } catch {
    // ignore storage failures
  }
}

function normalizeMealTime(name: string | null | undefined) {
  const normalized = (name ?? "").trim().toLowerCase();
  if (normalized === "pagi") return "pagi";
  if (normalized === "siang") return "siang";
  if (normalized === "sore") return "sore";
  return null;
}

function getTargetSessionNames() {
  return {
    pagi: CSV_MENU_PACKAGES.flatMap((item) => item.sessions.pagi.map((entry) => entry.name)),
    siang: CSV_MENU_PACKAGES.flatMap((item) => item.sessions.siang.map((entry) => entry.name)),
    sore: CSV_MENU_PACKAGES.flatMap((item) => item.sessions.sore.map((entry) => entry.name)),
  };
}

export async function ensureMenuCsvCatalogSynced() {
  if (getStoredSyncVersion() === MENU_CSV_SYNC_VERSION) {
    return null;
  }

  if (syncPromise) {
    return syncPromise;
  }

  syncPromise = (async () => {
    const summary: MenuCsvSyncSummary = {
      dishesCreated: 0,
      compositionsCreated: 0,
      compositionsUpdated: 0,
      slotsUpdated: 0,
      slotsCreated: 0,
      slotsDeleted: 0,
      skippedItems: [],
    };

    const [dishRows, compositionRows, itemRows, menuRows, mealTimes] = await Promise.all([
      listAllPaginatedRows<DishRow>(sdk.dishes.list.bind(sdk.dishes), {
        sortBy: "name",
        sortDir: "ASC",
      }),
      listAllPaginatedRows<DishCompositionRow>(sdk.dishCompositions.list.bind(sdk.dishCompositions), {
        sortBy: "id",
        sortDir: "ASC",
      }),
      listAllItems({
        sortBy: "name",
        sortDir: "ASC",
      }),
      listAllPaginatedRows<MenuRow>(sdk.menus.list.bind(sdk.menus), {
        sortBy: "id",
        sortDir: "ASC",
      }),
      sdk.mealTimes.list({ paginate: false, sortBy: "id", sortDir: "ASC" }),
    ]);

    const dishByName = new Map(
      dishRows.map((dish) => [normalizeCsvMenuName(dish.name), dish] as const),
    );
    const itemByName = new Map(
      itemRows.map((item) => [normalizeCsvMenuName(item.name), item] as const),
    );
    const compositionByKey = new Map<string, DishCompositionRow>(
      compositionRows.map((composition) => [
        `${Number(composition.dish_id)}::${Number(composition.item_id)}`,
        composition,
      ]),
    );
    const mealTimeIdByKey = new Map(
      mealTimes.data
        .map((mealTime) => [normalizeMealTime(mealTime.name), Number(mealTime.id)] as const)
        .filter((entry): entry is ["pagi" | "siang" | "sore", number] => entry[0] !== null),
    );

    for (const recipe of CSV_MENU_RECIPES) {
      const normalizedRecipeName = normalizeCsvMenuName(recipe.name);
      if (!dishByName.has(normalizedRecipeName)) {
        const createdDish = await sdk.dishes.create({ name: recipe.name });
        const createdDishData = createdDish.data as DishRow;
        dishByName.set(normalizeCsvMenuName(createdDishData.name), createdDishData);
        summary.dishesCreated += 1;
      }
    }

    for (const recipe of CSV_MENU_RECIPES) {
      const dish = dishByName.get(normalizeCsvMenuName(recipe.name));
      if (!dish) {
        continue;
      }

      for (const ingredient of recipe.ingredients) {
        const item = itemByName.get(normalizeCsvMenuName(ingredient.name));
        if (!item) {
          summary.skippedItems.push(`${recipe.name} → ${ingredient.name}`);
          continue;
        }

        const compositionKey = `${Number(dish.id)}::${Number(item.id)}`;
        const existingComposition = compositionByKey.get(compositionKey);

        if (!existingComposition) {
          const createdComposition = await sdk.dishCompositions.create({
            dish_id: Number(dish.id),
            item_id: Number(item.id),
            qty_per_patient: ingredient.qty,
          });

          compositionByKey.set(compositionKey, createdComposition.data as DishCompositionRow);
          summary.compositionsCreated += 1;
          continue;
        }

        if (String(existingComposition.qty_per_patient) !== ingredient.qty) {
          await sdk.dishCompositions.update(Number(existingComposition.id), {
            dish_id: Number(dish.id),
            item_id: Number(item.id),
            qty_per_patient: ingredient.qty,
          });

          summary.compositionsUpdated += 1;
        }
      }
    }

    const slotsRows = await listAllPaginatedRows<MenuSlotRow>(sdk.menus.slots.bind(sdk.menus), {
      sortBy: "id",
      sortDir: "ASC",
    });

    const packageMenus = [...menuRows].sort((left, right) => left.id - right.id);
    const packageLimit = Math.min(packageMenus.length, CSV_MENU_PACKAGES.length);

    for (let packageIndex = 0; packageIndex < packageLimit; packageIndex += 1) {
      const backendMenu = packageMenus[packageIndex];
      const csvPackage = CSV_MENU_PACKAGES[packageIndex];

      for (const mealKey of ["pagi", "siang", "sore"] as const) {
        const targetDishNames = csvPackage.sessions[mealKey].map((entry) => entry.name);
        const mealTimeId = mealTimeIdByKey.get(mealKey);
        if (!mealTimeId) {
          continue;
        }

        const targetDishIds = targetDishNames
          .map((dishName) => dishByName.get(normalizeCsvMenuName(dishName)))
          .filter((dish): dish is DishRow => Boolean(dish))
          .map((dish) => Number(dish.id));

        const currentSlots = slotsRows
          .filter(
            (slot) =>
              Number(slot.menu_id) === Number(backendMenu.id) &&
              normalizeMealTime(slot.meal_time?.name) === mealKey,
          )
          .sort((left, right) => Number(left.id) - Number(right.id));

        const desiredLength = targetDishIds.length;
        const existingLength = currentSlots.length;

        for (let index = 0; index < Math.max(desiredLength, existingLength); index += 1) {
          const targetDishId = targetDishIds[index] ?? null;
          const slot = currentSlots[index] ?? null;

          if (!targetDishId) {
            if (slot?.id) {
              await sdk.menus.deleteSlot(Number(slot.id));
              summary.slotsDeleted += 1;
            }
            continue;
          }

          if (!slot?.id) {
            await sdk.menus.assignSlot({
              menu_id: Number(backendMenu.id),
              meal_time_id: mealTimeId,
              dish_id: targetDishId,
            });
            summary.slotsCreated += 1;
            continue;
          }

          if (Number(slot.dish_id) !== targetDishId) {
            await sdk.menus.updateSlot(Number(slot.id), {
              dish_id: targetDishId,
            });
            summary.slotsUpdated += 1;
          }
        }
      }
    }

    setStoredSyncVersion(MENU_CSV_SYNC_VERSION);
    return summary;
  })().finally(() => {
    syncPromise = null;
  });

  return syncPromise;
}

export function getCsvPackageTitleByIndex(index: number) {
  return getCsvMenuPackageLabel(index);
}

export function getCsvSessionNameMap() {
  return getTargetSessionNames();
}
