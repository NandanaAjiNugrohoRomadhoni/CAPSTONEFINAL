"use client";

import { useEffect, useMemo, useState } from "react";
import { Search } from "lucide-react";
import sdk from "@/lib";
import {
  AdminPageHeading,
  ExportButton,
  Pagination,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import DateRangePicker from "@/components/filters/DateRangePicker";
import { buildExportFilename } from "@/lib/export-filename";
import { isIsoDateInRange } from "@/lib/date-range";
import {
  buildSpreadsheetDocument,
  downloadSpreadsheetHtml,
  escapeSpreadsheetHtml,
  formatSpreadsheetNumber,
} from "@/lib/spreadsheet-export";
import { listAllPaginatedRows } from "@/lib/pagination";
import {
  formatActivityDate,
  loadActivityRows,
  markActivityLogSeen,
  type ActivityType,
  type ActivityModule,
  type ActivityRow,
} from "@/data/activity-log";
import type { User } from "@/sdk/types/users";

const ACTIVITY_TYPE_OPTIONS = [
  { value: "Semua Jenis", label: "Semua Jenis" },
  { value: "Create", label: "Create" },
  { value: "Update", label: "Update" },
  { value: "Delete", label: "Delete" },
];

const MODULE_OPTIONS = [
  { value: "Semua Modul", label: "Semua Modul" },
  { value: "Transaksi", label: "Transaksi" },
  { value: "Master Barang", label: "Master Barang" },
  { value: "Menu", label: "Menu" },
  { value: "Pengguna", label: "Pengguna" },
  { value: "SPK", label: "SPK" },
  { value: "Stok", label: "Stok" },
  { value: "Laporan", label: "Laporan" },
];

type ExportActivityRow = {
  no: number;
  rawDate: string;
  dateTimeLabel: string;
  actor: string;
  role: string;
  module: string;
  activity: string;
  detail: string;
  activityType: ActivityType;
};

async function loadAllUsersSortedByCreatedAt(): Promise<User[]> {
  return listAllPaginatedRows<User>(
    sdk.users.list.bind(sdk.users),
    {
      sortBy: "created_at",
      sortDir: "DESC",
    },
    100,
    50,
  );
}

function getUserRoleName(user: User) {
  const rawUser = user as User & { role_name?: string };
  return rawUser.role_name ?? user.role?.name ?? null;
}

function getRoleLabel(roleName: string | null | undefined) {
  const normalized = String(roleName ?? "").trim().toLowerCase();

  switch (normalized) {
    case "admin":
      return "Super Admin";
    case "gudang":
      return "Petugas Gudang";
    case "dapur":
      return "Petugas Gizi";
    default:
      return roleName?.trim() || "-";
  }
}

function getModuleExportLabel(module: ActivityModule) {
  switch (module) {
    case "Transaksi":
      return "Transaksi Barang";
    case "Master Barang":
      return "Manajemen Master Barang";
    case "Menu":
      return "Manajemen Menu";
    case "Pengguna":
      return "Manajemen Pengguna";
    case "SPK":
      return "SPK";
    case "Stok":
      return "Manajemen Stok";
    case "Laporan":
      return "Laporan";
    default:
      return module;
  }
}

function matchesActivityFilters(
  row: ActivityRow,
  filters: {
    searchTerm: string;
    dateRange: { startDate: string; endDate: string };
    selectedActivityType: string;
    selectedModule: string;
  },
) {
  const query = filters.searchTerm.trim().toLowerCase();
  const matchesSearch =
    query.length === 0 ||
    [
      row.actor,
      row.actorInfo?.username,
      row.activityLabel ?? row.activityType,
      row.module,
      row.detail,
      formatActivityDate(row.date),
      row.time,
    ]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(query));

  const matchesDate = isIsoDateInRange(row.date, filters.dateRange);

  const matchesType =
    filters.selectedActivityType === "Semua Jenis" || row.activityType === filters.selectedActivityType;

  const matchesModule =
    filters.selectedModule === "Semua Modul" || row.module === filters.selectedModule;

  return matchesSearch && matchesDate && matchesType && matchesModule;
}

