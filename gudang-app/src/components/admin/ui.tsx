"use client";

import { useEffect, useRef, useState, type MouseEventHandler, type ReactNode } from "react";
import { createPortal } from "react-dom";
import {
  CalendarDays,
  ChevronLeft,
  ChevronRight,
  Download,
  Search,
} from "lucide-react";

export function AdminPageHeading({
  title,
  subtitle,
  action,
}: Readonly<{
  title: string;
  subtitle: string;
  action?: ReactNode;
}>) {
  return (
    <div className="mb-5 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h2 className="text-[22px] font-semibold text-[#16213E]">{title}</h2>
        <p className="mt-1 text-base text-[#94A3B8]">{subtitle}</p>
      </div>
      {action}
    </div>
  );
}

export function PrimaryAction({
  children,
  disabled = false,
  onClick,
  type = "button",
  className = "",
}: Readonly<{
  children: ReactNode;
  disabled?: boolean;
  onClick?: MouseEventHandler<HTMLButtonElement>;
  type?: "button" | "submit" | "reset";
  className?: string;
}>) {
  return (
    <button
      className={`rounded-lg bg-[#2155CD] px-4 py-2.5 text-base font-medium text-white shadow-[0_10px_24px_rgba(33,85,205,0.24)] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-[0_16px_30px_rgba(33,85,205,0.28)] disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0 disabled:hover:shadow-[0_10px_24px_rgba(33,85,205,0.24)] ${className}`}
      disabled={disabled}
      onClick={onClick}
      type={type}
    >
      {children}
    </button>
  );
}

export function OutlineAction({
  children,
  onClick,
}: Readonly<{
  children: ReactNode;
  onClick?: () => void;
}>) {
  return (
    <button
      className="rounded-lg border border-[#2155CD] bg-white px-4 py-2.5 text-base font-medium text-[#2155CD] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-[#EEF4FF]"
      onClick={onClick}
      type="button"
    >
      {children}
    </button>
  );
}

export function SurfaceCard({
  children,
  className = "",
}: Readonly<{
  children: ReactNode;
  className?: string;
}>) {
  return (
    <div
      className={`rounded-[18px] border border-[#D7E0EE] bg-white shadow-[0_10px_30px_rgba(15,23,42,0.06)] ${className}`}
    >
      {children}
    </div>
  );
}

export function FilterSearch({
  placeholder,
  value = "",
  onChange,
  readOnly = true,
  className = "",
}: Readonly<{
  placeholder: string;
  value?: string;
  onChange?: (value: string) => void;
  readOnly?: boolean;
  className?: string;
}>) {
  return (
    <div className={`flex h-12 items-center gap-3 rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-[#94A3B8] shadow-[inset_0_1px_0_rgba(255,255,255,0.45)] ${className}`}>
      <Search size={16} />
      <input
        className="w-full bg-transparent text-base text-[#334155] outline-none placeholder:text-[#94A3B8]"
        onChange={(event) => onChange?.(event.target.value)}
        placeholder={placeholder}
        readOnly={readOnly}
        value={value}
      />
    </div>
  );
}

export function FilterSelect({
  label,
}: Readonly<{
  label: string;
}>) {
  return (
    <button
      className="flex h-10 min-w-[124px] items-center justify-between gap-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-base text-[#334155] transition-colors duration-200 hover:bg-white"
      type="button"
    >
      <span>{label}</span>
      <ChevronRight size={14} className="rotate-90 text-[#64748B]" />
    </button>
  );
}

export type ThemedSelectOption = {
  value: string;
  label: string;
};

export function ThemedSelect({
  value,
  options,
  onChange,
  className = "",
  placeholder = "Pilih opsi",
  disabled = false,
}: Readonly<{
  value: string;
  options: ThemedSelectOption[];
  onChange: (value: string) => void;
  className?: string;
  placeholder?: string;
  disabled?: boolean;
}>) {
  const [open, setOpen] = useState(false);
  const [rect, setRect] = useState<DOMRect | null>(null);
  const buttonRef = useRef<HTMLButtonElement | null>(null);
  const menuRef = useRef<HTMLDivElement | null>(null);
  const selected = options.find((option) => option.value === value);

  useEffect(() => {
    if (!open) return;

    const updatePosition = () => setRect(buttonRef.current?.getBoundingClientRect() ?? null);
    const closeOnOutside = (event: MouseEvent) => {
      if (buttonRef.current?.contains(event.target as Node)) return;
      if (menuRef.current?.contains(event.target as Node)) return;
      setOpen(false);
    };

    updatePosition();
    window.addEventListener("resize", updatePosition);
    window.addEventListener("scroll", updatePosition, true);
    document.addEventListener("mousedown", closeOnOutside);

    return () => {
      window.removeEventListener("resize", updatePosition);
      window.removeEventListener("scroll", updatePosition, true);
      document.removeEventListener("mousedown", closeOnOutside);
    };
  }, [open]);

  const menu =
    open && rect
        ? createPortal(
          <div
            ref={menuRef}
            className="z-[9999] max-h-64 overflow-auto rounded-xl border border-[#D7E0EE] bg-white p-2 text-base text-[#16213E] shadow-[0_18px_50px_rgba(15,23,42,0.18)]"
            style={{
              left: rect.left,
              minWidth: rect.width,
              position: "fixed",
              top: rect.bottom + 8,
            }}
          >
            {options.map((option) => (
              <button
                key={option.value}
                className={`block w-full rounded-lg px-3 py-2.5 text-left transition-colors ${
                  option.value === value
                    ? "bg-[#EAF1FF] font-semibold text-[#2155CD]"
                    : "hover:bg-[#F1F5F9]"
                }`}
                onClick={() => {
                  onChange(option.value);
                  setOpen(false);
                }}
                type="button"
              >
                {option.label}
              </button>
            ))}
          </div>,
          document.body,
        )
      : null;

  return (
    <>
      <button
        ref={buttonRef}
        className={`flex h-12 items-center justify-between gap-3 rounded-[12px] border border-[#D7E0EE] bg-white px-4 text-base text-[#334155] outline-none transition-all hover:bg-[#F8FAFC] disabled:cursor-not-allowed disabled:bg-[#F8FAFC] disabled:text-[#94A3B8] ${open ? "border-[#2155CD] ring-2 ring-[#DBEAFE]" : ""} ${className}`}
        disabled={disabled}
        onClick={() => {
          if (disabled) return;
          setOpen((current) => !current);
        }}
        type="button"
      >
        <span>{selected?.label ?? placeholder}</span>
        <ChevronRight size={16} className={`text-[#64748B] transition-transform ${open ? "-rotate-90" : "rotate-90"}`} />
      </button>
      {menu}
    </>
  );
}

export function FilterDate() {
  return (
    <button
      className="flex h-10 min-w-[140px] items-center justify-between gap-3 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 text-base text-[#94A3B8] transition-colors duration-200 hover:bg-white"
      type="button"
    >
      <span>dd/mm/yyyy</span>
      <CalendarDays size={14} className="text-[#64748B]" />
    </button>
  );
}

export function ExportButton({
  children = "Export Data",
  onClick,
}: Readonly<{
  children?: ReactNode;
  onClick?: () => void;
}>) {
  return (
    <button
      className="inline-flex h-10 items-center gap-2 rounded-lg border border-[#2155CD] bg-white px-4 text-base font-medium text-[#2155CD] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-[#EEF4FF]"
      onClick={onClick}
      type="button"
    >
      <Download size={14} />
      {children}
    </button>
  );
}

export function MiniActionButton({
  children,
  tone = "neutral",
  variant,
  className = "",
  onClick,
  type = "button",
}: Readonly<{
  children: ReactNode;
  tone?: "neutral" | "danger" | "success";
  variant?: "neutral" | "danger" | "success";
  className?: string;
  onClick?: MouseEventHandler<HTMLButtonElement>;
  type?: "button" | "submit" | "reset";
}>) {
  const normalizedLabel =
    typeof children === "string" ? children.trim().toLowerCase() : "";

  const resolvedTone =
    tone === "danger" ||
    variant === "danger" ||
    normalizedLabel === "hapus" ||
    normalizedLabel === "nonaktifkan"
      ? "danger"
      : tone === "success" || variant === "success" || normalizedLabel === "aktifkan"
        ? "success"
      : normalizedLabel === "edit"
        ? "edit"
        : normalizedLabel === "detail"
          ? "detail"
          : "neutral";

  const toneClass =
    resolvedTone === "danger"
      ? "border border-[#FCA5A5] bg-[#FEE2E2] text-[#B91C1C] shadow-[0_6px_18px_rgba(220,38,38,0.18)] hover:bg-[#FECACA] hover:shadow-[0_10px_24px_rgba(220,38,38,0.24)]"
      : resolvedTone === "success"
        ? "border border-[#86EFAC] bg-[#DCFCE7] text-[#15803D] shadow-[0_6px_18px_rgba(34,197,94,0.18)] hover:bg-[#BBF7D0] hover:shadow-[0_10px_24px_rgba(34,197,94,0.24)]"
        : resolvedTone === "edit"
          ? "border border-[#FDE68A] bg-[#FEF3C7] text-[#B45309] shadow-[0_6px_18px_rgba(245,158,11,0.14)] hover:bg-[#FDE68A] hover:shadow-[0_10px_24px_rgba(245,158,11,0.18)]"
        : resolvedTone === "detail"
          ? "border border-[#BFDBFE] bg-[#DBEAFE] text-[#1D4ED8] shadow-[0_6px_18px_rgba(37,99,235,0.14)] hover:bg-[#BFDBFE] hover:shadow-[0_10px_24px_rgba(37,99,235,0.18)]"
          : "border border-[#CFFAFE] bg-[#ECFEFF] text-[#0F766E] shadow-[0_6px_18px_rgba(20,184,166,0.14)] hover:bg-[#CFFAFE] hover:shadow-[0_10px_24px_rgba(20,184,166,0.18)]";

  return (
    <button
      className={`rounded-lg px-3 py-2 text-sm font-semibold transition-all duration-300 ease-out hover:-translate-y-0.5 ${toneClass} ${className}`}
      onClick={onClick}
      type={type}
    >
      {children}
    </button>
  );
}

export function StatusPill({
  children,
  tone = "safe",
}: Readonly<{
  children: ReactNode;
  tone?: "safe" | "warning" | "critical" | "danger";
}>) {
  const palette: Record<string, string> = {
    safe: "border border-[#86EFAC] bg-[#DCFCE7] text-[#15803D]",
    warning: "border border-[#FCD34D] bg-[#FEF3C7] text-[#D97706]",
    critical: "border border-[#FDBA74] bg-[#FFF7ED] text-[#EA580C]",
    danger: "border border-[#FCA5A5] bg-[#FEE2E2] text-[#DC2626]",
  };

  return (
      <span
      className={`inline-flex rounded-full px-3 py-1 text-sm font-semibold ${palette[tone]}`}
    >
      {children}
    </span>
  );
}

export function Pagination({
  totalLabel,
  currentPage = 1,
  totalPages = 1,
  onPageChange,
}: Readonly<{
  totalLabel: string;
  currentPage?: number;
  totalPages?: number;
  onPageChange?: (page: number) => void;
}>) {
  const safeTotalPages = Math.max(1, totalPages);
  const safeCurrentPage = Math.min(Math.max(1, currentPage), safeTotalPages);

  const pageNumbers = (() => {
    if (safeTotalPages <= 5) {
      return Array.from({ length: safeTotalPages }, (_, index) => index + 1);
    }

    if (safeCurrentPage <= 3) {
      return [1, 2, 3, 4, 5];
    }

    if (safeCurrentPage >= safeTotalPages - 2) {
      return Array.from({ length: 5 }, (_, index) => safeTotalPages - 4 + index);
    }

    return [
      safeCurrentPage - 2,
      safeCurrentPage - 1,
      safeCurrentPage,
      safeCurrentPage + 1,
      safeCurrentPage + 2,
    ];
  })();

  return (
    <div className="flex flex-col items-center gap-4 border-t bg-[#F8FAFC] px-6 py-4 text-sm text-[#94A3B8] lg:flex-row lg:items-center lg:justify-between">
      <span className="text-sm text-[#94A3B8]">{totalLabel}</span>
      <div className="flex flex-1 justify-center">
        <div className="flex items-center gap-3">
          <button
            className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#D7E0EE] bg-white text-[#64748B] transition-all duration-200 hover:bg-[#EEF4FF] disabled:cursor-not-allowed disabled:opacity-45"
            disabled={safeCurrentPage <= 1}
            onClick={() => onPageChange?.(safeCurrentPage - 1)}
            type="button"
          >
            <ChevronLeft size={16} />
          </button>
          {pageNumbers.map((page) => (
            <button
              key={page}
              className={`min-w-[42px] rounded-xl px-3 py-2.5 text-base font-semibold transition-all duration-200 ${
                page === safeCurrentPage
                  ? "bg-[#2155CD] text-white shadow-[0_10px_24px_rgba(33,85,205,0.22)]"
                  : "border border-[#D7E0EE] bg-white text-[#475569] hover:bg-[#EEF4FF]"
              }`}
              onClick={() => onPageChange?.(page)}
              type="button"
            >
              {page}
            </button>
          ))}
          <button
            className="flex h-10 w-10 items-center justify-center rounded-xl border border-[#D7E0EE] bg-white text-[#64748B] transition-all duration-200 hover:bg-[#EEF4FF] disabled:cursor-not-allowed disabled:opacity-45"
            disabled={safeCurrentPage >= safeTotalPages}
            onClick={() => onPageChange?.(safeCurrentPage + 1)}
            type="button"
          >
            <ChevronRight size={16} />
          </button>
        </div>
      </div>
      <div className="hidden lg:block lg:w-[120px]" />
    </div>
  );
}
