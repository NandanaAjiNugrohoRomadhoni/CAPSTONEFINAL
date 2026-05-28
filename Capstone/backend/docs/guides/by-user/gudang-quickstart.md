# Gudang Quickstart Guide

## Your Role
The Gudang (Warehouse) role is responsible for the physical and digital management of all items in the inventory. Your primary objectives are to record stock movements, maintain accurate item data, and perform regular stock counts (opname) to ensure the system reflects real-world availability.

Otorisasi Anda didasarkan pada App Role `gudang` yang dikelola oleh `app/Filters/RoleFilter.php` di route API. Sistem membedakan grup otorisasi Shield (`admin`, `developer`, `user`) dari App Role operasional (`admin`, `dapur`, `gudang`) untuk keamanan dan fleksibilitas kredensial.

## Can/Can’t
- **Can:**
  - Create and update Items and their conversion rules.
  - Record stock movements using IN, OUT, and RETURN_IN transaction types.
  - Submit revisions for existing transactions if errors are found.
  - Create, submit, and manage Stock Opname drafts.
  - View all menus, schedules, and daily patient data.
  - Run the full SPK flow: preview, generate, inspect history, override recommendations, use stock-in prefill, and finalize SPK stock posting.
  - Access stock and transaction reports.
- **Can’t:**
  - Direct correction of `items.qty` (This is restricted to Admin).
  - Approve or Reject their own transaction revisions.
  - Finalize/Post Stock Opnames (Admin only).
  - Modify system lookup tables (Roles, Categories, Units).
  - Create or update Dishes/Menus (Dapur/Admin only).

## Key Workflows

### 1. Recording Stock Movements
Record any physical movement of goods using the appropriate transaction type.
- **Stock IN:** Used for receiving new supplies.
- **Stock OUT:** Used for issuing items to the kitchen or other departments.
- **Stock RETURN_IN:** Used when items previously issued are returned to the warehouse.

**Example Payload (POST /api/v1/stock-transactions):**
```json
{
  "type_name": "IN",
  "transaction_date": "2026-04-16",
  "details": [
    {
      "item_id": 1,
      "qty": 10,
      "input_unit": "base"
    }
  ]
}
```

### 2. Correcting Errors (Revision Flow)
If you discover an error in a transaction that has already been recorded, you cannot edit it directly. Instead, you must submit a revision for Admin approval.
- **Submit Revision:** `POST /api/v1/stock-transactions/(:num)/submit-revision`
- **Visibility:** Once submitted, the revision enters a PENDING state. It only affects stock levels AFTER an Admin approves it.

### 3. Stock Opname (Inventory Count)
Perform regular physical counts to synchronize system data with warehouse reality.
1. **Create Draft:** `POST /api/v1/stock-opnames`
2. **Record Counts:** Update counts during the opname process.
3. **Submit for Review:** `POST /api/v1/stock-opnames/(:num)/submit`
4. **Finalization:** An Admin will review the discrepancies, then approve and post the opname to adjust the inventory.

## Gotchas
- **Controlled Stock:** Direct edits to `items.qty` are not the normal path. Always use Transactions or Opnames to move stock.
- **Unit Conversions:** When recording transactions, you can specify `input_unit` as "base" or "convert". The system automatically handles the math based on the item's conversion factor.
- **Revision Limits:** A transaction lineage can only have one `PENDING` revision at a time, and you still cannot revise a revision. If you submit the same parent transaction again before Admin review, the system updates that same pending revision with your latest payload. After a sibling revision is approved or rejected, you can submit the next sibling revision against the same original transaction.
- **Negative Stock:** The system blocks OUT transactions if the requested quantity exceeds available stock. Check current levels before issuing items.
