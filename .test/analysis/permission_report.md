# Route Permission Analysis Report

## Status Summary

The current implementation of `backend/app/Config/Routes.php` contradicts the canonical documentation in `backend/docs/architecture/runtime-status.md` and `backend/docs/reference/api-contract.md`. 

The **Gizi/Dapur** role is currently blocked from almost all READ operations in the Menu, Dish, SPK, and Patient modules, while being allowed to perform WRITE operations on them.

## Discrepancies Found

| Resource / Endpoint | Expected (Docs) | Actual (Routes.php) | Status |
|---|---|---|---|
| `GET /api/v1/dishes` | `admin, gudang, dapur` | `admin, gudang` | 🔴 BUG |
| `GET /api/v1/menus` | `admin, gudang, dapur` | `admin, gudang` | 🔴 BUG |
| `GET /api/v1/menu-dishes` | `admin, gudang, dapur` | `admin, gudang` | 🔴 BUG |
| `GET /api/v1/menu-calendar` | `admin, gudang, dapur` | `admin, gudang` | 🔴 BUG |
| `GET /api/v1/spk/basah/history` | `admin, gudang, dapur` | `admin, gudang` | 🔴 BUG |
| `GET /api/v1/spk/kering-pengemas/history` | `admin, gudang, dapur` | `admin, gudang` | 🔴 BUG |
| `GET /api/v1/daily-patients` | `admin, gudang, dapur` | `admin, gudang` | 🔴 BUG |
| `POST /api/v1/daily-patients` | `admin, dapur` | `admin, dapur` | ✅ OK |
| `POST /api/v1/dishes` | `admin, dapur` | `admin, dapur` | ✅ OK |

## Root Cause

In `backend/app/Config/Routes.php`, line 215, a large group of routes is wrapped in:
```php
$routes->group("", ["filter" => "role:admin,gudang"], ...);
```
This group contains all the `GET` routes for the modules mentioned above. Because `dapur` is not in this list, they receive a `403 Forbidden` for all lookup/list/show actions in these modules.

However, line 292 has another group for write operations:
```php
$routes->group("", ["filter" => "role:admin,dapur"], ...);
```
This group correctly includes `dapur` for `POST/PUT/DELETE`.

## Impact on Flow

The **Menu Management Flow** is broken for Dapur because:
1. They can create a dish (`POST /dishes`), but they cannot see the list of dishes they just created (`GET /dishes`).
2. They can assign a slot (`POST /menu-dishes`), but they cannot see the current slot assignments (`GET /menu-dishes`) to know what to assign.
3. They can generate SPK (`POST /spk/basah/generate`), but they cannot see the SPK history (`GET /spk/basah/history`) to retrieve the recommendation for printing.

## Recommendation (For Future Action)

Update `backend/app/Config/Routes.php` to include `dapur` in the read-only group or move the shared read routes to a group that includes all three operational roles (`admin, gudang, dapur`).
