"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Calendar, ChevronLeft, ChevronRight, X } from "lucide-react";

type DateRangePickerProps = {
  startDate: string;
  endDate: string;
  onChange: (range: { startDate: string; endDate: string }) => void;
  placeholder?: string;
  className?: string;
  ariaLabel?: string;
};

function pad(value: number) {
  return String(value).padStart(2, "0");
}

function toIsoDate(date: Date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function parseIsoDate(value: string) {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!match) return null;
  const [, year, month, day] = match;
  const date = new Date(Number(year), Number(month) - 1, Number(day));
  return Number.isNaN(date.getTime()) ? null : date;
}

function formatDisplayDate(value: string) {
  const date = parseIsoDate(value);
  if (!date) return "";
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
}

function isSameDay(left: Date, right: Date) {
  return (
    left.getFullYear() === right.getFullYear() &&
    left.getMonth() === right.getMonth() &&
    left.getDate() === right.getDate()
  );
}

function startOfMonth(date: Date) {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function addMonths(date: Date, delta: number) {
  return new Date(date.getFullYear(), date.getMonth() + delta, 1);
}

function buildMonthCells(viewDate: Date) {
  const monthStart = startOfMonth(viewDate);
  const startWeekday = monthStart.getDay();
  const daysInMonth = new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 0).getDate();
  const cells: Array<Date | null> = [];

  for (let index = 0; index < startWeekday; index += 1) {
    cells.push(null);
  }

  for (let day = 1; day <= daysInMonth; day += 1) {
    cells.push(new Date(viewDate.getFullYear(), viewDate.getMonth(), day));
  }

  return cells;
}

