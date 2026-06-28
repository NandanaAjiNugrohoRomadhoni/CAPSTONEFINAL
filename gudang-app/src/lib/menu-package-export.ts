"use client";

import { CSV_MENU_PACKAGES } from "@/lib/menu-csv-plan";
import { buildExportFilename } from "@/lib/export-filename";
import {
  buildSpreadsheetDocument,
  downloadSpreadsheetHtml,
  escapeSpreadsheetHtml,
  formatSpreadsheetNumber,
} from "@/lib/spreadsheet-export";

type MealKey = "siang" | "sore" | "pagi";

type CsvIngredient = {
  name: string;
  qty: string;
  unit: string;
};

type CsvMenuEntry = {
  name: string;
  ingredients: CsvIngredient[];
};

type MealRow = {
  menuName?: string;
  menuRowspan?: number;
  ingredientName: string;
  qty: string;
  unit: string;
};

type PackageExportRow = {
  key: string;
  label: string;
  mealRows: Record<MealKey, MealRow[]>;
  rowCount: number;
};

const mealOrder: MealKey[] = ["siang", "sore", "pagi"];
const mealLabel: Record<MealKey, string> = {
  siang: "SIANG",
  sore: "SORE",
  pagi: "PAGI",
};

function normalizeIngredientValue(value: string | null | undefined) {
  const trimmed = String(value ?? "").trim();
  return trimmed || "-";
}

function flattenMealEntries(entries: CsvMenuEntry[]): MealRow[] {
  const rows: MealRow[] = [];

  for (const entry of entries) {
    const ingredients = entry.ingredients.length > 0 ? entry.ingredients : [{ name: "-", qty: "-", unit: "-" }];
    ingredients.forEach((ingredient, index) => {
      rows.push({
        menuName: index === 0 ? entry.name : undefined,
        menuRowspan: index === 0 ? ingredients.length : undefined,
        ingredientName: normalizeIngredientValue(ingredient.name),
        qty: normalizeIngredientValue(ingredient.qty),
        unit: normalizeIngredientValue(ingredient.unit),
      });
    });
  }

  return rows;
}

function buildPackageExportRows(): PackageExportRow[] {
  return CSV_MENU_PACKAGES.map((pkg) => {
    const mealRows = {
      siang: flattenMealEntries(pkg.sessions.siang),
      sore: flattenMealEntries(pkg.sessions.sore),
      pagi: flattenMealEntries(pkg.sessions.pagi),
    } satisfies Record<MealKey, MealRow[]>;

    return {
      key: pkg.key,
      label: pkg.label,
      mealRows,
      rowCount: Math.max(
        1,
        mealRows.siang.length,
        mealRows.sore.length,
        mealRows.pagi.length,
      ),
    };
  });
}

function buildSummaryRows(packageRows: PackageExportRow[]) {
  const totalPackage = packageRows.length;
  const totalMenu = packageRows.reduce(
    (total, row) =>
      total +
      mealOrder.reduce((mealTotal, mealKey) => {
        const rows = row.mealRows[mealKey];
        const menuCount = rows.filter((mealRow) => Boolean(mealRow.menuName)).length;
        return mealTotal + menuCount;
      }, 0),
    0,
  );
  return [
    ["Total Paket", `${formatSpreadsheetNumber(totalPackage, 0)} paket`],
    ["Total Menu", `${formatSpreadsheetNumber(totalMenu, 0)} menu`],
  ];
}

export function buildMenuPackageSpreadsheetHtml() {
  const packageRows = buildPackageExportRows();
  const summaryRows = buildSummaryRows(packageRows);

  const summaryHtml = `
    <table class="summary">
      ${summaryRows
        .map(
          ([label, value]) => `
            <tr>
              <td class="summary-label">${escapeSpreadsheetHtml(label)}</td>
              <td class="summary-value">${escapeSpreadsheetHtml(value)}</td>
            </tr>`,
        )
        .join("")}
    </table>
  `;

  const rowsHtml = packageRows
    .map((pkg, packageIndex) => {
      const rowCount = pkg.rowCount;
      const mealRows = {
        siang: pkg.mealRows.siang,
        sore: pkg.mealRows.sore,
        pagi: pkg.mealRows.pagi,
      };

      const rowParts: string[] = [];

      for (let rowIndex = 0; rowIndex < rowCount; rowIndex += 1) {
        const cells: string[] = [];

        if (rowIndex === 0) {
          cells.push(
            `<td class="rank" rowspan="${rowCount}">${escapeSpreadsheetHtml(
              formatSpreadsheetNumber(packageIndex + 1, 0),
            )}</td>`,
          );
        }

        for (const mealKey of mealOrder) {
          const row = mealRows[mealKey][rowIndex];
          if (!row) {
            cells.push(`<td></td><td></td><td></td><td></td>`);
            continue;
          }

          if (row.menuName) {
            cells.push(
              `<td class="text-strong" rowspan="${row.menuRowspan ?? 1}">${escapeSpreadsheetHtml(row.menuName)}</td>`,
            );
          }

          cells.push(
            `<td>${escapeSpreadsheetHtml(row.ingredientName)}</td>` +
            `<td class="number">${escapeSpreadsheetHtml(row.qty)}</td>` +
            `<td>${escapeSpreadsheetHtml(row.unit)}</td>`,
          );
        }

        rowParts.push(`<tr>${cells.join("")}</tr>`);
      }

      return rowParts.join("");
    })
    .join("");

  return buildSpreadsheetDocument({
    title: "LAPORAN PAKET MENU MAKANAN INSTALASI GIZI RSD BALUNG",
    subtitle: "Rekap komposisi menu per paket berdasarkan data paket menu pada sistem.",
    body: `
      <div class="title">LAPORAN PAKET MENU MAKANAN INSTALASI GIZI RSD BALUNG</div>
      <div class="subtitle">Data paket menu, menu, bahan, standar porsi, dan satuan per sesi makan.</div>

      ${summaryHtml}

      <table>
        <tr class="head">
          <th rowspan="2">MENU KE-</th>
          ${mealOrder.map((mealKey) => `<th colspan="4">${mealLabel[mealKey]}</th>`).join("")}
        </tr>
        <tr class="subhead">
          ${mealOrder
            .map(
              () => `
                <th>Menu</th>
                <th>Bahan</th>
                <th>Standar Porsi</th>
                <th>Satuan</th>`,
            )
            .join("")}
        </tr>
        ${rowsHtml}
      </table>
    `,
    extraStyles: `
      .subhead { background: #F0FDF4; color: #14532D; font-weight: 800; text-align: center; }
      .subhead th { background: #F0FDF4; color: #14532D; }
      .rank { text-align: center; font-weight: 700; }
    `,
  });
}

export function downloadMenuPackageSpreadsheet() {
  const html = buildMenuPackageSpreadsheetHtml();
  downloadSpreadsheetHtml(buildExportFilename("laporan-paket-menu-makanan"), html);
}
