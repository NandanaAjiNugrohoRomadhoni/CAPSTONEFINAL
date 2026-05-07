# Data Dictionary — Sistem Informasi Manajemen Gudang dan SPK Instalasi Gizi RSD Balung

## Quick Router

- **Canonical for:** schema structure, foreign keys, uniqueness, soft delete behavior, and persistence constraints.
- **Read this when:** you are changing migrations, models, lookup rules, or database-backed invariants.
- **Read next:** `api-contract.md` for request/response contract details and `../architecture/runtime-status.md` for compact runtime status.
- **Legacy context (Deprecated):** See `../governance/changelog.md` and `../governance/migration-map.md` for removed-file history.
- **Not canonical for:** full endpoint behavior, access rules per route, or planned module contracts.

## 1. Overview

Dokumen ini mendefinisikan data dictionary berdasarkan DB diagram terbaru untuk backend **CodeIgniter 4 + MySQL**.

Tujuan dokumen ini adalah:

- mendokumentasikan setiap tabel dan kolom penting;
- menjelaskan fungsi bisnis tiap tabel;
- mendokumentasikan foreign key dan constraint utama;
- menjadi referensi untuk implementasi model, migration, API, dan validasi.

## 2. Lookup Tables

### 2.1 `item_categories`

Fungsi: Menyimpan kategori barang sebagai lookup tetap. Mendukung soft delete.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID kategori barang |
| `name` | varchar(50) | not null | Nama kategori: BASAH, KERING, PENGEMAS |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |
| `deleted_at` | timestamp | nullable | Soft delete marker |
| `name_active_lookup` | varchar(50) | generated, nullable | Generated helper column untuk enforce unique aktif (trimmed + lowercase, NULL saat deleted) |

### 2.2 `transaction_types`

Fungsi: Menyimpan tipe transaksi stok. Mendukung soft delete.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID tipe transaksi |
| `name` | varchar(50) | not null, unique | Nilai bisnis: IN, OUT, RETURN_IN, OPNAME_ADJUSTMENT |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |
| `deleted_at` | timestamp | nullable | Soft delete marker |

### 2.3 `meal_times`

Fungsi: Menyimpan waktu makan standar.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID waktu makan |
| `name` | varchar(50) | not null, unique | SIANG, SORE, PAGI |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |

### 2.4 `approval_statuses`

Fungsi: Menyimpan status approval transaksi. Mendukung soft delete.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID status approval |
| `name` | varchar(50) | not null, unique | APPROVED, PENDING, REJECTED |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |
| `deleted_at` | timestamp | nullable | Soft delete marker |

### 2.5 `item_units`

Fungsi: Menyimpan satuan pengukuran barang (item unit lookup) yang dapat direferensikan oleh `items`. Mendukung soft delete. Soft-deleted item units tidak dapat diassign ke item baru.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID item unit |
| `name` | varchar(50) | not null | Nama satuan item (mis. gram, kg, ml, liter) |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |
| `deleted_at` | timestamp | nullable | Soft delete marker |
| `name_active_lookup` | varchar(50) | generated, nullable | Generated helper column untuk enforce unique aktif (trimmed + lowercase, NULL saat deleted) |

Catatan implementasi:

- `item_units` digunakan sebagai FK backing untuk `items.item_unit_base_id` dan `items.item_unit_convert_id`.
- Nama satuan di-resolve secara case-insensitive dari string `unit_base`/`unit_convert` yang dikirim client ke record `item_units` yang aktif.
- Soft-deleted item units tetap ada di database dan tetap dapat direferensikan oleh item historis melalui FK, tetapi tidak dapat diassign ke item baru.
- `item_units.name` harus unik hanya di antara row aktif (non-deleted), dengan pencocokan trimmed dan case-insensitive.
- Jika nama yang sama hanya ada pada row yang sudah soft-deleted, create akan ditolak dan admin harus memanggil restore endpoint untuk mengaktifkan kembali row lama.
- Implementasi schema memakai generated column `name_active_lookup` + unique index agar aturan active-only uniqueness kompatibel dengan MariaDB/MySQL, sedangkan perilaku soft delete/restore tetap didefinisikan di model CI4.

