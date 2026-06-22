# LOGDOCS Implementation Plan — Audit Log Coverage

> **Source**: [`LOGDOCS.md`](./LOGDOCS.md) — System Analyst audit log requirements
> **Status**: Current implementation covers ~30% of required write-path auditing
> **Generated**: 2026-06-22

---

## Table of Contents

1. [Current State Assessment](#1-current-state-assessment)
2. [Phase 1 — Critical Backend Audit Hooks](#2-phase-1--critical-backend-audit-hooks)
3. [Phase 2 — Controller Response Improvements](#3-phase-2--controller-response-improvements)
4. [Phase 3 — OpenAPI Documentation](#4-phase-3--openapi-documentation)
5. [Phase 4 — SDK Coverage](#5-phase-4--sdk-coverage)
6. [Phase 5 — Revision Detail Diff Formatter](#6-phase-5--revision-detail-diff-formatter)
7. [Phase 6 — First Revision Submit `old_values` Fix](#7-phase-6--first-revision-submit-old_values-fix)
8. [Phase 7 — Route & CORS Registration](#8-phase-7--route--cors-registration)
9. [Risk Register](#9-risk-register)
10. [Verification Checklist](#10-verification-checklist)

---

## 1. Current State Assessment

### 1.1 Audit Write Coverage

| Service | DB Writes | Audit Calls | Status |
|---------|-----------|-------------|--------|
| `StockTransactionService` | stock_transactions, stock_transaction_details | Create, Submit, Approval, Rejection | ✅ Complete |
| `StockOpnameService` | stock_opnames, stock_opname_details | Create, Update, Submit, Approval, Rejection, Post | ✅ Complete |
| `SpkOverrideService` | spk_recommendations | Override | ✅ Complete |
| `ItemManagementService` | items | Create, Update, Delete, Restore | ✅ Complete |
| `UserManagementService` | users | Create, Update, Activate, Deactivate, PasswordChange, Delete, Restore | ✅ Complete |
| `SpkBasahGenerationService` | spk_calculations, spk_recommendations | **None** | ❌ Missing |
| `SpkKeringPengemasGenerationService` | spk_calculations, spk_recommendations | **None** | ❌ Missing |
| `SpkPersistenceService` | spk_calculations (insert + is_latest toggle), spk_recommendations | **None** | ❌ Missing |
| `SpkStockPostingService` | spk_calculations.is_finish, stock_transactions (via StockTransactionService) | **None** | ❌ Missing |
| `DailyPatientService` | daily_patients | **None** | ❌ Missing |
| `DishManagementService` | dishes, menu_dishes (cascade delete) | **None** | ❌ Missing |
| `DishCompositionManagementService` | dish_compositions | **None** | ❌ Missing |
| `MenuPackageManagementService` | menu_dishes | **None** | ❌ Missing |
| `MenuScheduleManagementService` | menu_schedules | **None** | ❌ Missing |
| `AuthService` | users (password via UserProvider::save) | **None** | ❌ Missing |
| `NotificationService` | notifications | **None** | ⚠️ Low priority |
| `StockSnapshotService` | monthly_stock_snapshots | **None** | ❌ Missing |
| `HistoricalOpnameBackfillService` | stock_transactions, stock_transaction_details | **None** | ❌ Missing |

### 1.2 Controller Response

Current `AuditLogs::transformRow()` already implements the LOGDOCS §15 backward-compatible response shape:

```json
{
  "id": 1,
  "date": "2026-05-08",
  "time": "14:30",
  "actor": "Admin User",
  "actorInfo": { "id": 7, "name": "Admin User", "username": "admin" },
  "activityType": "approval",
  "activityLabel": "Approval",
  "module": "Stok",
  "detail": "Menyetujui penyesuaian stok",
  "description": "Menyetujui penyesuaian stok",
  "target": { "table": "stock_opnames", "recordId": 12 },
  "changes": { "before": {}, "after": {}, "diff": [] },
  "ipAddress": "127.0.0.1",
  "rawActionType": "stock_opname_approve",
  "created_at": "2026-05-08 14:30:00"
}
```

**Known bug**: `resolveActivityType()` maps `submit` → `'create'` (line 230-231). LOGDOCS §7 says `submit` is a workflow state change, should map to a distinct category or at minimum `'update'`, not `'create'`. Also `post` → `'update'` and `override` → `'update'` lose their distinct workflow meaning.

### 1.3 Route Registration

- `GET /api/v1/audit-logs` → `AuditLogs::index` ✅ registered under `role:admin` group
- `OPTIONS /api/v1/audit-logs` → NOT currently registered (needs preflight support)
- No `GET /api/v1/audit-logs/types` endpoint for filter metadata

### 1.4 OpenAPI Documentation

- `AuditLogs` controller has full `@OA\Get` annotation ✅
- `AuditLogSchemas.php` defines `AuditLogEntry` and `AuditLogCollectionResponse` schemas ✅
- **Neither is included in `OpenApiDocs.php::sourceFiles`** ❌ — nothing compiles into the spec
- `AuditLogSchemas.php` only documents **legacy** fields (actor string, activityType string, detail string, module string) — new structured fields (actorInfo, target, changes, description, ipAddress, rawActionType) are NOT documented

### 1.5 SDK Coverage

- 20 resource modules exist in `frontend/src/sdk/resources/`
- **Zero audit-log resource module** ❌ — no `auditLogs.ts` resource, no types, no SDK_MAP entry

---

## 2. Phase 1 — Critical Backend Audit Hooks

### 2.1 SpkBasahGenerationService `generate()`

**Files**: `backend/app/Services/SpkBasahGenerationService.php`

**Change**: Add `AuditService` dependency + log call after `SpkPersistenceService::createVersionedSpk()` succeeds.

```php
use App\Enums\AuditActionType;
use App\Services\AuditService;

// In constructor:
protected AuditService $auditService;

public function __construct()
{
    // ... existing deps
    $this->auditService = new AuditService();
}

// In generate(), after SpkPersistenceService::createVersionedSpk():
// Inside the success branch, before transComplete():
$auditLogged = $this->auditService->log(
    $userId,
    AuditActionType::Create,
    'spk_calculations',
    (int) $spkVersionId,
    'SPK Basah generated successfully.',
    null,
    ['service_date' => $targetDate, 'estimated_patients' => $estimatedPatients, 'scope_key' => $scopeKey],
    $this->request?->getIPAddress()
);
```

**ActionType**: `AuditActionType::Create` (mapped via `generate` → `create`)

**Table**: `spk_calculations` (or `spk_recommendations` — pick one primary table per operation)
**RecordId**: The SPK version id (`spk_calculations.id`) from `SpkPersistenceService::createVersionedSpk()` response

**Edge cases**:
- `userId` is already passed as parameter to `generate()` ✅
- IP address: service doesn't have access to `$this->request`. Pass `?string $ipAddress = null` parameter or use `service('request')->getIPAddress()`
- Rollback: if audit log fails, should rollback the entire generation. Current code doesn't have `transStart()` at this level — it's inside `SpkPersistenceService`. **Risk**: need to ensure audit failure doesn't leave orphaned SPK data.

**Recommendation**: Wrap audit call inside `SpkPersistenceService::createVersionedSpk()` itself, since both Basah and Kering call it. This is cleaner — one audit point instead of two.

### 2.2 SpkKeringPengemasGenerationService `generate()`

**Files**: `backend/app/Services/SpkKeringPengemasGenerationService.php`

**Change**: Same as 2.1 — add audit after `SpkPersistenceService::createVersionedSpk()`.

**Same recommendation**: Push audit into `SpkPersistenceService::createVersionedSpk()`.

### 2.3 SpkPersistenceService `createVersionedSpk()`

**Files**: `backend/app/Services/SpkPersistenceService.php`

**Change**: Add `AuditService` dependency. Log at end of successful version creation.

This is the **single best place** to audit SPK generation because both Basah and Kering generation services call it. One audit point covers both.

**Payload considerations**:
- `old_values`: `null` (this is creation)
- `new_values`: version data (scope_key, target_dates, estimated_patients)
- `ip_address`: need to pass through or inject from request

### 2.4 SpkStockPostingService `post()`

**Files**: `backend/app/Services/SpkStockPostingService.php`

**Change**: Add audit after SPK is marked as finished AND stock transactions are created.

The stock transactions are created via `StockTransactionService::createTransaction()` which already logs its own audit. But the **post action itself** — the SPK finalization (`is_finish = true`, `is_posted = true`) — has no audit.

```php
$this->auditService->log(
    $userId,
    AuditActionType::Post,
    'spk_calculations',
    (int) $calculationId,
    'SPK posted to stock.',
    [
        'spk_calculation_id' => (int) $calculationId,
        'previous_finish_state' => false,
    ],
    [
        'spk_calculation_id' => (int) $calculationId,
        'transaction_ids' => $createdTransactionIds,
    ],
    $ipAddress
);
```

**AuditActionType**: `Post`
**Table**: `spk_calculations`

### 2.5 DailyPatientService

**Files**: `backend/app/Services/DailyPatientService.php`

**Methods to audit**:
- `createDailyPatient()` → `AuditActionType::Create`
- `updateDailyPatient()` → `AuditActionType::Update`

**Add**: `AuditService` dependency + log after successful DB write in each method.

**Edge cases**:
- `old_values` on update: snapshot existing daily patient row before update
- `new_values`: submitted data after write
- `ipAddress`: pass through constructor or method parameter

### 2.6 DishManagementService

**Files**: `backend/app/Services/DishManagementService.php`

**Methods to audit**:
- `createDish()` → `AuditActionType::Create`
- `updateDish()` → `AuditActionType::Update`
- `deleteDish()` (soft delete) → `AuditActionType::Delete`
- `deactivateDish()` → `AuditActionType::Deactivate`
- (if reactivate exists) → `AuditActionType::Activate`

**Add**: `AuditService` dependency + log after each write.

### 2.7 DishCompositionManagementService

**Files**: `backend/app/Services/DishCompositionManagementService.php`

**Methods to audit**:
- `createComposition()` → `AuditActionType::Create`
- `updateComposition()` → `AuditActionType::Update`
- `deleteComposition()` → `AuditActionType::Delete`

### 2.8 MenuPackageManagementService

**Files**: `backend/app/Services/MenuPackageManagementService.php`

**Methods to audit**:
- `assignDishToSlot()` → `AuditActionType::Create`
- `updateSlotAssignment()` → `AuditActionType::Update`
- `deleteSlotAssignment()` → `AuditActionType::Delete`

### 2.9 MenuScheduleManagementService

**Files**: `backend/app/Services/MenuScheduleManagementService.php`

**Methods to audit**:
- `createSchedule()` → `AuditActionType::Create`
- `updateSchedule()` → `AuditActionType::Update`

### 2.10 AuthService `changePassword()`

**Files**: `backend/app/Services/AuthService.php`

**Change**: Add `AuditService` dependency. Log after successful password change.

```php
$this->auditService->log(
    $userId,
    AuditActionType::PasswordChange,
    'users',
    (int) $userId,
    'Password changed via self-service.',
    null,
    ['password_updated' => true],
    $ipAddress
);
```

**Important**: This is the self-service password change endpoint (`PATCH /api/v1/auth/password`). The admin-driven password change (`PATCH /api/v1/users/{id}/password` via `UserManagementService`) already logs. Both must log per LOGDOCS §14.7.

### 2.11 StockSnapshotService `takeOpeningSnapshot()`

**Files**: `backend/app/Services/StockSnapshotService.php`

**Change**: Add `AuditService` dependency. Log after successful snapshot creation.

```php
$this->auditService->log(
    $userId,
    AuditActionType::Create,
    'monthly_stock_snapshots',
    /* unique id or skip — use 0 for batch */ 0,
    'Monthly opening stock snapshot taken.',
    null,
    ['period' => $period, 'item_count' => count($items)],
    $ipAddress
);
```

**Issue**: `takeOpeningSnapshot()` uses `insertBatch()` — no single record ID. Use `0` or the first inserted ID. Consider whether this needs individual audit rows per item or one batch row. **Recommendation**: one batch audit row.

### 2.12 HistoricalOpnameBackfillService `backfill()`

**Files**: `backend/app/Services/HistoricalOpnameBackfillService.php`

**Change**: Add `AuditService` dependency. Log after successful backfill.

**Issue**: Bulk operation, no single record ID. Log one audit row per backfill operation.

### 2.13 NotificationService (Low Priority)

**Files**: `backend/app/Services/NotificationService.php`

**Assessment**: Notifications are ephemeral utility data. LOGDOCS doesn't explicitly require audit for notifications. **Defer** unless analyst confirms requirement.

---

## 3. Phase 2 — Controller Response Improvements

### 3.1 Fix `resolveActivityType()` — `submit` map

**Files**: `backend/app/Controllers/Api/V1/AuditLogs.php`

**Current bug** (line 230-231):
```php
if (str_contains($normalized, 'submit')) {
    return 'create';
}
```

**Change**: Map `submit` to a distinct category `'submit'` with label `'Submit'`, or to `'update'` as LOGDOCS §7 recommends (workflow state change).

**Recommendation**: Change to `'update'` per LOGDOCS:
```php
if (str_contains($normalized, 'submit')) {
    return 'update';
}
```

Also add `'submit'` handling in `resolveActivityLabel()` if using distinct category, or ensure `'update'` label covers it.

### 3.2 Fix `resolveActivityType()` — `post` and `override` maps

**Current**: `post` → `'update'`, `override` → `'update'`

These are acceptable per LOGDOCS §7 since they're workflow state changes. **No change needed** unless analyst wants separate categories. If separate needed:
- `post` → `'post'` → label `'Post'`
- `override` → `'override'` → label `'Override'`

### 3.3 Add `rawActionType` filter endpoint

**Files**: Create `GET /api/v1/audit-logs/types` route + method

**Change**: Return distinct `action_type` values from `audit_logs` table for UI filter dropdown.

```php
public function types(): ResponseInterface
{
    $types = $this->auditLogModel
        ->builder()
        ->select('DISTINCT action_type')
        ->orderBy('action_type', 'ASC')
        ->get()
        ->getResultArray();

    $actionTypes = array_column($types, 'action_type');
    // Also provide module types
    $moduleTypes = ['Transaksi', 'Master Barang', 'Menu', 'Pengguna', 'SPK', 'Stok', 'Laporan', 'Data Sistem'];

    return $this->response->setJSON([
        'actionTypes' => $actionTypes,
        'moduleTypes' => $moduleTypes,
        'tableNames'  => $this->getAuditedTables(),
    ]);
}
```

LOGDOCS §6 says "module filter values come from `/api/v1/audit-logs/types`".

---

## 4. Phase 3 — OpenAPI Documentation

### 4.1 Register source files in OpenApiDocs

**File**: `backend/app/Config/OpenApiDocs.php`

**Add to `$sourceFiles`**:

```php
APPPATH . 'OpenApi/AuditLogSchemas.php',
APPPATH . 'Controllers/Api/V1/AuditLogs.php',
```

**Order**: Add `AuditLogSchemas.php` after line 22 (after `SpkBasahSchemas.php`), and `AuditLogs.php` after line 42 (after `SpkStockInPrefill.php`).

### 4.2 Update AuditLogSchemas to document new fields

**File**: `backend/app/OpenApi/AuditLogSchemas.php`

**Current**: Only documents legacy fields (actor string, activityType string, module string, detail string).

**Change**: Add new structured fields:

```php
/**
 * @OA\Schema(
 *     schema="AuditLogEntry",
 *     type="object",
 *     required={"id","date","time","actor","actorInfo","activityType","activityLabel","module","detail","description","target","changes","ipAddress","rawActionType","created_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="date", type="string", example="2026-05-08"),
 *     @OA\Property(property="time", type="string", example="14:30"),
 *     @OA\Property(property="actor", type="string", example="Admin User"),
 *     @OA\Property(
 *         property="actorInfo",
 *         type="object",
 *         @OA\Property(property="id", type="integer", nullable=true, example=7),
 *         @OA\Property(property="name", type="string", example="Admin User"),
 *         @OA\Property(property="username", type="string", nullable=true, example="admin")
 *     ),
 *     @OA\Property(property="activityType", type="string", example="approval"),
 *     @OA\Property(property="activityLabel", type="string", example="Approval"),
 *     @OA\Property(property="module", type="string", example="Stok"),
 *     @OA\Property(property="detail", type="string", example="Menyetujui penyesuaian stok"),
 *     @OA\Property(property="description", type="string", example="Menyetujui penyesuaian stok"),
 *     @OA\Property(
 *         property="target",
 *         type="object",
 *         @OA\Property(property="table", type="string", example="stock_opnames"),
 *         @OA\Property(property="recordId", type="integer", example=12)
 *     ),
 *     @OA\Property(
 *         property="changes",
 *         type="object",
 *         @OA\Property(property="before", type="object", nullable=true),
 *         @OA\Property(property="after", type="object", nullable=true),
 *         @OA\Property(
 *             property="diff",
 *             type="array",
 *             @OA\Items(
 *                 type="object",
 *                 @OA\Property(property="field", type="string"),
 *                 @OA\Property(property="before", description="Value before change"),
 *                 @OA\Property(property="after", description="Value after change")
 *             )
 *         )
 *     ),
 *     @OA\Property(property="ipAddress", type="string", nullable=true, example="127.0.0.1"),
 *     @OA\Property(property="rawActionType", type="string", example="stock_opname_approve"),
 *     @OA\Property(property="created_at", type="string", nullable=true, example="2026-05-08 14:30:00")
 * )
 */
```

---

## 5. Phase 4 — SDK Coverage

### 5.1 Create auditLog types

**File**: `frontend/src/sdk/types/auditLogs.ts` (new)

```typescript
import type { PaginationMeta, PaginationLinks } from "./common";

/** Single audit log entry returned by the API. */
export interface AuditLogEntry {
  id: number;
  date: string | null;
  time: string | null;
  actor: string;
  actorInfo: {
    id: number | null;
    name: string;
    username: string | null;
  };
  activityType: string;
  activityLabel: string;
  module: string;
  detail: string;
  description: string;
  target: {
    table: string | null;
    recordId: number | null;
  };
  changes: {
    before: Record<string, unknown> | null;
    after: Record<string, unknown> | null;
    diff: Array<{ field: string; before: unknown; after: unknown }>;
  };
  ipAddress: string | null;
  rawActionType: string | null;
  created_at: string | null;
}

/** Query parameters for listing audit logs. */
export interface AuditLogListQuery {
  page?: number;
  perPage?: number;
  paginate?: string;
  q?: string;
  action_type?: string;
  table_name?: string;
  sortBy?: "id" | "created_at" | "action_type" | "table_name" | "record_id";
  sortDir?: "ASC" | "DESC";
}

/** Response envelope for list. */
export interface AuditLogListResponse {
  data: AuditLogEntry[];
  meta: PaginationMeta;
  links: PaginationLinks;
}

/** Response from the types endpoint. */
export interface AuditLogTypesResponse {
  actionTypes: string[];
  moduleTypes: string[];
  tableNames: string[];
}
```

### 5.2 Create auditLogs resource

**File**: `frontend/src/sdk/resources/auditLogs.ts` (new)

```typescript
import type { ApiClient } from "../client";
import type { AuditLogListResponse, AuditLogListQuery, AuditLogTypesResponse } from "../types";

/**
 * Audit Logs SDK Resource
 *
 * Wraps:    GET /api/v1/audit-logs
 * Contract: api-contract.md §5.1.2
 * Access:   admin
 *
 * Admin-only audit trail surface.
 */
export class AuditLogsResource {
  public constructor(private readonly client: ApiClient) {}

  /**
   * Lists audit log entries with pagination, search, and filtering.
   *
   * @endpoint GET /api/v1/audit-logs
   * @access   admin
   */
  public list(query?: AuditLogListQuery): Promise<AuditLogListResponse> {
    return this.client.request<AuditLogListResponse>({
      method: "GET",
      path: "/audit-logs",
      ...(query ? { query: buildAuditLogQuery(query) } : {}),
    });
  }

  /**
   * Returns available filter values for the audit log UI.
   *
   * @endpoint GET /api/v1/audit-logs/types
   * @access   admin
   */
  public types(): Promise<AuditLogTypesResponse> {
    return this.client.request<AuditLogTypesResponse>({
      method: "GET",
      path: "/audit-logs/types",
    });
  }
}

function buildAuditLogQuery(query: AuditLogListQuery): Record<string, string | number> {
  const result: Record<string, string | number> = {};

  if (query.page !== undefined) result.page = query.page;
  if (query.perPage !== undefined) result.perPage = query.perPage;
  if (query.paginate !== undefined) result.paginate = query.paginate;
  if (query.q !== undefined) result.q = query.q;
  if (query.action_type !== undefined) result.action_type = query.action_type;
  if (query.table_name !== undefined) result.table_name = query.table_name;
  if (query.sortBy !== undefined) result.sortBy = query.sortBy;
  if (query.sortDir !== undefined) result.sortDir = query.sortDir;

  return result;
}
```

### 5.3 Wire into SDK

**File**: `frontend/src/sdk/index.ts`

Changes:
1. Add export: `export * from "./resources/auditLogs";`
2. Add import: `import { AuditLogsResource } from "./resources/auditLogs";`
3. Add property: `public readonly auditLogs: AuditLogsResource;`
4. Add constructor init: `this.auditLogs = new AuditLogsResource(this.client);`

### 5.4 Add types export

**File**: `frontend/src/sdk/types/index.ts`

Add: `export * from "./auditLogs";`

### 5.5 Update SDK_MAP

**File**: `frontend/src/sdk/SDK_MAP.md`

Add section:

```markdown
| **Audit Logs** | | |
| `GET /api/v1/audit-logs` | `sdk.auditLogs.list(query?)` | `AuditLogListResponse` |
| `GET /api/v1/audit-logs/types` | `sdk.auditLogs.types()` | `AuditLogTypesResponse` |
```

### 5.6 Add unit tests

**File**: `frontend/src/sdk/tests/auditLogs.test.ts` (new)

Follow existing test patterns (see `roles.test.ts`, `users.test.ts`). Test:
- `list()` with no params
- `list()` with full query params
- `types()` returns actionTypes, moduleTypes, tableNames

---

## 6. Phase 5 — Revision Detail Diff Formatter

### 6.1 Controller-level helper

**Files**: `backend/app/Controllers/Api/V1/AuditLogs.php`

**Add method** to build item-level revision diff from `changes.before` and `changes.after`:

```php
/**
 * Build item-level diff for stock transaction revision details.
 * Compares details by item_id.
 *
 * @param array|null $before Old values (parent transaction or previous revision)
 * @param array|null $after New values (current revision)
 * @return array<int, array{item_id: int, label: string, qty_before: ?string, qty_after: ?string, unit_before: ?string, unit_after: ?string, status: string}>
 */
private function buildRevisionItemDiff(?array $before, ?array $after): array
{
    $before ??= [];
    $after ??= [];

    $beforeDetails = $before['parent_details'] ?? $before['revision_details'] ?? [];
    $afterDetails = $after['revision_details'] ?? [];

    // Index by item_id
    $beforeIndexed = [];
    foreach ($beforeDetails as $detail) {
        $beforeIndexed[$detail['item_id']] = $detail;
    }

    $afterIndexed = [];
    foreach ($afterDetails as $detail) {
        $afterIndexed[$detail['item_id']] = $detail;
    }

    $allItemIds = array_unique(array_merge(array_keys($beforeIndexed), array_keys($afterIndexed)));
    $diffs = [];

    foreach ($allItemIds as $itemId) {
        $b = $beforeIndexed[$itemId] ?? null;
        $a = $afterIndexed[$itemId] ?? null;

        if ($b === null && $a !== null) {
            // Item added in revision
            $diffs[] = [
                'item_id' => $itemId,
                'label' => $a['item_name'] ?? "Item #{$itemId}",
                'qty_before' => null,
                'qty_after' => $a['qty'],
                'unit_before' => null,
                'unit_after' => $a['input_unit'] ?? null,
                'status' => 'added',
            ];
        } elseif ($b !== null && $a === null) {
            // Item removed in revision
            $diffs[] = [
                'item_id' => $itemId,
                'label' => $b['item_name'] ?? "Item #{$itemId}",
                'qty_before' => $b['qty'],
                'qty_after' => null,
                'unit_before' => $b['input_unit'] ?? null,
                'unit_after' => null,
                'status' => 'removed',
            ];
        } elseif ($b !== null && $a !== null && (
            (string) $b['qty'] !== (string) $a['qty'] ||
            ($b['input_unit'] ?? null) !== ($a['input_unit'] ?? null)
        )) {
            // Quantity or unit changed
            $diffs[] = [
                'item_id' => $itemId,
                'label' => $a['item_name'] ?? $b['item_name'] ?? "Item #{$itemId}",
                'qty_before' => $b['qty'],
                'qty_after' => $a['qty'],
                'unit_before' => $b['input_unit'] ?? null,
                'unit_after' => $a['input_unit'] ?? null,
                'status' => 'changed',
            ];
        }
        // else: same — skip
    }

    return $diffs;
}
```

**Integration**: Add `itemDiff` to the `changes` object when table is `stock_transactions` and `action_type` contains `revision`:

```php
// In transformRow(), after building $changes:
if ($row['table_name'] === 'stock_transactions' && str_contains(strtolower($row['action_type'] ?? ''), 'revision')) {
    $changes['itemDiff'] = $this->buildRevisionItemDiff($before, $after);
}
```

---

## 7. Phase 6 — First Revision Submit `old_values` Fix

### 7.1 StockTransactionService `submitRevision()`

**Files**: `backend/app/Services/StockTransactionService.php`

**Current behavior** (per LOGDOCS §13):
- First revision submit: `old_values = null`, `new_values = revision header only`
- Pending revision resubmit: `old_values = existing pending revision`, `new_values = updated revision`

**Required change**: On first revision submit, include parent transaction header + details as `old_values`.

```php
// In submitRevision(), when building audit payload:
if ($existingPendingRevision === null) {
    // First submit — get parent transaction data
    $parent = $this->transactionModel->find($transactionId);
    $parentDetails = $this->detailModel->where('stock_transaction_id', $transactionId)->findAll();

    $auditOldValues = [
        'parent_header' => $parent,
        'parent_details' => $parentDetails,
    ];
} else {
    // Resubmit — use existing pending revision
    $auditOldValues = [
        'revision_header' => $existingPendingRevision,
        'revision_details' => $existingPendingDetails,
    ];
}
```

---

## 8. Phase 7 — Route & CORS Registration

### 8.1 Register OPTIONS route

**File**: `backend/app/Config/Routes.php`

Add OPTIONS route for CORS preflight:

```php
$routes->options(
    "audit-logs",
    static fn() => service("response")->setStatusCode(204),
);
```

Place this near line 261, alongside other OPTIONS routes.

### 8.2 Register `/types` route

```php
$routes->get("audit-logs/types", "AuditLogs::types");
```

Inside the `role:admin` group.

---

## 9. Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| Audit written outside transaction — orphaned data | Missing audit rows | Always call `auditService->log()` inside `transStart`/`transComplete` |
| IP address unavailable in service layer | `null` ip_address in audit | Pass `?string $ipAddress = null` parameter or use `service('request')->getIPAddress()` |
| Bulk operations (snapshot, backfill) have no single record_id | Can't link audit to specific record | Use `0` for batch operations, store context in `new_values` |
| `submit→create` bug breaks existing frontend filtering | UI shows wrong activity type | Fix mapping and coordinate frontend update |
| Existing SDK consumers get extra fields on audit log response | No breakage (backward compatible) | New fields added alongside legacy — `actorInfo` doesn't replace `actor` |
| Audit service instantiation pattern varies | Inconsistent code | Use `new AuditService()` in constructor like existing services |
| NotificationService audit may generate noise | Audit log dilution | Defer until analyst confirms requirement |

---

## 10. Verification Checklist

### After Phase 1 (Backend Hooks)

- [ ] `SpkPersistenceService::createVersionedSpk()` logs audit on successful SPK version creation
- [ ] `SpkStockPostingService::post()` logs audit with `AuditActionType::Post`
- [ ] `DailyPatientService::createDailyPatient()` logs audit
- [ ] `DailyPatientService::updateDailyPatient()` logs audit
- [ ] `DishManagementService::createDish()` logs audit
- [ ] `DishManagementService::updateDish()` logs audit
- [ ] `DishManagementService::deleteDish()` logs audit
- [ ] `DishManagementService::deactivateDish()` logs audit
- [ ] `DishCompositionManagementService::createComposition()` logs audit
- [ ] `DishCompositionManagementService::updateComposition()` logs audit
- [ ] `DishCompositionManagementService::deleteComposition()` logs audit
- [ ] `MenuPackageManagementService::assignDishToSlot()` logs audit
- [ ] `MenuPackageManagementService::updateSlotAssignment()` logs audit
- [ ] `MenuPackageManagementService::deleteSlotAssignment()` logs audit
- [ ] `MenuScheduleManagementService::createSchedule()` logs audit
- [ ] `MenuScheduleManagementService::updateSchedule()` logs audit
- [ ] `AuthService::changePassword()` logs audit (`AuditActionType::PasswordChange`)
- [ ] `StockSnapshotService::takeOpeningSnapshot()` logs audit
- [ ] `HistoricalOpnameBackfillService::backfill()` logs audit
- [ ] All audit calls are inside `transStart`/`transComplete`
- [ ] Audit rollback causes full transaction rollback

### After Phase 2 (Controller)

- [ ] `resolveActivityType()` maps `submit` correctly (not to `create`)
- [ ] `GET /api/v1/audit-logs/types` returns valid filter metadata
- [ ] Response includes all LOGDOCS §15 fields with backward-compatible aliases

### After Phase 3 (OpenAPI)

- [ ] `AuditLogSchemas.php` is in `OpenApiDocs.php::sourceFiles`
- [ ] `AuditLogs.php` controller is in `OpenApiDocs.php::sourceFiles`
- [ ] Generated OpenAPI spec includes `/api/v1/audit-logs` path
- [ ] Generated OpenAPI spec includes `AuditLogEntry` schema with new structured fields
- [ ] Generated OpenAPI spec includes `AuditLogCollectionResponse` schema

### After Phase 4 (SDK)

- [ ] `frontend/src/sdk/types/auditLogs.ts` exists with correct types
- [ ] `frontend/src/sdk/resources/auditLogs.ts` exists with `list()` and `types()` methods
- [ ] `frontend/src/sdk/index.ts` exports and wires `AuditLogsResource`
- [ ] `frontend/src/sdk/types/index.ts` exports auditLog types
- [ ] `frontend/src/sdk/SDK_MAP.md` has audit logs section
- [ ] Unit tests pass for auditLogs resource
- [ ] `npm run build` passes

### After Phase 5 (Diff Formatter)

- [ ] `buildRevisionItemDiff()` correctly identifies added/removed/changed items
- [ ] `itemDiff` is present in `changes` when applicable
- [ ] Items with same `item_id` and same `qty`/`unit` are omitted from diff

### After Phase 6 (First Submit)

- [ ] First revision submit includes `parent_header` + `parent_details` in `old_values`
- [ ] Resubmit includes previous `revision_header` + `revision_details` in `old_values`

### After Phase 7 (Routes)

- [ ] `OPTIONS /api/v1/audit-logs` returns 204
- [ ] `GET /api/v1/audit-logs/types` is registered and works
- [ ] `GET /api/v1/audit-logs` works through route group
- [ ] Non-admin role gets 403 on audit-logs endpoints

### Final Integration

- [ ] Admin can view audit trail via SDK: `sdk.auditLogs.list()`
- [ ] Audit logs from all write services appear in list response
- [ ] Activity types show correct labels (approval, rejection, create, update, delete, post)
- [ ] Revision transactions show item-level diff
- [ ] OpenAPI spec compiles and includes audit logs documentation
- [ ] Feature tests pass: `vendor/bin/phpunit tests/feature/Api/V1/AuditLogsTest.php`
