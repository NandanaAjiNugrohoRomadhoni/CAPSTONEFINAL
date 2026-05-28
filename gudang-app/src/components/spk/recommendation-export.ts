"use client";

type RecommendationExportRow = {
  itemName: string;
  categoryName?: string;
  currentStock: string;
  requiredQty: string;
  recommendedQty: string;
  numericRecommendedQty: number;
};

type RecommendationExportMeta = {
  spkId: number | null;
  generatedBy: string;
  calculationDate: string;
  targetLabel: string;
  itemCountLabel: string;
  formulaTitle: string;
  formulaDescription: string;
};

function escapeHtml(value: unknown) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

function formatPlainNumber(value: number) {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits: 2,
  }).format(value);
}

function buildSpreadsheetShell(body: string) {
  return `<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <style>
    body { font-family: Arial, sans-serif; color: #173321; }
    .title { font-size: 30px; font-weight: 800; color: #166534; margin-bottom: 6px; }
    .subtitle { color: #4B5563; font-size: 16px; margin-bottom: 16px; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #D9EAD3; padding: 10px 12px; font-size: 16px; vertical-align: middle; }
    .no-border td { border: 0; }
    .summary { background: #F0FDF4; border: 1px solid #BBF7D0; }
    .summary-label { color: #14532D; font-weight: 700; width: 200px; font-size: 16px; }
    .summary-value { color: #111827; font-weight: 600; font-size: 16px; }
    .pill { background: #DCFCE7; color: #166534; font-weight: 800; text-align: center; font-size: 16px; }
    .method { background: #ECFDF5; color: #166534; font-weight: 800; font-size: 28px; text-align: center; }
    .section { background: #DCFCE7; color: #14532D; font-weight: 800; font-size: 16px; }
    .head { background: #166534; color: #FFFFFF; font-weight: 800; text-align: center; font-size: 16px; }
    .rank { text-align: center; font-weight: 700; font-size: 16px; }
    .number { text-align: right; font-size: 16px; }
    .ok { color: #15803D; font-weight: 700; font-size: 16px; }
    .muted { color: #64748B; font-size: 16px; }
    .text-strong { font-weight: 700; font-size: 16px; }
  </style>
</head>
<body>
${body}
</body>
</html>`;
}

