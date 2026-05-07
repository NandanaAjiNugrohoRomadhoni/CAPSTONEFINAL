"use client";

import type { FormEvent } from "react";
import { useMemo, useState } from "react";
import { Eye, EyeOff, X } from "lucide-react";

export type RoleOption = {
  id: number;
  name: string;
};

export type EditableUser = {
  id: number;
  name: string;
  username: string;
  email: string | null;
  role_id: number;
};

type UserModalProps = {
  open: boolean;
  mode: "create" | "edit";
  roles: RoleOption[];
  initialUser?: EditableUser | null;
  submitting: boolean;
  error: string | null;
  onClose: () => void;
  onSubmit: (payload: {
    name: string;
    username: string;
    password?: string;
    role_id: number;
  }) => Promise<void>;
};

type FormState = {
  name: string;
  username: string;
  password: string;
  roleId: string;
};

function getRoleLabel(roleName: string): string {
  switch (roleName) {
    case "admin":
      return "Super Admin";
    case "gudang":
      return "Gudang";
    case "dapur":
      return "Gizi";
    default:
      return roleName;
  }
}

export default function UserModal({
  open,
  mode,
  roles,
  initialUser,
  submitting,
  error,
  onClose,
  onSubmit,
}: UserModalProps) {
  const initialFormState: FormState = {
    name: initialUser?.name ?? "",
    username: initialUser?.username ?? "",
    password: "",
    roleId: initialUser?.role_id ? String(initialUser.role_id) : "",
  };
  const [showPassword, setShowPassword] = useState(false);
  const [form, setForm] = useState<FormState>(initialFormState);

  const modalTitle = useMemo(
    () => (mode === "create" ? "Buat Akun Pengguna" : "Ubah Akun Pengguna"),
    [mode],
  );

  if (!open) {
    return null;
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    await onSubmit({
      name: form.name.trim(),
      username: form.username.trim(),
      password: mode === "create" ? form.password : undefined,
      role_id: Number(form.roleId),
    });
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center px-4">
      <div
        className="absolute inset-0 bg-black/40 backdrop-blur-sm"
        onClick={onClose}
      />

      <div className="animate-modal-enter relative w-full max-w-xl rounded-2xl bg-white p-6 shadow-xl transition-transform duration-300 ease-out hover:-translate-y-1 hover:shadow-[0_28px_70px_rgba(15,23,42,0.22)]">
        <div className="mb-4 flex items-start justify-between">
          <div>
            <h2 className="text-lg font-semibold text-gray-900">{modalTitle}</h2>
            <p className="text-sm text-gray-400">
              {mode === "create"
                ? "Masukkan detail akun pengguna baru."
                : "Perbarui informasi pengguna yang dipilih."}
            </p>
          </div>

          <button
            onClick={onClose}
            className="rounded-md p-2 text-gray-500 transition-all duration-300 ease-out hover:scale-105 hover:bg-gray-100 hover:text-gray-700"
            type="button"
          >
            <X size={18} />
          </button>
        </div>

        <form className="space-y-4" onSubmit={handleSubmit}>
          <div>
            <label className="text-sm font-medium text-gray-700">
              Nama Lengkap <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={form.name}
              onChange={(event) =>
                setForm((current) => ({ ...current, name: event.target.value }))
              }
              placeholder="Masukkan nama lengkap"
              className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              required
            />
          </div>

          <div>
            <label className="text-sm font-medium text-gray-700">
              Username <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              value={form.username}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  username: event.target.value,
                }))
              }
              placeholder="Masukkan username"
              className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              required
            />
          </div>

          <div>
            <label className="text-sm font-medium text-gray-700">
              {mode === "create" ? (
                <>
                  Password <span className="text-red-500">*</span>
                </>
              ) : (
                "Password Baru"
              )}
            </label>

            <div className="relative mt-1">
              <input
                type={showPassword ? "text" : "password"}
                value={form.password}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    password: event.target.value,
                  }))
                }
                placeholder={
                  mode === "create"
                    ? "Masukkan password"
                    : "Kosongkan jika tidak ingin mengubah password"
                }
                className="w-full rounded-lg border border-gray-200 px-3 py-2.5 pr-10 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
                minLength={mode === "create" || form.password ? 8 : undefined}
                required={mode === "create"}
              />

              <button
                type="button"
                onClick={() => setShowPassword((current) => !current)}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>

            {mode === "edit" && (
              <p className="mt-1 text-xs text-gray-400">
                Isi password baru untuk mengganti password pengguna ini.
              </p>
            )}
          </div>

          <div>
            <label className="text-sm font-medium text-gray-700">
              Role <span className="text-red-500">*</span>
            </label>
            <select
              value={form.roleId}
              onChange={(event) =>
                setForm((current) => ({ ...current, roleId: event.target.value }))
              }
              className="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100"
              required
            >
              <option value="">Pilih role pengguna</option>
              {roles.map((role) => (
                <option key={role.id} value={role.id}>
                  {getRoleLabel(role.name)}
                </option>
              ))}
            </select>
          </div>

          {error && (
            <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              {error}
            </div>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <button
              onClick={onClose}
              className="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-600 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-gray-50"
              type="button"
            >
              Batal
            </button>

            <button
              className="rounded-lg bg-blue-600 px-4 py-2 text-sm text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-[0_14px_30px_rgba(37,99,235,0.28)] disabled:cursor-not-allowed disabled:bg-blue-300 disabled:hover:translate-y-0 disabled:hover:shadow-none"
              disabled={submitting}
              type="submit"
            >
              {submitting ? "Menyimpan..." : "Simpan"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
