export type ActivityType = "Create" | "Update" | "Delete";
export type ActivityModule =
  | "Transaksi"
  | "Master Barang"
  | "Menu"
  | "Pengguna"
  | "SPK"
  | "Stok"
  | "Laporan";

export type ActivityRow = {
  id: number;
  date: string;
  time: string;
  actor: string;
  actorInitials: string;
  activityType: ActivityType;
  module: ActivityModule;
  detail: string;
};

export const ACTIVITY_ROWS: ActivityRow[] = [
  {
    id: 1,
    date: "2026-03-12",
    time: "11.45",
    actor: "Super Admin",
    actorInitials: "SA",
    activityType: "Create",
    module: "Transaksi",
    detail: "Menginputkan Barang Masuk",
  },
  {
    id: 2,
    date: "2026-03-11",
    time: "12.30",
    actor: "Nandini Putri",
    actorInitials: "NP",
    activityType: "Create",
    module: "Master Barang",
    detail: "Menambahkan Barang Baru",
  },
  {
    id: 3,
    date: "2026-03-10",
    time: "08.20",
    actor: "Aji Mahameru",
    actorInitials: "AM",
    activityType: "Update",
    module: "Menu",
    detail: "Mengubah Menu Ayam Koloke",
  },
  {
    id: 4,
    date: "2026-03-09",
    time: "09.11",
    actor: "Nandana Aji",
    actorInitials: "NA",
    activityType: "Delete",
    module: "Menu",
    detail: "Menghapus Paket Menu 9",
  },
  {
    id: 5,
    date: "2026-03-08",
    time: "07.34",
    actor: "Fathan",
    actorInitials: "F",
    activityType: "Create",
    module: "Transaksi",
    detail: "Menginputkan Barang Keluar",
  },
  {
    id: 6,
    date: "2026-03-07",
    time: "13.22",
    actor: "Jo",
    actorInitials: "J",
    activityType: "Create",
    module: "Menu",
    detail: "Menambahkan Menu Sate Gule",
  },
  {
    id: 7,
    date: "2026-03-06",
    time: "15.55",
    actor: "Rizal Prihadi",
    actorInitials: "RP",
    activityType: "Update",
    module: "Pengguna",
    detail: "Mengubah data profil",
  },
  {
    id: 8,
    date: "2026-03-05",
    time: "18.36",
    actor: "Nandini Putri",
    actorInitials: "NP",
    activityType: "Create",
    module: "SPK",
    detail: "Melakukan generate SPK Bahan Basah",
  },
  {
    id: 9,
    date: "2026-03-04",
    time: "20.40",
    actor: "Aji Mahameru",
    actorInitials: "AM",
    activityType: "Delete",
    module: "Stok",
    detail: "Menghapus Bahan Telur Ayam",
  },
  {
    id: 10,
    date: "2026-03-03",
    time: "21.45",
    actor: "Super Admin",
    actorInitials: "SA",
    activityType: "Create",
    module: "Laporan",
    detail: "Melakukan export laporan evaluasi",
  },
];

const ACTIVITY_LAST_VIEWED_KEY = "capstone-activity-log-last-viewed-at";

function parseActivityTimestamp(row: ActivityRow) {
  const normalizedTime = row.time.replace(".", ":");
  return new Date(`${row.date}T${normalizedTime}:00+07:00`).getTime();
}

export function getLatestActivityTimestamp() {
  return Math.max(...ACTIVITY_ROWS.map(parseActivityTimestamp));
}

export function getStoredActivityLogSeenAt() {
  if (typeof window === "undefined") {
    return 0;
  }

  const rawValue = window.localStorage.getItem(ACTIVITY_LAST_VIEWED_KEY);
  const parsedValue = rawValue ? Number(rawValue) : 0;
  return Number.isFinite(parsedValue) ? parsedValue : 0;
}

export function markActivityLogSeen() {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(ACTIVITY_LAST_VIEWED_KEY, String(getLatestActivityTimestamp()));
}

export function hasUnreadActivityLog() {
  if (typeof window === "undefined") {
    return false;
  }

  return getLatestActivityTimestamp() > getStoredActivityLogSeenAt();
}
