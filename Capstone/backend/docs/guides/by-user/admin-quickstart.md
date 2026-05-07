# Admin Quickstart Guide

## Your Role
The Admin role serves as the system overseer with full authority over organizational data, user management, and high-level stock governance. Your primary objectives include maintaining data integrity across lookup tables, managing user access, and performing final approvals or direct corrections on stock movements.

Aplikasi menggunakan otorisasi dua lapis:
1.  **Shield Groups** (`admin`, `developer`, `user`): Menentukan kredensial dasar tingkat sistem di `app/Config/AuthGroups.php`.
2.  **App Roles** (`admin`, `dapur`, `gudang`): Menentukan izin akses fitur aplikasi yang didefinisikan di `app/Database/Seeds/RoleSeeder.php` dan ditegakkan oleh `app/Filters/RoleFilter.php`.

Sebagai `admin`, Anda bertanggung jawab menetapkan App Role yang tepat saat membuat akun user baru.

## Can/Can’t
- **Can:**
  - Create, update, and soft-delete users.
  - Activate, deactivate, and reset passwords for any user.
  - Manage lookup tables (Item Categories, Item Units).
  - Approve or reject stock transactions and stock opnames.
  - Perform direct stock corrections that bypass the standard revision flow.
  - Post finalized SPK calculations to operational stock.
  - Access all reports and dashboard metrics.
- **Can’t:**
  - Delete roles (Roles are immutable system-level entities).
  - Modify stock transactions that are already in a finalized state (Approved/Rejected/Posted) without direct correction.
  - Authenticate as another user without their credentials.

## Key Workflows

### 1. User Management
Manage team access by creating new accounts or updating existing ones.
- **List Users:** `GET /api/v1/users`
- **Create User:** `POST /api/v1/users`
- **Reset Password:** `PATCH /api/v1/users/(:num)/password`

### 2. Stock Transaction Approval
Review and finalize stock movements submitted by the Gudang or Dapur roles.
- **List Transactions:** `GET /api/v1/stock-transactions`
- **Approve Transaction:** `POST /api/v1/stock-transactions/(:num)/approve`
- **Reject Transaction:** `POST /api/v1/stock-transactions/(:num)/reject`

### 3. Direct Stock Correction
Address discrepancies immediately by adjusting stock levels without a revision cycle.
- **Direct Correction:** `POST /api/v1/stock-transactions/direct-corrections`

## Gotchas
- **Soft Delete:** Deleting users, item categories, or item units uses soft delete. They remain in the database but are hidden from standard lists. Use the `/restore` endpoints to bring them back.
- **Unique Constraints:** Some deleted names (like usernames) remain globally unique and cannot be reused for new entries. Check the "restore" guidance if a validation error occurs on creation.
- **Inventory Control:** `items.qty` represents controlled operational stock. Avoid frequent direct corrections; prefer the standard transaction flow for better audit trails.
- **SPK Posting:** Posting stock from an SPK history entry is a one-way operation. Ensure the SPK calculations are correct before clicking post.
