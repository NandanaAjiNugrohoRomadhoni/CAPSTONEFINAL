"use client";

import { AlertTriangle, X } from "lucide-react";

type DeleteConfirmModalProps = {
  open?: boolean;
  isOpen?: boolean;
  title?: string;
  headline?: string;
  description?: string;
  message?: string;
  confirmLabel?: string;
  cancelLabel?: string;
  submitting?: boolean;
  loading?: boolean;
  error?: string | null;
  onClose: () => void;
  onConfirm: () => void | Promise<void>;
};

export default function DeleteConfirmModal({
  open,
  isOpen,
  title = "Konfirmasi Hapus",
  headline = "Data akan dihapus",
  description,
  message,
  confirmLabel = "Hapus",
  cancelLabel = "Batal",
  submitting = false,
  loading = false,
  error = null,
  onClose,
  onConfirm,
}: Readonly<DeleteConfirmModalProps>) {
  const visible = open ?? isOpen ?? false;
  const busy = submitting || loading;
  const resolvedDescription = description ?? message ?? "Apakah Anda yakin ingin menghapus data ini?";

  if (!visible) {
    return null;
  }

  return (
    <div className="fixed inset-0 z-[55] flex items-center justify-center px-4">
      <div
        className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]"
        onClick={onClose}
      />

      <div className="animate-modal-enter relative w-full max-w-[400px] overflow-hidden rounded-[22px] bg-white shadow-[0_20px_60px_rgba(15,23,42,0.18)] transition-transform duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_28px_70px_rgba(15,23,42,0.22)]">
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
          <div className="rounded-[18px] border border-red-200 bg-red-50 px-5 py-6 text-center">
            <div className="flex justify-center text-slate-700">
              <AlertTriangle size={36} strokeWidth={1.9} />
            </div>

            <p className="mt-3 text-base font-semibold text-red-600">{headline}</p>
            <p className="mt-2 text-sm leading-7 text-slate-500">{resolvedDescription}</p>
            {error && <p className="mt-3 text-sm font-medium text-red-600">{error}</p>}
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-slate-200 px-5 py-4">
          <button
            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-slate-50"
            onClick={onClose}
            type="button"
          >
            {cancelLabel}
          </button>
          <button
            className="rounded-xl bg-red-500 px-5 py-2.5 text-sm font-medium text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-red-600 hover:shadow-[0_14px_30px_rgba(239,68,68,0.24)] disabled:cursor-not-allowed disabled:bg-red-300 disabled:hover:translate-y-0 disabled:hover:shadow-none"
            disabled={busy}
            onClick={() => void onConfirm()}
            type="button"
          >
            {busy ? "Menghapus..." : confirmLabel}
          </button>
        </div>
      </div>
    </div>
  );
}
