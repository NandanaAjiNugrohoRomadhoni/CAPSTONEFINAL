"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { CalendarDays, ChevronLeft, ChevronRight, X } from "lucide-react";
import sdk from "@/lib";
import { formatLongDate, normaliseMealLabel } from "@/lib/admin-utils";
import { listAllPaginatedRows } from "@/lib/pagination";
import {
  AdminPageHeading,
  OutlineAction,
  SurfaceCard,
} from "@/components/admin/ui";

const weekDays = ["SEN", "SEL", "RAB", "KAM", "JUM", "SAB", "MIN"];
const mealTone: Record<string, string> = {
  SIANG: "bg-[#DCEAFE] text-[#0A6DDE]",
  SORE: "bg-[#ECE8FF] text-[#7C3AED]",
  PAGI: "bg-[#FFF4C7] text-[#D97706]",
};
const menuBadgePalette = [
  "bg-[#DCEAFE] text-[#2155CD]",
  "bg-[#DCFCE7] text-[#15803D]",
  "bg-[#FFF4C7] text-[#B45309]",
  "bg-[#ECE8FF] text-[#7C3AED]",
  "bg-[#FCE7F3] text-[#BE185D]",
];
const mealDisplayOrder = ["SIANG", "SORE", "PAGI"];
function getPackageNumber(name: string | null | undefined) {
  const match = String(name ?? "").match(/\d+/);
  return match ? Number(match[0]) : Number.POSITIVE_INFINITY;
}

function sortMenuPackages(packages: MenuRow[]) {
  return [...packages]
    .sort((a, b) => {
    const numberDiff = getPackageNumber(a.name) - getPackageNumber(b.name);
    if (numberDiff !== 0) return numberDiff;
    return a.id - b.id;
  });
}

