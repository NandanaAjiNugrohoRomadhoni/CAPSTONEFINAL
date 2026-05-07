# Dapur Quickstart Guide

## Your Role
The Dapur role is responsible for meal planning, dish management, and generating Surat Perintah Kerja (SPK) for nutritional operations. Your primary goals are to maintain an accurate menu cycle, manage dish compositions (recipes), and provide the daily patient counts that drive stock requirements. You bridge the gap between clinical nutrition needs and warehouse inventory.

Akses fitur Anda dikelola oleh App Role `dapur` melalui `app/Filters/RoleFilter.php` pada route API. Meskipun Shield Group Anda mungkin `user` di `app/Config/AuthGroups.php`, App Role `dapur` adalah yang memberikan Anda akses ke modul menu dan SPK.

## Can/Can’t
- **Can:**
  - Create and update dishes and their ingredient compositions.
  - Assign dishes to menu slots and meal times.
  - Create and manage cyclical menu schedules.
  - Input daily patient counts.
  - Generate SPK for both Basah (Fresh) and Kering/Pengemas (Dry/Packaging) categories.
  - Override system-recommended quantities in SPK drafts before they are finalized.
  - Access dashboard metrics related to menu cycles and SPK history.
- **Can’t:**
  - Manage user accounts or roles.
  - Modify the core inventory master list (Items, Categories, Units).
  - Create or approve standard stock transactions (IN/OUT/Correction) directly.
  - Perform the final "Post Stock" action on SPK (this is typically an Admin or Gudang responsibility to ensure physical inventory synchronization).
  - Access financial reports or global transaction logs outside of SPK contexts.

## Key Workflows

### 1. Meal & Menu Planning
Maintain the repository of dishes and their cyclical rotation.
- **Manage Dishes:** `POST /api/v1/dishes` (Create), `PUT /api/v1/dishes/(:num)` (Update)
- **Define Compositions:** `POST /api/v1/dish-compositions`
- **Assign to Menu:** `POST /api/v1/menu-dishes`
- **Set Schedule:** `POST /api/v1/menu-schedules`

### 2. Daily Patient Input
Every SPK calculation depends on the number of patients. Input this before generating SPK.
- **Create Entry:** `POST /api/v1/daily-patients`
- **Data Shape:** `{ "service_date": "YYYY-MM-DD", "total_patients": 120, "notes": "..." }`

### 3. SPK Basah (Fresh) Workflow
Generate requirements for fresh ingredients, usually on a daily basis.
- **Preview Stock:** `POST /api/v1/spk/basah/operational-stock-preview` (Check what's left today)
- **Generate SPK:** `POST /api/v1/spk/basah/generate`
- **Override Qty:** `POST /api/v1/spk/basah/history/(:num)/override` (Adjust recommendations)

### 4. SPK Kering/Pengemas (Dry/Packaging) Workflow
Generate requirements for dry goods and packaging, usually on a monthly or batch basis.
- **Generate SPK:** `POST /api/v1/spk/kering-pengemas/generate`
- **History Detail:** `GET /api/v1/spk/kering-pengemas/history/(:num)`

## Gotchas
- **Menu Slots:** Every menu (Packages 1–11) has three meal times: PAGI, SIANG, and SORE. Ensure all slots are filled for a complete calculation.
- **Versioned SPK:** Generating an SPK for the same date/category again will create a NEW version. It does not overwrite the previous one. Always check the `is_latest` flag in history.
- **Stock Posting:** Your SPK generation creates a "Draft" requirement. It does NOT subtract items from the warehouse until someone (Admin/Gudang) performs the **Post Stock** action.
- **Item Lookups:** When defining compositions, you use the `item_id`. If an item is missing or inactive, you cannot add it to a dish.
- **SPK Prefill:** Once an SPK is posted, the Gudang role can use the `/api/v1/spk/stock-in-prefill/(:num)` endpoint to quickly create a "Stock IN" transaction for the requested items.