Catatan kontrak runtime terkait lookup-by-name dan endpoint write tidak menjadi source utama dokumen ini. Untuk perilaku request/response yang aktif saat ini, lihat `api-contract.md` dan gunakan file ini untuk aspek schema, FK, dan constraint-nya.

## 3. Master Data & Users

### 3.1 `roles`

Fungsi: Menyimpan role utama pengguna. Mendukung soft delete.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | tinyint | PK | ID role |
| `name` | varchar(50) | not null, unique | admin, dapur, gudang |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |
| `deleted_at` | timestamp | nullable | Soft delete marker |

Supported roles:
- **admin**: Full system access, can manage users, roles, and all resources
- **dapur**: Kitchen and nutrition planning access
- **gudang**: Warehouse and inventory management access

### 3.2 `users`

Fungsi: Menyimpan akun pengguna sistem.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID user |
| `role_id` | tinyint | not null, FK | Relasi ke `roles.id` |
| `name` | varchar(255) | not null | Nama user |
| `username` | varchar(100) | not null, unique | Username login |
| `password` | varchar(255) | nullable | Password hash legacy/app field |
| `email` | varchar(255) | nullable | Email opsional untuk profil/internal use |
| `is_active` | boolean | default true | Status aktif user - controls login access |
| `last_active` | timestamp | nullable | Waktu akses terakhir |
| `status` | varchar(255) | nullable | Status auth tambahan kompatibel dengan Shield |
| `status_message` | varchar(255) | nullable | Pesan status auth |
| `active` | boolean | default false | Flag aktivasi yang dipakai provider auth - synced with is_active |
| `force_pass_reset` | boolean | default false | Penanda paksa reset password |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |
| `deleted_at` | timestamp | nullable | Soft delete marker |

**User Management Behavior:**

- **Deactivation**: Setting `is_active` and `active` to `false` blocks user from logging in. Existing tokens remain valid until revoked separately.
- **Password Change**: Changing a user's password automatically revokes ALL their access tokens via `auth_identities` table. User must log in again with the new password, and the password update must go through the Shield user entity/save flow.
- **Soft Delete**: Soft-deleting a user (`deleted_at` set) automatically revokes ALL their access tokens. Deleted users do not appear in user listings and cannot log in.
- **Username Uniqueness**: Username remains globally unique even after soft delete; deleted usernames cannot be reused.
- **Deleted User Mutations**: Soft-deleted users are treated as absent resources for update, activate, deactivate, password-change, and delete operations.
- **Token Revocation**: Tokens are revoked by removing entries from `auth_identities` table where `type = 'access_token'` and `user_id` matches the target user.
- **Role Assignment**: Users can only be assigned one of the three supported roles: admin, dapur, or gudang. Role changes are tracked via `updated_at`.
- **Email Field**: Email is optional but recommended for user identification and recovery workflows.
- **Active Flag Sync**: Both `is_active` (application-level) and `active` (Shield-level) must be kept in sync during user creation, update, activation, and deactivation operations.

### 3.3 `auth_identities`

Fungsi: Menyimpan identitas autentikasi dan personal access token untuk Shield-compatible API auth.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | int | PK, increment | ID identity |
| `user_id` | bigint | not null, FK | Relasi ke `users.id` |
| `type` | varchar(255) | not null | Tipe identity, mis. `email_password`, `access_token`, `username` |
| `name` | varchar(255) | nullable | Nama token/identity |
| `secret` | varchar(255) | not null | Secret atau hash token |
| `secret2` | varchar(255) | nullable | Hash password / secret tambahan |
| `expires` | timestamp | nullable | Waktu kedaluwarsa token |
| `extra` | text | nullable | Scope atau metadata tambahan |
| `force_reset` | boolean | default false | Penanda paksa reset |
| `last_used_at` | timestamp | nullable | Waktu terakhir dipakai |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |

### 3.4 `auth_logins`

Fungsi: Audit trail untuk percobaan login berbasis credential.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | int | PK, increment | ID login attempt |
| `ip_address` | varchar(255) | not null | IP address yang melakukan attempt |
| `user_agent` | varchar(255) | nullable | Browser/client user agent |
| `id_type` | varchar(255) | not null | Tipe identifier (email, username) |
| `identifier` | varchar(255) | not null | Nilai identifier yang digunakan |
| `user_id` | bigint | nullable | ID user jika berhasil login |
| `date` | timestamp | not null | Waktu attempt |
| `success` | boolean | not null | Berhasil atau gagal |