function getExportPeriodLabel(rows: ActivityRow[]) {
  const timestamps = rows
    .map((row) => {
      const value = row.created_at
        ? new Date(row.created_at.replace(" ", "T") + "Z").getTime()
        : new Date(`${row.date}T${row.time.replace(".", ":")}:00+07:00`).getTime();
      return Number.isFinite(value) ? value : null;
    })
    .filter((value): value is number => value !== null);

  if (timestamps.length === 0) {
    return "-";
  }

  const start = new Date(Math.min(...timestamps));
  const end = new Date(Math.max(...timestamps));
  const startLabel = formatActivityDate(start.toISOString().slice(0, 10));
  const endLabel = formatActivityDate(end.toISOString().slice(0, 10));

  return startLabel === endLabel ? startLabel : `${startLabel} s/d ${endLabel}`;
}

function buildCategoryActivityLabels() {
  return [
    "Login Sistem",
    "Logout Sistem",
    "Tambah Data",
    "Ubah Data",
    "Hapus Data",
    "Approval Data",
    "Generate SPK",
    "Export Laporan",
    "Penyesuaian Stok",
    "Transaksi Barang Masuk",
    "Transaksi Barang Keluar",
  ];
}

function localizeActivityDetail(detail: string, row: Pick<ActivityRow, "activityType" | "module">) {
  const rawDetail = detail.trim();
  if (!rawDetail) return "-";

  const transformSubject = (subject: string) => {
    const normalized = subject.trim().toLowerCase();
    if (!normalized) return subject;

    if (normalized.includes("spk history report")) return "laporan riwayat SPK";
    if (normalized.includes("transaction report")) return "laporan riwayat transaksi";
    if (normalized.includes("stock report")) return "laporan stok";
    if (normalized.includes("activity log")) return "log aktivitas";
    if (normalized.includes("stock adjustment")) return "laporan penyesuaian stok";
    if (normalized.includes("menu package")) return "laporan paket menu";
    if (normalized.includes("user")) return "pengguna";
    if (normalized.includes("item")) return "bahan";
    if (normalized.includes("menu")) return "menu";
    if (normalized.includes("report")) return "laporan";

    return subject;
  };

  const buildSentence = (action: string, subject: string) => {
    const cleanSubject = transformSubject(subject).replace(/\s+/g, " ").trim();
    return cleanSubject ? `${action} ${cleanSubject}` : action;
  };

  const lower = rawDetail.toLowerCase();
  if (lower.startsWith("exported ")) {
    const subject = rawDetail.slice("Exported ".length);
    return buildSentence("Mengekspor", subject);
  }

  if (lower.startsWith("created ")) {
    return buildSentence("Menambahkan", rawDetail.slice("Created ".length));
  }

  if (lower.startsWith("updated ")) {
    return buildSentence("Memperbarui", rawDetail.slice("Updated ".length));
  }

  if (lower.startsWith("deleted ")) {
    return buildSentence("Menghapus", rawDetail.slice("Deleted ".length));
  }

  if (lower.startsWith("approved ")) {
    return buildSentence("Menyetujui", rawDetail.slice("Approved ".length));
  }

  if (lower.startsWith("rejected ")) {
    return buildSentence("Menolak", rawDetail.slice("Rejected ".length));
  }

  if (row.activityType === "Delete" && lower.includes("hapus")) {
    return rawDetail.replace(/^deleted\s+/i, "Menghapus ");
  }

  return rawDetail;
}