export default function DateRangePicker({
  startDate,
  endDate,
  onChange,
  placeholder = "dd/mm/yyyy",
  className = "",
  ariaLabel = "Pilih rentang tanggal",
}: Readonly<DateRangePickerProps>) {
  const wrapperRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLButtonElement | null>(null);
  const dropdownRef = useRef<HTMLDivElement | null>(null);
  const [open, setOpen] = useState(false);
  const [viewDate, setViewDate] = useState(() => {
    return parseIsoDate(startDate) ?? new Date();
  });
  const [dropdownStyle, setDropdownStyle] = useState<{
    left: number;
    top?: number;
    bottom?: number;
    width: number;
    maxHeight: number;
  } | null>(null);

  const displayValue = useMemo(() => {
    if (startDate) {
      return formatDisplayDate(startDate);
    }
    if (endDate) {
      return formatDisplayDate(endDate);
    }
    return "";
  }, [endDate, startDate]);

  useEffect(() => {
    if (open) {
      const anchorDate = parseIsoDate(startDate) ?? parseIsoDate(endDate);
      if (anchorDate) {
        setViewDate(startOfMonth(anchorDate));
      }
    }
  }, [endDate, open, startDate]);

  useEffect(() => {
    function handleOutsideClick(event: MouseEvent) {
      const target = event.target as Node;
      if (!wrapperRef.current?.contains(target) && !dropdownRef.current?.contains(target)) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);
    return () => document.removeEventListener("mousedown", handleOutsideClick);
  }, []);

  useEffect(() => {
    if (!open) return;

    function updatePosition() {
      const rect = inputRef.current?.getBoundingClientRect();
      if (!rect) return;

      const viewportHeight = window.innerHeight;
      const spaceBelow = viewportHeight - rect.bottom - 16;
      const spaceAbove = rect.top - 16;
      const shouldOpenUpward = spaceBelow < 360 && spaceAbove > spaceBelow;
      const maxHeight = Math.max(260, Math.min(380, shouldOpenUpward ? spaceAbove - 8 : spaceBelow - 8));

      setDropdownStyle({
        left: rect.left,
        width: rect.width,
        maxHeight,
        ...(shouldOpenUpward
          ? { bottom: viewportHeight - rect.top + 8 }
          : { top: rect.bottom + 8 }),
      });
    }

    updatePosition();
    window.addEventListener("resize", updatePosition);
    window.addEventListener("scroll", updatePosition, true);

    return () => {
      window.removeEventListener("resize", updatePosition);
      window.removeEventListener("scroll", updatePosition, true);
    };
  }, [open]);

  function updateRange(nextDate: Date) {
    const nextIso = toIsoDate(nextDate);
    if (!startDate || (startDate && endDate)) {
      onChange({ startDate: nextIso, endDate: "" });
      return;
    }

    const currentStart = parseIsoDate(startDate);
    if (!currentStart) {
      onChange({ startDate: nextIso, endDate: "" });
      return;
    }

    if (nextDate.getTime() < currentStart.getTime()) {
      onChange({ startDate: nextIso, endDate: "" });
      return;
    }

    onChange({ startDate, endDate: nextIso });
    setOpen(false);
  }

  function renderMonthTitle(date: Date) {
    return new Intl.DateTimeFormat("id-ID", { month: "long", year: "numeric" }).format(date);
  }

  const cells = buildMonthCells(viewDate);
  const start = parseIsoDate(startDate);
  const end = parseIsoDate(endDate);

  const calendar = (
    <div
      ref={dropdownRef}
      className="fixed z-[140] overflow-hidden rounded-2xl border border-[#D7E0EE] bg-white shadow-[0_20px_50px_rgba(15,23,42,0.16)]"
      style={{
        left: dropdownStyle?.left ?? 0,
        top: dropdownStyle?.top,
        bottom: dropdownStyle?.bottom,
        width: Math.max(dropdownStyle?.width ?? 0, 400),
        height: dropdownStyle?.maxHeight ?? 380,
      }}
    >
      <div className="flex h-full flex-col">
        <div className="flex items-start justify-between gap-4 px-5 pt-5 pb-4">
          <div>
            <p className="text-sm font-semibold text-slate-700">Pilih rentang tanggal</p>
            <p className="mt-1 text-xs leading-5 text-slate-400">
              Klik tanggal awal lalu tanggal akhir
            </p>
          </div>
          <button
            className="rounded-lg border border-slate-200 p-1.5 text-slate-500 transition hover:bg-slate-50"
            onClick={() => {
              onChange({ startDate: "", endDate: "" });
              setOpen(false);
            }}
            type="button"
          >
            <X size={16} />
          </button>
        </div>

        <div className="border-t border-slate-100 px-5 pt-4 pb-3">
          <div className="flex items-center justify-between">
            <button
              className="rounded-lg border border-slate-200 p-2.5 text-slate-600 transition hover:bg-slate-50"
              onClick={() => setViewDate((current) => addMonths(current, -1))}
              type="button"
            >
              <ChevronLeft size={16} />
            </button>
            <div className="text-sm font-semibold tracking-wide text-slate-700">
              {renderMonthTitle(viewDate)}
            </div>
            <button
              className="rounded-lg border border-slate-200 p-2.5 text-slate-600 transition hover:bg-slate-50"
              onClick={() => setViewDate((current) => addMonths(current, 1))}
              type="button"
            >
              <ChevronRight size={16} />
            </button>
          </div>
        </div>

        <div className="px-5">
          <div className="grid grid-cols-7 gap-2 text-center text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
            {["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"].map((day) => (
              <div key={day} className="py-1">
                {day}
              </div>
            ))}
          </div>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto px-5 pb-4 pt-2">
          <div className="grid grid-cols-7 gap-2">
            {cells.map((cell, index) => {
              if (!cell) {
                return <div key={`empty-${index}`} className="h-11" />;
              }

              const iso = toIsoDate(cell);
              const selectedStart = start ? isSameDay(cell, start) : false;
              const selectedEnd = end ? isSameDay(cell, end) : false;
              const inRange =
                start &&
                end &&
                cell.getTime() > start.getTime() &&
                cell.getTime() < end.getTime();

              return (
                <button
                  key={iso}
                  className={`h-11 rounded-xl text-sm font-medium transition ${
                    selectedStart || selectedEnd
                      ? "bg-[#2155CD] text-white shadow-[0_10px_20px_rgba(33,85,205,0.18)]"
                      : inRange
                        ? "bg-[#DBEAFE] text-[#1D4ED8]"
                        : "text-slate-700 hover:bg-slate-100"
                  }`}
                  onClick={() => updateRange(cell)}
                  type="button"
                >
                  {cell.getDate()}
                </button>
              );
            })}
          </div>
        </div>

        <div className="flex items-center justify-between border-t border-slate-100 px-5 py-3">
          <div className="max-w-[220px] truncate text-xs text-slate-500">
            {displayValue || placeholder}
          </div>
          <button
            className="rounded-lg border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50"
            onClick={() => setOpen(false)}
            type="button"
          >
            Selesai
          </button>
        </div>
      </div>
    </div>
  );

  return (
    <div ref={wrapperRef} className={`relative ${className}`}>
      <button
        ref={inputRef}
        aria-label={ariaLabel}
        className="flex h-12 w-full items-center justify-between rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-left text-base text-[#334155] outline-none transition focus:border-[#2155CD] focus:ring-2 focus:ring-[#DBEAFE]"
        onClick={() => setOpen((current) => !current)}
        type="button"
      >
        <span className={displayValue ? "text-[#334155]" : "text-[#94A3B8]"}>
          {displayValue || placeholder}
        </span>
        <span className="ml-3 flex items-center gap-2 text-[#94A3B8]">
          <Calendar size={16} />
        </span>
      </button>

      {open && dropdownStyle && typeof document !== "undefined"
        ? createPortal(calendar, document.body)
        : null}
    </div>
  );
}
