"use client";

import type { ReactNode } from "react";
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
  onClick,
  type = "button",
  className = "",
}: Readonly<{
  children: ReactNode;
  onClick?: () => void;
  type?: "button" | "submit" | "reset";
  className?: string;
}>) {
  return (
    <button
      className={`rounded-lg bg-[#2155CD] px-4 py-2.5 text-base font-medium text-white shadow-[0_10px_24px_rgba(33,85,205,0.24)] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-[0_16px_30px_rgba(33,85,205,0.28)] ${className}`}
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
  onClick,
  type = "button",
}: Readonly<{
  children: ReactNode;
  tone?: "neutral" | "danger";
  onClick?: () => void;
  type?: "button" | "submit" | "reset";
}>) {
  const normalizedLabel =
    typeof children === "string" ? children.trim().toLowerCase() : "";

  const resolvedTone =
    tone === "danger" || normalizedLabel === "hapus"
      ? "danger"
      : normalizedLabel === "edit"
        ? "edit"
        : normalizedLabel === "detail"
          ? "detail"
          : "neutral";

  const toneClass =
    resolvedTone === "danger"
      ? "border border-[#FECACA] bg-[#FEE2E2] text-[#DC2626] shadow-[0_6px_18px_rgba(239,68,68,0.14)] hover:bg-[#FECACA] hover:shadow-[0_10px_24px_rgba(239,68,68,0.18)]"
      : resolvedTone === "edit"
        ? "border border-[#FDE68A] bg-[#FEF3C7] text-[#B45309] shadow-[0_6px_18px_rgba(245,158,11,0.14)] hover:bg-[#FDE68A] hover:shadow-[0_10px_24px_rgba(245,158,11,0.18)]"
        : resolvedTone === "detail"
          ? "border border-[#BFDBFE] bg-[#DBEAFE] text-[#1D4ED8] shadow-[0_6px_18px_rgba(37,99,235,0.14)] hover:bg-[#BFDBFE] hover:shadow-[0_10px_24px_rgba(37,99,235,0.18)]"
          : "border border-[#CFFAFE] bg-[#ECFEFF] text-[#0F766E] shadow-[0_6px_18px_rgba(20,184,166,0.14)] hover:bg-[#CFFAFE] hover:shadow-[0_10px_24px_rgba(20,184,166,0.18)]";

  return (
    <button
      className={`rounded-lg px-3 py-2 text-sm font-semibold transition-all duration-300 ease-out hover:-translate-y-0.5 ${toneClass}`}
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
    safe: "bg-[#DCFCE7] text-[#16A34A]",
    warning: "bg-[#FEF3C7] text-[#F59E0B]",
    critical: "bg-[#FEE2E2] text-[#EF4444]",
    danger: "bg-[#E2E8F0] text-[#334155]",
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
