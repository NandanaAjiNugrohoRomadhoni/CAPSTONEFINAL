# API Design — Sistem Informasi Manajemen Gudang dan SPK Instalasi Gizi RSD Balung

## Quick Router

- **Canonical for:** active runtime API contract, implemented-vs-planned endpoint inventory, and request/response behavior.
- **Read this when:** you are implementing or consuming a live backend endpoint or SDK surface.
- **Read next:** `../architecture/runtime-status.md` for the compact module/status index and `schema.md` for schema-backed rules.
- **Legacy context (Deprecated):** See `../governance/changelog.md` and `../governance/migration-map.md` for removed files.
- **Not canonical for:** target architecture decisions for modules that are still planned.

## 1. Overview

Dokumen ini mendefinisikan rancangan API untuk backend **CodeIgniter 4 + MySQL** yang sudah diselaraskan dengan DB diagram terbaru.

Dokumen ini sekarang dipisahkan menjadi dua status yang jelas:

- **Implemented** = endpoint yang benar-benar sudah ada di `app/Config/Routes.php` dan sudah didukung kode backend saat ini.
- **Planned** = endpoint yang masih merupakan target desain dan belum tersedia sebagai route aktif.

Source of truth untuk endpoint yang sudah berjalan adalah:

- `app/Config/Routes.php`
- controller di `app/Controllers/Api/V1/`
- feature tests di `tests/feature/Api/V1/`

Untuk indeks ringkas lintas modul yang merangkum status runtime, surface API, flow utama, ringkasan query/request, dan akses per modul, lihat `../architecture/runtime-status.md` bagian **4.2 Compact Runtime Cross-Reference Matrix**.

## 2. API Principles

- semua endpoint menggunakan prefix `/api/v1`;
- gunakan plural resource names;
- gunakan endpoint workflow untuk proses approval, revision, dan generate SPK;
- response JSON konsisten;
- tabel yang memiliki `deleted_at` diperlakukan sebagai soft-delete resources.

## 3. Standard Response Shape

### 3.1 Success — single resource

```json
{
  "data": {
    "id": 1
  }
}
```

### 3.2 Success — list resource

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 0,
    "totalPages": 0
  },
  "links": {
    "self": "/api/v1/items?page=1&perPage=10",
    "first": "/api/v1/items?page=1&perPage=10",
    "last": "/api/v1/items?page=1&perPage=10",
    "next": null,
    "previous": null
  }
}
```

### 3.3 Validation error

```json
{
  "message": "Validation failed",
  "errors": {
    "field": "The field is invalid."
  }
}
```

## 4. Authentication & Authorization Notes

Authentication runtime contract is documented in the implemented API surface under **5.1 Authentication & Access Endpoints**. Bagian ini hanya menyimpan contoh login dan format auth header yang dipakai lintas endpoint.

### 4.0 Authorization Architecture (Actor Model)

Sistem ini memisahkan otorisasi menjadi dua layer:
1.  **Shield Groups** (`admin`, `developer`, `user`, `beta`): Didefinisikan di `app/Config/AuthGroups.php`. Menangani otorisasi dasar tingkat sistem dan token Shield.
2.  **App Roles** (`admin`, `dapur`, `gudang`): Didefinisikan di `app/Database/Seeds/RoleSeeder.php` dan disimpan di tabel `roles`. Ini adalah peran operasional utama yang menentukan akses fitur di API.

Enforcement role operasional dilakukan oleh `App\Filters\RoleFilter` yang dikonfigurasi pada route group di `app/Config/Routes.php`.

### 4.1 Login Example

#### Request

```json
{
  "username": "example-user",
  "password": "example-password"
}
```

#### Response

```json
{
  "message": "Login successful.",
  "access_token": "<token>",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "role_id": 1,
    "name": "Example User",
    "username": "example-user",
    "is_active": true,
    "role": {
      "id": 1,
      "name": "admin"
    }
  }
}
```

### 4.2 Auth Header

Protected endpoints require:

```http
Authorization: Bearer <access_token>
```

## 5. Implemented API Surface

Bagian ini hanya berisi endpoint yang saat ini benar-benar tersedia sebagai route aktif.

### 5.1 Authentication & Access Endpoints

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/auth/login` | Login user with `username` and `password`, returns Bearer token |
| POST | `/api/v1/auth/logout` | Logout current Bearer token |
| GET | `/api/v1/auth/me` | Get current user profile from Bearer token |
| PATCH | `/api/v1/auth/password` | Self-service password change (requires valid token and current password, revokes all tokens) |
| GET | `/api/v1/roles` | List roles (paginated), restricted to `admin` via role filter |

#### 5.1.1 Self-Service Password Change

Authenticated users can change their own password. This endpoint requires the user's current password for verification and a new password. All access tokens are revoked after a successful password change.

**Access:** Requires valid Bearer token (any authenticated user)

##### Request

```json
{
  "current_password": "example-current-password",
  "password": "example-new-password"
}
```

##### Response (Success)

```json
{
  "message": "Password changed successfully. All access tokens have been revoked."
}
```

##### Response (Wrong Current Password)

```json
{
  "message": "Current password is incorrect."
}
```

##### Response (Validation Failure)

```json
{
  "message": "Validation failed.",
  "errors": {
    "current_password": "The current_password field is required.",
    "password": "The password field must be at least 8 characters in length."
  }
}
```

### 5.2 Inventory Lookup Endpoints

These endpoints provide reference data for creating and filtering inventory operations. All inventory lookup GET endpoints are accessible to users with `admin`, `dapur`, or `gudang` roles. Write operations on `item-units` and `item-categories` remain restricted to `admin` only.

All lookup list endpoints support pagination by default and return the standard `data/meta/links` envelope. Soft-deleted rows are excluded from all list and show responses.

Supported query parameters for all lookup list endpoints:

- `paginate` — optional boolean; default `true`. Use `paginate=false` for dropdown-style reads that should return all matching rows while keeping the same `data/meta/links` envelope.
- `page` — page number (positive integer, default `1`)
- `perPage` — results per page (integer 1–100, default `10`)
- `q` / `search` — partial name match (case-insensitive); `q` takes priority if both are sent
- `sortBy`, `sortDir` — allowlisted sorting fields per resource
- `created_at_from`, `created_at_to` — created-at date/datetime range
- `updated_at_from`, `updated_at_to` — updated-at date/datetime range
- Unknown query parameters return `400` validation errors.
- If `paginate=false`, the endpoint still returns `data/meta/links`; `meta.paginated=false`, `page=1`, `totalPages=1` (or `0` for empty results), and `next/previous=null`.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/item-categories` | List item categories (paginated) |
| GET | `/api/v1/item-categories/{id}` | Get item category detail |
| POST | `/api/v1/item-categories` | Create item category |
| PUT | `/api/v1/item-categories/{id}` | Update item category |
| DELETE | `/api/v1/item-categories/{id}` | Soft delete item category |
| PATCH | `/api/v1/item-categories/{id}/restore` | Restore soft-deleted item category |
| GET | `/api/v1/transaction-types` | List transaction types (paginated) |
| GET | `/api/v1/approval-statuses` | List approval statuses (paginated) |
| GET | `/api/v1/roles` | List roles (paginated) |
| GET | `/api/v1/meal-times` | List meal times (paginated) |
| GET | `/api/v1/item-units` | List item units (paginated) |
| GET | `/api/v1/item-units/{id}` | Get item unit detail |
| POST | `/api/v1/item-units` | Create item unit |
| PUT | `/api/v1/item-units/{id}` | Update item unit |
| DELETE | `/api/v1/item-units/{id}` | Soft delete item unit |
| PATCH | `/api/v1/item-units/{id}/restore` | Restore soft-deleted item unit |

#### 5.2.1 Item Categories

**Access:** GET/list-show for `admin`, `dapur`, `gudang`; write routes are `admin` only.

##### Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "BASAH",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    },
    {
      "id": 2,
      "name": "KERING",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 3,
    "totalPages": 1,
    "paginated": true
  },
  "links": {
    "self": "/api/v1/item-categories?page=1&perPage=10",
    "first": "/api/v1/item-categories?page=1&perPage=10",
    "last": "/api/v1/item-categories?page=1&perPage=10",
    "next": null,
    "previous": null
  }
}
```

##### Response with `paginate=false`

