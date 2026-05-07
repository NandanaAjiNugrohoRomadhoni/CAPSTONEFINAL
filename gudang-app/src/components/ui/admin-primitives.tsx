"use client";

import type { ReactNode } from "react";
import { CalendarDays, Search } from "lucide-react";

export function PageIntro({
  title,
  description,
  action,
}: Readonly<{
  title: string;
  description: string;
  action?: ReactNode;
}>) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 className="text-[22px] font-semibold tracking-[-0.02em] text-slate-900">
          {title}
        </h1>
        <p className="mt-1 text-sm text-slate-400">{description}</p>
      </div>
      {action}
    </div>
  );
}

export function PrimaryAction({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <button
      className="rounded-[10px] bg-[#2456B6] px-4 py-2 text-sm font-medium text-white shadow-[0_10px_24px_rgba(36,86,182,0.24)] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-[#1f4a9d] hover:shadow-[0_16px_30px_rgba(36,86,182,0.28)]"
      type="button"
    >
      {children}
    </button>
  );
}

export function SecondaryAction({ children }: Readonly<{ children: ReactNode }>) {
  return (
    <button
      className="rounded-[10px] border border-[#2456B6] bg-white px-4 py-2 text-sm font-medium text-[#2456B6] transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-50"
      type="button"
    >
      {children}
    </button>
  );
}

export function SectionCard({
  children,
  className = "",
}: Readonly<{
  children: ReactNode;
  className?: string;
}>) {
  return (
    <div
      className={`overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-[0_10px_30px_rgba(15,23,42,0.04)] ${className}`}
    >
      {children}
    </div>
  );
}

export function SearchField({ placeholder }: Readonly<{ placeholder: string }>) {
  return (
    <div className="flex h-10 w-full max-w-[160px] items-center rounded-[10px] border border-slate-200 bg-[#F2F6FD] px-3 text-sm text-slate-500">
      <Search size={15} className="mr-2 text-slate-400" />
      <span className="truncate">{placeholder}</span>
    </div>
  );
}

export function SelectField({ label }: Readonly<{ label: string }>) {
  return (
    <div className="flex h-10 min-w-[104px] items-center justify-between rounded-[10px] border border-slate-200 bg-[#F2F6FD] px-3 text-sm text-slate-600">
      <span>{label}</span>
      <span className="ml-4 text-xs">▼</span>
    </div>
  );
}

export function DateField() {
  return (
    <div className="flex h-10 min-w-[108px] items-center justify-between rounded-[10px] border border-slate-200 bg-[#F2F6FD] px-3 text-sm text-slate-500">
      <span>dd/mm/yyyy</span>
      <CalendarDays size={15} className="text-slate-400" />
    </div>
  );
}

export function Pagination({
  pages = ["1", "2", "3", "4"],
  active = "1",
}: Readonly<{
  pages?: string[];
  active?: string;
}>) {
  return (
    <div className="flex items-center gap-1">
      <button className="flex h-6 w-6 items-center justify-center rounded bg-[#EEF2FA] text-[11px] text-slate-400">
        ‹
      </button>
      {pages.map((page) => (
        <button
          key={page}
          className={`flex h-6 w-6 items-center justify-center rounded text-[11px] ${
            page === active
              ? "bg-[#2456B6] text-white"
              : "bg-[#EEF2FA] text-slate-500"
          }`}
        >
          {page}
        </button>
      ))}
      <button className="flex h-6 w-6 items-center justify-center rounded bg-[#EEF2FA] text-[11px] text-slate-400">
        ›
      </button>
    </div>
  );
}

export function StatusBadge({
  children,
  tone,
}: Readonly<{
  children: ReactNode;
  tone: "green" | "red" | "yellow" | "slate";
}>) {
  const toneClass =
    tone === "green"
      ? "bg-[#E8FFF0] text-[#24A148]"
      : tone === "red"
        ? "bg-[#FFEAEA] text-[#F04438]"
        : tone === "yellow"
          ? "bg-[#FFF2CC] text-[#F79009]"
          : "bg-[#EEF2FA] text-slate-600";

  return (
    <span
      className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${toneClass}`}
    >
      {children}
    </span>
  );
}

export function DataTable({
  header,
  children,
  footerLeft,
  footerRight,
}: Readonly<{
  header: ReactNode;
  children: ReactNode;
  footerLeft: ReactNode;
  footerRight: ReactNode;
}>) {
  return (
    <SectionCard>
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
        {header}
      </div>
      <div className="overflow-x-auto">{children}</div>
      <div className="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 text-xs text-slate-400">
        <span>{footerLeft}</span>
        {footerRight}
      </div>
    </SectionCard>
  );
}
