"use client";

import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";
import Image from "next/image";
import { usePathname } from "next/navigation";
import Link from "next/link";
import { LucideIcon } from "lucide-react";
import {
  Archive,
  LayoutDashboard,
  Users,
  Settings,
  Package,
  ClipboardList,
  Utensils,
  ChevronDown,
  FileText,
  ShoppingCart,
} from "lucide-react";
import rsdBalungLogo from "../../../IMAGE/LOGO RSD BALUNG_VERTIKAL 2.png";
import { useAuthStore } from "@/store/authStore";

type SubMenu = {
  name: string;
  path: string;
};

type MenuItem = {
  name: string;
  path?: string;
  icon: LucideIcon;
  children?: SubMenu[];
};

const menuConfig: Record<string, MenuItem[]> = {
  admin: [
    { name: "Dashboard", path: "/super-admin", icon: LayoutDashboard },
    { name: "Manajemen Pengguna", path: "/super-admin/users", icon: Users },
    {
      name: "Konfigurasi Master Data",
      icon: Settings,
      children: [
        { name: "Data Jenis Bahan", path: "/super-admin/master-data/jenis" },
        { name: "Data Satuan", path: "/super-admin/master-data/satuan" },
      ],
    },
    {
      name: "Manajemen Transaksi",
      icon: Package,
      children: [
        { name: "Barang Masuk", path: "/super-admin/transaksi/masuk" },
        { name: "Barang Keluar", path: "/super-admin/transaksi/keluar" },
        {
          name: "Riwayat Transaksi Barang",
          path: "/super-admin/transaksi/riwayat",
        },
        {
          name: "Pengajuan Revisi Transaksi Barang",
          path: "/super-admin/transaksi/pengajuan-revisi",
        },
      ],
    },
    {
      name: "Manajemen Stok",
      icon: Archive,
      children: [
        { name: "Stok Bahan", path: "/super-admin/stok/basah" },
        { name: "Penyesuaian Stok", path: "/super-admin/stok/riwayat" },
      ],
    },
    {
      name: "Manajemen Menu",
      icon: Utensils,
      children: [
        { name: "Menu Makanan", path: "/super-admin/menu" },
        { name: "Paket Menu", path: "/super-admin/menu/paket" },
        { name: "Kalender Menu", path: "/super-admin/menu/kalender" },
      ],
    },
    {
      name: "SPK Perencanaan",
      icon: ShoppingCart,
      children: [
        { name: "Belanja Basah", path: "/super-admin/spk/basah" },
        { name: "Belanja Kering & Pengemas", path: "/super-admin/spk/kering" },
        { name: "Riwayat SPK", path: "/super-admin/spk/riwayat" },
      ],
    },
    { name: "Laporan", path: "/super-admin/laporan", icon: FileText },
  ],
  gudang: [
    { name: "Dashboard", path: "/gudang", icon: LayoutDashboard },
    {
      name: "Manajemen Transaksi",
      icon: ClipboardList,
      children: [
        { name: "Barang Masuk", path: "/gudang/transaksi/masuk" },
        { name: "Barang Keluar", path: "/gudang/transaksi/keluar" },
        { name: "Riwayat Transaksi Barang", path: "/gudang/transaksi/riwayat" },
        {
          name: "Pengajuan Revisi Transaksi Barang",
          path: "/gudang/transaksi/pengajuan-revisi",
        },
      ],
    },
    {
      name: "Manajemen Stok",
      icon: Archive,
      children: [
        { name: "Stok Bahan", path: "/gudang/stok" },
        { name: "Penyesuaian Stok", path: "/gudang/stok/penyesuaian" },
      ],
    },
    {
      name: "SPK Perencanaan",
      icon: ShoppingCart,
      children: [
        { name: "Belanja Basah", path: "/gudang/spk/basah" },
        { name: "Belanja Kering & Pengemas", path: "/gudang/spk/kering" },
        { name: "Riwayat SPK", path: "/gudang/spk/riwayat" },
      ],
    },
    { name: "Laporan", path: "/gudang/laporan", icon: FileText },
  ],
  dapur: [
    { name: "Dashboard", path: "/gizi", icon: LayoutDashboard },
    {
      name: "Manajemen Menu",
      icon: Utensils,
      children: [
        { name: "Menu Makanan", path: "/gizi/menu" },
        { name: "Paket Menu", path: "/gizi/menu/paket" },
        { name: "Kalender Menu", path: "/gizi/menu/kalender" },
      ],
    },
    { name: "Manajemen Stok", path: "/gizi/stok", icon: Archive },
    { name: "SPK Perencanaan", path: "/gizi/spk", icon: ShoppingCart },
    { name: "Laporan", path: "/gizi/laporan", icon: FileText },
  ],
};

