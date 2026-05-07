"use client";

import type { ReactNode } from "react";
import { useEffect } from "react";
import { usePathname, useRouter } from "next/navigation";
import {
  getRoleHomePath,
  isRoleAllowedPath,
  useAuthStore,
} from "@/store/authStore";

export default function AuthGuard({
  children,
}: Readonly<{
  children: ReactNode;
}>) {
  const pathname = usePathname();
  const router = useRouter();
  const hydrated = useAuthStore((state) => state.hydrated);
  const initialized = useAuthStore((state) => state.initialized);
  const isInitializing = useAuthStore((state) => state.isInitializing);
  const user = useAuthStore((state) => state.user);
  const initialize = useAuthStore((state) => state.initialize);

  useEffect(() => {
    if (!hydrated) {
      return;
    }

    void initialize();
  }, [hydrated, initialize]);

  useEffect(() => {
    if (!hydrated || !initialized || isInitializing) {
      return;
    }

    if (!user) {
      router.replace("/login");
      return;
    }

    const roleName = user.role?.name;
    if (!isRoleAllowedPath(roleName, pathname)) {
      router.replace(getRoleHomePath(roleName));
    }
  }, [hydrated, initialized, isInitializing, pathname, router, user]);

  if (!hydrated || !initialized || isInitializing || !user) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[#F5F7FB] px-6">
        <div className="w-full max-w-sm rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm">
          <div className="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-blue-100 border-t-blue-600" />
          <h1 className="mt-4 text-lg font-semibold text-gray-900">
            Menyiapkan sesi
          </h1>
          <p className="mt-2 text-sm text-gray-500">
            Kami sedang memverifikasi akun dan hak akses Anda.
          </p>
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
