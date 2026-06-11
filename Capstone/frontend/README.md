# Frontend TypeScript SDK

This folder contains the TypeScript SDK for the currently implemented Capstone backend API.

It is a typed wrapper over the CodeIgniter 4 backend under `/api/v1`, with resource modules, request/response types, typed API errors, and a small shared HTTP client.

## Canonical Backend References

- **Use this file for:** SDK usage, SDK resource surface, SDK request/response typing, and frontend-facing examples.
- **Do not use this file as the canonical source for:** backend implementation status, backend schema rules, or route discovery workflow.
- **Read next before changing SDK contracts:** `../backend/AGENTS.md`, `../backend/docs/README.md`, `../backend/docs/architecture/runtime-status.md`, `../backend/docs/reference/api-contract.md`.

## Scope

The SDK only covers the backend routes that are implemented and verified now. It does not expose planned backend modules that do not yet exist as active API routes.

If you need a compact backend-side index of implemented vs planned modules, route groups, key flow rules, and permission notes before wiring new SDK surfaces, see `../backend/docs/architecture/runtime-status.md` (Canonical).

Implemented SDK resources:

- `auth`
- `roles`
- `users`
- `items`
- `stockTransactions`
- `itemCategories`
- `itemUnits`
- `mealTimes`
- `transactionTypes`
- `approvalStatuses`
- `dailyPatients`
- `spk`
- `menus`
- `dishes`
- `dishCompositions`
- `menuSchedules`
- `notifications`
- `dashboard`
- `reports`
- `stockOpnames`

## Folder structure

- `src/sdk/client.ts` — shared HTTP client, URL building, bearer token injection, JSON parsing
- `src/sdk/errors.ts` — typed API error classes and status-code mapping
- `src/sdk/resources/` — resource-specific API modules
- `src/sdk/types/` — request and response types
- `src/sdk/tests/` — SDK unit tests
- `src/index.ts` — top-level export entry
- `dist/` — generated build output

## Available scripts

- `npm test` — run SDK tests with Vitest
- `npm run typecheck` — run TypeScript checking without emitting files
- `npm run build` — regenerate `dist/`

## Quick start

```ts
import { createCapstoneSdk } from "./src";

const sdk = createCapstoneSdk({
  baseUrl: "http://127.0.0.1:8080"
});
```

By default, the client prefixes all requests with `/api/v1`, so the example above calls `http://127.0.0.1:8080/api/v1/...`.

## Client configuration

`createCapstoneSdk()` and `new CapstoneSdk()` both accept the same `ApiClientOptions`.

### Supported options

- `baseUrl` — backend origin; default: `http://127.0.0.1:8080`
- `apiBasePath` — API base path; default: `/api/v1`
- `accessToken` — initial bearer token stored in memory
- `getAccessToken` — sync or async token resolver called before each request
- `defaultHeaders` — shared request headers
- `fetchImplementation` — custom `fetch`, useful for SSR, tests, or non-browser environments

### Example: dynamic token lookup

```ts
const sdk = createCapstoneSdk({
  baseUrl: "http://127.0.0.1:8080",
  getAccessToken: () => localStorage.getItem("access_token")
});
```

### Example: in-memory token management

```ts
const login = await sdk.auth.login({
  username: "admin",
  password: "password123"
});

sdk.setAccessToken(login.access_token);

const me = await sdk.auth.me();

sdk.clearAccessToken();
```

## Authentication model

The SDK does not manage refresh tokens or persistent storage for you. It only injects a bearer token if one is available from:

1. `getAccessToken()`, if provided
2. the in-memory token set by `accessToken` or `setAccessToken()`

Protected endpoints send:

```http
Authorization: Bearer <token>
```

## Response shapes

The SDK preserves backend response envelopes instead of flattening them.

### Single resource

```ts
type ApiDataResponse<T> = {
  data: T;
};
```

### List resource

```ts
type ApiListResponse<T> = {
  data: T[];
  meta: {
    page: number;
    perPage: number;
    total: number;
    totalPages: number;
    paginated?: boolean;
  };
  links: {
    self: string;
    first: string;
    last: string;
    next: string | null;
    previous: string | null;
  };
};
```

### Message response

```ts
type ApiMessageResponse = {
  message: string;
};

type ApiMessageDataResponse<T> = {
  message: string;
  data: T;
};
```

## Error handling

Failed requests throw typed errors from `src/sdk/errors.ts`.