### 3.5 `auth_token_logins`

Fungsi: Audit trail untuk penggunaan Bearer token.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | int | PK, increment | ID token login attempt |
| `ip_address` | varchar(255) | not null | IP address yang menggunakan token |
| `user_agent` | varchar(255) | nullable | Browser/client user agent |
| `id_type` | varchar(255) | not null | Tipe token |
| `identifier` | varchar(255) | not null | Token identifier |
| `user_id` | bigint | nullable | ID user jika token valid |
| `date` | timestamp | not null | Waktu penggunaan |
| `success` | boolean | not null | Valid atau invalid |

### 3.6 `auth_remember_tokens`

Fungsi: Menyimpan remember-me token untuk Shield session authenticator.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | int | PK, increment | ID remember token |
| `selector` | varchar(255) | not null, unique | Token selector publik |
| `hashedValidator` | varchar(255) | not null | Hash validator untuk validasi |
| `user_id` | bigint | not null, FK | Relasi ke `users.id` |
| `expires` | timestamp | not null | Waktu kedaluwarsa token |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |

**Note:** Foreign key `user_id` CASCADE on delete — menghapus user otomatis menghapus remember token mereka.

### 3.7 `auth_groups_users`

Fungsi: Menyimpan relasi user ke Shield authorization groups untuk kompatibilitas internal Shield.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | int | PK, increment | ID group assignment |
| `user_id` | bigint | not null, FK | Relasi ke `users.id` |
| `group` | varchar(255) | not null | Nama group Shield |
| `created_at` | timestamp | nullable | Waktu dibuat |

**Important:** Tabel ini untuk kompatibilitas Shield internal saja. **Business authorization menggunakan tabel `roles`**, bukan Shield groups. Tabel ini TIDAK digunakan untuk authorization flow aplikasi utama.

**Note:** Foreign key `user_id` CASCADE on delete — menghapus user otomatis menghapus group assignments mereka.

### 3.8 `auth_permissions_users`

Fungsi: Menyimpan relasi user ke Shield authorization permissions untuk kompatibilitas internal Shield.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | int | PK, increment | ID permission assignment |
| `user_id` | bigint | not null, FK | Relasi ke `users.id` |
| `permission` | varchar(255) | not null | Nama permission Shield |
| `created_at` | timestamp | nullable | Waktu dibuat |

**Important:** Tabel ini untuk kompatibilitas Shield internal saja. **Business authorization menggunakan tabel `roles`**, bukan Shield permissions. Tabel ini TIDAK digunakan untuk authorization flow aplikasi utama.

**Note:** Foreign key `user_id` CASCADE on delete — menghapus user otomatis menghapus permission assignments mereka.

### 3.9 `settings`

Fungsi: Menyimpan konfigurasi setting yang dibutuhkan oleh package Settings/Shield.

### 3.10 `items`

Fungsi: Menyimpan master barang dan saldo stok berjalan.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID item |
| `name` | varchar(100) | not null | Nama barang |
| `item_category_id` | bigint | not null, FK | Relasi ke `item_categories.id` |
| `unit_base` | varchar(20) | not null | Satuan terkecil / dapur (string, disimpan untuk backward compatibility) |
| `unit_convert` | varchar(20) | not null | Satuan besar / gudang (string, disimpan untuk backward compatibility) |
| `item_unit_base_id` | bigint | nullable, FK | Relasi FK ke `item_units.id` untuk satuan dasar |
| `item_unit_convert_id` | bigint | nullable, FK | Relasi FK ke `item_units.id` untuk satuan konversi |
| `conversion_base` | int | not null | Nilai konversi dari satuan gudang ke satuan dasar |
| `is_active` | boolean | default true | Status aktif item |
| `qty` | decimal(12,2) | default 0 | Stok berjalan saat ini dalam satuan dasar |
| `min_stock` | int | default 0 | Batas stok minimum untuk peringatan stok rendah |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |
| `deleted_at` | timestamp | nullable | Soft delete marker |

Perilaku Phase 1 item master API:

- `name` diperlakukan unik secara global pada item master.
- `qty` wajib dianggap read-only pada endpoint item master.
- `item_category_id` harus merujuk ke kategori barang yang sudah ada.
- `item_category_name` dapat dipakai sebagai alternatif input API dan akan di-resolve ke `item_category_id`.
- `unit_base` dan `unit_convert` dikirim client sebagai string dan di-resolve ke row `item_units` yang aktif oleh service layer; FK `item_unit_base_id` / `item_unit_convert_id` diisi dari hasil resolusi tersebut.
- String `unit_base`/`unit_convert` tetap disimpan di kolom teks untuk backward compatibility dan muncul di response API.
- `item_unit_base_id` dan `item_unit_convert_id` bersifat nullable untuk mengakomodasi data historis dan periode transisi.

### 3.11 `notifications`

Stores system-generated notifications for users.

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT(11) | PK, Auto Increment | Primary key |
| `user_id` | INT(11) | FK | Refers to `users(id)` |
| `title` | VARCHAR(255) | NOT NULL | Short notification title |
| `message` | TEXT | NOT NULL | Body of the notification |
| `type` | VARCHAR(50) | NOT NULL | Notification category/type |
| `related_id` | INT(11) | NULL | Optional reference to a related resource |
| `is_read` | TINYINT(1) | DEFAULT 0 | Whether the notification has been read |
| `created_at` | DATETIME | NOT NULL | Timestamp |
| `updated_at` | DATETIME | NOT NULL | Timestamp |

## 4. Inventory & Stock Logic

### 4.1 `monthly_stock_snapshots`

Fungsi: Menyimpan snapshot stok awal bulanan per item untuk kontrol periode.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID snapshot |
| `period_month` | date | not null | Periode bulan dengan format YYYY-MM |
| `item_id` | bigint | not null, FK | Relasi ke `items.id` |
| `opening_qty` | decimal(12,2) | not null | Stok awal item pada awal bulan |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |

Constraint utama:

- unique `(period_month, item_id)` untuk mencegah snapshot ganda pada item dan bulan yang sama.

### 4.2 `stock_transactions`

Fungsi: Header transaksi stok, termasuk transaksi normal dan revisi.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID transaksi |
| `type_id` | bigint | not null, FK | Relasi ke `transaction_types.id` |
| `transaction_date` | date | not null | Tanggal transaksi |
| `is_revision` | boolean | default false | Penanda transaksi revisi |
| `parent_transaction_id` | bigint | nullable, self FK | Referensi transaksi asal jika ini revisi |
| `approval_status_id` | bigint | not null, FK | Relasi ke `approval_statuses.id` |
| `approved_by` | bigint | nullable, FK | User Admin yang menyetujui |
| `user_id` | bigint | not null, FK | User pembuat transaksi |
| `spk_id` | bigint | nullable | Relasi opsional ke `spk_calculations.id` pada fase integrasi SPK |
| `reason` | text | nullable | Alasan transaksi (digunakan pada koreksi stok langsung atau backfill) |
| `legacy_source_table` | varchar(100) | nullable | Table name for historical backfill linkage (e.g. `stock_opname_details`) |
| `legacy_source_id` | bigint | nullable | Header ID from the legacy source table |
| `legacy_source_detail_id` | bigint | nullable | Detail row ID from the legacy source table |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |
| `deleted_at` | timestamp | nullable | Soft delete marker |

Catatan implementasi API saat ini:

- create stock transaction menerima `type_id` atau `type_name`;
- `type_name` di-resolve ke `transaction_types.id` sebelum validasi tipe transaksi yang diizinkan dijalankan.

Catatan desain:

- Pada Milestone 1, `spk_id` dibuat nullable agar transaksi manual gudang tidak terblokir oleh modul SPK yang belum selesai.
- Revisi transaksi tidak menghapus transaksi asal, tetapi menaut ke `parent_transaction_id`.
- Untuk transaksi normal, aplikasi menetapkan status approval awal dengan lookup nama `APPROVED` di service layer, bukan mengandalkan DB default literal.
- Revisi yang baru disubmit disimpan dengan status `PENDING` dan tidak langsung mengubah `items.qty`.
- `approved_by` diisi saat admin melakukan approve atau reject terhadap revisi.
- Koreksi stok langsung disimpan sebagai transaksi final (`is_revision = false`, `parent_transaction_id = null`) dan menggunakan `reason` pada header transaksi untuk menjelaskan penyebab koreksi.
- Mutasi stok dari revisi hanya diterapkan ketika status revisi berubah menjadi `APPROVED`.
- Saat revisi di-approve, aplikasi menerapkan **selisih bersih** antara detail revisi pending dan **baseline efektif** per item (latest approved sibling dalam lineage, atau parent original jika belum ada approved sibling).
- Semua revisi pada satu lineage tetap sibling ke parent original (tidak ada revision chaining).
- Dalam satu lineage, maksimal hanya satu sibling berstatus `PENDING` pada waktu yang sama.

Index lineage revisi:

- `idx_st_lineage_parent_revision_status_deleted` pada `stock_transactions(parent_transaction_id, is_revision, approval_status_id, deleted_at, id)` untuk query helper pending/latest-approved sibling.

### 4.3 `stock_transaction_details`

Fungsi: Detail item untuk setiap transaksi stok.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID detail transaksi |
| `transaction_id` | bigint | not null, FK | Relasi ke `stock_transactions.id` |
| `item_id` | bigint | not null, FK | Relasi ke `items.id` |
| `qty` | decimal(12,2) | not null | Jumlah item dalam satuan dasar (base unit) setelah normalisasi |
| `input_qty` | decimal(12,2) | nullable | Jumlah asli yang dikirimkan oleh client sebelum konversi |
| `input_unit` | varchar(10) | nullable | Satuan input dari client: `"base"` (default) atau `"convert"` |

Catatan konversi satuan:

- `input_unit = "base"` → `qty` = `input_qty` (tidak ada konversi).
- `input_unit = "convert"` → `qty` = `input_qty` × `items.conversion_base`.
- `qty` selalu disimpan dalam satuan dasar (`unit_base`) dan digunakan untuk mutasi stok.
- `input_qty` disimpan untuk transparansi / audit dan diturunkan dari `qty` request sebelum normalisasi; client tidak mengirim field ini secara langsung.
- `input_unit` boleh dikirim client sebagai field opsional dengan nilai `base` atau `convert`; jika dihilangkan, service akan menetapkan nilai default `base`.

Constraint utama:

- unique `(transaction_id, item_id)` untuk mencegah item yang sama muncul dua kali dalam satu transaksi.

## 5. Daily Patients & Menu

### 5.1 `daily_patients`

Fungsi: Menyimpan jumlah pasien harian sebagai basis operasional dan input SPK.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID log pasien harian |
| `service_date` | date | not null | Tanggal layanan gizi |
| `total_patients` | int | not null | Jumlah total pasien |
| `notes` | text | nullable | Catatan tambahan |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |

Constraint utama:
- unique `service_date` untuk mencegah input ganda pada tanggal layanan yang sama.

### 5.2 `menus`

Fungsi: Menyimpan menu siklus utama (Paket 1 s/d 11).

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | tinyint | PK | ID menu siklus (dibatasi 1 s/d 11) |
| `name` | varchar(100) | not null | Nama menu, mis. "Paket 1" |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |

### 5.3 `menu_schedules`

Fungsi: Menyimpan pemetaan tanggal ke menu.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID jadwal menu |
| `day_of_month` | int | not null | Tanggal 1 s/d 31 |
| `menu_id` | tinyint | not null, FK | Relasi ke `menus.id` |

Constraint utama:

- unique `day_of_month` untuk memastikan satu tanggal hanya memiliki satu jadwal menu.

### 5.4 `dishes`

Fungsi: Menyimpan daftar dish/hidangan.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID dish |
| `name` | varchar(100) | not null | Nama dish |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |

### 5.5 `menu_dishes`

Fungsi: Menghubungkan menu (Paket) dengan dish pada waktu makan tertentu.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID relasi menu-dish |
| `menu_id` | tinyint | not null, FK | Relasi ke `menus.id` |
| `meal_time_id` | tinyint | not null, FK | Relasi ke `meal_times.id` (Pagi, Siang, Sore) |
| `dish_id` | bigint | not null, FK | Relasi ke `dishes.id` |

Constraint utama:
- unique `(menu_id, meal_time_id)` untuk mencegah slot ganda pada satu paket menu.