```json
{
  "data": [
    {
      "id": 1,
      "name": "BASAH",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    },
    {
      "id": 2,
      "name": "KERING",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 2,
    "total": 2,
    "totalPages": 1,
    "paginated": false
  },
  "links": {
    "self": "/api/v1/item-categories?paginate=false",
    "first": "/api/v1/item-categories?paginate=false",
    "last": "/api/v1/item-categories?paginate=false",
    "next": null,
    "previous": null
  }
}
```

#### 5.2.2 Transaction Types

**Access:** `admin`, `dapur`, `gudang`

##### Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "IN",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    },
    {
      "id": 2,
      "name": "OUT",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    },
    {
      "id": 3,
      "name": "RETURN_IN",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 3,
    "totalPages": 1,
    "paginated": true
  },
  "links": {
    "self": "/api/v1/transaction-types?page=1&perPage=10",
    "first": "/api/v1/transaction-types?page=1&perPage=10",
    "last": "/api/v1/transaction-types?page=1&perPage=10",
    "next": null,
    "previous": null
  }
}
```

#### 5.2.3 Approval Statuses

**Access:** `admin`, `dapur`, `gudang`

##### Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "APPROVED",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    },
    {
      "id": 2,
      "name": "PENDING",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    },
    {
      "id": 3,
      "name": "REJECTED",
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 3,
    "totalPages": 1
  },
  "links": {
    "self": "/api/v1/approval-statuses?page=1&perPage=10",
    "first": "/api/v1/approval-statuses?page=1&perPage=10",
    "last": "/api/v1/approval-statuses?page=1&perPage=10",
    "next": null,
    "previous": null
  }
}
```

#### 5.2.4 Item Units

**List / Show access:** `admin`, `dapur`, `gudang`
**Create / Update / Delete access:** `admin` only

`item_units` is a soft-deletable lookup table used as FK backing for item units. Soft-deleted item units are excluded from list and show responses, cannot be assigned to items, and delete is blocked while active items still reference the unit.

##### List Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "gram",
      "created_at": "2026-04-10 08:00:00",
      "updated_at": "2026-04-10 08:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 6,
    "totalPages": 1
  },
  "links": {
    "self": "/api/v1/item-units?page=1&perPage=10",
    "first": "/api/v1/item-units?page=1&perPage=10",
    "last": "/api/v1/item-units?page=1&perPage=10",
    "next": null,
    "previous": null
  }
}
```

`paginate=false` is supported here as well for dropdown-style consumers; the response keeps the same envelope and sets `meta.paginated=false`.

##### Show Response

```json
{
  "data": {
    "id": 1,
      "name": "gram",
    "created_at": "2026-04-10 08:00:00",
    "updated_at": "2026-04-10 08:00:00"
  }
}
```

##### Create Request

```json
{
  "name": "gram"
}
```

Name is stored as provided. Case-insensitive duplicate detection applies (e.g. `gram` and `GRAM` are considered the same normalized name).

##### Create Response (`201`)

```json
{
  "message": "Item unit created successfully.",
  "data": {
    "id": 7,
      "name": "gram",
    "created_at": "2026-04-10 08:00:00",
    "updated_at": "2026-04-10 08:00:00"
  }
}
```

##### Update Request

```json
{
  "name": "kilogram"
}
```

##### Update Response

```json
{
  "message": "Item unit updated successfully.",
  "data": {
    "id": 2,
      "name": "kilogram",
    "created_at": "2026-04-10 08:00:00",
    "updated_at": "2026-04-10 09:00:00"
  }
}
```

##### Delete Response

```json
{
  "message": "Item unit deleted successfully."
}
```

Item units are soft-deleted only. The row remains in the database with `deleted_at` set. `item_units.name` is unique only among active rows; if a matching deleted row exists, create returns `400` and the client must call the restore endpoint instead of recreating the name.

### 5.3 User Management Endpoints

All user management endpoints are restricted to users with the `admin` role.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/users` | List all users (excludes soft-deleted) |
| POST | `/api/v1/users` | Create new user |
| GET | `/api/v1/users/{id}` | Get user detail |
| PUT | `/api/v1/users/{id}` | Update user profile and role |
| PATCH | `/api/v1/users/{id}/activate` | Activate user account |
| PATCH | `/api/v1/users/{id}/deactivate` | Deactivate user account (blocks login) |
| PATCH | `/api/v1/users/{id}/password` | Change user password (revokes all tokens) |
| DELETE | `/api/v1/users/{id}` | Soft delete user (revokes all tokens) |
| PATCH | `/api/v1/users/{id}/restore` | Restore soft-deleted user |

#### 5.3.1 List Users

Supported query parameters:

- `page`, `perPage`
- `q` / `search` — partial name, username, or email match
- `sortBy`, `sortDir`
- `role_id` — filter by role ID
- `is_active` — filter by active status (`true` / `false` / `1` / `0`)
- `created_at_from`, `created_at_to`
- `updated_at_from`, `updated_at_to`
- Unknown query parameters return `400` validation errors.

#### Response

```json
{
  "data": [
    {
      "id": 1,
      "role_id": 1,
      "name": "Example User",
      "username": "example-user",
      "email": "example.user@example.test",
      "is_active": true,
      "created_at": "2026-04-02 10:00:00",
      "updated_at": "2026-04-02 10:00:00",
      "role": {
        "id": 1,
        "name": "admin"
      }
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 3,
    "totalPages": 1
  },
  "links": {
    "self": "/api/v1/users?page=1&perPage=10",
    "first": "/api/v1/users?page=1&perPage=10",
    "last": "/api/v1/users?page=1&perPage=10",
    "next": null,
    "previous": null
  }
}
```

#### 5.3.2 Create User

Lookup contract:

- client may send either `role_id` or `role_name`;
- `role_name` matching is trimmed and case-insensitive;
- sending both `role_id` and `role_name` in the same request returns `400` validation errors.

#### Request

```json
{
  "role_id": 2,
  "name": "New User",
  "username": "newuser",
  "email": "newuser@example.com",
  "password": "example-user-password",
  "is_active": true
}
```

#### Response

```json
{
  "message": "User created successfully.",
  "data": {
    "id": 4,
    "role_id": 2,
    "name": "New User",
    "username": "newuser",
    "email": "newuser@example.com",
    "is_active": true,
    "created_at": "2026-04-02 12:00:00",
    "updated_at": "2026-04-02 12:00:00",
    "role": {
      "id": 2,
      "name": "dapur"
    }
  }
}
```

#### 5.3.3 Update User

Lookup contract:

- update supports either `role_id` or `role_name` when changing the assigned role;
- `role_name` matching is trimmed and case-insensitive;
- sending both `role_id` and `role_name` in the same request returns `400` validation errors.

#### Request

```json
{
  "name": "Updated Name",
  "email": "updated@example.com",
  "role_id": 3
}
```

#### Response

```json
{
  "message": "User updated successfully.",
  "data": {
    "id": 4,
    "role_id": 3,
    "name": "Updated Name",
    "username": "newuser",
    "email": "updated@example.com",
    "is_active": true,
    "created_at": "2026-04-02 12:00:00",
    "updated_at": "2026-04-02 12:30:00",
    "role": {
      "id": 3,
      "name": "gudang"
    }
  }
}
```

#### 5.3.4 Deactivate User

Deactivated users cannot log in. Both `is_active` and `active` fields are set to `false`.

Soft-deleted users are treated as not found for all update, activate, deactivate, password-change, and delete operations.

#### Response

```json
{
  "message": "User deactivated successfully.",
  "data": {
    "id": 4,
    "is_active": false,
    ...
  }
}
```

#### 5.3.5 Admin Change Password

This endpoint is for administrators to change another user's password. Changing a user's password revokes all their access tokens. The user must log in again with the new password. Password updates use the Shield user entity flow so credential identity data stays synchronized.

#### Request

```json
{
  "password": "example-reset-password"
}
```

#### Response

```json
{
  "message": "Password changed successfully. All access tokens have been revoked."
}
```

#### 5.3.6 Delete User

Soft deletes the user and revokes all their access tokens. The user cannot log in and will not appear in user lists.

#### Response

```json
{
  "message": "User deleted successfully."
}
```

#### 5.3.7 Restore User

Restores a soft-deleted user. Only `admin` can call this endpoint.

- If the user is already active, returns `200` with current data (idempotent).
- If an active user with the same username already exists, returns `400` with a `username` error.
- If the user's assigned role is no longer active, returns `400` with a `role_id` error.
- If the user does not exist at all, returns `404`.

