import menuCsvPlan from "@/data/menu-csv-plan.json";

export type CsvMenuIngredient = {
  name: string;
  qty: string;
  unit: string;
};

export type CsvMenuRecipe = {
  name: string;
  ingredients: CsvMenuIngredient[];
};

export type CsvMenuPackageSession = {
  name: string;
  ingredients: CsvMenuIngredient[];
};

export type CsvMenuPackage = {
  key: string;
  label: string;
  sessions: Record<"pagi" | "siang" | "sore", CsvMenuPackageSession[]>;
};

type CsvMenuPlan = {
  packageOrder: string[];
  packages: CsvMenuPackage[];
  recipes: CsvMenuRecipe[];
};

const parsedPlan = menuCsvPlan as CsvMenuPlan;

function formatCsvMenuPackageLabel(label: string | null | undefined, index: number) {
  const trimmed = String(label ?? "").trim();
  if (!trimmed) return `Paket ${index + 1}`;
  if (/^Paket\s+[IVXLCDM]+$/i.test(trimmed)) {
    return `Paket ${index + 1}`;
  }
  return trimmed;
}

export const CSV_MENU_PACKAGE_ORDER = parsedPlan.packageOrder;
export const CSV_MENU_PACKAGES = parsedPlan.packages.map((pkg, index) => ({
  ...pkg,
  label: formatCsvMenuPackageLabel(pkg.label, index),
}));
export const CSV_MENU_RECIPES = parsedPlan.recipes;

export function getCsvMenuPackageLabel(index: number) {
  return CSV_MENU_PACKAGES[index]?.label ?? `Paket ${index + 1}`;
}

export function getCsvMenuPackageKey(index: number) {
  return CSV_MENU_PACKAGES[index]?.key ?? String(index + 1);
}

export function getCsvMenuPackageMap() {
  return new Map(CSV_MENU_PACKAGES.map((entry) => [entry.key, entry] as const));
}

export function getCsvRecipeMap() {
  return new Map(CSV_MENU_RECIPES.map((entry) => [entry.name, entry] as const));
}

export function normalizeCsvMenuName(value: string | null | undefined) {
  return (value ?? "").trim().toLowerCase().replace(/\s+/g, " ");
}

export function buildCsvPackageCards() {
  return CSV_MENU_PACKAGES.map((pkg, index) => ({
    id: index + 1,
    title: pkg.label,
    active: true,
    meals: {
      pagi: pkg.sessions.pagi.map((entry) => entry.name),
      siang: pkg.sessions.siang.map((entry) => entry.name),
      sore: pkg.sessions.sore.map((entry) => entry.name),
    },
  }));
}

export function buildCsvRecipeMenus() {
  return CSV_MENU_RECIPES.map((recipe, index) => ({
    id: -(index + 1),
    name: recipe.name,
    description: "",
    compositionSummary: recipe.ingredients
      .map((ingredient) => `${ingredient.name}${ingredient.qty ? ` ${ingredient.qty}${ingredient.unit ? ` ${ingredient.unit}` : ""}` : ""}`)
      .join(", "),
    ingredients: recipe.ingredients.map((ingredient, ingredientIndex) => ({
      localId: index * 100 + ingredientIndex + 1,
      item_id: null,
      qty_per_patient: ingredient.qty,
      unit: ingredient.unit,
      fallbackName: ingredient.name,
    })),
    isActive: true,
    isSynthetic: true,
  }));
}
