"use client";

import { create } from "zustand";
import { createJSONStorage, persist } from "zustand/middleware";
import type { StateStorage } from "zustand/middleware";
import sdk from "@/lib";

export type AuthRole = {
  id: number;
  name: string;
};

export type AuthUser = {
  id: number;
  role_id: number;
  name: string;
  username: string;
  email: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  role?: AuthRole;
};

type LoginPayload = {
  username: string;
  password: string;
};

type PersistenceMode = "local" | "session";

type AuthState = {
  accessToken: string | null;
  user: AuthUser | null;
  persistenceMode: PersistenceMode;
  hydrated: boolean;
  initialized: boolean;
  isInitializing: boolean;
  isAuthenticating: boolean;
  error: string | null;
  setHydrated: (hydrated: boolean) => void;
  syncUser: (user: AuthUser | null) => void;
  clearError: () => void;
  initialize: () => Promise<AuthUser | null>;
  login: (payload: LoginPayload, rememberMe?: boolean) => Promise<AuthUser>;
  logout: () => Promise<void>;
};

const AUTH_STORAGE_KEY = "capstone-auth";

const authStorage: StateStorage = {
  getItem: (name) => {
    if (typeof window === "undefined") {
      return null;
    }

    return window.sessionStorage.getItem(name) ?? window.localStorage.getItem(name);
  },
  setItem: (name, value) => {
    if (typeof window === "undefined") {
      return;
    }

    const parsed = JSON.parse(value) as {
      state?: { persistenceMode?: PersistenceMode };
    };
    const mode = parsed.state?.persistenceMode ?? "session";

    if (mode === "local") {
      window.localStorage.setItem(name, value);
      window.sessionStorage.removeItem(name);
      return;
    }

    window.sessionStorage.setItem(name, value);
    window.localStorage.removeItem(name);
  },
  removeItem: (name) => {
    if (typeof window === "undefined") {
      return;
    }

    window.localStorage.removeItem(name);
    window.sessionStorage.removeItem(name);
  },
};

function getErrorMessage(error: unknown, fallback: string): string {
  if (
    typeof error === "object" &&
    error !== null &&
    "body" in error &&
    typeof (error as { body?: unknown }).body === "object" &&
    (error as { body?: { message?: unknown } }).body !== null &&
    typeof (error as { body?: { message?: unknown } }).body?.message === "string"
  ) {
    return (error as { body: { message: string } }).body.message;
  }

  if (error instanceof Error && error.message) {
    return error.message;
  }

  return fallback;
}

export function getRoleHomePath(role?: string | null): string {
  switch (role) {
    case "admin":
      return "/super-admin";
    case "gudang":
      return "/gudang";
    case "dapur":
      return "/gizi";
    default:
      return "/login";
  }
}

export function getRoleLabel(role?: string | null): string {
  switch (role) {
    case "admin":
      return "Super Admin";
    case "gudang":
      return "Gudang";
    case "dapur":
      return "Gizi";
    default:
      return "Pengguna";
  }
}

export function isRoleAllowedPath(role: string | undefined, pathname: string): boolean {
  if (!role) {
    return false;
  }

  if (pathname.startsWith("/super-admin")) {
    return role === "admin";
  }

  if (pathname.startsWith("/gudang")) {
    return role === "gudang";
  }

  if (pathname.startsWith("/gizi")) {
    return role === "dapur";
  }

  return true;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      accessToken: null,
      user: null,
      persistenceMode: "session",
      hydrated: false,
      initialized: false,
      isInitializing: false,
      isAuthenticating: false,
      error: null,
      setHydrated: (hydrated) => set({ hydrated }),
      syncUser: (user) => set({ user }),
      clearError: () => set({ error: null }),
      initialize: async () => {
        const state = get();

        if (state.isInitializing) {
          return state.user;
        }

        if (!state.accessToken) {
          sdk.clearAccessToken();
          set({
            user: null,
            initialized: true,
            isInitializing: false,
            error: null,
          });
          return null;
        }

        sdk.setAccessToken(state.accessToken);
        set({ isInitializing: true, error: null });

        try {
          const response = await sdk.auth.me();
          const user = response.data as AuthUser;

          set({
            user,
            initialized: true,
            isInitializing: false,
          });

          return user;
        } catch (error) {
          sdk.clearAccessToken();
          set({
            accessToken: null,
            user: null,
            initialized: true,
            isInitializing: false,
            error: getErrorMessage(error, "Sesi tidak valid. Silakan login lagi."),
          });

          return null;
        }
      },
      login: async (payload, rememberMe = false) => {
        set({ isAuthenticating: true, error: null });

        try {
          const response = await sdk.auth.login(payload);
          const user = response.user as AuthUser;

          sdk.setAccessToken(response.access_token);

          set({
            accessToken: response.access_token,
            user,
            persistenceMode: rememberMe ? "local" : "session",
            initialized: true,
            isAuthenticating: false,
            error: null,
          });

          return user;
        } catch (error) {
          sdk.clearAccessToken();
          const message = getErrorMessage(error, "Login gagal.");

          set({
            accessToken: null,
            user: null,
            persistenceMode: "session",
            initialized: true,
            isAuthenticating: false,
            error: message,
          });

          throw error;
        }
      },
      logout: async () => {
        const token = get().accessToken;

        if (token) {
          sdk.setAccessToken(token);

          try {
            await sdk.auth.logout();
          } catch {
            // Clearing local session is more important than surfacing logout failures here.
          }
        }

        sdk.clearAccessToken();
        set({
          accessToken: null,
          user: null,
          persistenceMode: "session",
          initialized: true,
          isInitializing: false,
          isAuthenticating: false,
          error: null,
        });
      },
    }),
    {
      name: AUTH_STORAGE_KEY,
      storage: createJSONStorage(() => authStorage),
      partialize: (state) => ({
        accessToken: state.accessToken,
        user: state.user,
        persistenceMode: state.persistenceMode,
      }),
      onRehydrateStorage: () => (state) => {
        sdk.setAccessToken(state?.accessToken ?? null);
        state?.setHydrated(true);
      },
    },
  ),
);