Creating a new user with the username of a deleted user returns `400` with `errors.restore_id` pointing to the deleted user's ID.

#### Response

```json
{
  "message": "User restored successfully.",
  "data": {
    "id": 4,
    "username": "newuser",
    ...
  }
}
```

### 5.4 Item Endpoints

Phase 1 item management covers item master CRUD only. `qty` is read-only in this module and stock-related behavior stays in inventory transaction flows.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/items` | List items with pagination, filtering, and search |
| POST | `/api/v1/items` | Create item |
| GET | `/api/v1/items/{id}` | Get item detail |
| PUT | `/api/v1/items/{id}` | Partial update item |
| DELETE | `/api/v1/items/{id}` | Soft delete item |
| PATCH | `/api/v1/items/{id}/restore` | Restore soft-deleted item |

#### 5.4.1 Access Rules

- `admin` and `gudang` can list, create, view, and update items.
- `dapur` can list and view items only.
- `admin` only can soft delete or restore items.

#### 5.4.2 List Items

Supported query parameters:

- `page`, `perPage`
- `item_category_id` — filter by category ID
- `is_active` — filter by active status (`true` / `false` / `1` / `0`)
- `q` / `search` — partial name match; `q` takes priority if both sent
- `sortBy` — column to sort by (allowed values: `name`, `created_at`, `updated_at`; default `name`)
- `sortDir` — sort direction: `ASC` or `DESC` (default `ASC`)
- `created_at_from`, `created_at_to` — date range filter on `created_at`
- `updated_at_from`, `updated_at_to` — date range filter on `updated_at`

Rules:

- unknown query parameters return `400` validation errors;
- default order is `name ASC`;
- soft-deleted items are excluded.

#### Response

```json
{
  "data": [
    {
      "id": 1,
      "item_category_id": 2,
      "name": "Beras",
      "unit_base": "gram",
      "unit_convert": "kg",
      "item_unit_base_id": 1,
      "item_unit_convert_id": 2,
      "conversion_base": 1000,
      "qty": "1500.00",
      "min_stock": 100,
      "is_active": true,
      "created_at": "2026-04-03 10:00:00",
      "updated_at": "2026-04-03 10:00:00",
      "category": {
        "id": 2,
        "name": "KERING"
      },
      "item_unit_base": {
        "id": 1,
        "name": "gram"
      },
      "item_unit_convert": {
        "id": 2,
        "name": "kg"
      }
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 1,
    "totalPages": 1
  },
  "links": {
    "self": "/api/v1/items?page=1&perPage=10",
    "first": "/api/v1/items?page=1&perPage=10",
    "last": "/api/v1/items?page=1&perPage=10",
    "next": null,
    "previous": null
  }
}
```

#### 5.4.3 Create Item

Writable fields:

- `name`
- `item_category_id`
- `item_category_name`
- `unit_base`
- `unit_convert`
- `conversion_base`
- `min_stock`
- `is_active`

Lookup contract:

- client may send either `item_category_id` or `item_category_name`;
- `item_category_name` matching is trimmed and case-insensitive;
- sending both `item_category_id` and `item_category_name` in the same request returns `400` validation errors.

Unit resolution contract:

- `unit_base` and `unit_convert` are string values that are resolved to FK-backed `item_units` rows;
- resolution is case-insensitive;
- if the provided unit name cannot be matched to an active (non-deleted) item unit row, the request returns `400` with a `unit_base` or `unit_convert` error;
- the string values are still stored in `items.unit_base` / `items.unit_convert` for backward compatibility alongside the FK columns `item_unit_base_id` / `item_unit_convert_id`.

Forbidden write fields:

- `qty`
- `id`
- `created_at`
- `updated_at`
- `deleted_at`

#### Request

```json
{
  "name": "Minyak",
  "item_category_id": 3,
  "unit_base": "ml",
  "unit_convert": "liter",
  "conversion_base": 1000,
  "min_stock": 100,
  "is_active": true
}
```

#### Response

```json
{
  "message": "Item created successfully.",
  "data": {
    "id": 3,
    "item_category_id": 3,
    "name": "Minyak",
    "unit_base": "ml",
    "unit_convert": "liter",
    "item_unit_base_id": 3,
    "item_unit_convert_id": 4,
    "conversion_base": 1000,
    "qty": "0.00",
    "min_stock": 100,
    "is_active": true,
    "created_at": "2026-04-03 11:00:00",
    "updated_at": "2026-04-03 11:00:00",
    "category": {
      "id": 3,
      "name": "PENGEMAS"
    },
    "item_unit_base": {
      "id": 3,
      "name": "ml"
    },
    "item_unit_convert": {
      "id": 4,
      "name": "liter"
    }
  }
}
```

#### 5.4.4 Get Item Detail

#### Response

```json
{
  "data": {
    "id": 1,
    "item_category_id": 2,
    "name": "Beras",
    "unit_base": "gram",
    "unit_convert": "kg",
    "item_unit_base_id": 1,
    "item_unit_convert_id": 2,
    "conversion_base": 1000,
    "qty": "1500.00",
    "min_stock": 100,
    "is_active": true,
    "created_at": "2026-04-03 10:00:00",
    "updated_at": "2026-04-03 10:00:00",
    "category": {
      "id": 2,
      "name": "KERING"
    },
    "item_unit_base": {
      "id": 1,
      "name": "gram"
    },
    "item_unit_convert": {
      "id": 2,
      "name": "kg"
    }
  }
}
```

#### 5.4.5 Update Item

`PUT /api/v1/items/{id}`` uses partial-update semantics in Phase 1. Only fields present in the request are validated and updated.

Lookup contract:

- update supports either `item_category_id` or `item_category_name` when changing category;
- `item_category_name` matching is trimmed and case-insensitive;
- sending both `item_category_id` and `item_category_name` in the same request returns `400` validation errors.

Unit resolution contract:

- if `unit_base` or `unit_convert` is present in the request, each is resolved to an active item unit row (same rules as create);
- if the unit name cannot be resolved to an active item unit, the request returns `400`.

#### Request

```json
{
  "name": "Beras Premium",
  "min_stock": 50,
  "is_active": false
}
```

#### Response

```json
{
  "message": "Item updated successfully.",
  "data": {
    "id": 1,
    "item_category_id": 2,
    "name": "Beras Premium",
    "unit_base": "gram",
    "unit_convert": "kg",
    "item_unit_base_id": 1,
    "item_unit_convert_id": 2,
    "conversion_base": 1000,
    "qty": "1500.00",
    "min_stock": 50,
    "is_active": false,
    "created_at": "2026-04-03 10:00:00",
    "updated_at": "2026-04-03 11:30:00",
    "category": {
      "id": 2,
      "name": "KERING"
    },
    "item_unit_base": {
      "id": 1,
      "name": "gram"
    },
    "item_unit_convert": {
      "id": 2,
      "name": "kg"
    }
  }
}
```

#### 5.4.6 Delete Item

#### Response

```json
{
  "message": "Item deleted successfully."
}
```

#### 5.4.7 Validation Notes

- `qty` cannot be created or updated directly through the item master endpoints.
- `item_category_id` must reference an existing category.
- `item_category_name` may be used instead of `item_category_id` and resolves to the same lookup table.
- `unit_base` and `unit_convert` must resolve to existing, active (non-deleted) `item_units` rows; soft-deleted item units are rejected with a `400` error.
- `name` must be globally unique across both active and deleted rows; if an active row owns the name, create/update returns `400` with a `name` error.
- if a deleted row already owns the same name, create returns `400` with `errors.restore_id` pointing to the deleted item's ID and a restore-guidance message.
- Missing or soft-deleted items return `404` for all show/update/delete operations.

#### 5.4.8 Restore Item

Restores a soft-deleted item. Only `admin` can call this endpoint.

- If the item is already active, returns `200` with current data (idempotent).
- If an active item with the same name already exists, returns `400` with a `name` error.
- If the referenced category is no longer active, returns `400` with an `item_category_id` error.
- If the referenced base or converted unit is no longer active, returns `400` with a `unit_base` or `unit_convert` error.
- If the item does not exist at all, returns `404`.

Creating a new item with the name of a deleted item returns `400` with `errors.restore_id` pointing to the deleted item's ID.

#### Response (restored)

```json
{
  "message": "Item restored successfully.",
  "data": {
    "id": 3,
    "item_category_id": 3,
    "name": "Minyak",
    "unit_base": "ml",
    "unit_convert": "liter",
    "item_unit_base_id": 3,
    "item_unit_convert_id": 4,
    "conversion_base": 1000,
    "qty": "0.00",
    "min_stock": 100,
    "is_active": true,
    "created_at": "2026-04-03 11:00:00",
    "updated_at": "2026-04-03 12:00:00",
    "category": {
      "id": 3,
      "name": "PENGEMAS"
    },
    "item_unit_base": {
      "id": 3,
      "name": "ml"
    },
    "item_unit_convert": {
      "id": 4,
      "name": "liter"
    }
  }
}
```

#### Response (already active — idempotent 200)

```json
{
  "message": "Item restored successfully.",
  "data": { ... }
}
```

#### Response (active duplicate name — 400)

```json
{
  "message": "Validation failed.",
  "errors": {
    "name": "Cannot restore: an active item with this name already exists."
  }
}
```

#### 5.4.9 Deferred From Item Module

- `GET /api/v1/items/{id}/stock-summary`
- stock usage locking rules
- stock transaction integration

### 5.5 Inventory Transaction Endpoints

#### 5.5.1 Transactions

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/stock-transactions` | List stock transactions with pagination |
| POST | `/api/v1/stock-transactions` | Create stock transaction header + details |
| POST | `/api/v1/stock-transactions/direct-corrections` | Admin-only direct stock correction for a single item |
| GET | `/api/v1/stock-transactions/{id}` | Get stock transaction header only |
| GET | `/api/v1/stock-transactions/{id}/details` | Get stock transaction item lines only |

#### 5.5.2 Access Rules

- `admin` dan `gudang` dapat mengakses endpoint transaksi stok Milestone 1.
- `admin` only dapat mengakses endpoint `/api/v1/stock-transactions/direct-corrections`.
- `dapur` tidak memiliki akses ke endpoint transaksi stok.
- Stock transactions intentionally have **no DELETE route**. Transactions are permanent audit records and cannot be soft-deleted or hard-deleted through the API. Any DELETE request to `/api/v1/stock-transactions/{id}` returns `404`.

#### 5.5.3 Unified Opname Semantics

Stock opname results are now unified into the transaction ledger using the `OPNAME_ADJUSTMENT` transaction type. This provides a single source of truth for all stock movements while preserving the dedicated opname workflow for drafting and approval.

- **Transaction Type:** `OPNAME_ADJUSTMENT`
- **Behavior:** Acts as a targeted adjustment to align system stock with physical count.
- **Posting:** When a dedicated stock opname is posted, it generates one or more `OPNAME_ADJUSTMENT` transactions in the ledger.
- **Historical Linkage:** Backfilled historical opnames are linked via `legacy_source_table`, `legacy_source_id`, and `legacy_source_detail_id`.

#### 5.5.4 List Stock Transactions

Supported query parameters:

- `page`, `perPage`
- `q` / `search` — partial match on `spk_id`; `q` takes priority if both sent
- `sortBy` — column to sort by (allowed: `id`, `transaction_date`, `type_id`, `approval_status_id`, `created_at`, `updated_at`; default `transaction_date`)
- `sortDir` — sort direction: `ASC` or `DESC` (default `DESC`)
- `type_id` — filter by transaction type ID
- `status_id` — filter by approval status ID
- `transaction_date_from`, `transaction_date_to` — date range filter on `transaction_date`
- `created_at_from`, `created_at_to` — date range filter on `created_at`
- `updated_at_from`, `updated_at_to` — date range filter on `updated_at`

Rules:

- unknown query parameters return `400` validation errors;
- default order is `transaction_date DESC`, then transaction `id DESC`;
- soft-deleted transactions are excluded.

#### Response

```json
{
  "data": [
    {
      "id": 10,
      "type_id": 1,
      "transaction_date": "2026-04-18",
      "is_revision": false,
      "parent_transaction_id": null,
      "approval_status_id": 1,
      "approved_by": null,
      "user_id": 2,
      "spk_id": null,
      "reason": null,
      "created_at": "2026-04-18 08:00:00",
      "updated_at": "2026-04-18 08:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 1,
    "totalPages": 1
  },
  "links": {
    "self": "/api/v1/stock-transactions?page=1&perPage=10",
    "first": "/api/v1/stock-transactions?page=1&perPage=10",
    "last": "/api/v1/stock-transactions?page=1&perPage=10",
    "next": null,
    "previous": null
  }
}
```

#### 5.5.4 Create Stock Transaction

Allowed request fields:

- `type_id`
- `type_name`
- `transaction_date`
- `spk_id`
- `details`

Lookup contract:

- client may send either `type_id` or `type_name`;
- `type_name` matching is trimmed and case-insensitive;
- sending both `type_id` and `type_name` in the same request returns `400` validation errors.

Allowed detail fields:

- `item_id`
- `qty`
- `input_unit` _(optional)_

**Unit conversion contract for detail rows:**

- `input_unit` is optional. Omitting it defaults to `"base"`.
- Allowed values: `"base"` or `"convert"`. Any other value returns `400`.
- `input_unit = "base"`: stored `qty` = request `qty` (no conversion).
- `input_unit = "convert"`: stored `qty` = request `qty` × `items.conversion_base`.
- `input_qty` (original request qty before normalization) is always persisted but is **not** a client-writable field.
- `stock_transaction_details.qty` always stores normalized base-unit qty.

Forbidden client-controlled fields:

- `user_id`
- `approved_by`
- `approval_status_id`
- `is_revision`
- `parent_transaction_id`
- `created_at`
- `updated_at`
- `deleted_at`

Rules:

- `user_id` diambil dari authenticated Bearer token context;
- transaksi normal dibuat dengan status approval bernama `APPROVED` dan `is_revision = false`;
- `spk_id` bersifat opsional / nullable;
- item yang sama tidak boleh muncul dua kali dalam satu request `details`;
- transaksi `OUT` ditolak jika akan membuat `items.qty` menjadi negatif;
- perubahan header, detail, qty, dan audit log ditulis dalam satu transaksi database.

#### Request

```json
{
  "type_id": 1,
  "transaction_date": "2026-04-02",
  "details": [
    {
      "item_id": 1,
      "qty": 5000
    }
  ]
}
```

Legacy request (no `input_unit`) — backward-compatible, qty stored as-is:

```json
{
  "type_name": "IN",
  "transaction_date": "2026-04-02",
  "details": [
    { "item_id": 1, "qty": 5000 }
  ]
}
```

Request with unit conversion — `qty` is in convert units (e.g. kg), stored as base units (g):

```json
{
  "type_name": "IN",
  "transaction_date": "2026-04-02",
  "details": [
    { "item_id": 1, "qty": 5, "input_unit": "convert" }
  ]
}
```

> Catatan: `user_id` diambil dari authenticated session/token context, bukan dikirim bebas oleh client.

#### Response

```json
{
  "message": "Stock transaction created successfully.",
  "data": {
    "id": 10,
    "approval_status_id": 1,
    "is_revision": false
  }
}
```

> Catatan: contoh `approval_status_id` di dokumen ini mengikuti seeded development baseline saat ini. Runtime code menetapkan status berdasarkan lookup nama `APPROVED`, bukan literal angka yang di-hardcode.

#### 5.5.5 Get Stock Transaction Header

`GET /api/v1/stock-transactions/{id}` hanya mengembalikan resource header transaksi, bukan item lines.

#### Response

```json
{
  "data": {
    "id": 10,
    "type_id": 1,
    "transaction_date": "2026-04-18",
    "is_revision": false,
    "parent_transaction_id": null,
    "approval_status_id": 1,
    "approved_by": null,
    "user_id": 2,
    "spk_id": null,
    "created_at": "2026-04-18 08:00:00",
    "updated_at": "2026-04-18 08:00:00"
  }
}
```

#### 5.5.6 Get Stock Transaction Details

`GET /api/v1/stock-transactions/{id}/details` hanya mengembalikan item lines transaksi.

#### Response

```json
{
  "data": [
    {
      "id": 1,
      "transaction_id": 10,
      "item_id": 1,
      "item_name": "Beras",
      "item_category_id": 1,
      "item_category_name": "KERING",
      "satuan": "gram",
      "qty": "5000.00",
      "input_qty": "5.00",
      "input_unit": "convert"
    }
  ]
}
```

- `item_name` — item name resolved from `items.name`.
- `item_category_id` — item category ID resolved from `items.item_category_id`.
- `item_category_name` — item category name resolved from `item_categories.name`.
- `satuan` — base unit label for the normalized `qty`, sourced from `items.unit_base`.
- `qty` — normalized base-unit quantity that was stored and used for stock mutation.
- `input_qty` — original quantity as submitted in the request.
- `input_unit` — `"base"` (default) or `"convert"` as submitted/defaulted.

#### 5.5.7 Direct Stock Correction

Admin can directly correct an item's stock level. The system calculates the required mutation (IN or OUT) based on the target quantity and the expected current quantity.

**Access:** `admin` only

##### Request

```json
{
  "transaction_date": "2026-04-13",
  "item_id": 1,
  "expected_current_qty": 5000,
  "target_qty": 4800,
  "reason": "Damaged goods found during physical count"
}
```

- `transaction_date` — date of the correction.
- `item_id` — ID of the item to correct.
- `expected_current_qty` — current quantity expected by the admin (used for optimistic concurrency).
- `target_qty` — the desired final quantity in base units.
- `reason` — explanation for the correction (persisted in `stock_transactions`).

##### Response (`201`)

```json
{
  "message": "Direct stock correction created successfully.",
  "data": {
    "id": 15,
    "approval_status_id": 1,
    "is_revision": false
  }
}
```

Rules:

- endpoint ini hanya untuk `admin`;
- request harus memuat tepat satu `item_id` dengan `expected_current_qty`, `target_qty`, dan `reason`;
- client tidak mengirim `type_id`/`type_name`; server menentukan `IN` atau `OUT` dari selisih `target_qty - expected_current_qty`;
- koreksi langsung disimpan sebagai transaksi final (`is_revision = false`, `parent_transaction_id = null`) dengan status `APPROVED`;
- jika stok aktual tidak lagi sama dengan `expected_current_qty`, request ditolak agar koreksi tidak menimpa perubahan stok yang lebih baru.

#### 5.5.8 Revision Workflow Actions

Workflow revisi transaksi stok berikut sudah diimplementasikan setelah Milestone 1.

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/stock-transactions/{id}/submit-revision` | Submit revision against parent transaction |
| POST | `/api/v1/stock-transactions/{id}/approve` | Approve revision transaction |
| POST | `/api/v1/stock-transactions/{id}/reject` | Reject revision transaction (optional body: `reason`) |
| POST | `/api/v1/stock-opnames` | Create dedicated stock opname draft |
| PUT | `/api/v1/stock-opnames/{id}` | Update stock opname draft or rejected revision |
| GET | `/api/v1/stock-opnames/{id}` | Get stock opname header and details |
| POST | `/api/v1/stock-opnames/{id}/submit` | Submit stock opname draft for approval |
| POST | `/api/v1/stock-opnames/{id}/approve` | Approve submitted stock opname |
| POST | `/api/v1/stock-opnames/{id}/reject` | Reject submitted stock opname |
| POST | `/api/v1/stock-opnames/{id}/post` | Post approved stock opname variances to stock |

#### Revision workflow rules

- submit revision dapat dilakukan oleh `admin` dan `gudang`;
- approve/reject revision hanya dapat dilakukan oleh `admin`;
- submit revision membuat child transaction dengan `is_revision = true` dan `approval_status_id = PENDING`, atau memperbarui child revision `PENDING` yang sudah ada untuk parent yang sama;
- submit revision tidak membuat multiple sibling revision berstatus `PENDING` secara bersamaan; repeated submit sebelum admin action akan mereuse revision `PENDING` yang sama;
- submit revision **tidak** mengubah `items.qty`;
- `items.qty` baru berubah ketika revision di-approve;
- saat approve, sistem **tidak** memperlakukan qty revision sebagai mutasi baru yang ditambahkan di atas parent;
- saat approve, sistem menghitung **selisih bersih (net difference)** antara detail revision pending dan baseline efektif per item;
- baseline efektif saat approve = latest approved sibling dalam lineage yang sama, jika ada; jika tidak ada maka baseline = parent original;
- successive approved sibling revisions diperbolehkan sepanjang alur tetap sequential (maksimal satu pending sibling yang direuse per lineage);
- revision-on-revision tetap ditolak;
- reject revision tidak mengubah `items.qty`;
- reject revision menerima body JSON opsional `{ "reason": "..." }` untuk catatan penolakan admin;
- request field `reason` disimpan ke kolom `stock_transactions.rejection_reason`, bukan ke `stock_transactions.reason`;
- parent transaction tetap dipertahankan sebagai histori asal.

#### 5.5.10 Stock Opname Compatibility Facade

The `/api/v1/stock-opnames/*` routes are preserved as a compatibility facade. While the underlying implementation now unifies with the transaction ledger, the dedicated workflow for opname management remains available.

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/stock-opnames` | Create dedicated stock opname draft |
| PUT | `/api/v1/stock-opnames/{id}` | Update stock opname draft or rejected revision |
| GET | `/api/v1/stock-opnames/{id}` | Get stock opname header and details |
| POST | `/api/v1/stock-opnames/{id}/submit` | Submit stock opname draft for approval |
| POST | `/api/v1/stock-opnames/{id}/approve` | Approve submitted stock opname |
| POST | `/api/v1/stock-opnames/{id}/reject` | Reject submitted stock opname |
| POST | `/api/v1/stock-opnames/{id}/post` | Post approved stock opname variances as `OPNAME_ADJUSTMENT` transactions |

This facade ensures that existing clients can continue to use the structured opname workflow while the system maintains a unified transaction ledger for all stock movements.

### 5.6 Menu & Nutrition Endpoints

These endpoints manage meal planning, including dishes, compositions, and cyclical menu schedules.

#### 5.6.1 Access Rules

- `admin`, `dapur`, and `gudang` can read (GET) all menu-related resources.
- `admin` and `dapur` can write menu-related resources that are mutable at runtime (POST/PUT on dishes, dish compositions, menu slots, and menu schedules).
- Package headers `Paket 1..11` are fixed identities and are not exposed as create/delete endpoints.
- `gudang` has no write access to the menu domain.

#### 5.6.2 Menus & Dishes

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/menus` | List all menus (Packages 1-11) |
| GET | `/api/v1/menu-dishes` | List meal time slots for menus |
| POST | `/api/v1/menu-dishes` | Assign a dish to a menu and meal time slot (rejects occupied slot) |
| PUT | `/api/v1/menu-dishes/{id}` | Update/replace dish or slot metadata |
| DELETE | `/api/v1/menu-dishes/{id}` | Delete a slot assignment |
| GET | `/api/v1/dishes` | List dishes |
| GET | `/api/v1/dishes/{id}` | Get dish detail |

All `dishes` collection endpoints support the standard filtering and pagination parameters. Specifically:
- `is_active` — filter by active status (`true` / `false` / `1` / `0`)

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/dishes` | Create a dish |
| PUT | `/api/v1/dishes/{id}` | Update a dish |
| DELETE | `/api/v1/dishes/{id}` | Delete a dish |
| PATCH | `/api/v1/dishes/{id}/deactivate` | Deactivate a dish (unlinks from menus) |
| PATCH | `/api/v1/dishes/{id}/reactivate` | Reactivate a dish |

#### 5.6.3 Dish Lifecycle Semantics

- **Deactivate**: Sets `is_active = false`. This action preserves the dish row and its compositions but **removes all associated menu slot assignments** (`menu_dishes`).
- **Reactivate**: Sets `is_active = true`. This allows the dish to be assigned to menu slots again. It does **not** restore prior menu slot assignments.
- **Delete**: 
  - Deletion is blocked if the dish is currently `is_active = true` OR still referenced by any menu slots.
  - Only inactive, detached dishes can be deleted.
  - Associated `dish_compositions` are removed by database cascade on final delete.

#### 5.6.4 Dish Compositions

Compositions define the items and quantities required for each dish per patient.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/dish-compositions` | List all dish compositions |
| GET | `/api/v1/dish-compositions/{id}` | Get composition detail |
| POST | `/api/v1/dish-compositions` | Create a composition |
| PUT | `/api/v1/dish-compositions/{id}` | Update a composition |
| DELETE | `/api/v1/dish-compositions/{id}` | Delete a composition |

Access note: semua endpoint `GET` pada domain menu & nutrition (`menus`, `menu-dishes`, `dishes`, `dish-compositions`, `menu-schedules`, `menu-calendar`) dapat diakses oleh `admin`, `dapur`, dan `gudang`.

#### 5.6.4 Menu Schedules & Calendar

Schedules map days of the month (1-31) to specific menus. The Calendar Projection endpoint resolves which menu applies to any given date.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/menu-schedules` | List manual schedule overrides |
| GET | `/api/v1/menu-schedules/{id}` | Get schedule detail |
| POST | `/api/v1/menu-schedules` | Create a schedule override for a day of month |
| PUT | `/api/v1/menu-schedules/{id}` | Update a schedule override |
| GET | `/api/v1/menu-calendar` | Resolve effective menu for date, month, or range |

**Calendar Resolver Modes:**
The `/api/v1/menu-calendar` endpoint requires exactly one of these query parameter sets:
- `date` (YYYY-MM-DD): returns the menu for a single day.
- `month` (YYYY-MM): returns a list of menus for every day in that month.
- `start_date` + `end_date` (YYYY-MM-DD): returns menus for the specified range.

**Resolution Rules:**
1. **Specific Date:** Feb 29th always maps to Package 9.
2. **Day 31:** Any 31st day of a month always maps to Package 11.
3. **Manual Override:** If a `menu_schedules` entry exists for the day of the month, that package is used.
4. **Default Pattern:** If no override exists:
   - Days 1-9 map to Packages 1-9.
   - Day 10 maps to Package 10.
   - Days 11-20 map to Package % 10 (e.g., 14 -> Package 4, 20 -> Package 10).
   - Days 21-30 map to Package % 10 (e.g., 27 -> Package 7, 30 -> Package 10).

#### 5.6.5 Slot Assignment Detail & Examples

**POST /api/v1/menu-dishes**
Assign a dish to a menu slot. Fails with 400 if the slot (`menu_id`, `meal_time_id`) is already occupied. This is not an upsert endpoint.

**PUT /api/v1/menu-dishes/{id}**
Update an existing slot assignment. Use this to replace a dish in a slot or reassign the slot to a different menu/meal time.

**DELETE /api/v1/menu-dishes/{id}**
Remove a slot assignment.

**Example Update Success (200):**
```json
{
  "message": "Menu slot updated successfully.",
  "data": {
    "id": 1,
    "menu_id": 1,
    "meal_time_id": 1,
    "dish_id": 2,
    "created_at": "2026-04-20 10:00:00",
    "updated_at": "2026-04-20 10:05:00",
    "menu": { "id": 1, "name": "Paket 1" },
    "meal_time": { "id": 1, "name": "Pagi" },
    "dish": { "id": 2, "name": "Nasi Tim" }
  }
}
```

**Example Delete Success (200):**
```json
{
  "message": "Menu slot deleted successfully."
}
```

**Example Not Found (404):**
```json
{
  "message": "Menu slot not found."
}
```

**Example Conflict/Validation Failure (400):**
```json
{
  "message": "Validation failed.",
  "errors": {
    "menu_id,meal_time_id": "The menu_id and meal_time_id combination has already been taken."
  }
}
```

### 5.7 Daily Patients & SPK Runtime Contract Freeze

Bagian ini membekukan kontrak route, boundary, dan lifecycle untuk fondasi implementasi SPK basah serta kering/pengemas.

#### 5.7.1 Daily Patients

`daily-patients` adalah input harian pasien untuk tanggal operasional tertentu. Resource ini berdiri sendiri dan tidak ditumpangkan ke `/menu-calendar`.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/daily-patients` | List daily patient rows (standard `data/meta/links`) |
| POST | `/api/v1/daily-patients` | Create daily patient row |
| PUT | `/api/v1/daily-patients/{id}` | Update daily patient row by numeric id |
| GET | `/api/v1/daily-patients/{service_date}` | Get daily patient detail by service date (`Y-m-d`) |

Access note: `GET` daily-patients tersedia untuk `admin`, `dapur`, dan `gudang`; `POST` dan `PUT` daily-patients tersedia untuk `admin` dan `dapur`.

Collection response contract mengikuti envelope standar (`data`, `meta`, `links`).

Create/detail response contract menggunakan `data` object.

Example create response:

```json
{
  "message": "Daily patient created successfully.",
  "data": {
    "id": 1,
    "service_date": "2026-05-01",
    "total_patients": 120,
    "notes": null,
    "created_at": "2026-05-01 06:00:00",
    "updated_at": "2026-05-01 06:00:00"
  }
}
```

Duplicate create requests now return `400` with an actionable error that includes `existing_id` and directs clients to use `PUT /api/v1/daily-patients/{id}`.

#### 5.7.2 SPK Basah Route Family

SPK basah dipisahkan menjadi tiga surface yang berbeda: menu projection, generation/history, dan stock posting.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/spk/basah/menu-calendar` | Projection-only resolver untuk menu calendar context |
| POST | `/api/v1/spk/basah/operational-stock-preview` | Preview sisa stok untuk tanggal yang sama |
| POST | `/api/v1/spk/basah/generate` | Generate SPK basah (membuat versi histori baru; duplicate active scope returns 409 unless `regenerate=true`) |
| GET | `/api/v1/spk/basah/history` | List histori SPK basah |
| GET | `/api/v1/spk/basah/history/{id}` | Detail histori SPK basah (termasuk rekomendasi item) |
| POST | `/api/v1/spk/basah/history/{id}/override` | Override rekomendasi qty per item |
| POST | `/api/v1/spk/basah/history/{id}/post-stock` | Explicit stock posting action (membukukan rekomendasi SPK sebagai transaksi stok `IN`), finalizes SPK (`is_finish=true`) |

#### 5.7.3 SPK Kering/Pengemas Route Family

SPK kering dan pengemas digabung dalam satu family route `spk/kering-pengemas`.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/spk/kering-pengemas/menu-calendar` | Projection-only resolver |
| POST | `/api/v1/spk/kering-pengemas/generate` | Generate SPK kering/pengemas (monthly basis; duplicate active scope returns 409 unless `regenerate=true`) |
| GET | `/api/v1/spk/kering-pengemas/history` | List histori |
| GET | `/api/v1/spk/kering-pengemas/history/{id}` | Detail histori |
| POST | `/api/v1/spk/kering-pengemas/history/{id}/override` | Override rekomendasi qty per item |
| POST | `/api/v1/spk/kering-pengemas/history/{id}/post-stock` | Explicit stock posting action (membukukan rekomendasi SPK sebagai transaksi stok `IN`), finalizes SPK (`is_finish=true`) |

#### 5.7.4 Shared SPK Utility

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/spk/stock-in-prefill/{id}` | Prefill data untuk transaksi IN berdasarkan SPK |

Access note: endpoint read untuk SPK history/calendar (`GET /spk/basah/menu-calendar`, `GET /spk/basah/history*`, `GET /spk/kering-pengemas/menu-calendar`, `GET /spk/kering-pengemas/history*`) tersedia untuk `admin`, `dapur`, dan `gudang`.

Access note: seluruh write/helper flow SPK yang saat ini diimplementasikan juga tersedia untuk `admin`, `dapur`, dan `gudang`, termasuk `POST /spk/basah/operational-stock-preview`, `POST /spk/basah/generate`, `POST /spk/basah/history/{id}/override`, `POST /spk/kering-pengemas/generate`, `POST /spk/kering-pengemas/history/{id}/override`, dan `GET /spk/stock-in-prefill/{id}`. Final stock posting (`POST /spk/*/history/{id}/post-stock`) tersedia untuk `admin` dan `gudang`.

#### 5.7.5 Controller/Service Boundary Freeze

Kontrak boundary backend dibekukan sebagai berikut:

- `MenuSchedules::calendarProjection` menangani **hanya** `/api/v1/menu-calendar` (menu projection umum).
- `SpkBasah::*` dan `SpkKeringPengemas::*` menangani domain SPK masing-masing route family.
- Endpoint `/post-stock` adalah satu-satunya action SPK yang boleh men-trigger pembuatan stock transaction.
- Endpoint `/generate` **tidak** membuat stock transaction otomatis.

#### 5.7.5 Lifecycle & Regeneration/Versioning Freeze

Regeneration SPK dibekukan dengan semantics berikut:

1. Generate ulang untuk kombinasi konteks yang sama (tanggal/category) harus membuat **baris histori baru** (new row / new version).
2. Histori versi sebelumnya tidak boleh di-overwrite.
3. Endpoint history list/detail harus memungkinkan pelacakan versi.
4. Stock posting adalah langkah terpisah setelah versi dipilih, bukan side effect dari generate.
5. Jika masih ada SPK aktif (`is_finish=false`) untuk scope yang sama, generate mengembalikan `409 Conflict` beserta metadata SPK existing. Klien harus mengirim `regenerate=true` untuk membuat versi baru secara eksplisit.

Contoh response envelope generate:

```json
{
  "message": "SPK generated successfully.",
  "data": {
    "id": 31,
    "version": 3,
    "regenerated_from_id": 29,
    "status": "DRAFT",
    "stock_posted": false,
    "created_at": "2026-05-01 08:30:00",
    "updated_at": "2026-05-01 08:30:00"
  }
}
```

### 5.8 Dashboard Aggregate Endpoint (Minimum SRS Scope)

`GET /api/v1/dashboard`

Endpoint ini mengembalikan agregasi dashboard minimum sesuai SRS dengan payload yang dibentuk eksplisit berdasarkan role login (`admin`, `gudang`, `dapur`).

**Access:** `admin`, `dapur`, `gudang`

**Response envelope:**

```json
{
  "data": {
    "role": "admin",
    "generated_at": "2026-04-15 10:30:00",
    "aggregates": {
      "...": "role-specific keys"
    }
  }
}
```

**Role payload keys (minimum contract):**

- `admin`: `stock_summary`, `dry_stock_status`, `spending_trend`, `current_menu_cycle`, `latest_spk_history`, `patient_fluctuation`
- `gudang`: `stock_summary`, `dry_stock_status`, `spending_trend`, `latest_spk_history`, `patient_fluctuation`
- `dapur`: `current_menu_cycle`, `current_menu_composition`, `latest_spk_history`, `stock_summary`, `dry_stock_status`

Unauthorized/forbidden behavior mengikuti filter runtime:

- missing/invalid token → `401` dengan shape `{"message": "..."}`
- inactive account / role tidak memenuhi policy endpoint → `403` dengan shape `{"message": "..."}`

### 5.9 Reporting Endpoints (Export-ready Dataset)

Reporting API menyediakan dataset JSON siap ekspor (tanpa coupling ke engine PDF).

**Access:** `admin`, `dapur`, `gudang`

Semua endpoint report menggunakan query period mandatory:

- `period_start` (required, `Y-m-d`)
- `period_end` (required, `Y-m-d`)

Validation contract:

- unknown query parameters -> `400` + `{"message":"Validation failed.","errors":{...}}`
- malformed date / `period_start > period_end` -> `400` + `errors` per field

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/reports/stocks` | Stock report dataset by period and optional stock filters |
| GET | `/api/v1/reports/transactions` | Stock transaction line dataset by period and optional transaction filters |
| GET | `/api/v1/reports/spk-history` | SPK history dataset by period and optional SPK filters |
| GET | `/api/v1/reports/evaluation` | Plan vs realization evaluation dataset with variance |

#### 5.9.1 Stock Report

Optional filters:

- `category_id`
- `item_id`
- `is_active` (`true`/`false`/`1`/`0`)

Response shape:

```json
{
  "data": {
    "report_type": "stocks",
    "period": { "start": "2026-04-10", "end": "2026-04-20" },
    "filters": {},
    "summary": {
      "total_items": 2,
      "active_items": 2,
      "total_qty": 1500
    },
    "rows": [
      {
        "item_id": 1,
        "item_name": "Beras",
        "category_id": 2,
        "category_name": "KERING",
        "qty": 1000,
        "unit_base": "gram",
        "unit_convert": "kg",
        "is_active": true,
        "updated_at": "2026-04-15 10:00:00"
      }
    ]
  }
}
```

#### 5.9.2 Transaction Report

Optional filters:

- `type_id`
- `status_id`
- `item_id`

Response `rows` berisi flattened transaction + detail lines untuk kebutuhan ekspor tabular.

#### 5.9.3 SPK History Report

Optional filters:

- `spk_type` (`basah` / `kering_pengemas`)
- `category_id`

Response berisi ringkasan per `spk_calculations` row (termasuk agregat rekomendasi qty).

Tambahan kontrak kompatibilitas (non-breaking) untuk rekonsiliasi SRS-vs-runtime:

- `data.compatibility_projection` menyajikan proyeksi SRS-safe dari schema SPK runtime yang lebih kaya.
- Proyeksi ini **tidak** menggantikan field runtime yang sudah ada pada `data.rows`; proyeksi hanya tambahan untuk consumer SRS/report/export.
- Mapping rekomendasi SRS: `spk_recommendations.qty` diproyeksikan dari `spk_recommendations.recommended_qty` (final recommended quantity).
- Kontrak projection `spk_calculations` yang dipertahankan: `id`, `calculation_date`, `target_date_start`, `target_date_end`, `daily_patient_id`, `user_id`, `category_id`, `estimated_patients`, `is_finish`.
- Kontrak projection `spk_recommendations` yang dipertahankan: `id`, `spk_id`, `item_id`, `qty`.

#### 5.9.4 Evaluation Report (Plan vs Realization)

Optional filters:

- `spk_type` (`basah` / `kering_pengemas`)
- `category_id`

Evaluation semantics:

- `planned_qty` = total `spk_recommendations.recommended_qty` per SPK dalam periode
- `realization_qty` = total `stock_transaction_details.qty` untuk transaksi `OUT` + `APPROVED` yang mereferensikan `spk_id` terkait dalam periode
- `variance_qty` = `realization_qty - planned_qty`

Summary menyertakan total planned/realization/variance lintas baris hasil.

### 5.10 Notifications

Endpoint untuk notifikasi sistem.

#### Notification Types & Flow
Sistem secara otomatis membuat dan mendistribusikan notifikasi berdasarkan event operasional berikut:

| Event / Konteks | `type` Value | Penerima Notifikasi | `related_id` Merujuk Pada |
|---|---|---|---|
| **Peringatan Stok Minimum** | `MIN_STOCK` | Role: `admin` | `items.id` |
| **Pengajuan Revisi Transaksi** | `STOCK_REVISION` | Role: `admin` | transaction/revision id |
| **Status Revisi Transaksi** | `STOCK_REVISION` | User submitter | transaction/revision id |
| **Pengajuan Stock Opname** | `STOCK_OPNAME` | Role: `admin` | `stock_opnames.id` |
| **Status Stock Opname** | `STOCK_OPNAME` | User submitter | `stock_opnames.id` |

#### 5.10.1 List Notifications
- **Method:** `GET /api/v1/notifications`
- **Access:** `authenticated` (self-scoped)
- **Query Parameters:** `page`, `perPage`, `paginate`, `is_read`, `type`, `q`, `sortBy`, `sortDir`

#### Response

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "title": "Low Stock Warning",
      "message": "Stock for 'Beras Premium' has reached minimum limit.",
      "type": "MIN_STOCK",
      "related_id": 1,
      "is_read": false,
      "created_at": "2026-05-15 10:00:00",
      "updated_at": "2026-05-15 10:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 1,
    "totalPages": 1
  },
  "links": {
    "self": "...",
    "first": "...",
    "last": "...",
    "next": null,
    "previous": null
  }
}
```

#### 5.10.2 Mark Notification as Read
- **Method:** `POST /api/v1/notifications/{id}/read`
- **Access:** `authenticated` (owner of notification)

#### 5.10.3 Mark All Notifications as Read
- **Method:** `POST /api/v1/notifications/read-all`
- **Access:** `authenticated` (self-scoped)

#### 5.10.4 List Notifications with Filters, Sorting, and Pagination

The notifications list endpoint supports filtering, sorting, and pagination control via query parameters.

**Query Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `page` | number | Page number (default: 1) |
| `perPage` | number | Items per page (default: 10, max: 100) |
| `paginate` | boolean | Set to `false` to return all records without pagination |
| `is_read` | boolean\|number | Filter by read status (`1` or `true` for read, `0` or `false` for unread) |
| `type` | string | Filter by notification type (e.g., `MIN_STOCK`, `STOCK_OPNAME`, `STOCK_REVISION`) |
| `q` | string | Search term to match against title and message |
| `sortBy` | string | Sort key: `id`, `created_at`, `updated_at`, `is_read`, `type` (default: `created_at`) |
| `sortDir` | string | Sort direction: `ASC` or `DESC` (default: `DESC`) |

**Example Query 1: Unread notifications, sorted by newest first**
```
GET /api/v1/notifications?is_read=0&sortBy=created_at&sortDir=DESC
```

**Example Query 2: Search by keyword with pagination**
```
GET /api/v1/notifications?q=stock&is_read=false&page=1&perPage=20
```

**Example Query 3: Filter by notification type with stable sorting**
```
GET /api/v1/notifications?type=STOCK_OPNAME&sortBy=type&sortDir=ASC&page=1&perPage=10
```

**Example Query 4: Get all notifications without pagination**
```
GET /api/v1/notifications?paginate=false
```

**Example Response (Paginated)**

```json
{
  "data": [
    {
      "id": 5,
      "user_id": 2,
      "title": "Opname Required",
      "message": "Stock opname for April 2026 pending approval.",
      "type": "STOCK_OPNAME",
      "related_id": 123,
      "is_read": false,
      "created_at": "2026-05-16 08:30:00",
      "updated_at": "2026-05-16 08:30:00"
    },
    {
      "id": 4,
      "user_id": 2,
      "title": "Low Stock Alert",
      "message": "Beras Premium stock reached minimum level.",
      "type": "MIN_STOCK",
      "related_id": 1,
      "is_read": true,
      "created_at": "2026-05-15 10:00:00",
      "updated_at": "2026-05-15 14:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 10,
    "total": 2,
    "totalPages": 1,
    "paginated": true
  },
  "links": {
    "self": "/api/v1/notifications?page=1&perPage=10&type=STOCK_OPNAME",
    "first": "/api/v1/notifications?page=1&perPage=10&type=STOCK_OPNAME",
    "last": "/api/v1/notifications?page=1&perPage=10&type=STOCK_OPNAME",
    "next": null,
    "previous": null
  }
}
```

**Example Response (All records, paginate=false)**

```json
{
  "data": [
    {
      "id": 5,
      "user_id": 2,
      "title": "Opname Required",
      "message": "Stock opname for April 2026 pending approval.",
      "type": "STOCK_OPNAME",
      "related_id": 123,
      "is_read": false,
      "created_at": "2026-05-16 08:30:00",
      "updated_at": "2026-05-16 08:30:00"
    },
    {
      "id": 4,
      "user_id": 2,
      "title": "Low Stock Alert",
      "message": "Beras Premium stock reached minimum level.",
      "type": "MIN_STOCK",
      "related_id": 1,
      "is_read": true,
      "created_at": "2026-05-15 10:00:00",
      "updated_at": "2026-05-15 14:00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 2,
    "total": 2,
    "totalPages": 1,
    "paginated": false
  },
  "links": {
    "self": "/api/v1/notifications?paginate=false",
    "first": "/api/v1/notifications?paginate=false",
    "last": "/api/v1/notifications?paginate=false",
    "next": null,
    "previous": null
  }
}
```

#### 5.10.5 Delete a Single Notification
- **Method:** `DELETE /api/v1/notifications/{id}`
- **Access:** `authenticated` (owner of notification)
- **Behavior:** Only deletes if the notification belongs to the authenticated user.

**Example Request**
```
DELETE /api/v1/notifications/123
Authorization: Bearer <token>
```

**Example Response (Success)**
```json
{
  "message": "Notification deleted."
}
```

**Example Response (Not Found / Access Denied)**
```json
{
  "message": "Notification not found or access denied.",
  "errors": []
}
```

#### 5.10.6 Delete All Notifications
- **Method:** `DELETE /api/v1/notifications`
- **Access:** `authenticated` (self-scoped)
- **Behavior:** Deletes all notifications for the authenticated user.

**Example Request**
```
DELETE /api/v1/notifications
Authorization: Bearer <token>
```

**Example Response (Success)**
```json
{
  "message": "All notifications deleted."
}
```

**Example Response (No Notifications to Delete)**
```json
{
  "message": "All notifications deleted."
}
```

## 6. Planned API Surface

Bagian ini berisi endpoint yang masih merupakan target desain dan **belum tersedia sebagai route aktif**.

### 6.1 Planned Lookup Endpoints

Endpoint berikut saat ini **belum tersedia** di `app/Config/Routes.php`, walaupun tabel lookup-nya sudah ada di schema:

| Method | Endpoint | Description |
|---|---|---|
| (None) | | |

### 6.2 Monthly Snapshot Endpoints

Endpoint berikut masih planned dan belum tersedia sebagai route aktif.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/monthly-stock-snapshots` | List monthly stock snapshots |
| POST | `/api/v1/monthly-stock-snapshots` | Create monthly stock snapshot |

### 6.3 Deferred From Item Module

- `GET /api/v1/items/{id}/stock-summary`
- stock usage locking rules
- stock transaction integration

### 6.4 Planned Audit & Reporting Endpoints

Endpoint berikut masih planned dan belum tersedia sebagai route aktif.

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/audit-logs` | List audit logs |
| POST | `/api/v1/reports/export-pdf` | Export report as PDF |

## 7. Example Request/Response Contracts

### 7.1 Create Item

Kontrak request/response untuk create item mengikuti bagian **5.4.3 Create Item** di atas.

### 7.2 Create Stock Transaction

Kontrak request/response untuk create stock transaction mengikuti bagian **5.5.4 Create Stock Transaction** di atas.

### 7.3 Submit Revision

Kontrak detail row untuk submit revision sama dengan create stock transaction:

- client tetap mengirim `qty`;
- client boleh menambahkan `input_unit` opsional dengan nilai `base` atau `convert`;
- jika `input_unit` dihilangkan, runtime memperlakukan request sebagai `base`;
- detail yang disimpan tetap menormalisasi `qty` ke satuan dasar dan mencatat `input_qty`/`input_unit` untuk audit.

#### Request

```json
{
  "transaction_date": "2026-04-02",
  "details": [
    {
      "item_id": 1,
      "qty": 4500
    }
  ]
}
```

Request dengan konversi satuan:

```json
{
  "transaction_date": "2026-04-02",
  "details": [
    {
      "item_id": 1,
      "qty": 4,
      "input_unit": "convert"
    }
  ]
}
```

> Catatan: `parent_transaction_id` diambil dari path parameter endpoint `/api/v1/stock-transactions/{id}/submit-revision`, bukan dari body request.

#### Response

```json
{
  "message": "Revision submitted successfully.",
  "data": {
    "id": 11,
    "parent_transaction_id": 10,
    "approval_status_id": 2,
    "is_revision": true
  }
}
```

Submit revision hanya membuat atau memperbarui child revision pending dan tidak langsung mengubah stok. Jika parent yang sama sudah memiliki revision `PENDING`, payload terbaru akan menggantikan header/detail revision pending tersebut tanpa membuat sibling pending baru.

### 7.4 Approve Revision

#### Request

```json
{}
```

#### Response

```json
{
  "message": "Revision approved successfully.",
  "data": {
    "id": 11,
    "approval_status_id": 1,
    "approved_by": 1
  }
}
```

Saat approve berhasil, sistem menerapkan **selisih bersih** antara detail parent dan detail revision ke `items.qty` secara atomik. Dengan kata lain, revision berfungsi sebagai koreksi terhadap efek parent, bukan sebagai mutasi stok kedua yang berdiri sendiri.

### 7.5 Reject Revision

#### Request

```json
{}
```

#### Response

```json
{
  "message": "Revision rejected successfully.",
  "data": {
    "id": 11,
    "approval_status_id": 3,
    "approved_by": 1
  }
}
```

Reject revision hanya mengubah status approval dan tidak mengubah stok.

### 7.6 Generate SPK

#### Request

```json
{
  "target_date": "2026-05-01",
  "daily_patient_id": 1,
  "category_id": 1
}
```

#### Response

```json
{
  "message": "SPK generated successfully.",
  "data": {
    "id": 31,
    "version": 3,
    "status": "DRAFT",
    "stock_posted": false
  }
}
```

## 8. CodeIgniter 4 Notes

- gunakan plural resources dan route grouping di `/api/v1`;
- tabel dengan `deleted_at` cocok dengan soft delete convention CI4;
- approval/revision lebih tepat dipresentasikan sebagai command endpoint daripada CRUD murni;
- audit log dapat dicatat melalui callback model atau service khusus.
