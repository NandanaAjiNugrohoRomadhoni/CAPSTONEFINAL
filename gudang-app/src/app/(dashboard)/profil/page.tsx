"use client";

import { useMemo, useState } from "react";
import {
  CircleUserRound,
  Eye,
  EyeOff,
  KeyRound,
  Lock,
  User,
  UserRound,
} from "lucide-react";
import SuccessModal from "@/components/feedback/SuccessModal";
import { PrimaryAction, SurfaceCard } from "@/components/admin/ui";
import { getRoleLabel, useAuthStore } from "@/store/authStore";
import { useRouter } from "next/navigation";

type SuccessState = {
  headline: string;
  message: string;
} | null;

function getErrorMessage(error: unknown, fallback: string): string {
  if (
    typeof error === "object" &&
    error !== null &&
    "body" in error &&
    typeof (error as { body?: unknown }).body === "object" &&
    (error as { body?: { message?: unknown; errors?: Record<string, unknown> } }).body !== null
  ) {
    const body = (error as {
      body: { message?: unknown; errors?: Record<string, unknown> };
    }).body;

    if (body.errors && typeof body.errors === "object") {
      const firstError = Object.values(body.errors).find(
        (value) => typeof value === "string" && value.trim().length > 0,
      );

      if (typeof firstError === "string") {
        return firstError;
      }
    }

    if (typeof body.message === "string") {
      return body.message;
    }
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

async function changeOwnPassword(params: {
  accessToken: string;
  currentPassword: string;
  password: string;
}): Promise<void> {
  const response = await fetch(
    `${process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8080"}/api/v1/auth/password`,
    {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${params.accessToken}`,
      },
      body: JSON.stringify({
        current_password: params.currentPassword,
        password: params.password,
      }),
    },
  );

  if (!response.ok) {
    let message = "Gagal mengubah password.";

    try {
      const body = (await response.json()) as {
        message?: string;
        errors?: Record<string, string>;
      };

      const firstError = body.errors ? Object.values(body.errors)[0] : null;
      message = typeof firstError === "string" ? firstError : body.message ?? message;
    } catch {
      // Ignore JSON parsing failures and keep fallback message.
    }

    throw new Error(message);
  }
}

export default function ProfilePage() {
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const accessToken = useAuthStore((state) => state.accessToken);
  const logout = useAuthStore((state) => state.logout);
  const [activeTab, setActiveTab] = useState<"info" | "password">("info");
  const [passwordSubmitting, setPasswordSubmitting] = useState(false);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [successState, setSuccessState] = useState<SuccessState>(null);
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const initials = useMemo(
    () =>
      user?.name
        ?.split(" ")
        .map((part) => part.charAt(0))
        .slice(0, 2)
        .join("")
        .toUpperCase() || "AU",
    [user?.name],
  );

  const maskedPassword = useMemo(() => "********", []);

  async function handlePasswordChange() {
    if (!user || !accessToken) {
      return;
    }

    if (!currentPassword.trim()) {
      setPasswordError("Password saat ini wajib diisi.");
      return;
    }

    if (!newPassword.trim() || !confirmPassword.trim()) {
      setPasswordError("Password baru dan konfirmasi password wajib diisi.");
      return;
    }

    if (newPassword !== confirmPassword) {
      setPasswordError("Konfirmasi password belum sama.");
      return;
    }

    setPasswordSubmitting(true);
    setPasswordError(null);

    try {
      await changeOwnPassword({
        accessToken,
        currentPassword: currentPassword.trim(),
        password: newPassword.trim(),
      });
      setCurrentPassword("");
      setNewPassword("");
      setConfirmPassword("");
      setSuccessState({
        headline: "Password Berhasil Diubah",
        message: "Password akun Anda berhasil diperbarui. Silakan login kembali.",
      });
      router.refresh();
    } catch (error) {
      setPasswordError(
        getErrorMessage(
          error,
          "Gagal mengubah password.",
        ),
      );
    } finally {
      setPasswordSubmitting(false);
    }
  }

  if (!user) {
    return null;
  }

  return (
    <>
      <div className="space-y-5">
        <div>
          <h2 className="text-[22px] font-semibold text-[#16213E]">Profil Saya</h2>
          <p className="mt-1 text-sm text-[#94A3B8]">
            Kelola profil dan informasi akun Anda
          </p>
        </div>

        <div className="grid gap-4 xl:grid-cols-[320px_1fr]">
          <SurfaceCard className="p-5">
            <div className="flex flex-col items-center">
              <div className="flex h-24 w-24 items-center justify-center rounded-full bg-[#4E7BF4] text-[30px] font-semibold text-white shadow-[0_12px_28px_rgba(78,123,244,0.35)]">
                {initials}
              </div>

              <h3 className="mt-4 text-[22px] font-semibold text-[#16213E]">{user.name}</h3>
              <span className="mt-2 rounded-full bg-[#EEF4FF] px-3 py-1 text-xs font-semibold text-[#2155CD]">
                {getRoleLabel(user.role?.name)}
              </span>
            </div>

            <div className="mt-5 border-t border-[#E5EDF7] pt-5">
              <div className="space-y-4">
                <div className="border-b border-dashed border-[#E5EDF7] pb-3">
                  <div className="flex items-center gap-2 text-sm text-[#94A3B8]">
                    <UserRound size={14} />
                    <span>Nama Lengkap</span>
                  </div>
                  <p className="mt-1 text-sm font-semibold text-[#16213E]">{user.name}</p>
                </div>

                <div className="border-b border-dashed border-[#E5EDF7] pb-3">
                  <div className="flex items-center gap-2 text-sm text-[#94A3B8]">
                    <Lock size={14} />
                    <span>Password</span>
                  </div>
                  <p className="mt-1 text-sm font-semibold text-[#16213E]">{maskedPassword}</p>
                </div>

                <div>
                  <div className="flex items-center gap-2 text-sm text-[#94A3B8]">
                    <CircleUserRound size={14} />
                    <span>Status Akun</span>
                  </div>
                  <div className="mt-2 inline-flex items-center gap-2 rounded-full bg-[#DCFCE7] px-3 py-1 text-xs font-semibold text-[#16A34A]">
                    <span className="h-2 w-2 rounded-full bg-[#22C55E]" />
                    {user.is_active ? "Aktif" : "Nonaktif"}
                  </div>
                </div>
              </div>
            </div>
          </SurfaceCard>

          <SurfaceCard className="overflow-hidden">
            <div className="flex border-b border-[#E5EDF7] px-4 pt-4">
              <button
                className={`rounded-t-xl px-4 py-3 text-sm font-semibold transition ${
                  activeTab === "info"
                    ? "bg-[#EEF4FF] text-[#2155CD]"
                    : "text-[#64748B] hover:text-[#16213E]"
                }`}
                onClick={() => setActiveTab("info")}
                type="button"
              >
                Informasi Akun
              </button>
              <button
                className={`rounded-t-xl px-4 py-3 text-sm font-semibold transition ${
                  activeTab === "password"
                    ? "bg-[#EEF4FF] text-[#2155CD]"
                    : "text-[#64748B] hover:text-[#16213E]"
                }`}
                onClick={() => setActiveTab("password")}
                type="button"
              >
                Ubah Password
              </button>
            </div>

            {activeTab === "info" ? (
              <div className="p-5">
                <div className="flex items-center gap-2 border-b border-[#E5EDF7] pb-4">
                  <UserRound size={16} className="text-[#64748B]" />
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-[#16213E]">Data Pribadi & Akun</p>
                  </div>
                  <p className="text-sm font-semibold text-[#94A3B8]">Informasi Akun</p>
                </div>

                <div className="mt-5 space-y-4">
                  <div>
                    <label className="block text-sm font-semibold text-[#64748B]">Nama Lengkap</label>
                    <div className="mt-2 flex h-11 items-center rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 text-[#94A3B8]">
                      <UserRound size={16} />
                      <span className="ml-3 text-sm text-[#64748B]">{user.name}</span>
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-[#64748B]">Username</label>
                    <div className="mt-2 flex h-11 items-center rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 text-[#94A3B8]">
                      <User size={16} />
                      <span className="ml-3 text-sm text-[#64748B]">{user.username}</span>
                    </div>
                  </div>

                </div>
              </div>
            ) : (
              <div className="p-5">
                <div className="flex items-center gap-2 border-b border-[#E5EDF7] pb-4">
                  <KeyRound size={16} className="text-[#64748B]" />
                  <p className="text-sm font-semibold text-[#16213E]">Keamanan Akun</p>
                </div>

                <div className="mt-5 space-y-4">
                  <div>
                    <label className="block text-sm font-semibold text-[#64748B]">Password Saat Ini</label>
                    <div className="mt-2 flex h-11 items-center rounded-xl border border-[#E2E8F0] bg-white px-4">
                      <Lock size={16} className="text-[#94A3B8]" />
                      <input
                        value={currentPassword}
                        onChange={(event) => setCurrentPassword(event.target.value)}
                        type={showCurrentPassword ? "text" : "password"}
                        className="ml-3 w-full bg-transparent text-sm text-[#16213E] outline-none"
                        placeholder="Masukkan password saat ini"
                      />
                      <button
                        className="text-[#64748B]"
                        onClick={() => setShowCurrentPassword((current) => !current)}
                        type="button"
                      >
                        {showCurrentPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                      </button>
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-[#64748B]">Password Baru</label>
                    <div className="mt-2 flex h-11 items-center rounded-xl border border-[#E2E8F0] bg-white px-4">
                      <Lock size={16} className="text-[#94A3B8]" />
                      <input
                        value={newPassword}
                        onChange={(event) => setNewPassword(event.target.value)}
                        type={showNewPassword ? "text" : "password"}
                        className="ml-3 w-full bg-transparent text-sm text-[#16213E] outline-none"
                        placeholder="Masukkan password baru"
                      />
                      <button
                        className="text-[#64748B]"
                        onClick={() => setShowNewPassword((current) => !current)}
                        type="button"
                      >
                        {showNewPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                      </button>
                    </div>
                  </div>

                  <div>
                    <label className="block text-sm font-semibold text-[#64748B]">Konfirmasi Password Baru</label>
                    <div className="mt-2 flex h-11 items-center rounded-xl border border-[#E2E8F0] bg-white px-4">
                      <Lock size={16} className="text-[#94A3B8]" />
                      <input
                        value={confirmPassword}
                        onChange={(event) => setConfirmPassword(event.target.value)}
                        type={showConfirmPassword ? "text" : "password"}
                        className="ml-3 w-full bg-transparent text-sm text-[#16213E] outline-none"
                        placeholder="Konfirmasi password baru"
                      />
                      <button
                        className="text-[#64748B]"
                        onClick={() => setShowConfirmPassword((current) => !current)}
                        type="button"
                      >
                        {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                      </button>
                    </div>
                  </div>

                  {passwordError ? (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600">
                      {passwordError}
                    </div>
                  ) : null}
                </div>

                <div className="mt-6 flex justify-end">
                  <div className="flex gap-3">
                    <button
                      className="rounded-xl border border-blue-200 px-4 py-2 text-sm font-medium text-blue-600 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-50"
                      onClick={() => {
                        setCurrentPassword("");
                        setNewPassword("");
                        setConfirmPassword("");
                        setPasswordError(null);
                      }}
                      type="button"
                    >
                      Batal
                    </button>
                    <PrimaryAction onClick={() => void handlePasswordChange()}>
                      {passwordSubmitting ? "Menyimpan..." : "Simpan Password"}
                    </PrimaryAction>
                  </div>
                </div>
              </div>
            )}
          </SurfaceCard>
        </div>
      </div>

      <SuccessModal
        open={successState !== null}
        title="Berhasil"
        headline={successState?.headline ?? ""}
        message={successState?.message ?? ""}
        onClose={() => {
          const shouldRelogin = successState?.headline === "Password Berhasil Diubah";
          setSuccessState(null);

          if (shouldRelogin) {
            void logout().finally(() => {
              router.replace("/login");
            });
          }
        }}
      />
    </>
  );
}
