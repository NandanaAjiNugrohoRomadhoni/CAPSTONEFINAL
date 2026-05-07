"use client";

import type { FormEvent } from "react";
import { useEffect, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { Eye, EyeOff, Lock, User2 } from "lucide-react";
import loginBackground from "../../../../IMAGE/BACKGROUND LOGIN.png";
import rsdBalungLogo from "../../../../IMAGE/LOGO RSD BALUNG_VERTIKAL 2.png";
import { getRoleHomePath, useAuthStore } from "@/store/authStore";

export default function LoginPage() {
  const router = useRouter();
  const login = useAuthStore((state) => state.login);
  const initialize = useAuthStore((state) => state.initialize);
  const hydrated = useAuthStore((state) => state.hydrated);
  const initialized = useAuthStore((state) => state.initialized);
  const isInitializing = useAuthStore((state) => state.isInitializing);
  const isAuthenticating = useAuthStore((state) => state.isAuthenticating);
  const error = useAuthStore((state) => state.error);
  const user = useAuthStore((state) => state.user);
  const clearError = useAuthStore((state) => state.clearError);

  const [form, setForm] = useState({
    username: "",
    password: "",
  });
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);

  useEffect(() => {
    if (!hydrated) {
      return;
    }

    void initialize();
  }, [hydrated, initialize]);

  useEffect(() => {
    if (!hydrated || !initialized || isInitializing || !user) {
      return;
    }

    router.replace(getRoleHomePath(user.role?.name));
  }, [hydrated, initialized, isInitializing, router, user]);

  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    clearError();

    try {
      const nextUser = await login(form, rememberMe);
      router.replace(getRoleHomePath(nextUser.role?.name));
    } catch {
      // Error state is stored and shown in the form.
    }
  }

  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#123E8B] px-4 py-8 text-white">
      <Image
        src={loginBackground}
        alt="Latar belakang halaman login RSD Balung"
        fill
        priority
        className="object-cover"
      />

      <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(25,83,184,0.78),rgba(8,43,108,0.84))]" />
      <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.12)_0px,rgba(255,255,255,0.04)_1px,transparent_1px,transparent_44px)] opacity-45" />
      <div className="absolute -left-16 top-8 h-72 w-72 rounded-full bg-white/10 blur-2xl" />
      <div className="absolute bottom-14 left-10 h-64 w-32 rounded-full bg-white/12 blur-md" />
      <div className="absolute right-0 top-0 h-[420px] w-[320px] rounded-full bg-white/10 blur-3xl" />

      <section className="relative z-10 w-full max-w-[360px] overflow-hidden rounded-[28px] border border-white/90 bg-white/100 px-7 py-7 shadow-[0_22px_48px_rgba(7,24,62,0.34)] sm:px-8 sm:py-8">
        <div className="text-center">
          <div className="mx-auto flex justify-center">
            <Image
              src={rsdBalungLogo}
              alt="Logo RSD Balung"
              priority
              className="h-auto w-[76px]"
            />
          </div>
          <h1 className="mt-2 text-[26px] font-black tracking-[-0.03em] text-[#123E8B]">
            RSD Balung
          </h1>
          <p className="mt-1 text-[14px] text-slate-500">
            Sistem Manajemen Gizi Rumah Sakit
          </p>
        </div>

        <div className="mt-7 text-center">
          <h2 className="text-[24px] font-bold tracking-[-0.03em] text-[#123E8B]">
            Selamat Datang
          </h2>
          <p className="mt-1.5 text-[15px] leading-6 text-slate-500">
            Silakan masuk menggunakan akun Anda
          </p>
        </div>

        <form className="mt-6 space-y-4" onSubmit={handleLogin}>
          <div>
            <label className="mb-2 block text-[12px] font-bold uppercase tracking-[0.14em] text-slate-600">
              Username
            </label>
            <div className="flex h-11 items-center rounded-xl border border-slate-200 bg-white px-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.18)] transition focus-within:border-[#3D85FF] focus-within:bg-white">
              <User2 size={16} className="mr-2.5 shrink-0 text-slate-400" />
              <input
                className="w-full bg-transparent text-[16px] text-slate-800 outline-none placeholder:text-slate-400"
                placeholder="Masukkan username"
                value={form.username}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    username: event.target.value,
                  }))
                }
                required
              />
            </div>
          </div>

          <div>
            <label className="mb-2 block text-[12px] font-bold uppercase tracking-[0.14em] text-slate-600">
              Password
            </label>
            <div className="flex h-11 items-center rounded-xl border border-slate-200 bg-white px-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.18)] transition focus-within:border-[#3D85FF] focus-within:bg-white">
              <Lock size={16} className="mr-2.5 shrink-0 text-slate-400" />
              <input
                className="w-full bg-transparent text-[16px] text-slate-800 outline-none placeholder:text-slate-400"
                type={showPassword ? "text" : "password"}
                placeholder="Masukkan password"
                value={form.password}
                onChange={(event) =>
                  setForm((current) => ({
                    ...current,
                    password: event.target.value,
                  }))
                }
                required
              />
              <button
                type="button"
                onClick={() => setShowPassword((current) => !current)}
                className="ml-2 text-slate-400 transition hover:text-slate-700"
                aria-label={showPassword ? "Sembunyikan password" : "Tampilkan password"}
              >
                {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
              </button>
            </div>
          </div>

          <label className="flex items-center gap-2 text-[15px] text-slate-600">
            <input
              type="checkbox"
              checked={rememberMe}
              onChange={(event) => setRememberMe(event.target.checked)}
              className="h-4 w-4 rounded border border-slate-300 bg-transparent accent-[#2D6BFF]"
            />
            Ingat saya
          </label>

          {error && (
            <div className="rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-[16px] text-red-600">
              {error}
            </div>
          )}

          <button
            className="mt-2 h-11 w-full rounded-xl bg-[linear-gradient(180deg,#3D85FF_0%,#2C69EB_100%)] text-[16px] font-semibold text-white shadow-[0_16px_30px_rgba(29,94,255,0.35)] transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60"
            disabled={!hydrated || isInitializing || isAuthenticating}
            type="submit"
          >
            {isInitializing || isAuthenticating ? "Memproses..." : "Masuk"}
          </button>
        </form>

        <p className="mt-5 text-center text-[14px] leading-6 text-slate-500">
          Lupa Password?{" "}
          <span className="font-semibold text-[#123E8B]">Hubungi Super Admin</span>
        </p>
      </section>
    </main>
  );
}