export default function Sidebar({
  open,
  setOpen,
}: Readonly<{
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}>) {
  const pathname = usePathname();
  const user = useAuthStore((state) => state.user);
  const [openMenus, setOpenMenus] = useState<{ names: string[]; pathname: string | null }>({
    names: [],
    pathname: null,
  });

  let role = user?.role?.name ?? "admin";
  if (!menuConfig[role]) {
    if (pathname.startsWith("/gudang")) {
      role = "gudang";
    } else if (pathname.startsWith("/gizi")) {
      role = "dapur";
    } else {
      role = "admin";
    }
  }

  const menus = menuConfig[role];
  const matchedParentName =
    menus.find((item) => item.children?.some((sub) => pathname.startsWith(sub.path)))?.name ?? null;
  const manualOpenMenus = openMenus.pathname === pathname ? openMenus.names : [];

  return (
    <>
      {open && (
        <div
          className="fixed inset-0 z-40 bg-black/50 lg:hidden"
          onClick={() => setOpen(false)}
        />
      )}

      <aside
        className={`fixed left-0 top-0 z-50 flex h-screen w-64 flex-col overflow-y-auto bg-[#0F172A] p-5 text-white transition-transform ${
          open ? "translate-x-0" : "-translate-x-full"
        } lg:translate-x-0`}
      >
        <div className="mb-8 flex min-h-[52px] items-center">
          <Image
            src={rsdBalungLogo}
            alt="Logo RSD Balung"
            priority
            className="h-[42px] w-[132px] object-contain object-left"
          />
        </div>

        <p className="mb-2 text-xs text-gray-400">MENU</p>

        <ul className="space-y-1">
          {menus.map((item) => {
            const Icon = item.icon;
            const isActive = item.path && pathname === item.path;
            const isOpen = manualOpenMenus.includes(item.name) || matchedParentName === item.name;

            if (!item.children) {
              return (
                <li key={item.name}>
                  <Link
                    href={item.path ?? "#"}
                    onClick={() => setOpenMenus({ names: [], pathname })}
                    className={`group relative flex items-center gap-3 overflow-hidden rounded-xl p-3 text-left text-base transition-all duration-300 ease-out ${
                      isActive
                        ? "bg-[#4F8CFF] text-white shadow-[0_10px_24px_rgba(79,140,255,0.34)]"
                        : "text-gray-300 hover:bg-[#1B3357] hover:text-white"
                    }`}
                  >
                    <span className="absolute inset-y-1 left-1 w-1 rounded-full bg-[#BFDBFE] opacity-0 transition-all duration-300 group-hover:opacity-100 group-hover:shadow-[0_0_18px_rgba(191,219,254,0.9)]" />
                    <Icon
                      size={18}
                      className="relative z-10 transition-transform duration-300 ease-out group-hover:scale-110 group-hover:translate-x-0.5"
                    />
                    <span className="relative z-10 transition-transform duration-300 ease-out group-hover:translate-x-0.5">
                      {item.name}
                    </span>
                  </Link>
                </li>
              );
            }

            return (
              <li key={item.name}>
                <button
                  onClick={() =>
                    setOpenMenus((current) => {
                      const currentNames =
                        current.pathname === pathname ? current.names : [];
                      const nextNames = currentNames.includes(item.name)
                        ? currentNames.filter((name) => name !== item.name)
                        : [...currentNames, item.name];

                      return {
                        names: nextNames,
                        pathname,
                      };
                    })
                  }
                  className={`group relative flex w-full items-center justify-between overflow-hidden rounded-xl p-3 text-left text-base transition-all duration-300 ease-out ${
                    isOpen
                      ? "text-white"
                      : "text-gray-300 hover:bg-[#1B3357] hover:text-white"
                  }`}
                  type="button"
                >
                  <span className="absolute inset-y-1 left-1 w-1 rounded-full bg-[#BFDBFE] opacity-0 transition-all duration-300 group-hover:opacity-100 group-hover:shadow-[0_0_18px_rgba(191,219,254,0.9)]" />
                  <div className="flex items-center gap-3">
                    <Icon
                      size={18}
                      className="relative z-10 transition-transform duration-300 ease-out group-hover:scale-110 group-hover:translate-x-0.5"
                    />
                    <span className="relative z-10 transition-transform duration-300 ease-out group-hover:translate-x-0.5">
                      {item.name}
                    </span>
                  </div>

                  <ChevronDown
                    size={16}
                    className={`relative z-10 transition duration-300 ${isOpen ? "rotate-180" : ""}`}
                  />
                </button>

                {isOpen && (
                  <div className="ml-8 mt-1 space-y-1">
                    {item.children.map((sub) => {
                      const activeSub = pathname === sub.path;

                      return (
                        <Link
                          key={sub.path}
                          href={sub.path}
                          onClick={() => setOpenMenus({ names: [], pathname: sub.path })}
                          className={`group block rounded-lg px-3 py-2 text-left text-[15px] transition-all duration-300 ease-out ${
                            activeSub
                              ? "bg-[#4F8CFF] font-medium text-white shadow-[0_10px_24px_rgba(79,140,255,0.34)]"
                              : "text-gray-300 hover:bg-white/8 hover:pl-3 hover:text-white"
                          }`}
                        >
                          {sub.name}
                        </Link>
                      );
                    })}
                  </div>
                )}
              </li>
            );
          })}
        </ul>

      </aside>
    </>
  );
}
