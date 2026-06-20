"use client";

import sdk from "@/lib";

export type NotificationRole = "admin" | "gudang" | "dapur";
export type NotificationKind =
  | "stock-adjustment-submitted"
  | "stock-adjustment-approved"
  | "stock-adjustment-rejected"
  | "stock-adjustment-created"
  | "minimum-stock"
  | "stock-revision-submitted"
  | "stock-revision-approved"
  | "stock-revision-rejected"
  | "stock-transaction-created"
  | "generic";

export type StockAdjustmentNotification = {
  id: string;
  kind: NotificationKind;
  title: string;
  message: string;
  createdAt: string;
  relatedId: number;
  read: boolean;
  route?: string;
  sourceType: string;
};

const NOTIFICATION_EVENT_NAME = "capstone-stock-adjustment-notifications-changed";

type BackendNotification = Awaited<ReturnType<typeof sdk.notifications.list>>["data"][number];

function canUseDom() {
  return typeof window !== "undefined";
}

function normalizeNotificationRole(role?: string | null): NotificationRole | null {
  switch (role) {
    case "admin":
      return "admin";
    case "gudang":
      return "gudang";
    case "dapur":
      return "dapur";
    default:
      return null;
  }
}

function getNotificationActorLabel(role?: NotificationRole | null) {
  switch (role) {
    case "admin":
      return "Admin";
    case "gudang":
      return "Petugas Gudang";
    case "dapur":
      return "Petugas Dapur";
    default:
      return "Petugas";
  }
}

function rewriteNotificationMessage(
  notification: BackendNotification,
  currentRole?: NotificationRole | null,
) {
  const actorLabel = getNotificationActorLabel(currentRole);
  const originalMessage = notification.message ?? "";

  if (notification.type === "STOCK_OPNAME") {
    const isSubmissionMessage =
      originalMessage.toLowerCase().includes("diajukan") ||
      originalMessage.toLowerCase().includes("pengajuan") ||
      originalMessage.toLowerCase().includes("verifikasi");

    if (!isSubmissionMessage) {
      return originalMessage;
    }

    return originalMessage
      .replace(/oleh\s+Petugas Gudang/gi, `oleh ${actorLabel}`)
      .replace(/oleh\s+Admin/gi, `oleh ${actorLabel}`)
      .replace(/oleh\s+Petugas Dapur/gi, `oleh ${actorLabel}`);
  }

  return originalMessage;
}

function inferNotificationKind(notification: BackendNotification): NotificationKind {
  const haystack = `${notification.title} ${notification.message}`.toLowerCase();

  if (notification.type === "MIN_STOCK") {
    return "minimum-stock";
  }

  if (notification.type === "STOCK_OPNAME") {
    if (haystack.includes("disetujui") || haystack.includes("approved")) return "stock-adjustment-approved";
    if (haystack.includes("ditolak") || haystack.includes("rejected")) return "stock-adjustment-rejected";
    if (
      haystack.includes("diajukan") ||
      haystack.includes("verifikasi") ||
      haystack.includes("menunggu") ||
      haystack.includes("pending") ||
      haystack.includes("submitted")
    ) {
      return "stock-adjustment-submitted";
    }
    return "stock-adjustment-created";
  }

  if (notification.type === "STOCK_REVISION") {
    if (haystack.includes("disetujui") || haystack.includes("approved")) return "stock-revision-approved";
    if (haystack.includes("ditolak") || haystack.includes("rejected")) return "stock-revision-rejected";
    if (
      haystack.includes("diajukan") ||
      haystack.includes("verifikasi") ||
      haystack.includes("menunggu") ||
      haystack.includes("pending") ||
      haystack.includes("submitted")
    ) {
      return "stock-revision-submitted";
    }
  }

  if (notification.type === "STOCK_TRANSACTION") {
    return "stock-transaction-created";
  }

  return "generic";
}

function inferNotificationRoute(
  notification: BackendNotification,
  currentRole?: NotificationRole | null,
): string | undefined {
  if (notification.type === "STOCK_OPNAME") {
    if (currentRole === "admin") {
      return "/super-admin/stok/riwayat";
    }

    if (currentRole === "gudang") {
      return "/gudang/stok/penyesuaian";
    }

    return "/gizi/stok";
  }

  if (notification.type === "MIN_STOCK") {
    if (currentRole === "admin") {
      return "/super-admin/stok/basah";
    }

    if (currentRole === "gudang") {
      return "/gudang/stok";
    }

    return "/gizi/stok";
  }

  if (notification.type === "STOCK_REVISION") {
    const kind = inferNotificationKind(notification);

    if (kind === "stock-revision-rejected") {
      return currentRole === "gudang"
        ? "/gudang/transaksi/pengajuan-revisi"
        : "/super-admin/transaksi/pengajuan-revisi";
    }

    if (currentRole === "admin") {
      return kind === "stock-revision-submitted"
        ? "/super-admin/transaksi/pengajuan-revisi"
        : "/super-admin/transaksi/riwayat";
    }

    if (currentRole === "gudang") {
      return "/gudang/transaksi/riwayat";
    }
  }

  if (notification.type === "STOCK_TRANSACTION") {
    if (currentRole === "admin") return "/super-admin/transaksi/riwayat";
    if (currentRole === "gudang") return "/gudang/transaksi/riwayat";
  }

  return undefined;
}

function mapBackendNotification(
  notification: BackendNotification,
  currentRole?: NotificationRole | null,
): StockAdjustmentNotification {
  return {
    id: String(notification.id),
    kind: inferNotificationKind(notification),
    title: notification.title,
    message: rewriteNotificationMessage(notification, currentRole),
    createdAt: notification.created_at,
    relatedId: Number(notification.related_id ?? 0),
    read: Boolean(notification.is_read),
    route: inferNotificationRoute(notification, currentRole),
    sourceType: notification.type,
  };
}

export async function listStockAdjustmentNotifications(
  currentRole?: NotificationRole | null,
): Promise<StockAdjustmentNotification[]> {
  const response = await sdk.notifications.list({
    paginate: false,
    sortBy: "created_at",
    sortDir: "DESC",
  });

  return (response.data ?? [])
    .map((notification) => mapBackendNotification(notification, currentRole))
    .sort((left, right) => new Date(right.createdAt).getTime() - new Date(left.createdAt).getTime());
}

export async function markAllStockAdjustmentNotificationsRead() {
  await sdk.notifications.markAllAsRead();
}

export async function markStockAdjustmentNotificationRead(id: string | number) {
  await sdk.notifications.markAsRead(Number(id));
}

export async function clearStockAdjustmentNotifications() {
  await sdk.notifications.deleteAll();
}

export function refreshStockAdjustmentNotifications() {
  if (!canUseDom()) return;
  window.dispatchEvent(new CustomEvent(NOTIFICATION_EVENT_NAME));
}

export function subscribeStockAdjustmentNotifications(listener: () => void) {
  if (!canUseDom()) {
    return () => undefined;
  }

  function handleRefresh() {
    listener();
  }

  window.addEventListener(NOTIFICATION_EVENT_NAME, handleRefresh);

  return () => {
    window.removeEventListener(NOTIFICATION_EVENT_NAME, handleRefresh);
  };
}

export function resolveNotificationRole(role?: string | null) {
  return normalizeNotificationRole(role);
}
