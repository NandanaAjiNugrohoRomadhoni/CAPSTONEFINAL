# UI and Backend Audit Checklist

Last updated: 2026-04-19

This checklist is based on:
- Backend routes in `D:\PROJEK CAPSTONE\Capstone\backend\app\Config\Routes.php`
- SDK surface in `D:\PROJEK CAPSTONE\Capstone\frontend\src\sdk`
- Current UI usage in `D:\PROJEK CAPSTONE\gudang-app\src`

The goal is to track:
- which backend APIs already exist
- which SDK resources already exist
- which ones are already used by the UI
- which ones are still not connected

## Status Legend

- `USED`: backend/API/SDK already consumed in the UI
- `PARTIAL`: some data is real, but the full feature is not supported yet
- `NOT USED`: backend/API/SDK exists, but current UI does not consume it
- `UI ONLY`: page exists, but it still depends mostly on local/static data
- `BLOCKED BY BACKEND`: UI wants the feature, but backend route support is incomplete

## API and SDK Checklist

| Area | Backend Route Exists | SDK Exists | UI Uses It | Status | Notes |
|---|---|---:|---:|---|---|
| Auth | Yes | Yes | Yes | USED | Login, logout, `me`, and password flow are wired |
| Dashboard | Yes | Yes | Yes | USED | Dashboard aggregate is used on admin dashboard |
| Users | Yes | Yes | Yes | USED | List, create, edit, activate, deactivate, delete are wired |
| Roles | Yes | Yes | Yes | USED | Used in user management role options |
| Item Categories / Jenis Bahan | Yes | Yes | Yes | USED | Data Jenis Bahan is backend-backed |
| Item Units / Satuan | Yes | Yes | Yes | USED | Data Satuan is backend-backed |
| Items / Stock Items | Yes | Yes | Yes | USED | Used in stock and transaction pages |
| Transaction Types | Yes | Yes | Yes | USED | Used in transaction history |
| Approval Statuses | Yes | Yes | Yes | USED | Used in transaction history |
| Dishes / Menu Makanan | Yes | Yes | Yes | PARTIAL | CRUD is wired, but displayed composition quality depends on joined data |
| Dish Compositions | Yes | Yes | Yes | PARTIAL | UI uses it, but some dishes still show no composition because join/data quality still needs checking |
| Menus / Paket Menu master | Yes (`GET`) | Yes | Yes | PARTIAL | Read is real; full create/edit/delete package flow is not supported by backend routes |
| Menu Dishes / Paket Menu slots | Yes (`GET`, `POST`) | Yes | Yes | PARTIAL | Assign slot works where backend allows; replace/remove slot is not fully supported |
| Menu Schedules | Yes | Yes | Yes | USED | Calendar projection is already wired |
| Menu Calendar | Yes | Covered by schedule resource | Yes | USED | Calendar page reads backend projection |
| Daily Patients | Yes | Yes | Yes | USED | Used in SPK Basah and transaksi keluar flow |
| SPK Basah | Yes | Yes | Yes | USED | Generate and history are wired in super admin |
| SPK Kering & Pengemas | Yes | Yes | Yes | USED | Generate and history are wired in super admin |
| SPK Stock In Prefill | Yes | Included under SPK flow | No | NOT USED | Backend exists but current UI does not directly expose it |
| Reports Stocks | Yes | Yes | No | NOT USED | Route exists, current laporan page focuses on evaluation view |
| Reports Transactions | Yes | Yes | No | NOT USED | Could be implemented in report/filter page later |
| Reports SPK History | Yes | Yes | No | NOT USED | Backend exists, not surfaced as dedicated report UI yet |
| Reports Evaluation | Yes | Yes | Yes | USED | Laporan page already uses evaluation data |
| Stock Transactions | Yes | Yes | Yes | USED | Incoming, outgoing, and history pages are connected |
| Stock Opnames / Penyesuaian Stok | Yes | Yes | No | NOT USED | Backend and SDK exist, but current UI page is still not connected |
| Meal Times | Yes | Yes | No | NOT USED | Backend lookup exists, but UI does not use it directly |
| Restore flows for item categories | Yes | SDK support via direct request patterns if added | No | NOT USED | Backend restore exists, UI not built for archive/restore flow yet |
| Restore flows for item units | Yes | SDK support via direct request patterns if added | No | NOT USED | Same as above |
| Restore flows for items | Yes | SDK support via direct request patterns if added | No | NOT USED | Same as above |
| Restore flows for users | Yes | SDK support via direct request patterns if added | No | NOT USED | Same as above |

## Known Backend Constraints

### Paket Menu is still limited by backend

The backend currently supports reading package data and assigning slots, but not full package CRUD.

