"use client";

import { useEffect, useMemo, useState } from "react";
import { Search } from "lucide-react";
import {
  AdminPageHeading,
  Pagination,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import DateRangePicker from "@/components/filters/DateRangePicker";
import { isIsoDateInRange } from "@/lib/date-range";
import {
  formatActivityDate,
  loadActivityRows,
  markActivityLogSeen,
  type ActivityType,
  type ActivityModule,
  type ActivityRow,
} from "@/data/activity-log";

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

export default function ActivityLogPage() {
  const [activityRows, setActivityRows] = useState<ActivityRow[]>([]);
  const [loading, setLoading] = useState(true);
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


  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Log Aktivitas"
        subtitle="Melihat log aktivitas yang terjadi di sistem"
      />

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
                      <span className="font-semibold text-gray-900">{row.actor}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4">
                    <ActivityBadge tone={row.activityType} />
                  </td>
                  <td className="px-6 py-4">
                    <ModuleBadge label={row.module} />
                  </td>
                  <td className="px-6 py-4 font-semibold text-gray-900">{row.detail}</td>
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
