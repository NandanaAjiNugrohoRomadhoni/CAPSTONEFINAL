"use client";

const recentDownloadSignatures = new Map<string, number>();
const DOWNLOAD_GUARD_WINDOW_MS = 1500;

export function escapeSpreadsheetHtml(value: unknown) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}

export function formatSpreadsheetNumber(value: number, maximumFractionDigits = 2) {
  return new Intl.NumberFormat("id-ID", {
    maximumFractionDigits,
  }).format(value);
}

export function buildSpreadsheetDocument({
  title,
  subtitle,
  body,
  extraStyles = "",
}: {
  title: string;
  subtitle?: string;
  body: string;
  extraStyles?: string;
}) {
  return `<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <style>
    body { font-family: Arial, sans-serif; color: #173321; }
    .title { font-size: 28px; font-weight: 800; color: #166534; margin-bottom: 6px; }
    .subtitle { color: #4B5563; font-size: 14px; margin-bottom: 16px; }
    table { border-collapse: collapse; width: 100%; }
    td, th { border: 1px solid #D9EAD3; padding: 10px 12px; font-size: 14px; vertical-align: middle; }
    .no-border td { border: 0; }
    .summary { background: #F0FDF4; border: 1px solid #BBF7D0; }
    .summary-label { color: #14532D; font-weight: 700; width: 190px; font-size: 14px; }
    .summary-value { color: #111827; font-weight: 600; font-size: 14px; }
    .pill { font-weight: 800; text-align: center; font-size: 14px; border: 1px solid transparent; }
    .pill.safe { background: #DCFCE7; color: #15803D; border-color: #86EFAC; }
    .pill.warning { background: #FEF3C7; color: #D97706; border-color: #FCD34D; }
    .pill.critical { background: #FFF7ED; color: #EA580C; border-color: #FDBA74; }
    .pill.danger { background: #FEE2E2; color: #DC2626; border-color: #FCA5A5; }
    .status { text-align: center; font-weight: 800; font-size: 14px; border: 1px solid transparent; }
    .status.safe { background: #DCFCE7; color: #15803D; border-color: #86EFAC; }
    .status.warning { background: #FEF3C7; color: #D97706; border-color: #FCD34D; }
    .status.critical { background: #FFF7ED; color: #EA580C; border-color: #FDBA74; }
    .status.danger { background: #FEE2E2; color: #DC2626; border-color: #FCA5A5; }
    .method { background: #ECFDF5; color: #166534; font-weight: 800; font-size: 22px; text-align: center; }
    .section { background: #DCFCE7; color: #14532D; font-weight: 800; font-size: 14px; }
    .head { background: #166534; color: #FFFFFF; font-weight: 800; text-align: center; font-size: 14px; }
    .rank { text-align: center; font-weight: 700; font-size: 14px; }
    .number { text-align: right; font-size: 14px; }
    .ok { color: #15803D; font-weight: 700; font-size: 14px; }
    .muted { color: #64748B; font-size: 14px; }
    .danger { color: #DC2626; font-weight: 700; }
    .warning { color: #D97706; font-weight: 700; }
    .safe { color: #15803D; font-weight: 700; }
    .text-strong { font-weight: 700; font-size: 14px; }
    .section-gap { margin-bottom: 12px; }
    ${extraStyles}
  </style>
</head>
<body>
${body}
</body>
</html>`;
}

export function downloadSpreadsheetHtml(filename: string, html: string) {
  const signature = buildDownloadSignature(filename, html);
  const now = Date.now();
  const lastRunAt = recentDownloadSignatures.get(signature) ?? 0;

  if (now - lastRunAt < DOWNLOAD_GUARD_WINDOW_MS) {
    return;
  }

  recentDownloadSignatures.set(signature, now);

  const blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);

  window.setTimeout(() => {
    if (recentDownloadSignatures.get(signature) === now) {
      recentDownloadSignatures.delete(signature);
    }
  }, DOWNLOAD_GUARD_WINDOW_MS);
}

function buildDownloadSignature(filename: string, html: string) {
  let hash = 2166136261;
  const source = `${filename}\u0000${html}`;

  for (let index = 0; index < source.length; index += 1) {
    hash ^= source.charCodeAt(index);
    hash = Math.imul(hash, 16777619);
  }

  return `${filename}:${hash >>> 0}`;
}

