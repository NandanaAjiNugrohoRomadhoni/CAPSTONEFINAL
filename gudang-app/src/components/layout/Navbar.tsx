"use client";

import type { Dispatch, SetStateAction } from "react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { usePathname, useRouter } from "next/navigation";
import {
  Bell,
  Settings,
  Search,
  ChevronDown,
  LogOut,
  UserCircle2,
  X,
  MoreVertical,
  CheckCircle2,
  Clock3,
  XCircle,
  FilePlus2,
  AlertTriangle,
} from "lucide-react";
import { getRoleLabel, useAuthStore } from "@/store/authStore";
import {
  clearStockAdjustmentNotifications,
  listStockAdjustmentNotifications,
  markAllStockAdjustmentNotificationsRead,
  markStockAdjustmentNotificationRead,
  subscribeStockAdjustmentNotifications,
  resolveNotificationRole,
  type StockAdjustmentNotification,
} from "@/lib/stock-adjustment-notifications";

export default function Navbar({
  setOpen,
}: Readonly<{
  setOpen: Dispatch<SetStateAction<boolean>>;
}>) {
  const [openNotif, setOpenNotif] = useState(false);
  const [showAllNotifications, setShowAllNotifications] = useState(false);
  const [openProfileMenu, setOpenProfileMenu] = useState(false);
  const notifMenuRef = useRef<HTMLDivElement | null>(null);
  const profileMenuRef = useRef<HTMLDivElement | null>(null);
  const pathname = usePathname();
  const router = useRouter();
  const user = useAuthStore((state) => state.user);
  const logout = useAuthStore((state) => state.logout);
  const currentNotificationRole = resolveNotificationRole(user?.role?.name);
  const [notifications, setNotifications] = useState<StockAdjustmentNotification[]>([]);
  const [notificationNow, setNotificationNow] = useState(() => Date.now());

  const pageTitle = useMemo(() => {
    const titleMap: Record<string, string> = {
      "/super-admin": "Dashboard",
      "/super-admin/users": "Manajemen Pengguna",
      "/super-admin/master-data/jenis": "Data Jenis Bahan",
      "/super-admin/master-data/satuan": "Data Satuan",
      "/super-admin/transaksi/masuk": "Barang Masuk",
      "/super-admin/transaksi/keluar": "Barang Keluar",
      "/super-admin/transaksi/riwayat": "Riwayat Transaksi Barang",
      "/super-admin/stok/basah": "Stok Bahan",
      "/super-admin/stok/riwayat": "Penyesuaian Stok",
      "/super-admin/menu": "Menu Makanan",
      "/super-admin/menu/paket": "Paket Menu",
      "/super-admin/menu/kalender": "Kalender Menu",
      "/super-admin/spk/basah": "Belanja Basah",
      "/super-admin/spk/kering": "Belanja Kering & Pengemas",
      "/super-admin/spk/riwayat": "Riwayat SPK",
      "/super-admin/laporan": "Laporan",
      "/gizi": "Dashboard",
      "/gizi/menu": "Menu Makanan",
      "/gizi/menu/paket": "Paket Menu",
      "/gizi/menu/kalender": "Kalender Menu",
      "/gizi/stok": "Manajemen Stok",
      "/gizi/spk": "SPK Perencanaan",
      "/gizi/laporan": "Laporan",
      "/profil": "Profil",
    };

    if (titleMap[pathname]) {
      return titleMap[pathname];
    }

    const parts = pathname.split("/").filter(Boolean);
    const lastPart = parts.at(-1);

    if (!lastPart) {
      return "Dashboard";
    }

    return lastPart
      .split("-")
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(" ");
  }, [pathname]);

  const breadcrumb =
    pathname === "/profil"
      ? "Pengaturan / Profil"
      : `${user?.role?.name ? getRoleLabel(user.role.name) : "Dashboard"} / ${pageTitle}`;
  const initials =
    user?.name
      ?.split(" ")
      .map((part) => part.charAt(0))
      .slice(0, 2)
      .join("")
      .toUpperCase() || "SA";
  const scopedNotifications = notifications;
  const visibleNotifications = useMemo(
    () => (showAllNotifications ? scopedNotifications : scopedNotifications.slice(0, 3)),
    [scopedNotifications, showAllNotifications],
  );
  const unreadNotificationCount = scopedNotifications.filter((item) => !item.read).length;

  const loadNotifications = useCallback(async () => {
    if (!user) {
      setNotifications([]);
      return;
    }

    try {
      const nextNotifications = await listStockAdjustmentNotifications(currentNotificationRole);
      setNotifications(nextNotifications);
    } catch {
      setNotifications([]);
    }
  }, [currentNotificationRole, user]);

  async function handleLogout() {
    await logout();
    router.replace("/login");
  }

  function handleOpenProfile() {
    setOpenProfileMenu(false);
    router.push("/profil");
  }

  useEffect(() => {
    const unsubscribe = subscribeStockAdjustmentNotifications(() => {
      void loadNotifications();
    });

    return unsubscribe;
  }, [loadNotifications]);

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      void loadNotifications();
    }, 0);

    return () => {
      window.clearTimeout(timeoutId);
    };
  }, [loadNotifications, pathname]);

  useEffect(() => {
    if (!openNotif) {
      return;
    }

    const timeoutId = window.setTimeout(() => {
      void loadNotifications();
    }, 0);

    return () => {
      window.clearTimeout(timeoutId);
    };
  }, [loadNotifications, openNotif]);

  useEffect(() => {
    if (!openNotif) {
      return;
    }

    console.log(
      "[notif:panel]",
      visibleNotifications.map((notification) => ({
          title: notification.title,
          kind: notification.kind,
          sourceType: notification.sourceType,
          createdAt: notification.createdAt,
        })),
    );
  }, [openNotif, visibleNotifications]);

  useEffect(() => {
    function handlePointerDown(event: MouseEvent) {
      if (
        event.target instanceof Node &&
        openProfileMenu &&
        profileMenuRef.current &&
        !profileMenuRef.current.contains(event.target)
      ) {
        setOpenProfileMenu(false);
      }

      if (
        event.target instanceof Node &&
        openNotif &&
        notifMenuRef.current &&
        !notifMenuRef.current.contains(event.target)
      ) {
        setOpenNotif(false);
        setShowAllNotifications(false);
      }
    }

    document.addEventListener("mousedown", handlePointerDown);
    return () => {
      document.removeEventListener("mousedown", handlePointerDown);
    };
  }, [openNotif, openProfileMenu]);

  function formatNotificationTime(value: string) {
    const targetDate = new Date(value);
    const diffMinutes = Math.max(0, Math.floor((notificationNow - targetDate.getTime()) / 60000));

    if (diffMinutes < 1) return "Baru saja";
    if (diffMinutes < 60) return `${diffMinutes} menit yang lalu`;
    if (diffMinutes < 1440) return `${Math.floor(diffMinutes / 60)} jam yang lalu`;
    return `${Math.floor(diffMinutes / 1440)} hari yang lalu`;
  }

  function getNotificationAccent(kind: string) {
    switch (kind) {
      case "stock-adjustment-approved":
      case "stock-revision-approved":
        return "bg-[#22C55E]";
      case "stock-adjustment-rejected":
      case "stock-revision-rejected":
        return "bg-[#EF4444]";
      case "stock-adjustment-submitted":
      case "stock-revision-submitted":
        return "bg-[#2563EB]";
      case "minimum-stock":
        return "bg-[#F59E0B]";
      default:
        return "bg-[#38BDF8]";
    }
  }

  function getNotificationVisual(kind: string) {
    switch (kind) {
      case "stock-adjustment-approved":
      case "stock-revision-approved":
        return {
          Icon: CheckCircle2,
          avatarClass: "bg-[#ECFDF3] text-[#16A34A]",
          dotClass: "bg-[#22C55E]",
        };
      case "stock-adjustment-rejected":
      case "stock-revision-rejected":
        return {
          Icon: XCircle,
          avatarClass: "bg-[#FEF2F2] text-[#DC2626]",
          dotClass: "bg-[#EF4444]",
        };
      case "stock-adjustment-submitted":
      case "stock-revision-submitted":
        return {
          Icon: Clock3,
          avatarClass: "bg-[#EEF4FF] text-[#2155CD]",
          dotClass: "bg-[#2563EB]",
        };
      case "minimum-stock":
        return {
          Icon: AlertTriangle,
          avatarClass: "bg-[#FFF7ED] text-[#D97706]",
          dotClass: "bg-[#F59E0B]",
        };
      default:
        return {
          Icon: FilePlus2,
          avatarClass: "bg-[#F0F9FF] text-[#0284C7]",
          dotClass: "bg-[#38BDF8]",
        };
    }
  }

  return (
    <div className="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-gray-100 bg-white px-6">
      <div className="flex items-center gap-3">
        <button className="lg:hidden" onClick={() => setOpen(true)} type="button">
          Menu
        </button>

        <div>
          <p className="text-xs text-gray-400">{breadcrumb}</p>
          <h1 className="font-semibold text-gray-900">{pageTitle}</h1>
        </div>
      </div>

      <div className="flex items-center gap-4">
        <div className="hidden items-center rounded-lg bg-gray-100 px-3 py-2 md:flex">
          <Search size={16} className="mr-2 text-gray-400" />
          <input placeholder="Cari..." className="bg-transparent text-sm outline-none" />
        </div>

        <p className="hidden text-sm text-gray-400 md:block">
          {new Intl.DateTimeFormat("id-ID", {
            weekday: "short",
            day: "2-digit",
            month: "short",
            year: "numeric",
          }).format(new Date())}
        </p>

        <div className="relative" ref={notifMenuRef}>
          <button
            onClick={() => {
              setOpenNotif((current) => {
                const next = !current;
                if (next) {
                  setNotificationNow(Date.now());
                  setShowAllNotifications(false);
                  void markAllStockAdjustmentNotificationsRead()
                    .catch(() => undefined)
                    .finally(() => {
                      void loadNotifications();
                    });
                }
                return next;
              });
              setOpenProfileMenu(false);
            }}
            className="relative rounded-lg bg-gray-100 p-2"
            type="button"
          >
            <Bell size={18} />
            {unreadNotificationCount > 0 ? (
              <span className="absolute -right-1 -top-1 flex min-h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#EF4444] px-1 text-[10px] font-semibold text-white">
                {unreadNotificationCount > 9 ? "9+" : unreadNotificationCount}
              </span>
            ) : null}
          </button>

          {openNotif && (
            <div className="absolute right-0 z-50 mt-2 flex max-h-[calc(100vh-110px)] w-[420px] flex-col overflow-hidden rounded-[24px] border border-[#D7E0EE] bg-white text-[#16213E] shadow-[0_24px_70px_rgba(15,23,42,0.18)]">
              <div className="flex items-center justify-between border-b border-[#E2E8F0] px-5 py-4">
                <h3 className="text-[18px] font-semibold text-[#16213E]">Notifikasi</h3>
                <button
                  className="rounded-full bg-[#F8FAFC] p-2 text-[#64748B] transition hover:bg-[#EEF4FF] hover:text-[#2155CD]"
                  onClick={() => setOpenNotif(false)}
                  type="button"
                >
                  <X size={16} />
                </button>
              </div>

              <div className="min-h-0 flex-1 overflow-y-auto">
                {visibleNotifications.length > 0 ? (
                  visibleNotifications.map((notification) => {
                    const { Icon, avatarClass, dotClass } = getNotificationVisual(notification.kind);

                    return (
                      <button
                        key={notification.id}
                        className="flex w-full items-start gap-3 border-b border-[#E2E8F0] px-5 py-4 text-left transition hover:bg-[#F8FAFC]"
                        onClick={() => {
                          setOpenNotif(false);
                          void markStockAdjustmentNotificationRead(notification.id)
                            .catch(() => undefined)
                            .finally(() => {
                              void loadNotifications();
                              if (notification.route) {
                                router.push(notification.route);
                              }
                            });
                        }}
                        type="button"
                      >
                        <div className={`relative mt-1 flex h-12 w-12 shrink-0 items-center justify-center rounded-full ${avatarClass}`}>
                          <Icon size={20} />
                          <span className={`absolute left-0 top-1/2 h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full ${dotClass || getNotificationAccent(notification.kind)}`} />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="text-[15px] font-semibold leading-6 text-[#16213E]">{notification.title}</p>
                          <p className="mt-1 text-sm leading-6 text-[#64748B]">{notification.message}</p>
                          <p className="mt-3 text-sm text-[#94A3B8]">{formatNotificationTime(notification.createdAt)}</p>
                        </div>
                        <div
                          className="mt-1 rounded-full p-1 text-[#94A3B8] transition hover:bg-[#EEF4FF] hover:text-[#2155CD]"
                          onClick={(event) => event.stopPropagation()}
                          role="presentation"
                        >
                          <MoreVertical size={16} />
                        </div>
                      </button>
                    );
                  })
                ) : (
                  <div className="px-5 py-6 text-sm text-[#94A3B8]">
                    Belum ada notifikasi.
                  </div>
                )}
              </div>

              <div className="shrink-0 border-t border-[#E2E8F0] bg-white px-5 py-3">
                <div className="flex flex-col gap-2">
                  {scopedNotifications.length > 3 ? (
                    <button
                      className="w-full rounded-xl border border-[#D7E0EE] bg-[#F8FAFC] px-4 py-2.5 text-sm font-medium text-[#2155CD] transition hover:bg-[#EEF4FF]"
                      onClick={() => setShowAllNotifications((current) => !current)}
                      type="button"
                    >
                      {showAllNotifications ? "Tampilkan lebih sedikit" : "Lihat lebih banyak"}
                    </button>
                  ) : null}

                  {currentNotificationRole ? (
                    <button
                      className="w-full rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-2.5 text-sm font-medium text-[#DC2626] transition hover:bg-[#FEE2E2]"
                      onClick={() => {
                        void clearStockAdjustmentNotifications()
                          .catch(() => undefined)
                          .finally(() => {
                            setShowAllNotifications(false);
                            void loadNotifications();
                          });
                      }}
                      type="button"
                    >
                      Reset Semua Notifikasi
                    </button>
                  ) : null}
                </div>
              </div>
            </div>
          )}
        </div>

        <button className="rounded-lg bg-gray-100 p-2 text-gray-700" type="button">
          <Settings size={18} />
        </button>

        <div className="relative" ref={profileMenuRef}>
          <button
            onClick={() => {
              setOpenProfileMenu((current) => !current);
              setOpenNotif(false);
            }}
            className={`flex items-center gap-2 rounded-lg px-3 py-2 transition ${
              openProfileMenu ? "bg-[#EEF4FF] text-[#2155CD]" : "bg-gray-100 text-gray-700"
            }`}
            type="button"
          >
            <div className="flex h-6 w-6 items-center justify-center rounded-full bg-blue-500 text-xs text-white">
              {initials}
            </div>
            <ChevronDown size={16} />
          </button>

          {openProfileMenu && (
            <div className="absolute right-0 mt-2 w-[220px] overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-[0_18px_40px_rgba(15,23,42,0.12)]">
              <div className="border-b border-[#E2E8F0] px-4 py-3">
                <p className="text-sm font-semibold text-[#64748B]">{user?.name ?? "Pengguna"}</p>
                <p className="mt-1 text-xs text-[#94A3B8]">{getRoleLabel(user?.role?.name)}</p>
              </div>

              <div className="p-2">
                <button
                  onClick={handleOpenProfile}
                  className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-[#16213E] transition hover:bg-[#F8FAFC]"
                  type="button"
                >
                  <UserCircle2 size={18} className="text-[#64748B]" />
                  Profil
                </button>

                <button
                  onClick={handleLogout}
                  className="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-medium text-[#16213E] transition hover:bg-[#F8FAFC]"
                  type="button"
                >
                  <LogOut size={18} className="text-[#64748B]" />
                  Logout
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
