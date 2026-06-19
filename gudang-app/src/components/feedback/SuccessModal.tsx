"use client";

import type { ReactNode } from "react";
import { CheckCircle2, X } from "lucide-react";

type SuccessModalProps = {
  open?: boolean;
  isOpen?: boolean;
  title?: string;
  headline: string;
  message: string;
  tone?: "success" | "danger";
  icon?: ReactNode;
  onClose: () => void;
};

export default function SuccessModal({
  open,
  isOpen,
  title = "Berhasil",
  headline,
  message,
  tone = "success",
  icon,
  onClose,
}: Readonly<SuccessModalProps>) {
  const visible = open ?? isOpen ?? false;
  const isDangerTone = tone === "danger";
  const cardClasses = isDangerTone
    ? "border-red-200 bg-red-50"
    : "border-blue-200 bg-blue-50";
  const iconClasses = isDangerTone ? "text-red-600" : "text-blue-600";
  const headlineClasses = isDangerTone ? "text-red-700" : "text-blue-700";
  const buttonClasses = isDangerTone
    ? "bg-red-600 hover:bg-red-700 hover:shadow-[0_14px_30px_rgba(239,68,68,0.28)]"
    : "bg-blue-600 hover:bg-blue-700 hover:shadow-[0_14px_30px_rgba(37,99,235,0.28)]";

  if (!visible) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-[150] flex items-center justify-center px-4">
      <div
        className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]"
        onClick={onClose}
      />

      <div className="animate-modal-enter relative w-full max-w-[360px] overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)] transition-transform duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_28px_70px_rgba(15,23,42,0.22)]">
        <div className="flex items-start justify-between border-b border-slate-200 px-5 py-4">
          <h2 className="text-[22px] font-semibold leading-none text-slate-900">
            {title}
          </h2>

          <button
            className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-slate-400 transition-all duration-300 ease-out hover:scale-105 hover:bg-slate-200 hover:text-slate-500"
            onClick={onClose}
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <div className="px-5 py-5">
          <div className={`rounded-[18px] border px-5 py-6 text-center ${cardClasses}`}>
            <div className={`flex justify-center ${iconClasses}`}>
              {icon ?? <CheckCircle2 size={36} strokeWidth={2.1} />}
            </div>

            <p className={`mt-3 text-base font-semibold ${headlineClasses}`}>
              {headline}
            </p>
            <p className="mt-2 text-sm leading-6 text-slate-600">{message}</p>
          </div>
        </div>

        <div className="flex justify-end border-t border-slate-200 px-5 py-4">
          <button
            className={`rounded-xl px-5 py-2.5 text-sm font-medium text-white transition-all duration-300 ease-out hover:-translate-y-0.5 ${buttonClasses}`}
            onClick={onClose}
            type="button"
          >
            Oke
          </button>
        </div>
      </div>
    </div>
  );
}