### Available error classes

- `ApiError`
- `ValidationApiError`
- `AuthenticationApiError`
- `AuthorizationApiError`
- `NotFoundApiError`

### Status mapping

- `400` with validation body → `ValidationApiError`
- `401` → `AuthenticationApiError`
- `403` → `AuthorizationApiError`
- `404` → `NotFoundApiError`
- anything else → generic `ApiError`

### Example

```ts
import {
  NotFoundApiError,
  ValidationApiError,
  createCapstoneSdk
} from "./src";

const sdk = createCapstoneSdk({ baseUrl: "http://127.0.0.1:8080" });

try {
  await sdk.itemUnits.create({ name: "gram" });
} catch (error) {
  if (error instanceof ValidationApiError) {
    console.log(error.message);
    console.log(error.errors);
  }

  if (error instanceof NotFoundApiError) {
    console.log(error.status);
  }
}
```

## Request typing rules

Several SDK request shapes intentionally mirror backend validation rules.

### Mutually exclusive lookup identifiers

Some create/update requests support either an ID field or a name field, but not both. The SDK models this with XOR-style types.

Examples:

- users: `role_id` **or** `role_name`
- items: `item_category_id` **or** `item_category_name`
- stock transactions: `type_id` **or** `type_name`

That means this is valid:

```ts
await sdk.users.create({
  name: "Gudang User",
  username: "gudang1",
  password: "password123",
  role_name: "gudang"
});
```

And this is intentionally invalid at the type level:

```ts
await sdk.users.create({
  name: "Broken",
  username: "broken",
  password: "password123",
  role_id: 3,
  role_name: "gudang"
});
```

## Resource reference

### `auth`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.auth.login(payload)` | `POST /api/v1/auth/login` | public |
| `sdk.auth.me()` | `GET /api/v1/auth/me` | authenticated |
| `sdk.auth.logout()` | `POST /api/v1/auth/logout` | authenticated |
| `sdk.auth.changePassword(payload)` | `PATCH /api/v1/auth/password` | authenticated |

#### Login request

```ts
await sdk.auth.login({
  username: "admin",
  password: "password123"
});
```

#### Login response shape

```ts
{
  message: string;
  access_token: string;
  token_type: "Bearer";
  user: User;
}
```

### `roles`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.roles.list(query?)` | `GET /api/v1/roles` | `admin` only |

`roles` is currently a read-only lookup resource in the SDK.

### `itemCategories`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.itemCategories.list(query?)` | `GET /api/v1/item-categories` | `admin`, `dapur`, `gudang` |
| `sdk.itemCategories.get(id)` | `GET /api/v1/item-categories/{id}` | `admin`, `dapur`, `gudang` |
| `sdk.itemCategories.create(payload)` | `POST /api/v1/item-categories` | `admin` only |
| `sdk.itemCategories.update(id, payload)` | `PUT /api/v1/item-categories/{id}` | `admin` only |
| `sdk.itemCategories.delete(id)` | `DELETE /api/v1/item-categories/{id}` | `admin` only |
| `sdk.itemCategories.restore(id)` | `PATCH /api/v1/item-categories/{id}/restore` | `admin` only |

#### Soft delete and restore behavior

- names are unique only among active rows
- create/update still reject active duplicates
- if a deleted row already owns the same normalized name, create returns a validation error with restore guidance and `restore_id`
- the client should call `restore()` explicitly instead of retrying create

### `itemUnits`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.itemUnits.list(query?)` | `GET /api/v1/item-units` | `admin`, `dapur`, `gudang` |
| `sdk.itemUnits.get(id)` | `GET /api/v1/item-units/{id}` | `admin`, `dapur`, `gudang` |
| `sdk.itemUnits.create(payload)` | `POST /api/v1/item-units` | `admin` only |
| `sdk.itemUnits.update(id, payload)` | `PUT /api/v1/item-units/{id}` | `admin` only |
| `sdk.itemUnits.delete(id)` | `DELETE /api/v1/item-units/{id}` | `admin` only |
| `sdk.itemUnits.restore(id)` | `PATCH /api/v1/item-units/{id}/restore` | `admin` only |

#### Soft delete and restore behavior

- names are unique only among active rows
- delete is blocked while active items still reference the unit
- if create hits a deleted-name collision, the API responds with `400`, `errors.name`, and `errors.restore_id`
- restore is explicit; the SDK now exposes it directly

