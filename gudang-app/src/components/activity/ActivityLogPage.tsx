"use client";

import { useEffect, useMemo, useState } from "react";
import { CalendarDays, Search } from "lucide-react";
import {
  AdminPageHeading,
  Pagination,
  SurfaceCard,
  ThemedSelect,
} from "@/components/admin/ui";
import DateRangePicker from "@/components/filters/DateRangePicker";
import { isIsoDateInRange } from "@/lib/date-range";
import { ACTIVITY_ROWS, type ActivityType, type ActivityModule, markActivityLogSeen } from "@/data/activity-log";

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
  const [searchTerm, setSearchTerm] = useState("");
  const [dateRange, setDateRange] = useState({ startDate: "", endDate: "" });
  const [selectedActivityType, setSelectedActivityType] = useState("Semua Jenis");
  const [selectedModule, setSelectedModule] = useState("Semua Modul");
  const [currentPage, setCurrentPage] = useState(1);

  const filteredRows = useMemo(() => {
    const query = searchTerm.trim().toLowerCase();

    return ACTIVITY_ROWS.filter((row) => {
      const matchesSearch =
        query.length === 0 ||
        row.actor.toLowerCase().includes(query) ||
        row.detail.toLowerCase().includes(query) ||
        row.module.toLowerCase().includes(query) ||
        row.activityType.toLowerCase().includes(query);

      const matchesDate = isIsoDateInRange(row.date, dateRange);
      const matchesType =
        selectedActivityType === "Semua Jenis" || row.activityType === selectedActivityType;
      const matchesModule =
        selectedModule === "Semua Modul" || row.module === selectedModule;

      return matchesSearch && matchesDate && matchesType && matchesModule;
    });
  }, [dateRange, searchTerm, selectedActivityType, selectedModule]);

  const pageSize = 10;
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
  const safeCurrentPage = Math.min(currentPage, totalPages);
  const pageStartIndex = (safeCurrentPage - 1) * pageSize;
  const paginatedRows = useMemo(
    () => filteredRows.slice(pageStartIndex, pageStartIndex + pageSize),
    [filteredRows, pageStartIndex],
  );

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  const totalLabel = `${filteredRows.length === 0 ? 0 : pageStartIndex + 1}-${Math.min(filteredRows.length, pageStartIndex + pageSize)} dari ${filteredRows.length} item`;

  useEffect(() => {
    markActivityLogSeen();
  }, []);

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
              {paginatedRows.map((row) => (
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

              {paginatedRows.length === 0 ? (
                <tr>
                  <td className="px-6 py-8 text-center text-gray-400" colSpan={6}>
                    Belum ada log aktivitas pada filter ini.
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

function formatActivityDate(value: string) {
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat("id-ID", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    timeZone: "Asia/Jakarta",
  }).format(date);
}