### 5.6 `dish_compositions`

Fungsi: Menyimpan komposisi item per dish.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID komposisi |
| `item_id` | bigint | not null, FK | Relasi ke `items.id` |
| `dish_id` | bigint | not null, FK | Relasi ke `dishes.id` |
| `qty_per_patient` | decimal(10,2) | not null | Kebutuhan item per pasien untuk dish tersebut |

Constraint utama:

- unique `(dish_id, item_id)` untuk mencegah komposisi item ganda pada dish yang sama.

## 6. SPK & Recommendations

### 6.1 `spk_calculations`

Fungsi: Menyimpan header perhitungan SPK.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID kalkulasi SPK |
| `spk_type` | varchar(50) | not null | Tipe SPK (basah, kering-pengemas) |
| `version` | int | default 1 | Versi regenerasi untuk scope yang sama |
| `scope_key` | varchar(255) | not null | Hash/key identitas scope (tanggal/bulan/meal/category) |
| `is_latest` | boolean | default true | Penanda versi terbaru untuk scope_key |
| `calculation_scope` | varchar(50) | not null | Skala hitung (daily, monthly) |
| `calculation_date` | date | not null | Tanggal kalkulasi dibuat |
| `target_date_start` | date | nullable | Tanggal awal target belanja |
| `target_date_end` | date | nullable | Tanggal akhir target belanja |
| `target_month` | varchar(7) | nullable | Target bulan (YYYY-MM) |
| `daily_patient_id` | bigint | nullable, FK | Relasi ke `daily_patients.id` (untuk SPK basah) |
| `user_id` | bigint | not null, FK | User pembuat SPK |
| `category_id` | bigint | not null, FK | Relasi ke `item_categories.id` |
| `estimated_patients` | int | not null | Jumlah pasien terkunci saat generate |
| `is_finish` | bool | default false | Status finalisasi / validasi SPK |
| `stock_posted` | bool | default false | Penanda mutasi stok OUT sudah di-post |
| `created_at` | timestamp | nullable | Waktu dibuat |
| `updated_at` | timestamp | nullable | Waktu diperbarui |

### 6.2 `spk_recommendations`

Fungsi: Menyimpan hasil rekomendasi item untuk satu kalkulasi SPK.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID rekomendasi |
| `spk_id` | bigint | not null, FK | Relasi ke `spk_calculations.id` |
| `item_id` | bigint | not null, FK | Relasi ke `items.id` |
| `target_date` | date | nullable | Tanggal spesifik kebutuhan (untuk SPK basah) |
| `current_stock_qty` | decimal(12,2) | not null | Sisa stok saat generate |
| `required_qty` | decimal(12,2) | not null | Kebutuhan bruto sistem |
| `system_recommended_qty` | decimal(12,2) | not null | Rekomendasi belanja (bruto - stok) |
| `recommended_qty` | decimal(12,2) | not null | Rekomendasi final setelah override |
| `is_overridden` | boolean | default false | Penanda ada override manual |
| `override_reason` | text | nullable | Alasan override |
| `overridden_by` | bigint | nullable, FK | User yang melakukan override |
| `overridden_at` | timestamp | nullable | Waktu override |

Constraint utama:

- unique `(spk_id, item_id)` untuk mencegah item ganda pada satu hasil SPK.

Catatan kompatibilitas SRS/report:

- Runtime persistence menyimpan rekomendasi kaya (`required_qty`, `system_recommended_qty`, `recommended_qty`, override metadata).
- Untuk kebutuhan kontrak SRS/report yang lebih sederhana, backend menyediakan lapisan proyeksi kompatibilitas yang memetakan `recommended_qty` -> `qty` tanpa menghapus field kaya di persistence.
- Proyeksi kompatibilitas ini bersifat additive/read-only terhadap response report; histori versi dan semantics persistence tetap mengikuti model runtime saat ini.

## 7. Audit Logging

### 7.1 `audit_logs`

Fungsi: Menyimpan histori aktivitas penting pengguna dan sistem.