### `transactionTypes`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.transactionTypes.list(query?)` | `GET /api/v1/transaction-types` | `admin`, `dapur`, `gudang` |

### `approvalStatuses`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.approvalStatuses.list(query?)` | `GET /api/v1/approval-statuses` | `admin`, `dapur`, `gudang` |

### `mealTimes`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.mealTimes.list(query?)` | `GET /api/v1/meal-times` | `admin`, `dapur`, `gudang` |

### `items`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.items.list(query?)` | `GET /api/v1/items` | `admin`, `dapur`, `gudang` |
| `sdk.items.get(id)` | `GET /api/v1/items/{id}` | `admin`, `dapur`, `gudang` |
| `sdk.items.create(payload)` | `POST /api/v1/items` | `admin`, `gudang` |
| `sdk.items.update(id, payload)` | `PUT /api/v1/items/{id}` | `admin`, `gudang` |
| `sdk.items.delete(id)` | `DELETE /api/v1/items/{id}` | `admin` only |
| `sdk.items.restore(id)` | `PATCH /api/v1/items/{id}/restore` | `admin` only |

#### Important item behavior

- `qty` is backend-controlled and is **not** a writable request field
- `unit_base` and `unit_convert` are still sent as strings on write
- item responses also include `item_unit_base_id`, `item_unit_convert_id`, and nested `item_unit_base` / `item_unit_convert`
- item names remain globally unique even after soft delete
- creating an item with the name of a deleted item returns `400` with `errors.restore_id`
- restore is explicit through `sdk.items.restore(id)` and is idempotent when the item is already active
- restore also returns `400` if the item's category or units are no longer active

#### Example item response shape

```ts
{
  id: 1,
  item_category_id: 2,
  name: "Beras",
  unit_base: "gram",
  unit_convert: "kg",
  item_unit_base_id: 1,
  item_unit_convert_id: 2,
  conversion_base: 1000,
  qty: "1500.00",
  is_active: true,
  created_at: "2026-04-01 10:00:00",
  updated_at: "2026-04-01 10:00:00",
  category: {
    id: 2,
    name: "KERING"
  },
  item_unit_base: {
    id: 1,
    name: "gram"
  },
  item_unit_convert: {
    id: 2,
    name: "kg"
  }
}
```

### `stockTransactions`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.stockTransactions.list(query?)` | `GET /api/v1/stock-transactions` | `admin`, `gudang` |
| `sdk.stockTransactions.get(id)` | `GET /api/v1/stock-transactions/{id}` | `admin`, `gudang` |
| `sdk.stockTransactions.details(id)` | `GET /api/v1/stock-transactions/{id}/details` | `admin`, `gudang` |
| `sdk.stockTransactions.create(payload)` | `POST /api/v1/stock-transactions` | `admin`, `gudang` |
| `sdk.stockTransactions.directCorrection(payload)` | `POST /api/v1/stock-transactions/direct-corrections` | `admin` only |
| `sdk.stockTransactions.submitRevision(id, payload)` | `POST /api/v1/stock-transactions/{id}/submit-revision` | `admin`, `gudang` |
| `sdk.stockTransactions.approve(id)` | `POST /api/v1/stock-transactions/{id}/approve` | `admin` only |
| `sdk.stockTransactions.reject(id)` | `POST /api/v1/stock-transactions/{id}/reject` | `admin` only |

#### Important stock transaction behavior

- list search uses `q` / `search` against `spk_id`
- list filters support `type_id`, `status_id`, `transaction_date_from/to`, `created_at_from/to`, `updated_at_from/to`
- create supports `type_id` or `type_name`
- direct correction is admin-only and requires `item_id`, `expected_current_qty`, `target_qty`, and `reason`
- direct correction is stored as a normal stock transaction (not a revision), with the server deriving whether the adjustment is `IN` or `OUT`
- submit revision creates a pending child revision on first submit, then reuses and replaces that same pending child revision if the same parent is resubmitted before admin review; it does not change stock immediately
- only one pending revision is allowed at a time for the same original transaction lineage; repeated submits update that same pending sibling, and after a revision is approved or rejected, a new sibling revision can be submitted against the same original transaction
- approve revision applies the revision as a **net correction** against the latest approved revision in the lineage (or the original parent when no approved sibling exists), not as a second additive stock movement
- `sdk.stockTransactions.details(id)` returns normalized detail rows with item metadata, including `satuan` as the base-unit label for `qty`; use `input_qty` + `input_unit` for the original entered quantity mode
- detail rows still use `item_id`; there is no item-name write shortcut in transaction details
- there is intentionally no `sdk.stockTransactions.delete()` method because the backend exposes no delete route for stock transactions

