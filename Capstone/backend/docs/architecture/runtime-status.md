# Project Flow Alignment and Revised Class Diagram

## Quick Router

- **Canonical for:** compact runtime status index across modules, flow summary, and cross-doc navigation.
- **Read this when:** you need the fastest answer for what is implemented vs planned and which detailed doc to open next.
- **Read next:** `../reference/api-contract.md` for endpoint contracts, `../reference/schema.md` for schema-constraints.
- **Legacy context (Deprecated):** See `../governance/changelog.md` and `../governance/migration-map.md` for removed files.
- **Not canonical for:** full request/response payload detail or field-by-field schema definitions.

## 1. Purpose

Dokumen ini menyelaraskan class diagram usulan dengan implementasi backend yang benar-benar ada saat ini.

Kesimpulan utamanya:

- project ini **bukan** Laravel MVC dengan Blade views;
- backend saat ini adalah **CodeIgniter 4 REST API**;
- frontend akan menjadi **Next.js client** yang mengonsumsi API;
- arsitektur aktual yang berjalan saat ini adalah **route/filter -> controller -> service -> model -> database**.

Source of truth yang dipakai untuk alignment ini:

- `app/Config/Routes.php`
- `app/Controllers/Api/V1/*`
- `app/Services/*`
- `app/Models/*`
- `app/Filters/RoleFilter.php`
- `app/Libraries/JsonApiExceptionHandler.php`
- `../reference/api-contract.md`
- `../reference/schema.md`

## 3. Actor Model & Auth Architecture

Aplikasi ini menggunakan model otorisasi dua lapis untuk memisahkan manajemen kredensial tingkat rendah dari logika bisnis aplikasi.