Currently available:
- `GET /api/v1/menus`
- `GET /api/v1/menu-dishes`
- `POST /api/v1/menu-dishes`

Still missing for full UI behavior:
- `POST /api/v1/menus`
- `PUT /api/v1/menus/{id}`
- `DELETE /api/v1/menus/{id}`
- `PUT /api/v1/menu-dishes/{id}` or equivalent replace endpoint
- `DELETE /api/v1/menu-dishes/{id}` or equivalent remove endpoint

Because of that, the Paket Menu page is only `PARTIAL`, not fully synchronized for true create/edit/delete package behavior.

### Role naming mismatch risk

Backend route protection uses:
- `admin`
- `dapur`
- `gudang`

UI language uses:
- `Super Admin`
- `Gizi`
- `Gudang`

This creates a compatibility risk, especially for `Gizi`, because it appears to map to backend role `dapur`, not literally `gizi`.

## Page-by-Page UI Audit

| Page | Current State | Notes |
|---|---|---|
| Login | USED | Uses auth flow and remember-me logic in frontend store |
| Profil | USED | Reads login/auth state and updates user profile through API |
| Super Admin Dashboard | USED | Connected to dashboard and evaluation/report data |
| Super Admin Users | USED | Fully backend-backed for current CRUD/status flow |
| Super Admin Data Jenis Bahan | USED | Connected to item categories |
| Super Admin Data Satuan | USED | Connected to item units |
| Super Admin Menu Makanan | PARTIAL | Dishes and dish compositions are real, but some composition display still depends on backend join/data completeness |
| Super Admin Paket Menu | PARTIAL | Real read and slot assignment, but full package CRUD is blocked by backend |
| Super Admin Kalender Menu | USED | Reads real schedule/calendar data |
| Super Admin Stok Bahan Basah | USED | Reads real item data |
| Super Admin Stok Bahan Kering | USED | Reads real item data |
| Super Admin Penyesuaian / Riwayat Stok | UI ONLY | Still mostly static/local and should move to stock opname endpoints |
| Super Admin Transaksi Masuk | USED | Connected to stock transaction create flow |
| Super Admin Transaksi Keluar | USED | Connected to daily patients plus stock transaction flow |
| Super Admin Riwayat Transaksi | USED | Connected to stock transaction list and lookups |
| Super Admin SPK Basah | USED | Connected |
| Super Admin SPK Kering & Pengemas | USED | Connected |
| Super Admin Riwayat SPK | USED | Connected |
| Super Admin Laporan | PARTIAL | Evaluation report is connected, but other report endpoints are still unused |
| Gizi Dashboard | USED (reuse) | Reuses existing backend-backed admin dashboard style |
| Gizi Menu Makanan | PARTIAL | Same real data pattern as admin menu makanan |
| Gizi Paket Menu | PARTIAL | Same backend limitation as admin paket menu |
| Gizi Kalender Menu | USED | Connected to backend |
| Gizi Stok | USED (reuse) | Reuses backend-backed stock page |
| Gizi SPK | UI ONLY | Still local/static and not yet connected to backend flow |
| Gizi Laporan | USED (reuse) | Reuses existing report page behavior |
| Gudang Dashboard | USED (reuse) | Reuses existing backend-backed dashboard structure |

## Backend Features Not Yet Reflected Well in UI

These are the biggest remaining opportunities:

1. Stock Opname / Penyesuaian Stok
   - backend exists
   - SDK exists
   - UI page is still not really connected

2. Restore / Archive flows
   - backend restore endpoints exist for several entities
   - UI still behaves mostly as delete-only

3. Full Paket Menu CRUD
   - blocked because backend route set is incomplete

4. Full reporting views
   - stocks report
   - transaction report
   - SPK history report
   - currently not exposed as rich UI features

5. Direct use of meal-time lookup
   - backend exists
   - UI currently hardcodes meal session handling more than it should

6. SPK helper endpoints
   - backend has more SPK support than what current UI directly exposes

## Recommended Next Priority

### Highest-value UI work

1. Connect Penyesuaian Stok page to `stock-opnames`
2. Finish Menu Makanan composition join so descriptions/details always reflect real `dish-compositions`
3. Expose archive/restore flows for users/items/master data
4. Upgrade laporan to consume more report endpoints

### Requires backend support first

1. Real Paket Menu create
2. Real Paket Menu edit slot replacement
3. Real Paket Menu delete

## Quick Summary

- The project source has been reviewed across backend, SDK, and UI folders.
- The UI is already connected for many core features.
- The biggest missing real implementation is `stock-opnames`.
- The biggest partially implemented area is `paket menu`.
- The biggest compatibility risk is role naming mismatch between `gizi` and backend `dapur`.