### `users`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.users.list(query?)` | `GET /api/v1/users` | `admin` only |
| `sdk.users.get(id)` | `GET /api/v1/users/{id}` | `admin` only |
| `sdk.users.create(payload)` | `POST /api/v1/users` | `admin` only |
| `sdk.users.update(id, payload)` | `PUT /api/v1/users/{id}` | `admin` only |
| `sdk.users.activate(id)` | `PATCH /api/v1/users/{id}/activate` | `admin` only |
| `sdk.users.deactivate(id)` | `PATCH /api/v1/users/{id}/deactivate` | `admin` only |
| `sdk.users.changePassword(id, payload)` | `PATCH /api/v1/users/{id}/password` | `admin` only |
| `sdk.users.delete(id)` | `DELETE /api/v1/users/{id}` | `admin` only |
| `sdk.users.restore(id)` | `PATCH /api/v1/users/{id}/restore` | `admin` only |

#### Important user behavior

- usernames remain globally unique even after soft delete
- `is_active` controls application-level activation status
- soft delete revokes access tokens and makes the user effectively absent from active reads/mutations
- create/update accept `role_id` or `role_name`
- creating a user with the username of a deleted user returns `400` with `errors.restore_id`
- restore is explicit through `sdk.users.restore(id)` and is idempotent when the user is already active
- restore also returns `400` if the user's assigned role is no longer active

### `dailyPatients`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.dailyPatients.list()` | `GET /api/v1/daily-patients` | `admin`, `dapur`, `gudang` |
| `sdk.dailyPatients.get(serviceDate)` | `GET /api/v1/daily-patients/{service_date}` | `admin`, `dapur`, `gudang` |
| `sdk.dailyPatients.create(payload)` | `POST /api/v1/daily-patients` | `admin`, `gudang` |
| `sdk.dailyPatients.update(id, payload)` | `PUT /api/v1/daily-patients/{id}` | `admin`, `gudang` |

### `spk`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.spk.basahMenuCalendar(query?)` | `GET /api/v1/spk/basah/menu-calendar` | `admin`, `dapur`, `gudang` |
| `sdk.spk.operationalStockPreview(payload)` | `POST /api/v1/spk/basah/operational-stock-preview` | `admin`, `dapur`, `gudang` |
| `sdk.spk.generateBasah(payload)` | `POST /api/v1/spk/basah/generate` | `admin`, `dapur`, `gudang` |
| `sdk.spk.listBasah()` | `GET /api/v1/spk/basah/history` | `admin`, `dapur`, `gudang` |
| `sdk.spk.getBasah(id)` | `GET /api/v1/spk/basah/history/{id}` | `admin`, `dapur`, `gudang` |
| `sdk.spk.overrideBasah(id, payload)` | `POST /api/v1/spk/basah/history/{id}/override` | `admin`, `dapur`, `gudang` |
| `sdk.spk.postBasahStock(id)` | `POST /api/v1/spk/basah/history/{id}/post-stock` | `admin`, `gudang` |
| `sdk.spk.keringPengemasMenuCalendar(query?)` | `GET /api/v1/spk/kering-pengemas/menu-calendar` | `admin`, `dapur`, `gudang` |
| `sdk.spk.generateKeringPengemas(payload)` | `POST /api/v1/spk/kering-pengemas/generate` | `admin`, `dapur`, `gudang` |
| `sdk.spk.listKeringPengemas()` | `GET /api/v1/spk/kering-pengemas/history` | `admin`, `dapur`, `gudang` |
| `sdk.spk.getKeringPengemas(id)` | `GET /api/v1/spk/kering-pengemas/history/{id}` | `admin`, `dapur`, `gudang` |
| `sdk.spk.overrideKeringPengemas(id, payload)` | `POST /api/v1/spk/kering-pengemas/history/{id}/override` | `admin`, `dapur`, `gudang` |
| `sdk.spk.postKeringPengemasStock(id)` | `POST /api/v1/spk/kering-pengemas/history/{id}/post-stock` | `admin`, `gudang` |
| `sdk.spk.stockInPrefill(id)` | `GET /api/v1/spk/stock-in-prefill/{id}` | `admin`, `dapur`, `gudang` |