function toLocalDateString(year: number, monthIndex: number, day: number) {
  return `${year}-${String(monthIndex + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}

type CalendarEntry = {
  date: string;
  day_of_month: number;
  menu_id: number;
  menu_name: string;
};

type MenuRow = Awaited<ReturnType<typeof sdk.menus.list>>["data"][number];
type SlotRow = Awaited<ReturnType<typeof sdk.menus.slots>>["data"][number];
type SelectedMealCard = {
  label: string;
  meal: string;
  tone: string;
  packageName: string;
  dateLabel: string;
};

export default function Page() {
  const today = useMemo(() => new Date(), []);
  const monthPickerRef = useRef<HTMLInputElement | null>(null);
  const [viewDate, setViewDate] = useState<Date>(today);
  const [selectedDay, setSelectedDay] = useState<number>(today.getDate());
  const [calendarEntries, setCalendarEntries] = useState<CalendarEntry[]>([]);
  const [menuPackages, setMenuPackages] = useState<MenuRow[]>([]);
  const [slots, setSlots] = useState<SlotRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedMealCard, setSelectedMealCard] = useState<SelectedMealCard | null>(null);

  useEffect(() => {
    let cancelled = false;
    const month = `${viewDate.getFullYear()}-${String(viewDate.getMonth() + 1).padStart(2, "0")}`;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        const [calendarResponse, menuResponse, slotResponse] = await Promise.all([
          sdk.menuSchedules.calendarProjection({ month }),
          listAllPaginatedRows<MenuRow>(sdk.menus.list.bind(sdk.menus), {
            sortBy: "id",
            sortDir: "ASC",
          }),
          listAllPaginatedRows<SlotRow>(sdk.menus.slots.bind(sdk.menus), {
            sortBy: "id",
            sortDir: "ASC",
          }),
        ]);

        if (cancelled) return;

        const calendarData = Array.isArray(calendarResponse.data)
          ? (calendarResponse.data as CalendarEntry[])
          : calendarResponse.data
            ? [calendarResponse.data as CalendarEntry]
            : [];

        setCalendarEntries(calendarData);
        setMenuPackages(sortMenuPackages(menuResponse));
        setSlots(slotResponse);
      } catch (loadError) {
        if (!cancelled) {
          setError(loadError instanceof Error ? loadError.message : "Gagal memuat kalender menu.");
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    void load();
    return () => {
      cancelled = true;
    };
  }, [viewDate]);

  const firstDay = new Date(viewDate.getFullYear(), viewDate.getMonth(), 1);
  const startDay = firstDay.getDay();
  const daysInMonth = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
  const offset = (startDay + 6) % 7;
  const previousMonthDays = new Date(viewDate.getFullYear(), viewDate.getMonth(), 0).getDate();
  const isTodayVisible =
    viewDate.getFullYear() === today.getFullYear() &&
    viewDate.getMonth() === today.getMonth();

  const calendarCells = useMemo(() => {
    const cells: Array<{ key: string; day: number; currentMonth: boolean }> = [];

    for (let index = offset - 1; index >= 0; index -= 1) {
      cells.push({
        key: `prev-${index}`,
        day: previousMonthDays - index,
        currentMonth: false,
      });
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      cells.push({ key: `current-${day}`, day, currentMonth: true });
    }

    while (cells.length < 42) {
      cells.push({
        key: `next-${cells.length}`,
        day: cells.length - daysInMonth - offset + 1,
        currentMonth: false,
      });
    }

    return cells;
  }, [daysInMonth, offset, previousMonthDays]);

  const projectedEntryMap = useMemo(() => {
    const map = new Map<number, CalendarEntry>();
    for (const entry of calendarEntries) {
      map.set(entry.day_of_month, entry);
    }
    return map;
  }, [calendarEntries]);

  const dayEntryMap = useMemo(() => {
    const map = new Map<number, CalendarEntry>();

    for (let day = 1; day <= daysInMonth; day += 1) {
      const projected = projectedEntryMap.get(day);
      const cycleMenu = menuPackages.length > 0 ? menuPackages[(day - 1) % menuPackages.length] : null;
      if (cycleMenu) {
        map.set(day, {
          date: projected?.date ?? toLocalDateString(viewDate.getFullYear(), viewDate.getMonth(), day),
          day_of_month: day,
          menu_id: cycleMenu.id,
          menu_name: cycleMenu.name,
        });
      } else if (projected) {
        map.set(day, projected);
      }
    }

    return map;
  }, [daysInMonth, menuPackages, projectedEntryMap, viewDate]);

  const selectedEntryByDay = dayEntryMap.get(selectedDay) ?? null;
  const selectedMeals = useMemo(() => {
    if (!selectedEntryByDay) return [];
    const uniqueSlots = new Map<string, SlotRow>();
    for (const slot of slots) {
      if (slot.menu_id !== selectedEntryByDay.menu_id) continue;
      const key = `${normaliseMealLabel(slot.meal_time?.name)}::${slot.dish?.name ?? "-"}`;
      if (!uniqueSlots.has(key)) uniqueSlots.set(key, slot);
    }
    return Array.from(uniqueSlots.values())
      .map((slot) => {
        const label = normaliseMealLabel(slot.meal_time?.name);
        return {
          label,
          meal: slot.dish?.name ?? "-",
          tone: mealTone[label] ?? "bg-[#F8FAFC] text-[#475569]",
          packageName: selectedEntryByDay.menu_name,
          dateLabel: formatLongDate(selectedEntryByDay.date),
        };
      })
      .sort(
        (left, right) =>
          mealDisplayOrder.indexOf(left.label) - mealDisplayOrder.indexOf(right.label),
      );
  }, [selectedEntryByDay, slots]);

  function getMenuBadgeTone(menuId: number) {
    return menuBadgePalette[Math.abs(menuId) % menuBadgePalette.length];
  }

  const monthPickerValue = `${viewDate.getFullYear()}-${String(viewDate.getMonth() + 1).padStart(2, "0")}`;

  return (
    <div className="space-y-5">
      <AdminPageHeading
        title="Kalender Menu"
        subtitle="Atur jadwal menu harian - Klik tanggal untuk melihat detail paket menu"
        action={
          <div className="flex gap-2">
            <OutlineAction
              onClick={() => {
                setViewDate(today);
                setSelectedDay(today.getDate());
              }}
            >
              Hari Ini
            </OutlineAction>
          </div>
        }
      />

      {error ? (
        <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-600">
          {error}
        </div>
      ) : null}

      <SurfaceCard className="overflow-hidden">
        <div className="flex items-center gap-3 border-b bg-[#F8FAFC] px-5 py-4">
          <button
            className="flex h-8 w-8 items-center justify-center rounded-full border border-[#E2E8F0] bg-white text-[#64748B]"
            onClick={() => setViewDate((current) => new Date(current.getFullYear(), current.getMonth() - 1, 1))}
            type="button"
          >
            <ChevronLeft size={14} />
          </button>
          <button
            className="flex h-8 w-8 items-center justify-center rounded-full border border-[#E2E8F0] bg-white text-[#64748B]"
            onClick={() => setViewDate((current) => new Date(current.getFullYear(), current.getMonth() + 1, 1))}
            type="button"
          >
            <ChevronRight size={14} />
          </button>
          <h3 className="text-base font-semibold text-[#16213E]">
            {viewDate.toLocaleDateString("id-ID", { month: "long", year: "numeric" })}
          </h3>
          <div className="relative">
            <input
              ref={monthPickerRef}
              type="month"
              value={monthPickerValue}
              onChange={(event) => {
                if (!event.target.value) return;
                const [year, month] = event.target.value.split("-").map(Number);
                if (!year || !month) return;
                setViewDate(new Date(year, month - 1, 1));
                if (year === today.getFullYear() && month - 1 === today.getMonth()) {
                  setSelectedDay(today.getDate());
                } else {
                  setSelectedDay(1);
                }
              }}
              className="pointer-events-none absolute inset-0 opacity-0"
              tabIndex={-1}
              aria-hidden="true"
            />
            <button
              className="flex h-9 w-9 items-center justify-center rounded-full border border-[#D7E0EE] bg-white text-[#64748B] transition hover:border-[#93B4F7] hover:text-[#2155CD]"
              onClick={() => {
                const picker = monthPickerRef.current;
                if (!picker) return;
                picker.showPicker?.();
                picker.focus();
                picker.click();
              }}
              type="button"
              aria-label="Pilih bulan dan tahun"
            >
              <CalendarDays size={16} />
            </button>
          </div>
        </div>

        <div className="grid grid-cols-7 border-b border-[#E8EEF8] bg-white">
          {weekDays.map((day) => (
            <div key={day} className="border-r border-[#EEF2F7] px-3 py-3 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-500 last:border-r-0">
              {day}
            </div>
          ))}
        </div>

        <div className="grid grid-cols-7">
          {calendarCells.map((cell) => {
            const isSelected = cell.day === selectedDay && cell.currentMonth;
            const dayEntry = cell.currentMonth ? (dayEntryMap.get(cell.day) ?? null) : null;
            const isCurrentToday = isTodayVisible && cell.currentMonth && cell.day === today.getDate();

            return (
              <button
                key={cell.key}
                onClick={() => {
                  if (cell.currentMonth) setSelectedDay(cell.day);
                }}
                className={`relative min-h-[86px] border-r border-b border-[#EEF2F7] px-4 py-3 text-left text-sm last:border-r-0 ${
                  isSelected ? "bg-[#DCEAFE]" : isCurrentToday ? "bg-[#F8FBFF]" : "bg-white hover:bg-[#F8FBFF]"
                }`}
                type="button"
              >
                <div className="flex items-start justify-between gap-2">
                  <span
                    className={`inline-flex h-6 w-6 items-center justify-center rounded-full text-[12px] font-semibold ${
                      isSelected
                        ? "bg-[#2155CD] text-white"
                        : cell.currentMonth
                          ? "text-[#16213E]"
                          : "text-[#CBD5E1]"
                    }`}
                  >
                    {cell.day}
                  </span>
                  {dayEntry ? (
                    <div
                      className={`rounded-[8px] px-2 py-1 text-[11px] font-semibold leading-none ${getMenuBadgeTone(dayEntry.menu_id)}`}
                    >
                      {dayEntry.menu_name}
                    </div>
                  ) : null}
                </div>
              </button>
            );
          })}
        </div>
      </SurfaceCard>

      <SurfaceCard className="overflow-hidden">
        <div className="flex items-center gap-2 border-b border-[#E8EEF8] bg-[#EDF5FF] px-5 py-4">
          <h3 className="text-base font-semibold text-[#16213E]">
            {selectedEntryByDay ? formatLongDate(selectedEntryByDay.date) : "Belum ada jadwal"}
          </h3>
          {selectedEntryByDay ? (
            <span className="rounded-full bg-[#2155CD] px-2 py-0.5 text-[9px] font-bold text-white">
              AKTIF
            </span>
          ) : null}
          {selectedEntryByDay?.menu_name ? (
            <span className={`rounded-[8px] px-2 py-1 text-[11px] font-semibold leading-none ${getMenuBadgeTone(selectedEntryByDay.menu_id)}`}>
              {selectedEntryByDay.menu_name}
            </span>
          ) : null}
        </div>
        <div className="grid gap-3 p-5 md:grid-cols-3">
          {selectedMeals.map((item) => (
            <button
              key={item.label}
              className={`rounded-[10px] px-4 py-4 text-left transition hover:-translate-y-0.5 hover:shadow-[0_12px_28px_rgba(15,23,42,0.10)] ${item.tone}`}
              onClick={() => setSelectedMealCard(item)}
              type="button"
            >
              <p className="text-[10px] font-bold">{item.label}</p>
              <p className="mt-2 text-sm font-semibold text-[#16213E]">{item.meal}</p>
              <p className="mt-1 text-xs text-[#64748B]">
                {selectedEntryByDay?.menu_name ? `Bagian dari ${selectedEntryByDay.menu_name}.` : "Belum ada paket aktif."}
              </p>
            </button>
          ))}
          {!loading && selectedMeals.length === 0 ? (
            <div className="col-span-full rounded-xl bg-[#F8FAFC] px-4 py-8 text-center text-sm text-gray-400">
              Belum ada komposisi menu pada tanggal ini.
            </div>
          ) : null}
        </div>
      </SurfaceCard>

      {selectedMealCard ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center px-4 py-6">
          <div
            className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]"
            onClick={() => setSelectedMealCard(null)}
          />
          <div className="animate-modal-enter relative flex max-h-[calc(100vh-3rem)] w-full max-w-[520px] flex-col overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)]">
            <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
              <div>
                <h2 className="text-[22px] font-semibold text-slate-900">{selectedMealCard.meal}</h2>
                <p className="mt-2 text-sm text-slate-400">{selectedMealCard.dateLabel}</p>
              </div>
              <button
                className="flex h-8 w-8 items-center justify-center rounded-md bg-slate-100 text-slate-400"
                onClick={() => setSelectedMealCard(null)}
                type="button"
              >
                <X size={18} />
              </button>
            </div>

            <div className="space-y-4 px-5 py-5">
              <div className="rounded-[12px] bg-[#EDF4FF] px-4 py-3">
                <p className="text-[11px] font-bold uppercase tracking-wide text-[#2155CD]">Paket Menu</p>
                <p className="mt-1 text-sm font-semibold text-slate-800">{selectedMealCard.packageName}</p>
              </div>

              <div className={`rounded-[12px] px-4 py-4 ${selectedMealCard.tone}`}>
                <p className="text-[10px] font-bold">{selectedMealCard.label}</p>
                <p className="mt-2 text-lg font-semibold text-[#16213E]">{selectedMealCard.meal}</p>
                <p className="mt-1 text-xs text-[#64748B]">Detail menu untuk sesi {selectedMealCard.label.toLowerCase()}.</p>
              </div>
            </div>

            <div className="flex justify-end border-t border-slate-200 px-5 py-4">
              <button
                className="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600"
                onClick={() => setSelectedMealCard(null)}
                type="button"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
