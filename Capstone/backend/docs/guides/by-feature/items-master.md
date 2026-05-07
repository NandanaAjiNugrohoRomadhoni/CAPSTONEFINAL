# Items and Master Data Feature Guide

Manages the catalog of physical items used in the kitchen and inventory.

## Overview

Items are the core resources of the inventory system. Each item belongs to a category and has defined base and conversion units. Stock levels (`qty`) are controlled strictly through operational transactions.

## Item Lifecycle

### 1. Creation (`POST /api/v1/items`)
- **Required Fields**: `name`, `unit_base`, `unit_convert`, `conversion_base`.
- **Category Resolution**: Supports `item_category_id` OR `item_category_name`.
- **Unit Resolution**: `unit_base` and `unit_convert` strings are automatically resolved to active `item_units` records.
- **Uniqueness**: Item names are globally unique across both active and soft-deleted rows.
- **Initial Stock**: New items always start with `qty: 0.00`.
- **De-duplication**: If a name matches a soft-deleted item, the API returns a `400` error with a `restore_id` and guidance to restore the item instead of recreating it.

### 2. Retrieval (`GET /api/v1/items`)
- **Listing**: Paginated by default. Supports filtering by `item_category_id`, `is_active`, and date ranges.
- **Search**: Case-insensitive partial name match using `q` or `search` parameters.
- **Detail**: Returns full item metadata including nested category and unit objects.
- **Soft Delete Filter**: Soft-deleted items are excluded from list and show responses.

### 3. Updates (`PUT /api/v1/items/{id}`)
- **Partial Update**: Only fields present in the request are modified.
- **Forbidden Fields**: `qty`, `id`, `created_at`, `updated_at`, and `deleted_at` cannot be modified directly.
- **Validation**: Name changes must not collide with other active or deleted items. Unit changes must resolve to active `item_units`.

### 4. Soft Delete (`DELETE /api/v1/items/{id}`)
- **Behavior**: Marks the item as deleted by setting `deleted_at`.
- **Effect**: Item becomes invisible to active list/show endpoints and cannot be used in new transactions.
- **Audit**: Historical transactions referencing the item remain intact.

### 5. Restore (`PATCH /api/v1/items/{id}/restore`)
- **Idempotency**: Returns success if the item is already active.
- **Conflict Checks**:
  - Blocks restore if an active item already exists with the same name.
  - Blocks restore if the item's category or units are no longer active (soft-deleted).
- **Outcome**: Clears `deleted_at` and makes the item active again.

## Role Access Matrix

| Operation | Admin | Gudang | Dapur |
|-----------|:-----:|:------:|:-----:|
| List/Show Items | Yes | Yes | No |
| Create Item | Yes | Yes | No |
| Update Item | Yes | Yes | No |
| Soft Delete | Yes | No | No |
| Restore Item | Yes | No | No |
| Create/Update Lookups* | Yes | No | No |

*\*Lookups include `item-categories` and `item-units`.*

## Backend to SDK Mapping

| Backend Action | HTTP Method | SDK Method |
|----------------|:-----------:|------------|
| List Items | `GET /items` | `sdk.items.list(query)` |
| Get Item | `GET /items/{id}` | `sdk.items.get(id)` |
| Create Item | `POST /items` | `sdk.items.create(payload)` |
| Update Item | `PUT /items/{id}` | `sdk.items.update(id, payload)` |
| Delete Item | `DELETE /items/{id}` | `sdk.items.delete(id)` |
| Restore Item | `PATCH /items/{id}/restore` | `sdk.items.restore(id)` |

## Implementation Truths

- **Stock Control**: `items.qty` is never writable via the items API. It only changes through [Stock Transactions](./stock-transactions.md) or [Stock Opname](./stock-opname.md).
- **Normalization**: `items.qty` is always stored and calculated in the `unit_base` (e.g., grams).
- **Unit Logic**: `conversion_base` defines how many base units are in one converted unit (e.g., 1000g in 1kg).
- **Case Sensitivity**: Name lookups and unit resolutions are trimmed and case-insensitive.

## Related Documentation
- [API Contract (Canonical)](../../reference/api-contract.md)
- [Database Schema (Canonical)](../../reference/schema.md)
- [Stock Transactions Guide](./stock-transactions.md)
- [Stock Opname Guide](./stock-opname.md)
