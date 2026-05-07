# Error Reference — Sistem Informasi Manajemen Gudang dan SPK Instalasi Gizi RSD Balung

## Overview

All `/api/v1` endpoints return a consistent JSON error structure when a request fails. This reference describes the common HTTP status codes, error response shape, and validation patterns.

## 1. Error Response Shape

When an error occurs, the API returns an object with a top-level `message`. For validation failures (`400`), an additional `errors` object provides field-specific details.

```json
{
  "message": "Human-readable error summary",
  "errors": {
    "field_name": "Specific error message for this field."
  }
}
```

In development environments, additional fields like `type`, `file`, `line`, and `trace` may be present.

## 2. Common HTTP Status Codes

| Code | Status | Description |
|---|---|---|
| `400` | Bad Request | The request was malformed or failed validation. |
| `401` | Unauthorized | Missing or invalid Bearer token. |
| `403` | Forbidden | Authenticated user does not have the required role. |
| `404` | Not Found | The requested resource (or parent resource) does not exist. |
| `405` | Method Not Allowed | The HTTP method used is not supported for this endpoint. |
| `422` | Unprocessable Entity | (Internal) Used for complex domain-rule failures. |
| `500` | Internal Error | An unexpected server-side error occurred. |

## 3. Validation Patterns

### 3.1 Field-Specific Errors
Common validation keys used in the `errors` object:
- `page`: Must be a positive integer.
- `perPage`: Must be an integer between 1 and 100.
- `sortBy`: Must be an allowed column name.
- `sortDir`: Must be `ASC` or `DESC`.
- `[field]_from` / `[field]_to`: Must be valid date/datetime strings.
- `query`: Used for "Unsupported query parameter" errors.

### 3.2 Domain-Specific Errors
- **Uniqueness**: `name` or `username` errors when a value already exists.
- **Soft Delete Restore**: If a deleted resource owns a unique name, create returns `400` with `errors.restore_id`.
- **Reference Integrity**: `item_category_id`, `item_unit_base_id` / `item_unit_convert_id` resolution targets (via `unit_base` / `unit_convert`), and other FK-backed inputs must point to active, existing records.
- **Stock Rules**: `OUT` transactions are rejected with a `400` if they result in negative stock.
- **Direct Correction**: Rejected with a `400` if `expected_current_qty` does not match the actual current stock.

## 4. Recovery Actions

- **401 Unauthorized**: Refresh the token or log in again.
- **404 Not Found**: Verify the ID and ensure the resource hasn't been hard-deleted.
- **400 Validation**: Check the `errors` object and adjust the request body or query parameters.
- **500 Internal Error**: Contact the backend team with the error `trace` (in dev) or the timestamp of the request.
