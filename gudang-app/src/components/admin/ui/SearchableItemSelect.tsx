"use client";

import { useEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { ChevronDown } from "lucide-react";

type SearchableItemSelectProps = {
  options: Array<{ id: number; label: string; unit: string }>;
  value: number | null;
  placeholder: string;
  onChange: (itemId: number | null, unit?: string) => void;
  className?: string;
  displayValue?: string;
  disabled?: boolean;
};

export default function SearchableItemSelect({
  options,
  value,
  placeholder,
  onChange,
  className = "",
  displayValue = "",
  disabled = false,
}: Readonly<SearchableItemSelectProps>) {
  const wrapperRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);
  const dropdownRef = useRef<HTMLDivElement | null>(null);
  const selectedOption = useMemo(() => options.find((option) => option.id === value) ?? null, [options, value]);
  const [query, setQuery] = useState(selectedOption?.label ?? displayValue);
  const [open, setOpen] = useState(false);
  const [dropdownStyle, setDropdownStyle] = useState<{
    top?: number;
    bottom?: number;
    left: number;
    width: number;
    maxHeight: number;
  } | null>(null);

  useEffect(() => {
    function handleOutsideClick(event: MouseEvent) {
      const target = event.target as Node;
      const clickedInsideInput = wrapperRef.current?.contains(target);
      const clickedInsideDropdown = dropdownRef.current?.contains(target);

      if (!clickedInsideInput && !clickedInsideDropdown) {
        setOpen(false);
      }
    }

    document.addEventListener("mousedown", handleOutsideClick);
    return () => {
      document.removeEventListener("mousedown", handleOutsideClick);
    };
  }, []);

  useEffect(() => {
    if (!open) return;

    function updatePosition() {
      const rect = inputRef.current?.getBoundingClientRect();
      if (!rect) return;

      const viewportHeight = window.innerHeight;
      const spaceBelow = viewportHeight - rect.bottom - 16;
      const spaceAbove = rect.top - 16;
      const shouldOpenUpward = spaceBelow < 240 && spaceAbove > spaceBelow;
      const maxHeight = Math.max(160, Math.min(240, shouldOpenUpward ? spaceAbove - 8 : spaceBelow - 8));

      setDropdownStyle({
        left: rect.left,
        width: rect.width,
        maxHeight,
        ...(shouldOpenUpward ? { bottom: viewportHeight - rect.top + 8 } : { top: rect.bottom + 8 }),
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

  const filteredOptions = useMemo(() => {
    const normalizedQuery = query.trim().toLowerCase();
    if (!normalizedQuery) {
      return options;
    }

    return options.filter((option) => option.label.toLowerCase().includes(normalizedQuery));
  }, [options, query]);

  return (
    <div ref={wrapperRef} className={`relative ${open ? "z-50" : "z-20"} ${className}`}>
      <div className="relative">
        <input
          ref={inputRef}
          value={open ? query : (selectedOption?.label ?? displayValue ?? query)}
          onChange={(event) => {
            if (disabled) return;
            const nextValue = event.target.value;
            setQuery(nextValue);
            setOpen(true);
            if (!nextValue.trim()) {
              onChange(null);
            }
          }}
          onFocus={() => {
            if (disabled) return;
            setQuery(selectedOption?.label ?? displayValue ?? "");
            setOpen(true);
          }}
          disabled={disabled}
          className="h-11 w-full rounded-xl border border-slate-200 bg-white px-4 pr-10 text-sm text-slate-700 outline-none transition disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500 focus:border-[#2563EB] focus:ring-2 focus:ring-[#DBEAFE]"
          placeholder={placeholder}
        />
        <button
          className="absolute right-2 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent"
          onClick={() => {
            if (disabled) return;
            setOpen((current) => {
              const next = !current;
              if (next) {
                setQuery(selectedOption?.label ?? displayValue ?? "");
              }
              return next;
            });
          }}
          type="button"
          disabled={disabled}
        >
          <ChevronDown size={16} className={`transition-transform duration-200 ${open ? "rotate-180" : ""}`} />
        </button>
      </div>

      {open && dropdownStyle ? createPortal(
        <div
          ref={dropdownRef}
          className="fixed z-[140] overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-[0_20px_40px_rgba(15,23,42,0.12)]"
          style={{
            top: dropdownStyle.top,
            bottom: dropdownStyle.bottom,
            left: dropdownStyle.left,
            width: dropdownStyle.width,
            maxHeight: dropdownStyle.maxHeight,
          }}
        >
          <button
            className={`flex w-full items-center rounded-lg px-3 py-2 text-left text-sm transition ${
              value === null ? "bg-[#EEF4FF] font-medium text-[#2563EB]" : "text-slate-600 hover:bg-slate-50"
            }`}
            onClick={() => {
              setQuery("");
              onChange(null);
              setOpen(false);
            }}
            type="button"
          >
            Kosongkan pilihan
          </button>

          {filteredOptions.length > 0 ? (
            filteredOptions.map((option) => (
              <button
                key={option.id}
                className={`mt-1 flex w-full items-center rounded-lg px-3 py-2 text-left text-sm transition ${
                  option.id === value
                    ? "bg-[#EEF4FF] font-medium text-[#2563EB]"
                    : "text-slate-700 hover:bg-slate-50"
                }`}
                onClick={() => {
                  setQuery(option.label);
                  onChange(option.id, option.unit);
                  setOpen(false);
                }}
                type="button"
              >
                {option.label}
              </button>
            ))
          ) : (
            <div className="px-3 py-2 text-sm text-slate-400">Tidak ada bahan yang cocok.</div>
          )}
        </div>,
        document.body,
      ) : null}
    </div>
  );
}