#### SPK Recommendation logic

- **Basah:** `((daily_patients × 1.05) × composition_qty) - current_stock`, clamped to 0.
- **Kering/Pengemas:** `(prev_month_actual_usage × 1.10) - current_stock`, clamped to 0.
- Each `generate*()` call creates a new history row/version; earlier versions are preserved.
- `generate*()` and `operationalStockPreview()` are calculation helpers only; they do **not** create stock transactions and do **not** mutate stock.
- `postBasahStock()` and `postKeringPengemasStock()` create the stock transaction and finalize the SPK (`is_finish=true`).

### `menus` / `dishes` / `dishCompositions` / `menuSchedules`

These resources provide management for nutrition standards and calendar scheduling.

| Resource | Methods | Access (Write) | Access (Read) |
|---|---|---|---|
| `menus` | `list` | None (Fixed) | `admin`, `dapur`, `gudang` |
| `menus` (slots) | `slots`, `assignSlot`, `updateSlot`, `deleteSlot` | `admin`, `dapur` | `admin`, `dapur`, `gudang` |
| `dishes` | `list`, `get`, `create`, `update`, `deactivate`, `reactivate`, `delete` | `admin`, `dapur` | `admin`, `dapur`, `gudang` |
| `dishCompositions` | `list`, `get`, `create`, `update`, `delete` | `admin`, `dapur` | `admin`, `dapur`, `gudang` |
| `menuSchedules` | `list`, `get`, `create`, `update`, `calendarProjection` | `admin`, `dapur` | `admin`, `dapur`, `gudang` |

#### Important dish lifecycle behavior

- `Dish` responses now include `is_active`
- `sdk.dishes.list({ paginate, is_active })` forwards the supported list controls to `GET /api/v1/dishes`; `paginate=false` keeps the same `data/meta/links` envelope and sets `meta.paginated=false`
- `sdk.dishes.deactivate(id)` calls `PATCH /api/v1/dishes/{id}/deactivate`, keeps the dish row and compositions, and removes linked menu slot assignments
- `sdk.dishes.reactivate(id)` calls `PATCH /api/v1/dishes/{id}/reactivate` and only restores availability for future slot writes
- `sdk.dishes.delete(id)` is only valid after the dish is inactive and detached from menu slots; final delete removes compositions by DB cascade

#### Important dish composition list behavior

- `sdk.dishCompositions.list({ paginate, dish_id, item_id })` forwards the supported list controls to `GET /api/v1/dish-compositions`; `paginate=false` keeps the same `data/meta/links` envelope and sets `meta.paginated=false`

#### Important menu slot write behavior

- `sdk.menus.assignSlot()` and `sdk.menus.updateSlot()` reject inactive dishes with the validation message `The selected dish is inactive.`

#### Example dish lifecycle flow

```ts
const dishes = await sdk.dishes.list({ paginate: false, is_active: false, sortBy: "updated_at", sortDir: "DESC" });

await sdk.dishes.deactivate(9);
await sdk.dishes.reactivate(9);
```

### `notifications`

| SDK method | HTTP endpoint | Access |
|---|---|---|
| `sdk.notifications.list(query?)` | `GET /api/v1/notifications` | authenticated (self-scoped) |
| `sdk.notifications.markAsRead(id)` | `POST /api/v1/notifications/{id}/read` | authenticated (owner only) |
| `sdk.notifications.markAllAsRead()` | `POST /api/v1/notifications/read-all` | authenticated (self-scoped) |
| `sdk.notifications.delete(id)` | `DELETE /api/v1/notifications/{id}` | authenticated (owner only) |
| `sdk.notifications.deleteAll()` | `DELETE /api/v1/notifications` | authenticated (self-scoped) |

Query parameters and filters supported by `GET /api/v1/notifications`:
- `page` (number) — page index (default: 1)
- `perPage` (number) — items per page (default: 10, max: 100)
- `paginate` (boolean|0|1) — if false/0 the endpoint returns all matched records (no paging). Default: true.
- `is_read` (boolean|0|1) — filter by read status
- `type` (string) — filter by notification type/category (e.g. `MIN_STOCK`, `STOCK_OPNAME`)
- `q` (string) — full-text-ish search over `title` and `message`
- `sortBy` (string) — one of `id`, `created_at`, `updated_at`, `is_read`, `type` (default: `created_at`)
- `sortDir` (string) — `ASC` or `DESC` (default: `DESC`)