### Layer 1: Shield Auth Groups (Kredensial)
Dikelola oleh [CodeIgniter Shield](https://shield.codeigniter.com/) melalui `app/Config/AuthGroups.php`. Layer ini menentukan grup otorisasi dasar untuk manajemen token dan akses sistem secara umum.
- **Groups:** `admin`, `developer`, `user` (default), `beta`.
- **Enforcement:** Melalui filter `tokens` dan `session` bawaan Shield.

### Layer 2: App Roles (Logika Bisnis)
Dikelola melalui tabel `roles` dan `RoleFilter.php`. Layer ini adalah **source of truth** untuk semua gate fitur di dalam aplikasi. Role ini ditetapkan ke user saat pembuatan akun oleh Admin.
- **Roles:** `admin`, `dapur`, `gudang` (didefinisikan di `app/Database/Seeds/RoleSeeder.php`).
- **Enforcement:** Melalui `app/Filters/RoleFilter.php` yang dipasang pada grup route di `app/Config/Routes.php` (contoh: `filter => role:admin,gudang`).

Pemisahan ini memungkinkan fleksibilitas di mana user dengan grup Shield `user` dapat memiliki peran operasional yang berbeda (`dapur` atau `gudang`) tanpa harus mengubah skema otorisasi dasar Shield.

## 4. Module Matrix (Implemented)

| Module | Status | Routes/Endpoints | Key Logic / Constraints |
|---|---|---|---|
| Auth | Implemented | `/auth/login`, `/auth/me`, `/auth/logout`, `/auth/password` | Login berbasis token Shield; self-service password change revoke semua token user |
| Users | Implemented | `/users`, `/users/{id}`, `/users/{id}/restore` | Soft delete revoke token; activate/deactivate mengontrol login; role resolution bisa by id or by name; restore bersifat idempotent |
| Items | Implemented | `/items`, `/items/{id}`, `/items/{id}/restore` | `qty` read-only; unit write pakai nama; delete bersifat soft delete; restore bersifat idempotent |
| Stock Transactions | Implemented | `/stock-transactions`, `/submit-revision`, `/approve`, `/reject`, `/stock-transactions/direct-corrections` | Transaksi stok adalah satu-satunya jalur mutasi stok; revision workflow adalah domain flow inti; direct stock correction tersedia untuk admin; **tidak ada DELETE route** |
| Menu & Nutrition | Implemented | `/menus`, `/menu-dishes`, `/menu-schedules`, `/menu-calendar`, `/dishes`, `/dish-compositions` | Siklus menu 1-11; package header immutable; menu-dishes (slot assignment) supports full CRUD (POST/PUT/DELETE); resolver order: Feb 29 -> Paket 9, day 31 -> Paket 11, manual override, lalu default pattern |
| Daily Patients | Implemented | `/daily-patients`, `/daily-patients/{service_date}` | Input pasien harian per service date; immutable audit record (no edit/delete route) |
| SPK Calculations | Implemented | `/spk/basah/*`, `/spk/kering-pengemas/*` | Basah: input-day basis; Kering: monthly basis; generation membuat versi histori baru; stock posting adalah langkah eksplisit |
| Stock Opnames | Implemented (Facade) | `/stock-opnames/*` | Dedicated opname workflow preserved as compatibility facade; `POSTED` opnames result in `OPNAME_ADJUSTMENT` ledger transactions |
| Audit Reporting | Implemented (JSON) | `/reports/stocks`, `/reports/transactions`, `/reports/spk-history`, `/reports/evaluation` | Dataset JSON siap ekspor tersedia runtime; period mandatory |

## 4.2 Compact Runtime Cross-Reference Matrix

Compact matrix ini dipakai sebagai truth index lintas runtime route, actor gate, module ownership backend, dan metode frontend SDK yang benar-benar ada.

| Domain | Implemented Routes (exact) | Actor gates (RoleFilter) | Module ownership (controller -> service) | Frontend SDK methods (exact) |
|---|---|---|---|---|
| `items` | `GET /api/v1/items`, `POST /api/v1/items`, `GET /api/v1/items/{id}`, `PUT /api/v1/items/{id}`, `DELETE /api/v1/items/{id}`, `PATCH /api/v1/items/{id}/restore` | read/write: `admin,gudang`; delete/restore: `admin` | `Items` -> `ItemManagementService` | `sdk.items.list`, `sdk.items.get`, `sdk.items.create`, `sdk.items.update`, `sdk.items.delete`, `sdk.items.restore` |
| `auth/password` | `PATCH /api/v1/auth/password` | authenticated token user (`admin`, `dapur`, `gudang`) | `Auth::changePassword` -> `AuthService::changePassword` | `sdk.auth.changePassword` |
| `daily-patients` | `GET /api/v1/daily-patients`, `GET /api/v1/daily-patients/{service_date}`, `POST /api/v1/daily-patients` | GET: `admin,dapur,gudang`; POST: `admin,dapur` | `DailyPatients` -> `DailyPatientService` | `sdk.dailyPatients.list`, `sdk.dailyPatients.get`, `sdk.dailyPatients.create` |
| `spk` | `GET /api/v1/spk/basah/menu-calendar`, `POST /api/v1/spk/basah/operational-stock-preview`, `POST /api/v1/spk/basah/generate`, `GET /api/v1/spk/basah/history`, `GET /api/v1/spk/basah/history/{id}`, `POST /api/v1/spk/basah/history/{id}/override`, `POST /api/v1/spk/basah/history/{id}/post-stock`, `GET /api/v1/spk/kering-pengemas/menu-calendar`, `POST /api/v1/spk/kering-pengemas/generate`, `GET /api/v1/spk/kering-pengemas/history`, `GET /api/v1/spk/kering-pengemas/history/{id}`, `POST /api/v1/spk/kering-pengemas/history/{id}/override`, `POST /api/v1/spk/kering-pengemas/history/{id}/post-stock`, `GET /api/v1/spk/stock-in-prefill/{id}` | basah/kering read history+calendar: `admin,dapur,gudang`; generate+override: `admin,dapur`; post-stock: `admin`; `stock-in-prefill`: `admin,dapur` | `SpkBasah` -> `SpkBasahGenerationService`/`SpkStockPostingService`; `SpkKeringPengemas` -> `SpkKeringPengemasGenerationService`/`SpkStockPostingService` | `sdk.spk.basahMenuCalendar`, `sdk.spk.operationalStockPreview`, `sdk.spk.generateBasah`, `sdk.spk.listBasah`, `sdk.spk.getBasah`, `sdk.spk.overrideBasah`, `sdk.spk.postBasahStock`, `sdk.spk.keringPengemasMenuCalendar`, `sdk.spk.generateKeringPengemas`, `sdk.spk.listKeringPengemas`, `sdk.spk.getKeringPengemas`, `sdk.spk.overrideKeringPengemas`, `sdk.spk.postKeringPengemasStock`, `sdk.spk.stockInPrefill` |
| `stock-opnames` | `POST /api/v1/stock-opnames`, `GET /api/v1/stock-opnames/{id}`, `POST /api/v1/stock-opnames/{id}/submit`, `POST /api/v1/stock-opnames/{id}/approve`, `POST /api/v1/stock-opnames/{id}/reject`, `POST /api/v1/stock-opnames/{id}/post` | create/show/submit: `admin,gudang`; approve/reject/post: `admin` | `StockOpnames` -> `StockOpnameService` | `sdk.stockOpnames.create`, `sdk.stockOpnames.get`, `sdk.stockOpnames.submit`, `sdk.stockOpnames.approve`, `sdk.stockOpnames.reject`, `sdk.stockOpnames.post` |
| `dashboard` | `GET /api/v1/dashboard` | `admin,dapur,gudang` | `Dashboard::index` -> `DashboardAggregateService::getDashboardAggregateForUser` | `sdk.dashboard.getAggregate` |
| `reports` | `GET /api/v1/reports/stocks`, `GET /api/v1/reports/transactions`, `GET /api/v1/reports/spk-history`, `GET /api/v1/reports/evaluation` | `admin,dapur,gudang` | `Reports` -> `ReportingService` | `sdk.reports.getStocks`, `sdk.reports.getTransactions`, `sdk.reports.getSpkHistory`, `sdk.reports.getEvaluation` |
| `menu-dishes` | `GET /api/v1/menu-dishes`, `POST /api/v1/menu-dishes`, `PUT /api/v1/menu-dishes/{id}`, `DELETE /api/v1/menu-dishes/{id}` | GET: `admin,dapur,gudang`; POST/PUT/DELETE: `admin,dapur` | `Menus::slots`, `assignSlot`, `updateSlot`, `deleteSlot` | `sdk.menus.slots`, `sdk.menus.assignSlot`, `sdk.menus.updateSlot`, `sdk.menus.deleteSlot` |
| `menus` | `GET /api/v1/menus` | `admin,dapur,gudang` | `Menus::index` | `sdk.menus.list` |
| `dishes` | `GET /api/v1/dishes`, `GET /api/v1/dishes/{id}`, `POST /api/v1/dishes`, `PUT /api/v1/dishes/{id}`, `DELETE /api/v1/dishes/{id}` | GET: `admin,dapur,gudang`; POST/PUT/DELETE: `admin,dapur` | `Dishes` | `sdk.dishes.list`, `sdk.dishes.get`, `sdk.dishes.create`, `sdk.dishes.update`, `sdk.dishes.delete` |
| `menu-schedules` | `GET /api/v1/menu-schedules`, `GET /api/v1/menu-schedules/{id}`, `POST /api/v1/menu-schedules`, `PUT /api/v1/menu-schedules/{id}`, `GET /api/v1/menu-calendar` | GET: `admin,dapur,gudang`; POST/PUT: `admin,dapur` | `MenuSchedules` | `sdk.menuSchedules.list`, `sdk.menuSchedules.get`, `sdk.menuSchedules.create`, `sdk.menuSchedules.update`, `sdk.menuSchedules.calendarProjection` |
- endpoint baru sebaiknya didokumentasikan di `../reference/api-contract.md` sebagai `Implemented` atau `Planned`;
- dokumen desain sistem harus tetap membedakan antara target domain dan route yang sudah aktif;
- audit logging perlu diperluas ke write flow penting lain, bukan hanya transaksi stok;
- `items.qty` harus tetap dianggap sebagai controlled operational balance, bukan field bebas edit.

## 8. Final Alignment Summary

Revisi paling penting dari diagram awal adalah:

1. hapus layer `Views` dari diagram backend utama;
2. ganti dengan `NextjsFrontend` sebagai external client;
3. tambahkan **service layer** sebagai pusat business logic;
4. tampilkan filter/auth/error handling sebagai komponen lintas-layer;
5. pisahkan dengan tegas antara:
   - **implemented architecture**; dan
   - **future planned architecture**.

Dengan revisi ini, dokumentasi akan selaras dengan codebase sekarang dan tetap berguna sebagai blueprint pengembangan berikutnya.