| Column | Type | Constraint | Description |
|---|---|---|---|
| `id` | bigint | PK, increment | ID log |
| `user_id` | bigint | nullable, FK | User pelaku, null jika oleh sistem |
| `action_type` | varchar(50) | not null | Jenis aksi yang dicatat, mis. `stock_transaction_create` |
| `table_name` | varchar(100) | not null | Nama tabel target perubahan |
| `record_id` | bigint | not null | ID record target |
| `message` | text | nullable | Pesan ringkas log |
| `old_values` | json | nullable | Nilai lama sebelum perubahan |
| `new_values` | json | nullable | Nilai baru setelah perubahan |
| `ip_address` | varchar(45) | nullable | IP address pelaku |
| `created_at` | timestamp | nullable | Waktu kejadian |

Index utama:

- `(table_name, record_id)` untuk pencarian histori per record
- `user_id` untuk pencarian histori per user

## 8. Relationships Summary

### 8.1 Lookup Relations

- `items.item_category_id -> item_categories.id`
- `items.item_unit_base_id -> item_units.id`
- `items.item_unit_convert_id -> item_units.id`
- `stock_transactions.type_id -> transaction_types.id`
- `stock_transactions.approval_status_id -> approval_statuses.id`
- `menu_dishes.meal_time_id -> meal_times.id`
- `spk_calculations.category_id -> item_categories.id`

### 8.2 User Relations

- `users.role_id -> roles.id`
- `audit_logs.user_id -> users.id`
- `stock_transactions.user_id -> users.id`
- `stock_transactions.approved_by -> users.id`
- `spk_calculations.user_id -> users.id`

### 8.3 Inventory Relations

- `stock_transactions.parent_transaction_id -> stock_transactions.id`
- `stock_transaction_details.transaction_id -> stock_transactions.id`
- `stock_transaction_details.item_id -> items.id`
- `monthly_stock_snapshots.item_id -> items.id`

### 8.4 Menu Relations

- `menu_schedules.menu_id -> menus.id`
- `menu_dishes.menu_id -> menus.id`
- `menu_dishes.dish_id -> dishes.id`
- `dish_compositions.dish_id -> dishes.id`
- `dish_compositions.item_id -> items.id`

### 8.5 SPK Relations

- `spk_calculations.daily_patient_id -> daily_patients.id`
- `spk_recommendations.spk_id -> spk_calculations.id`
- `spk_recommendations.item_id -> items.id`
- `stock_transactions.spk_id -> spk_calculations.id`

## 9. Data Integrity Notes

1. `items.qty` harus dijaga melalui alur transaksi stok, bukan update manual sembarangan.
2. Revisi transaksi harus tetap menjaga histori transaksi asal melalui `parent_transaction_id`.
3. `monthly_stock_snapshots` perlu aturan tambahan jika ada koreksi backdated.
4. Perubahan `dish_compositions` setelah menu aktif perlu kebijakan histori agar SPK lama tetap konsisten.
5. `spk_recommendations` tidak boleh berdiri sendiri tanpa `spk_calculations`.
6. Audit log harus dicatat untuk aksi penting dan approval workflow.
7. Soft delete pada tabel lookup (`roles`, `item_categories`, `transaction_types`, `approval_statuses`, `item_units`) tidak menghapus row secara fisik; row yang sudah soft-deleted tetap dapat direferensikan oleh data historis melalui FK, tetapi tidak muncul pada endpoint list/show aktif dan tidak dapat diassign ke resource baru.
8. `item_categories.name` dan `item_units.name` unik hanya di antara row aktif. Jika nama yang sama sudah ada pada row yang di-soft-delete, row tersebut harus di-restore, bukan dibuat ulang.
9. `items.item_unit_base_id` dan `items.item_unit_convert_id` bersifat nullable untuk akomodasi data historis dan periode transisi; nilai string di `unit_base`/`unit_convert` tetap ada sebagai fallback backward compatibility.

## 10. Open Questions

1. Bagaimana kebijakan rekonsiliasi jika laporan evaluasi perlu menggabungkan mutasi `OPNAME_ADJUSTMENT` dengan transaksi `OUT` SPK pada periode yang sama.
2. Apakah perlu kebijakan arsip/retensi khusus untuk histori SPK multi-versi (`is_latest=false`) ketika volume data meningkat.
3. Apakah menu historis perlu dibekukan saat dish composition berubah.
4. Bagaimana aturan koreksi transaksi setelah snapshot bulanan terbentuk.