export default function ActivityLogPage() {
  const [activityRows, setActivityRows] = useState<ActivityRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState("");
  const [dateRange, setDateRange] = useState({ startDate: "", endDate: "" });
  const [selectedActivityType, setSelectedActivityType] = useState("Semua Jenis");
  const [selectedModule, setSelectedModule] = useState("Semua Modul");
  const [currentPage, setCurrentPage] = useState(1);

  // Total pages and count from server-side pagination meta
  const [serverTotalPages, setServerTotalPages] = useState(1);
  const [serverTotalCount, setServerTotalCount] = useState(0);

  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState(searchTerm);

  // Debounce search term changes
  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedSearchTerm(searchTerm);
    }, 300);

    return () => {
      clearTimeout(handler);
    };
  }, [searchTerm]);

  // Reset page when filter inputs change
  useEffect(() => {
    setCurrentPage(1);
  }, [debouncedSearchTerm, selectedActivityType, selectedModule, dateRange]);

  // Check if we can do server-side pagination for active filters
  const canServerPaginate = useMemo(() => {
    // Backend doesn't support date range filtering
    if (dateRange.startDate !== "" || dateRange.endDate !== "") {
      return false;
    }

    // selectedActivityType: "Semua Jenis", "Create", and "Delete" are server-paginated.
    // "Update" maps to multiple backend action types, so it must be client-filtered.
    if (selectedActivityType !== "Semua Jenis" && selectedActivityType !== "Create" && selectedActivityType !== "Delete") {
      return false;
    }

    // selectedModule: "Semua Modul", "Master Barang", "Pengguna", and "Laporan" are server-paginated.
    // "Transaksi", "Menu", "SPK", "Stok" map to multiple tables, so they must be client-filtered.
    if (selectedModule !== "Semua Modul" && selectedModule !== "Master Barang" && selectedModule !== "Pengguna" && selectedModule !== "Laporan") {
      return false;
    }

    return true;
  }, [dateRange, selectedActivityType, selectedModule]);

  // Effect to load data
  useEffect(() => {
    let cancelled = false;

    async function loadRows() {
      setLoading(true);

      const query: any = {
        sortBy: "created_at",
        sortDir: "DESC",
      };

      if (debouncedSearchTerm.trim() !== "") {
        query.q = debouncedSearchTerm.trim();
      }

      if (selectedActivityType === "Create") {
        query.action_type = "create";
      } else if (selectedActivityType === "Delete") {
        query.action_type = "delete";
      }

      if (selectedModule === "Master Barang") {
        query.table_name = "items";
      } else if (selectedModule === "Pengguna") {
        query.table_name = "users";
      } else if (selectedModule === "Laporan") {
        query.table_name = "reports";
      }

      if (canServerPaginate) {
        query.paginate = true;
        query.page = currentPage;
        query.perPage = 10;
      } else {
        query.paginate = false;
      }

      const response = await loadActivityRows(query);
      if (cancelled) return;

      setActivityRows(response.data);
      setError(null);

      if (canServerPaginate) {
        setServerTotalPages(response.meta.totalPages || 1);
        setServerTotalCount(response.meta.total || 0);
      } else {
        setServerTotalPages(1);
        setServerTotalCount(0);
      }

      setLoading(false);

      const data = response.data;
      if (data.length > 0 && currentPage === 1) {
        const latestTimestamp = new Date(`${data[0].date}T${data[0].time.replace(".", ":")}:00+07:00`).getTime();
        markActivityLogSeen(latestTimestamp);
      }
    }

    void loadRows();

    return () => {
      cancelled = true;
    };
  }, [currentPage, debouncedSearchTerm, selectedActivityType, selectedModule, dateRange, canServerPaginate]);

  const filteredRows = useMemo(() => {
    if (canServerPaginate) {
      return activityRows;
    }

    return activityRows.filter((row) => {
      const matchesDate = isIsoDateInRange(row.date, dateRange);

      const matchesType =
        selectedActivityType === "Semua Jenis" || row.activityType === selectedActivityType;

      const matchesModule =
        selectedModule === "Semua Modul" || row.module === selectedModule;

      return matchesDate && matchesType && matchesModule;
    });
  }, [activityRows, canServerPaginate, dateRange, selectedActivityType, selectedModule]);

  const totalPages = useMemo(() => {
    if (canServerPaginate) {
      return serverTotalPages;
    }
    return Math.max(1, Math.ceil(filteredRows.length / 10));
  }, [canServerPaginate, serverTotalPages, filteredRows.length]);

  const safeCurrentPage = useMemo(() => {
    return Math.min(currentPage, totalPages);
  }, [currentPage, totalPages]);

  const pageStartIndex = (safeCurrentPage - 1) * 10;

  const paginatedRows = useMemo(() => {
    if (canServerPaginate) {
      return filteredRows;
    }
    return filteredRows.slice(pageStartIndex, pageStartIndex + 10);
  }, [canServerPaginate, filteredRows, pageStartIndex]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  const totalLabel = useMemo(() => {
    if (canServerPaginate) {
      const start = serverTotalCount === 0 ? 0 : pageStartIndex + 1;
      const end = Math.min(serverTotalCount, pageStartIndex + 10);
      return `${start}-${end} dari ${serverTotalCount} item`;
    } else {
      const start = filteredRows.length === 0 ? 0 : pageStartIndex + 1;
      const end = Math.min(filteredRows.length, pageStartIndex + 10);
      return `${start}-${end} dari ${filteredRows.length} item`;
    }
  }, [canServerPaginate, serverTotalCount, filteredRows.length, pageStartIndex]);

  async function handleExport() {
    if (typeof window === "undefined") {
      return;
    }

    try {
      const exportQuery: Parameters<typeof loadActivityRows>[0] = {
        sortBy: "created_at",
        sortDir: "DESC",
        paginate: false,
      };

      if (debouncedSearchTerm.trim() !== "") {
        exportQuery.q = debouncedSearchTerm.trim();
      }

      if (selectedActivityType === "Create") {
        exportQuery.action_type = "create";
      } else if (selectedActivityType === "Delete") {
        exportQuery.action_type = "delete";
      }

      if (selectedModule === "Master Barang") {
        exportQuery.table_name = "items";
      } else if (selectedModule === "Pengguna") {
        exportQuery.table_name = "users";
      } else if (selectedModule === "Laporan") {
        exportQuery.table_name = "reports";
      }

      const [rowsResponse, users] = await Promise.all([
        loadActivityRows(exportQuery),
        loadAllUsersSortedByCreatedAt(),
      ]);

      const userRoleMap = new Map<string, string>();
      for (const user of users) {
        const roleLabel = getRoleLabel(getUserRoleName(user));
        userRoleMap.set(`id:${user.id}`, roleLabel);
        userRoleMap.set(`username:${String(user.username).trim().toLowerCase()}`, roleLabel);
        userRoleMap.set(`name:${String(user.name).trim().toLowerCase()}`, roleLabel);
      }

      const exportSource = rowsResponse.data.filter((row) =>
        matchesActivityFilters(row, {
          searchTerm: debouncedSearchTerm,
          dateRange,
          selectedActivityType,
          selectedModule,
        }),
      );

      if (exportSource.length === 0) {
        setError("Belum ada data log aktivitas yang bisa diexport dari filter saat ini.");
        return;
      }

      const exportRows: ExportActivityRow[] = exportSource.map((row, index) => {
        const actorId = row.actorInfo?.id ?? null;
        const actorName = String(row.actor ?? "").trim().toLowerCase();
        const actorUsername = String(row.actorInfo?.username ?? "").trim().toLowerCase();
        const actorLabel = row.actor === "Sistem" ? "Sistem" : row.actorInfo?.username?.trim() || row.actor;
        const role =
          row.actor === "Sistem"
            ? "Sistem"
            : (actorId !== null && userRoleMap.get(`id:${actorId}`)) ||
              (actorUsername && userRoleMap.get(`username:${actorUsername}`)) ||
              (actorName && userRoleMap.get(`name:${actorName}`)) ||
              "-";
        return {
          no: index + 1,
          rawDate: row.date,
          dateTimeLabel: `${formatActivityDate(row.date)}<br />${row.time || "-"}`,
          actor: actorLabel,
          role,
          module: getModuleExportLabel(row.module),
          activity: row.activityLabel?.trim() || row.activityType,
          detail: localizeActivityDetail(row.detail || "-", row),
          activityType: row.activityType,
        };
      });

      const totalActivities = exportRows.length;
      const roleCounts = {
        superAdmin: exportRows.filter((row) => row.role === "Super Admin").length,
        gudang: exportRows.filter((row) => row.role === "Petugas Gudang").length,
        gizi: exportRows.filter((row) => row.role === "Petugas Gizi").length,
      };
      const activityCounts = {
        create: exportRows.filter((row) => row.activityType === "Create").length,
        update: exportRows.filter((row) => row.activityType === "Update").length,
        delete: exportRows.filter((row) => row.activityType === "Delete").length,
      };
      const periodLabel = getExportPeriodLabel(exportSource);
      const todayLabel = formatActivityDate(
        new Date(Date.now() + 7 * 60 * 60 * 1000).toISOString().slice(0, 10),
      );

      const summaryHtml = `
        <table class="summary">
          <tr><td class="summary-label">Total Aktivitas Tercatat</td><td class="summary-value">${formatSpreadsheetNumber(totalActivities, 0)} item</td></tr>
          <tr><td class="summary-label">Aktivitas Super Admin</td><td class="summary-value">${formatSpreadsheetNumber(roleCounts.superAdmin, 0)} item</td></tr>
          <tr><td class="summary-label">Aktivitas Petugas Gudang</td><td class="summary-value">${formatSpreadsheetNumber(roleCounts.gudang, 0)} item</td></tr>
          <tr><td class="summary-label">Aktivitas Petugas Gizi</td><td class="summary-value">${formatSpreadsheetNumber(roleCounts.gizi, 0)} item</td></tr>
          <tr><td class="summary-label">Aktivitas Create</td><td class="summary-value">${formatSpreadsheetNumber(activityCounts.create, 0)} item</td></tr>
          <tr><td class="summary-label">Aktivitas Update</td><td class="summary-value">${formatSpreadsheetNumber(activityCounts.update, 0)} item</td></tr>
          <tr><td class="summary-label">Aktivitas Delete</td><td class="summary-value">${formatSpreadsheetNumber(activityCounts.delete, 0)} item</td></tr>
        </table>
      `;

      const filterSummaryHtml = `
        <table>
          <tr><td class="section" colspan="2">RINGKASAN FILTER</td></tr>
          <tr class="head"><th>Jenis</th><th>Keterangan</th></tr>
          <tr><td>Periode</td><td>${escapeSpreadsheetHtml(periodLabel)}</td></tr>
          <tr><td>Nama Pengguna</td><td>${escapeSpreadsheetHtml(debouncedSearchTerm.trim() || "Semua Nama")}</td></tr>
          <tr><td>Role</td><td>${escapeSpreadsheetHtml("Semua Role")}</td></tr>
          <tr><td>Modul</td><td>${escapeSpreadsheetHtml(selectedModule)}</td></tr>
          <tr><td>Jenis Aktivitas</td><td>${escapeSpreadsheetHtml(selectedActivityType)}</td></tr>
        </table>
      `;

      const categoryRows = buildCategoryActivityLabels()
        .map((label, index) => `
          <tr>
            <td class="rank">${index + 1}</td>
            <td>${escapeSpreadsheetHtml(label)}</td>
          </tr>
        `)
        .join("");

      const exportRowsHtml = exportRows
        .map((row) => `
          <tr>
            <td class="rank">${row.no}</td>
            <td class="text-strong">${row.dateTimeLabel}</td>
            <td class="text-strong">${escapeSpreadsheetHtml(row.actor)}</td>
            <td>${escapeSpreadsheetHtml(row.role)}</td>
            <td>${escapeSpreadsheetHtml(row.module)}</td>
            <td>${escapeSpreadsheetHtml(row.activity)}</td>
            <td>${escapeSpreadsheetHtml(row.detail)}</td>
          </tr>
        `)
        .join("");

      const html = buildSpreadsheetDocument({
        title: "LAPORAN LOG AKTIVITAS SISTEM INSTALASI GIZI RSD BALUNG",
        subtitle: "Laporan ini digunakan untuk memantau dan merekam seluruh aktivitas pengguna pada sistem sebagai bentuk audit trail, pengawasan penggunaan sistem, serta pelacakan perubahan data yang dilakukan oleh pengguna.",
        extraStyles: `
          .report-note { color: #475569; font-size: 13px; line-height: 1.45; margin-bottom: 14px; }
          .section-title { background: #DCFCE7; color: #14532D; font-weight: 800; font-size: 14px; }
          .signature-block { margin-top: 18px; width: 260px; text-align: center; }
          .signature-space { height: 58px; }
          .row-gap { margin-top: 14px; }
        `,
        body: `
          <div class="title">LAPORAN LOG AKTIVITAS SISTEM INSTALASI GIZI RSD BALUNG</div>
          <div class="subtitle">Periode : ${escapeSpreadsheetHtml(periodLabel)} &nbsp;&nbsp;&nbsp; Tanggal Cetak : ${escapeSpreadsheetHtml(todayLabel)}</div>

          <div class="report-note">
            Tujuan: Laporan ini digunakan untuk memantau dan merekam seluruh aktivitas pengguna pada sistem sebagai bentuk audit trail, pengawasan penggunaan sistem, serta pelacakan perubahan data yang dilakukan oleh pengguna.
          </div>

          <table class="no-border section-gap">
            <tr>
              <td style="width: 34%; padding: 0 12px 12px 0;">${summaryHtml}</td>
              <td style="width: 66%; padding: 0 0 12px 0;">${filterSummaryHtml}</td>
            </tr>
          </table>

          <table>
            <tr class="head">
              <th>No</th>
              <th>Tanggal &amp; Waktu</th>
              <th>Nama Pengguna</th>
              <th>Role</th>
              <th>Modul</th>
              <th>Jenis Aktivitas</th>
              <th>Detail Aktivitas</th>
            </tr>
            ${exportRowsHtml}
          </table>

          <table class="no-border row-gap">
            <tr>
              <td style="width: 55%; padding: 0 12px 0 0; vertical-align: top;">
                <table>
                  <tr><td class="section-title" colspan="2">Kategori Aktivitas</td></tr>
                  <tr class="head"><th style="width: 60px;">No</th><th>Kategori</th></tr>
                  ${categoryRows}
                </table>
              </td>
              <td style="width: 45%; padding: 0; vertical-align: top;">
                <table>
                  <tr><td class="section-title">Keterangan</td></tr>
                  <tr><td>Setiap aktivitas penting yang dilakukan pengguna akan tercatat secara otomatis oleh sistem.</td></tr>
                  <tr><td>Data log aktivitas tidak dapat diubah atau dihapus oleh pengguna.</td></tr>
                  <tr><td>Laporan ini digunakan untuk kebutuhan audit, monitoring penggunaan sistem, dan investigasi apabila terjadi kesalahan data.</td></tr>
                </table>
              </td>
            </tr>
          </table>

          <div class="signature-block">
            <div>Mengetahui,</div>
            <div class="text-strong" style="margin-top: 6px;">Super Admin</div>
            <div class="signature-space"></div>
            <div>Nama Terang</div>
          </div>
        `,
      });

      downloadSpreadsheetHtml(buildExportFilename("laporan-log-aktivitas-sistem"), html);
    } catch (error) {
      console.error("Failed to export activity log:", error);
      setError("Gagal mengekspor log aktivitas.");
    }
  }


  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Log Aktivitas"
      />

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <SurfaceCard className="overflow-hidden">
        <div className="flex flex-wrap items-center gap-3 border-b border-[#D7E0EE] bg-[#F8FAFC] px-5 py-4">
          <div className="flex flex-1 flex-wrap items-center gap-3">
            <div className="w-full lg:w-[260px]">
              <label className="flex h-12 items-center gap-3 rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-[#94A3B8] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)]">
                <Search size={16} />
                <input
                  className="w-full bg-transparent text-base text-[#334155] outline-none placeholder:text-[#94A3B8]"
                  onChange={(event) => setSearchTerm(event.target.value)}
                  placeholder="Cari..."
                  value={searchTerm}
                />
              </label>
            </div>

            <div className="min-w-[170px]">
              <DateRangePicker
                ariaLabel="Pilih tanggal log aktivitas"
                className="w-full"
                endDate={dateRange.endDate}
                onChange={setDateRange}
                placeholder="dd/mm/yyyy"
                startDate={dateRange.startDate}
              />
            </div>

            <div className="min-w-[170px]">
              <ThemedSelect
                value={selectedActivityType}
                onChange={setSelectedActivityType}
                options={ACTIVITY_TYPE_OPTIONS}
                placeholder="Semua Jenis"
              />
            </div>

            <div className="min-w-[170px]">
              <ThemedSelect
                value={selectedModule}
                onChange={setSelectedModule}
                options={MODULE_OPTIONS}
                placeholder="Semua Modul"
              />
            </div>
          </div>

          <div className="ml-auto">
            <ExportButton onClick={handleExport}>Export Log</ExportButton>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead className="bg-[#F1F5F9] text-[11px] font-semibold uppercase tracking-wide text-gray-500">
              <tr>
                <th className="px-6 py-3">Tanggal</th>
                <th className="px-6 py-3">Jam</th>
                <th className="px-6 py-3">Nama Petugas</th>
                <th className="px-6 py-3">Jenis Aktivitas</th>
                <th className="px-6 py-3">Jenis Modul</th>
                <th className="px-6 py-3">Detail Aktivitas</th>
              </tr>
            </thead>
            <tbody className="bg-white text-sm text-gray-700">
              {!loading && paginatedRows.map((row) => (
                <tr key={row.id} className="border-t border-gray-200 transition hover:bg-gray-50">
                  <td className="px-6 py-4 font-medium text-gray-900">{formatActivityDate(row.date)}</td>
                  <td className="px-6 py-4">{row.time}</td>
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-3">
                      <div className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold text-white ${getAvatarTone(row.activityType)}`}>
                        {row.actorInitials}
                      </div>
            <span className="font-semibold text-gray-900">{row.actorInfo?.username?.trim() || row.actor}</span>
          </div>
        </td>
                  <td className="px-6 py-4">
                    <ActivityBadge tone={row.activityType} />
                  </td>
                  <td className="px-6 py-4">
                    <ModuleBadge label={row.module} />
                  </td>
                  <td className="px-6 py-4 font-semibold text-gray-900">
                    {localizeActivityDetail(row.detail, row)}
                  </td>
                </tr>
                ))}

              {!loading && paginatedRows.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={6}>
                    Belum ada log aktivitas pada filter ini.
                  </td>
                </tr>
              ) : null}

              {loading ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={6}>
                    Memuat log aktivitas...
                  </td>
                </tr>
              ) : null}
            </tbody>
          </table>
        </div>

        <Pagination
          currentPage={safeCurrentPage}
          onPageChange={setCurrentPage}
          totalLabel={totalLabel}
          totalPages={totalPages}
        />
      </SurfaceCard>
    </div>
  );
}

function ActivityBadge({ tone }: { tone: ActivityType }) {
  const palette: Record<ActivityType, string> = {
    Create: "bg-[#DCFCE7] text-[#15803D]",
    Update: "bg-[#DBEAFE] text-[#1D4ED8]",
    Delete: "bg-[#FEE2E2] text-[#DC2626]",
  };

  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-sm font-semibold ${palette[tone]}`}>
      {tone}
    </span>
  );
}

function ModuleBadge({ label }: { label: ActivityModule }) {
  return (
    <span className="inline-flex rounded-full border border-[#D7E0EE] bg-[#F8FAFC] px-3 py-1 text-sm font-semibold text-[#475569]">
      {label}
    </span>
  );
}

function getAvatarTone(activityType: ActivityType) {
  switch (activityType) {
    case "Create":
      return "bg-[#22C55E]";
    case "Update":
      return "bg-[#6366F1]";
    case "Delete":
      return "bg-[#EF4444]";
  }
}