export function downloadRecommendationSpreadsheet({
  filename,
  html,
}: {
  filename: string;
  html: string;
}) {
  const blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export function buildKeringRecommendationSpreadsheet(
  meta: RecommendationExportMeta,
  rows: RecommendationExportRow[],
) {
  const totalRecommended = rows.reduce((total, row) => total + row.numericRecommendedQty, 0);

  const body = `
  <div class="title">SPS - REKOMENDASI BELANJA</div>
  <div class="subtitle">Hasil rekomendasi belanja berdasarkan data SPK dan stok bahan pada sistem.</div>

  <table class="no-border">
    <tr>
      <td style="width: 38%; padding: 0 12px 12px 0;">
        <table class="summary">
          <tr><td class="summary-label">Nama Pengaju</td><td class="summary-value">${escapeHtml(meta.generatedBy)}</td></tr>
          <tr><td class="summary-label">Jenis SPK</td><td class="summary-value">Kering & Pengemas</td></tr>
          <tr><td class="summary-label">Tanggal Berlaku</td><td class="summary-value">${escapeHtml(meta.targetLabel)}</td></tr>
          <tr><td class="summary-label">Jumlah Produk</td><td class="summary-value">${escapeHtml(meta.itemCountLabel)}</td></tr>
          <tr><td class="summary-label">Total Rekomendasi</td><td class="summary-value">${formatPlainNumber(totalRecommended)}</td></tr>
        </table>
      </td>
      <td style="width: 40%; padding: 0 12px 12px 0;">
        <table>
          <tr><td class="section" colspan="4">KRITERIA YANG DIGUNAKAN</td></tr>
          <tr class="head"><th>Kode</th><th>Kriteria</th><th>Tipe</th><th>Keterangan</th></tr>
          <tr><td>C1</td><td>Stok Saat Ini</td><td>Cost</td><td>Semakin rendah, semakin prioritas</td></tr>
          <tr><td>C2</td><td>Kebutuhan</td><td>Benefit</td><td>Semakin tinggi, semakin prioritas</td></tr>
          <tr><td>C3</td><td>Rekomendasi Sistem</td><td>Benefit</td><td>Jumlah belanja akhir dari sistem</td></tr>
        </table>
      </td>
      <td style="width: 22%; padding: 0 0 12px 0;">
        <table>
          <tr><td class="pill">Tanggal Perhitungan</td><td>${escapeHtml(meta.calculationDate)}</td></tr>
          <tr><td class="pill">ID SPK</td><td>${meta.spkId ? `SPK-${String(meta.spkId).padStart(4, "0")}` : "-"}</td></tr>
          <tr><td class="method" colspan="2">METODE<br/>SPK</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <table style="margin-bottom: 12px;">
    <tr><td class="section">${escapeHtml(meta.formulaTitle)}</td></tr>
    <tr><td>${escapeHtml(meta.formulaDescription)}</td></tr>
  </table>

  <table>
    <tr class="head">
      <th>Ranking</th>
      <th>Nama Bahan</th>
      <th>Kategori</th>
      <th>Pemakaian Bulan Lalu</th>
      <th>Stok Saat Ini</th>
      <th>Rekomendasi Beli</th>
      <th>Rekomendasi</th>
    </tr>
    ${rows
      .map(
        (row, index) => `
    <tr>
      <td class="rank">${index + 1}</td>
      <td class="text-strong">${escapeHtml(row.itemName)}</td>
      <td>${escapeHtml(row.categoryName ?? "-")}</td>
      <td class="number">${escapeHtml(row.requiredQty)}</td>
      <td class="number">${escapeHtml(row.currentStock)}</td>
      <td class="number text-strong">${escapeHtml(row.recommendedQty)}</td>
      <td class="${row.numericRecommendedQty > 0 ? "ok" : "muted"}">${row.numericRecommendedQty > 0 ? "Direkomendasikan" : "Tidak ada tambahan"}</td>
    </tr>`,
      )
      .join("")}
  </table>`;

  return buildSpreadsheetShell(body);
}

export function buildBasahRecommendationSpreadsheet(
  meta: RecommendationExportMeta,
  rows: RecommendationExportRow[],
) {
  const totalRecommended = rows.reduce((total, row) => total + row.numericRecommendedQty, 0);

  const body = `
  <div class="title">SPS - REKOMENDASI BELANJA</div>
  <div class="subtitle">Hasil rekomendasi belanja berdasarkan data SPK dan stok bahan pada sistem.</div>

  <table class="no-border">
    <tr>
      <td style="width: 38%; padding: 0 12px 12px 0;">
        <table class="summary">
          <tr><td class="summary-label">Nama Pengaju</td><td class="summary-value">${escapeHtml(meta.generatedBy)}</td></tr>
          <tr><td class="summary-label">Jenis SPK</td><td class="summary-value">Basah</td></tr>
          <tr><td class="summary-label">Tanggal Berlaku</td><td class="summary-value">${escapeHtml(meta.targetLabel)}</td></tr>
          <tr><td class="summary-label">Jumlah Produk</td><td class="summary-value">${escapeHtml(meta.itemCountLabel)}</td></tr>
          <tr><td class="summary-label">Total Rekomendasi</td><td class="summary-value">${formatPlainNumber(totalRecommended)}</td></tr>
        </table>
      </td>
      <td style="width: 40%; padding: 0 12px 12px 0;">
        <table>
          <tr><td class="section" colspan="4">KRITERIA YANG DIGUNAKAN</td></tr>
          <tr class="head"><th>Kode</th><th>Kriteria</th><th>Tipe</th><th>Keterangan</th></tr>
          <tr><td>C1</td><td>Stok Saat Ini</td><td>Cost</td><td>Semakin rendah, semakin prioritas</td></tr>
          <tr><td>C2</td><td>Kebutuhan</td><td>Benefit</td><td>Semakin tinggi, semakin prioritas</td></tr>
          <tr><td>C3</td><td>Rekomendasi Sistem</td><td>Benefit</td><td>Jumlah belanja akhir dari sistem</td></tr>
        </table>
      </td>
      <td style="width: 22%; padding: 0 0 12px 0;">
        <table>
          <tr><td class="pill">Tanggal Perhitungan</td><td>${escapeHtml(meta.calculationDate)}</td></tr>
          <tr><td class="pill">ID SPK</td><td>${meta.spkId ? `SPK-${String(meta.spkId).padStart(4, "0")}` : "-"}</td></tr>
          <tr><td class="method" colspan="2">METODE<br/>SPK</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <table style="margin-bottom: 12px;">
    <tr><td class="section">${escapeHtml(meta.formulaTitle)}</td></tr>
    <tr><td>${escapeHtml(meta.formulaDescription)}</td></tr>
  </table>

  <table>
    <tr class="head">
      <th>Ranking</th>
      <th>Nama Bahan</th>
      <th>Kategori</th>
      <th>Stok Saat Ini</th>
      <th>Kebutuhan</th>
      <th>Rekomendasi Beli</th>
      <th>Rekomendasi</th>
    </tr>
    ${rows
      .map(
        (row, index) => `
    <tr>
      <td class="rank">${index + 1}</td>
      <td class="text-strong">${escapeHtml(row.itemName)}</td>
      <td>${escapeHtml(row.categoryName ?? "BASAH")}</td>
      <td class="number">${escapeHtml(row.currentStock)}</td>
      <td class="number">${escapeHtml(row.requiredQty)}</td>
      <td class="number text-strong">${escapeHtml(row.recommendedQty)}</td>
      <td class="${row.numericRecommendedQty > 0 ? "ok" : "muted"}">${row.numericRecommendedQty > 0 ? "Direkomendasikan" : "Tidak ada tambahan"}</td>
    </tr>`,
      )
      .join("")}
  </table>`;

  return buildSpreadsheetShell(body);
}
