"use client";

import { X } from "lucide-react";

type GenerateSpkConfirmModalProps = {
  open: boolean;
  targetLabel: string;
  loading?: boolean;
  onClose: () => void;
  onConfirm: () => void;
};

export default function GenerateSpkConfirmModal({
  open,
  targetLabel,
  loading = false,
  onClose,
  onConfirm,
}: Readonly<GenerateSpkConfirmModalProps>) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center px-4">
      <div className="absolute inset-0 bg-slate-900/35 backdrop-blur-[2px]" onClick={loading ? undefined : onClose} />
      <div className="relative w-full max-w-[520px] overflow-hidden rounded-[24px] bg-white shadow-[0_24px_70px_rgba(15,23,42,0.22)]">
        <div className="flex items-start justify-between border-b border-slate-200 px-6 py-5">
          <div>
            <h2 className="text-[26px] font-semibold leading-tight text-[#16213E]">Konfirmasi Generate SPK</h2>
            <p className="mt-2 text-base text-[#8A9BB8]">Pastikan rekomendasi siap dibuat dan disimpan.</p>
          </div>
          <button
            className="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-600 disabled:cursor-not-allowed disabled:opacity-60"
            disabled={loading}
            onClick={onClose}
            type="button"
          >
            <X size={22} />
          </button>
        </div>

        <div className="px-6 py-6">
          <div className="rounded-[20px] border border-blue-200 bg-[#EEF4FF] px-5 py-5 text-center text-base leading-8 text-[#334155]">
            Apakah anda ingin melihat rekomendasi belanja bahan untuk{" "}
            <span className="font-semibold text-[#2155CD]">{targetLabel}</span> dan disimpan kedalam riwayat SPK?
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-slate-200 px-6 py-5">
          <button
            className="rounded-xl border border-slate-300 px-6 py-3 text-base font-medium text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
            disabled={loading}
            onClick={onClose}
            type="button"
          >
            Batal
          </button>
          <button
            className="rounded-xl bg-[#2155CD] px-7 py-3 text-base font-semibold text-white shadow-[0_12px_24px_rgba(33,85,205,0.22)] transition hover:bg-[#1D4BC0] disabled:cursor-not-allowed disabled:bg-blue-300"
            disabled={loading}
            onClick={onConfirm}
            type="button"
          >
            {loading ? "Menyimpan..." : "Simpan"}
          </button>
        </div>
      </div>
    </div>
  );
}