Notes:
- Example query: `GET /api/v1/notifications?page=2&perPage=20&is_read=0&type=MIN_STOCK&sortBy=created_at&sortDir=DESC`
- When `paginate=false` the response includes the full `data` array and `meta` will indicate `paginated: false` and `perPage` will reflect the returned count.
- All collection responses follow the standard paginated envelope: `{ data: [...], meta: { page, perPage, total, totalPages, paginated }, links: { self, first, last, next, previous } }`.
- `related_id` in each notification points to the relevant resource: `MIN_STOCK` -> `items.id`, `STOCK_REVISION` -> revision/transaction id, `STOCK_OPNAME` -> `stock_opnames.id`.
- Frontend should use `type` + `related_id` to route the user to the appropriate page.

### `dashboard` / `reports` / `stockOpnames`

These resources provide analytical views and auditing tools.

| Resource | Methods | Access |
|---|---|---|
| `dashboard` | `getAggregate` | `admin`, `dapur`, `gudang` |
| `reports` | `getStocks`, `getTransactions`, `getSpkHistory`, `getEvaluation`, `getMonthlyStockExport` | `admin`, `dapur`, `gudang` |
| `stockOpnames` | `create`, `get`, `submit` | `admin`, `gudang` |
| `stockOpnames` | `approve`, `reject`, `post` | `admin` |

## List query reference

Most collection endpoints return paginated envelopes and accept resource-specific filters.

### Shared paginated lookup query

Used by:

- `roles.list()`
- `itemCategories.list()`
- `itemUnits.list()`
- `transactionTypes.list()`
- `approvalStatuses.list()`

Supported fields:

- `paginate` — optional boolean; use `false` for dropdown-style lookup reads
- `page`
- `perPage`
- `q`
- `search`
- `sortBy`
- `sortDir`
- `created_at_from`
- `created_at_to`
- `updated_at_from`
- `updated_at_to`

Rules:

- unknown lookup query parameters return `400` validation errors
- if both `q` and `search` are sent, backend behavior gives precedence to `q`

### `items.list(query)`

Supported fields:

- `page`
- `perPage`
- `item_category_id`
- `is_active`
- `q`
- `search`
- `sortBy`
- `sortDir`
- `created_at_from`
- `created_at_to`
- `updated_at_from`
- `updated_at_to`

### `users.list(query)`

Supported fields:

- `page`
- `perPage`
- `q`
- `search`
- `sortBy`
- `sortDir`
- `role_id`
- `is_active`
- `created_at_from`
- `created_at_to`
- `updated_at_from`
- `updated_at_to`

### `stockTransactions.list(query)`

Supported fields:

- `page`
- `perPage`
- `q`
- `search`
- `sortBy`
- `sortDir`
- `type_id`
- `status_id`
- `transaction_date_from`
- `transaction_date_to`
- `created_at_from`
- `created_at_to`
- `updated_at_from`
- `updated_at_to`

## Practical examples

### Full auth flow

```ts
import { createCapstoneSdk } from "./src";

const sdk = createCapstoneSdk({
  baseUrl: "http://127.0.0.1:8080"
});

const login = await sdk.auth.login({
  username: "admin",
  password: "password123"
});

sdk.setAccessToken(login.access_token);

const currentUser = await sdk.auth.me();
await sdk.auth.logout();
sdk.clearAccessToken();
```

### List items with filters

```ts
const items = await sdk.items.list({
  page: 1,
  perPage: 20,
  q: "beras",
  item_category_id: 2,
  is_active: true,
  sortBy: "updated_at",
  sortDir: "DESC",
  created_at_from: "2026-04-01",
  updated_at_to: "2026-04-30"
});
```

### Create an item using category-name lookup

```ts
const createdItem = await sdk.items.create({
  name: "Minyak",
  item_category_name: "PENGEMAS",
  unit_base: "ml",
  unit_convert: "liter",
  conversion_base: 1000,
  is_active: true
});
```

### Restore flow for a deleted lookup

```ts
import { ValidationApiError } from "./src";

try {
  await sdk.itemUnits.create({ name: "pack" });
} catch (error) {
  if (error instanceof ValidationApiError) {
    const restoreId = error.errors.restore_id;

    if (restoreId) {
      await sdk.itemUnits.restore(Number(restoreId));
    }
  }
}
```

