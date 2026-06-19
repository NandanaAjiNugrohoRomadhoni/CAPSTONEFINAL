import { toIsoDate } from "@/lib/admin-utils";

function slugify(value: string) {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/-+/g, "-")
    .replace(/^-|-$/g, "");
}

export function buildExportFilename(label: string, date = new Date(), extension = "xls") {
  const safeLabel = slugify(label) || "export";
  return `${safeLabel}-${toIsoDate(date)}.${extension.replace(/^\./, "")}`;
}