### Dropdown lookup flow with `paginate=false`

```ts
const lookup = await sdk.itemUnits.list({
  paginate: false,
  sortBy: "name",
  sortDir: "ASC"
});

const options = lookup.data.map((unit) => ({
  value: unit.id,
  label: unit.name
}));

console.log(lookup.meta.paginated); // false
```

Even with `paginate=false`, lookup endpoints still return the same `data/meta/links` envelope.

### List users with admin-only filters

```ts
const users = await sdk.users.list({
  q: "gudang",
  role_id: 3,
  is_active: true,
  sortBy: "email",
  sortDir: "ASC"
});
```

### Stock transaction workflow

```ts
const created = await sdk.stockTransactions.create({
  type_name: "IN",
  transaction_date: "2026-04-18",
  spk_id: 12345,
  details: [
    {
      item_id: 1,
      qty: 5000,
      input_unit: "base"
    }
  ]
});

// Admin direct stock correction example
await sdk.stockTransactions.directCorrection({
  transaction_date: "2026-04-20",
  item_id: 1,
  expected_current_qty: 5000,
  target_qty: 4800,
  reason: "Found 200g damaged during audit"
});

const revision = await sdk.stockTransactions.submitRevision(created.data.id, {
  transaction_date: "2026-04-19",
  spk_id: 12345,
  details: [
    {
      item_id: 1,
      qty: 4500,
      input_unit: "base"
    }
  ]
});

await sdk.stockTransactions.approve(revision.data.id);
```


### Direct stock correction workflow

```ts
await sdk.stockTransactions.directCorrection({
  transaction_date: "2026-04-20",
  item_id: 1,
  expected_current_qty: 5000,
  target_qty: 4800,
  reason: "Found 200g damaged during audit"
});
```

In the workflow above, approval corrects the original transaction lineage based on the difference between the revision details and the latest approved baseline in that lineage. It does not replay the revision quantities as an additional standalone movement, and it does not allow multiple pending sibling revisions at the same time.

## End-to-end SDK flow example

A common operational flow involving patient input, SPK generation, and stock management:

### 1. Daily Setup & Patient Input
```ts
import { createCapstoneSdk } from "./src";
const sdk = createCapstoneSdk({ baseUrl: "http://127.0.0.1:8080" });

// Authenticate
const login = await sdk.auth.login({ username: "gudang", password: "password123" });
sdk.setAccessToken(login.access_token);

// Input daily patients as Gudang before SPK Basah generation
const patients = await sdk.dailyPatients.create({
  service_date: "2026-04-14",
  total_patients: 120
});
```

### 2. SPK Generation (Calculation Helper)
```ts
// Generate SPK Basah recommendation
const spk = await sdk.spk.generateBasah({
  daily_patient_id: patients.data.id,
  service_date: "2026-04-14",
  category_id: 1 // BASAH
});

// Basah recommendation formula:
// ((120 * 1.05) * composition_qty) - current_stock, clamped to 0
```

### 3. Stock Mutation (Authoritative Action)
The UI may allow overriding quantities before finalizing. Once ready, stock posting must be triggered explicitly.

```ts
// Optional helper: prefill a stock-IN transaction payload from the SPK
const prefill = await sdk.spk.stockInPrefill(spk.data.id);
console.log(prefill.data);

// Finalize the SPK and post its stock mutation
await sdk.spk.postBasahStock(spk.data.id);
```

### 4. History and Printing
```ts
// Fetch historical SPK for printing
const history = await sdk.spk.getBasah(spk.data.id);
// Use history.data for rendering formal print documents
```

## Design rules kept by the SDK

- request DTOs are separate from response DTOs
- backend-managed fields are not exposed as writable request fields
- resource methods mirror real backend routes instead of inventing convenience contracts
- list responses preserve pagination metadata and links
- soft-delete restore behavior is explicit where the backend requires it

## Updating the SDK when the backend changes

Do not update the SDK by guessing from one controller or one doc.

Use the canonical backend discovery workflow in `../backend/AGENTS.md`.

Minimal SDK update read order:

1. `../backend/AGENTS.md`
2. `../backend/docs/architecture/runtime-status.md` (Canonical)
3. `../backend/docs/reference/api-contract.md` (Canonical)
4. matching backend code and feature tests
5. SDK source, SDK tests, then rebuild `dist/`

Supporting references:

- `../backend/AGENTS.md`
