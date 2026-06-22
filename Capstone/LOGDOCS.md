# LOGDOCS

## Scope
Audit log implementation guide for requirement from System Analyst.

Source basis:
- `backend/app/Services/AuditService.php`
- `backend/app/Services/NotificationService.php`
- `backend/app/Services/StockTransactionService.php`
- `backend/app/Services/StockOpnameService.php`
- `backend/app/Services/SpkOverrideService.php`
- `backend/app/Controllers/Api/V1/AuditLogs.php`
- `backend/docs/reference/schema.md`
- `backend/docs/reference/api-contract.md`
- `backend/docs/architecture/runtime-status.md`
- `backend/docs/archive/Software Requirements Specification (SRS).md`

## 1. Analyst requirement summary
System Analyst wants audit log display and storage to cover:

| Requirement | Meaning | Current support |
|---|---|---|
| Tanggal | Date of activity | Supported from `audit_logs.created_at`, transformed to `date` |
| Jam | Time of activity | Supported from `audit_logs.created_at`, transformed to `time` |
| Nama/username pelaku | User who performed activity | Supported through `audit_logs.user_id -> users.name/users.username`; nullable means fallback `Sistem` |
| Modul yang diakses | Module/entity touched | Supported through `table_name`, transformed by controller to `module` |
| Jenis aktivitas | Insert/create, update, delete, approval, rejection | Partially supported; current controller collapses approval/rejection into `Update` |
| Deskripsi aktivitas | Human-readable activity detail | Supported through `message` and controller detail resolver |
| Detail sebelum/sesudah | Old and new values if feasible | Supported by `old_values` and `new_values` JSON columns, but not exposed by current list response |

Coverage note:
- `menus`, `menu_dishes`, `menu_schedules`, and `meal_times` are package-menu surface; map module label to `Menu`.
- `menus` read/list is lookup-only. No audit write there.
- menu write paths, if added or already present in package/menu services, must log by raw `table_name` and typed action.
- `action_type` filter and `module` filter must be queryable in list response and documentable through a types endpoint.

Conclusion: database already strong enough. Main implementation gap is API response contract, activity type mapping, and discoverable filter metadata.


## 2. Current implementation finding
Audit logging is **not event-driven**.

Current design:
1. Controller calls domain service.
2. Domain service mutates primary table.
3. Same service prepares `oldValues` and `newValues` when available.
4. Same service calls `AuditService::log(...)`.
5. Some branches rollback transaction if audit insert fails.

No audit trigger service exists.
No framework event hook exists for audit write.
No database trigger exists for audit write.

This is acceptable for current scope if every write path calls audit explicitly. Risk: new write path can forget audit log.

## 3. Existing data model
`audit_logs` table already supports analyst requirement.

Columns from schema:

| Column | Purpose for requirement |
|---|---|
| `id` | Audit row id |
| `user_id` | Actor link to `users` |
| `action_type` | Raw activity type code |
| `table_name` | Module/entity source |
| `record_id` | Target record id |
| `message` | Activity description |
| `old_values` | JSON snapshot before change |
| `new_values` | JSON snapshot after change |
| `ip_address` | Security traceability |
| `created_at` | Source for date and time |

Important: `old_values` and `new_values` are JSON. No schema migration needed for before/after detail.

## 4. Existing write contract
Current service signature:

```php
AuditService::log(
    ?int $userId,
    AuditActionType $actionType,
    string $tableName,
    int $recordId,
    ?string $message = null,
    ?array $oldValues = null,
    ?array $newValues = null,
    ?string $ipAddress = null
): bool
```

Rules:
- caller owns business context
- caller must send actor id, `AuditActionType` enum, table/module source, record id, message, and optional before/after values
- `AuditService` only encodes JSON and inserts row
- return `false` means insert failed
- caller decides rollback policy

## 4.1 Action type reference
Action type must use `App\Enums\AuditActionType`, not raw strings.

Lean action set:
- `create`
- `update`
- `delete`
- `approval`
- `rejection`
- `submit`
- `post`
- `override`
- `activate`
- `deactivate`
- `password_change`
- `restore`

Rule:
- service writes typed enum value to `action_type`
- module/table/message carry specific meaning; avoid module-specific enum explosion
- when new write flow appears, map it to nearest generic action first

## 4.2 Use in read contract
`action_type` filter should accept generic action value; UI can combine it with `table_name` / `module` for precise filtering



## 5. Existing read endpoint
Implemented route:

```http
GET /api/v1/audit-logs
```

Access:
- admin-only route gate in routing/runtime docs

Supported query params in controller:
- `page`
- `perPage`
- `paginate`
- `q`
- `action_type`
- `table_name`
- `sortBy`
- `sortDir`

Current response row:

```json
{
  "id": 1,
  "date": "2026-05-08",
  "time": "14:30",
  "actor": "Admin User",
  "activityType": "Update",
  "module": "Stok",
  "detail": "Menerapkan penyesuaian stok",
  "created_at": "2026-05-08 14:30:00"
}
```

Current response omits:
- raw `action_type`
- `table_name`
- `record_id`
- `old_values`
- `new_values`
- `ip_address`
- explicit `username` field separate from name

For analyst requirement, response needs improvement.

## 6. Required API response improvement
Recommended response row:

```json
{
  "id": 1,
  "date": "2026-05-08",
  "time": "14:30",
  "actor": {
    "id": 7,
    "name": "Admin User",
    "username": "admin"
  },
  "module": "Stok",
  "activityType": "approval",
  "activityLabel": "Approval",
  "description": "Menyetujui penyesuaian stok",
  "target": {
    "table": "stock_opnames",
    "recordId": 12
  },
  "changes": {
    "before": {
      "state": "SUBMITTED"
    },
    "after": {
      "state": "APPROVED"
    },
    "diff": [
      {
        "field": "state",
        "before": "SUBMITTED",
        "after": "APPROVED"
      }
    ]
  },
  "ipAddress": "127.0.0.1",
  "created_at": "2026-05-08 14:30:00"
}
```

Backward-compatible option:
- keep existing flat keys `actor`, `detail`, `activityType`
- add new fields `actorInfo`, `description`, `target`, `changes`, `ipAddress`, `rawActionType`

Clean contract option:
- change response to structured row above
- update frontend table binding at same time

Recommendation: use backward-compatible option if frontend already consumes current `actor/detail/activityType` names. Use clean contract only if frontend audit page is still flexible.

UI contract note:
- table must show `module` next to date, time, username, activity type, and detail activity
- filter bar must include module filter
- module filter values come from `/api/v1/audit-logs/types` via `moduleTypes`; UI maps selected module back to allowed `table_name` values
- detail drawer still uses `changes.before` / `changes.after` / `changes.diff`

Detail activity label rules:
- should read like verb phrase + target, not raw status text
- examples: `Menghapus menu 9`, `Mengubah menu`, `Mengubah data profil`, `Menyetujui penyesuaian stok`, `Menolak revisi transaksi`
- for `users`, prefer profile wording when action touches own account fields
- for `menus`, include package number/name when record is known
- for `items`, prefer item name instead of generic `data`
- if message exists and is specific, use it as `description`; only fall back to template from action + module
- keep `activityLabel` short (`Delete`, `Update`, `Approval`) and `description` human-readable (`Menghapus menu 9`)

## 7. Activity type mapping requirement
Analyst explicitly lists:
- insert/create
- update
- delete
- approval
- rejection

Current `resolveActivityType()` behavior:
- rejection returns `Update`
- approval returns `Update`
- submit/post/override return `Update`
- create defaults to `Create`

Need replace with explicit categories.

Recommended raw category values:

| Raw `action_type` contains | `activityType` | `activityLabel` |
|---|---|---|
| `reject` | `rejection` | `Rejection` |
| `approve` | `approval` | `Approval` |
| `delete`, `remove` | `delete` | `Delete` |
| `create`, `draft` | `create` | `Create` |
| `insert` | `create` | `Create` |
| `update`, `change`, `override`, `submit`, `post`, `revision` | `update` | `Update` |
| fallback | `update` | `Update` |

Note: `submit` and `post` are workflow changes, so `update` is acceptable unless analyst wants separate workflow category.

## 8. Before/after detail implementation
Existing DB stores full JSON in `old_values` and `new_values`.

Implementation steps:
1. In `AuditLogs::transformRow()`, decode JSON using `json_decode(..., true)`.
2. Return decoded `before` and `after` under `changes`.
3. Build optional `diff` array for top-level scalar/object fields.
4. Do not compute deep diff first unless UI needs it.

Recommended helper behavior:

```php
private function decodeJsonObject(mixed $value): ?array
{
    if ($value === null || $value === '') {
        return null;
    }

    $decoded = json_decode((string) $value, true);

    return is_array($decoded) ? $decoded : null;
}
```

Recommended diff shape:

```json
[
  {
    "field": "state",
    "before": "SUBMITTED",
    "after": "APPROVED"
  },
  {
    "field": "approved_by",
    "before": null,
    "after": 7
  }
]
```

Diff rules:
- compare keys from union of before/after top-level arrays
- include only fields where values differ
- preserve arrays/objects as JSON values, not strings
- if one side is null, show all fields from other side
- if values are large nested records, first version may show `before` and `after` only; diff can be empty or omitted

## 9. Module mapping requirement
Current module mapping comes from `table_name`.

Existing mapping:

| Table | Module |
|---|---|
| `stock_transactions`, `daily_patients` | `Transaksi` |
| `items`, `item_categories`, `item_units`, `approval_statuses` | `Master Barang` |
| `dishes`, `dish_compositions`, `menus`, `menu_dishes`, `menu_schedules`, `meal_times` | `Menu` |
| `users` | `Pengguna` |
| `spk_calculations`, `spk_recommendations` | `SPK` |
| `stock_opnames` | `Stok` |
| `reports` | `Laporan` |
| fallback | `Data Sistem` |

Requirement fit: good. Keep this mapping unless UI needs more granular module labels.

## 10. Description requirement
Current `resolveDetail()` already creates Indonesian descriptions for:
- stock transaction revision submit/approve/reject
- stock opname approve/reject/post/submit/update/create
- SPK override/generate
- generic create/update/delete fallback

Requirement fit: good.

Recommended change:
- expose description as `description`
- keep `detail` only as legacy alias if frontend already uses it
- prefer message from audit row when generic resolver has no specific mapping

## 11. Actor requirement
Current controller selects:
- `users.name AS user_name`
- `users.username AS user_username`

Current transform returns one string:

```php
$actor = $row['user_name'] ?: ($row['user_username'] ?: 'Sistem');
```

Requirement asks name/username. Improve to expose both:

```json
"actor": {
  "id": 7,
  "name": "Admin User",
  "username": "admin"
}
```

For system rows:

```json
"actor": {
  "id": null,
  "name": "Sistem",
  "username": null
}
```

Backward-compatible alias:

```json
"actorName": "Admin User"
```

## 12. Date and time requirement
Current `created_at` supports date/time split.

Keep:
- `date`: `Y-m-d`
- `time`: `H:i`
- `created_at`: raw DB timestamp

Optional future improvement:
- add ISO field `occurredAt` if frontend needs timezone-safe parsing

Do not remove `created_at` unless all consumers migrate.

## 13. Current audit write coverage
Observed audit write points:

### Stock transactions
- create transaction: `stock_transaction_create`
- direct correction: `stock_direct_correction_create`
- opname adjustment transaction: `stock_transaction_opname_adjustment_create`
- revision submit/resubmit: `stock_transaction_revision_submit`
- revision approve: `stock_transaction_revision_approve`
- revision reject: `stock_transaction_revision_reject`

### Stock opname
- draft create: `stock_opname_create_draft`
- update: `stock_opname_update`
- submit: `stock_opname_submit`
- approve: `stock_opname_approve`
- reject: `stock_opname_reject`
- post/finalize: `stock_opname_post`

### SPK override
- recommendation override: `spk_recommendation_override`

Gap: this file does not prove every CRUD module logs audit. Only listed services observed.

### User account management
Observed service methods exist in `UserManagementService`:
- create user
- update user
- activate user
- deactivate user
- change password
- delete user
- restore user

✅ `AuditService` usage is now implemented in `UserManagementService` for all methods.

Requirement impact: user account changes are **covered** by current audit log implementation.

Audit action types used:
- `AuditActionType::Create` (create user)
- `AuditActionType::Update` (update user)
- `AuditActionType::Activate` (activate user)
- `AuditActionType::Deactivate` (deactivate user)
- `AuditActionType::PasswordChange` (change password)
- `AuditActionType::Delete` (delete user)
- `AuditActionType::Restore` (restore user)

Payload follows the recommended shape below (table_name: `users`, record_id: target user id, old_values/new_values exclude password hash fields).
- `user_id`: admin/operator id from authenticated session, not target user id
- `old_values`: user fields before change, excluding password hash/token/secret fields
- `new_values`: user fields after change, excluding password hash/token/secret fields
- `message`: Indonesian action summary, e.g. `Mengubah akun pengguna.`

Security rule: never store password, password hash, reset token, access token, remember token, or raw credentials in `old_values`/`new_values`.

### Item/master barang management
Observed service methods exist in `ItemManagementService`:
- create item
- update item
- delete item
- restore item

✅ `AuditService` usage is now implemented in `ItemManagementService` for all methods.

Requirement impact: item/master barang changes are **covered** by current audit log implementation.

Audit action types used:
- `AuditActionType::Create` (create item)
- `AuditActionType::Update` (update item)
- `AuditActionType::Delete` (delete item)
- `AuditActionType::Restore` (restore item)

Payload follows the recommended shape below (table_name: `items`, record_id: item id, old_values/new_values exclude sensitive fields).


Recommended payload:
- `table_name`: `items`
- `record_id`: item id
- `old_values`: item row before change, including category/unit ids and stock policy fields
- `new_values`: item row after change
- `message`: Indonesian action summary, e.g. `Mengubah data bahan.`

Important item fields for before/after detail:
- name
- category
- unit
- min stock
- active/deleted status
- conversion fields if present

### Stock revision item changes
Stock revision submit/resubmit already logs audit with header/detail payload.

Current behavior:
- first revision submit: `old_values = null`
- first revision submit: `new_values = revision header only`
- pending revision resubmit: `old_values.header = existing pending revision`
- pending revision resubmit: `old_values.details = existing pending details`
- pending revision resubmit: `new_values.header = updated revision header`
- pending revision resubmit: `new_values.details = replacement details`

Gap: first revision submit does not store original parent transaction details as `old_values`. If analyst wants "items changed from original transaction to revision", first submit should include parent transaction snapshot and details.

Recommended first-submit payload:

```php
old_values: [
    'parent_header' => $parent,
    'parent_details' => $parentDetails,
]

new_values: [
    'revision_header' => array_merge($revisionData, ['id' => (int) $revisionId]),
    'revision_details' => $detailWriteResult['details'],
]
```

Recommended resubmit payload:

```php
old_values: [
    'revision_header' => $existingPendingRevision,
    'revision_details' => $existingPendingDetails,
]

new_values: [
    'revision_header' => array_merge($revisionData, ['id' => (int) $revisionId]),
    'revision_details' => $detailWriteResult['details'],
]
```

UI diff rule for revision details:
- compare details by `item_id`
- show item added when item exists only in revision
- show item removed when item exists only in original/old revision
- show item quantity changed when same `item_id` has different `qty`
- show unit/conversion changed when same `item_id` has different input/conversion fields

This item-level revision diff should be a specialized frontend/API formatter. Generic top-level diff will only show `details` changed, not readable item changes.

## 14. Implementation checklist
Minimum code changes for analyst requirement:

1. Update `AuditLogs::transformRow()`:
   - include actor id/name/username
   - include module
   - include activity type category and label
   - include description
   - include target table and record id
   - include decoded before/after JSON
   - include optional top-level diff
   - include IP address

2. Update `AuditLogs::resolveActivityType()`:
   - return `approval` for approve actions
   - return `rejection` for reject actions
   - return `create`, `update`, `delete` explicitly

3. Add `resolveActivityLabel()`:
   - map category to display label

4. Add JSON helpers:
   - `decodeJsonObject()`
   - `buildChangeDiff()`

5. Add missing audit writes:
   - `UserManagementService` for account create/update/status/password/delete/restore
   - `ItemManagementService` for item create/update/delete/restore
   - stock revision first submit should store parent header/details in `old_values`

6. Add specialized revision detail diff formatter:
   - compare by `item_id`
   - label added/removed/qty changed/unit changed rows

7. Add login/password policy note:
   - login attempts stay in `auth_logins` / `auth_token_logins`
   - password change must also write `audit_logs` because it is a critical account mutation

8. Update enum template contract:
   - `AuditActionType` must stay generic and lean
   - service writes enum value, not raw string
   - new action must map to nearest generic verb first
   - avoid module-specific enum cases

9. Update API docs:
   - `backend/docs/reference/api-contract.md` section for audit logs must say implemented, not planned
   - document new response shape and before/after payload

10. Keep schema docs unchanged unless column names change.

11. Frontend audit table fields should display:
   - Tanggal: `date`
   - Jam: `time`
   - Nama/username: `actor.name` and/or `actor.username`
   - Modul: `module`
   - Jenis aktivitas: `activityLabel`
   - Deskripsi: `description`
   - Detail perubahan: `changes.diff` or before/after drawer
   - Stock revision item detail: specialized item diff list

## 15. Suggested controller response compatibility
To reduce frontend breakage, return both old and new keys temporarily:

```json
{
  "id": 1,
  "date": "2026-05-08",
  "time": "14:30",
  "actor": "Admin User",
  "actorInfo": {
    "id": 7,
    "name": "Admin User",
    "username": "admin"
  },
  "activityType": "approval",
  "activityLabel": "Approval",
  "module": "Stok",
  "detail": "Menyetujui penyesuaian stok",
  "description": "Menyetujui penyesuaian stok",
  "target": {
    "table": "stock_opnames",
    "recordId": 12
  },
  "changes": {
    "before": {},
    "after": {},
    "diff": []
  },
  "ipAddress": "127.0.0.1",
  "rawActionType": "stock_opname_approve",
  "created_at": "2026-05-08 14:30:00"
}
```

This satisfies analyst requirement without forcing immediate UI rewrite.

## 16. Risks and decisions
| Risk | Impact | Decision |
|---|---|---|
| Manual audit calls can be forgotten | Missing logs in new write paths | Add checklist for every future write service |
| User account changes have no audit calls | Requirement gap for account create/update/delete/password/status | Add `AuditService` calls in `UserManagementService` |
| Item/master barang changes have no audit calls | Requirement gap for item create/update/delete/restore | Add `AuditService` calls in `ItemManagementService` |
| First stock revision submit lacks parent before snapshot | Cannot show original items before first revision | Add parent header/details to `old_values` |
| `approval`/`rejection` currently shown as `Update` | Requirement mismatch | Change mapping |
| Password change only in auth tables | Critical mutation missing from audit UI | Duplicate as audit-log event in user management |
| Before/after JSON can be large | UI table can become noisy | Show diff in expandable detail, not main table |
| Nested JSON diff can be complex | Slow implementation | Start with top-level diff and raw before/after; add specialized revision item diff |
| Existing frontend may depend on `actor` string and `detail` | Breaking UI | Add structured fields while keeping legacy aliases |

## 17. Bottom line
Current backend already stores enough data for requested audit logs:
- tanggal
- jam
- actor name/username source
- module source
- activity type source
- description source
- before/after JSON source

Needed implementation work:
- expose stored fields in audit list API
- fix activity type categorization for approval/rejection
- decode and return before/after changes
- add audit writes for user account management
- add audit writes for item/master barang management
- include parent transaction header/details for first stock revision submit
- add item-level diff formatter for stock revision details
- sync API contract docs from planned to implemented

## 15. Suggested controller response compatibility
To reduce frontend breakage, return both old and new keys temporarily:

```json
{
  "id": 1,
  "date": "2026-05-08",
  "time": "14:30",
  "actor": "Admin User",
  "actorInfo": {
    "id": 7,
    "name": "Admin User",
    "username": "admin"
  },
  "activityType": "approval",
  "activityLabel": "Approval",
  "module": "Stok",
  "detail": "Menyetujui penyesuaian stok",
  "description": "Menyetujui penyesuaian stok",
  "target": {
    "table": "stock_opnames",
    "recordId": 12
  },
  "changes": {
    "before": {},
    "after": {},
    "diff": []
  },
  "ipAddress": "127.0.0.1",
  "rawActionType": "stock_opname_approve",
  "created_at": "2026-05-08 14:30:00"
}
```

This satisfies analyst requirement without forcing immediate UI rewrite.

## 16. Risks and decisions
| Risk | Impact | Decision |
|---|---|---|
| Manual audit calls can be forgotten | Missing logs in new write paths | Add checklist for every future write service |
| User account changes have no audit calls | Requirement gap for account create/update/delete/password/status | Add `AuditService` calls in `UserManagementService` |
| Item/master barang changes have no audit calls | Requirement gap for item create/update/delete/restore | Add `AuditService` calls in `ItemManagementService` |
| First stock revision submit lacks parent before snapshot | Cannot show original items before first revision | Add parent header/details to `old_values` |
| `approval`/`rejection` currently shown as `Update` | Requirement mismatch | Change mapping |
| Before/after JSON can be large | UI table can become noisy | Show diff in expandable detail, not main table |
| Nested JSON diff can be complex | Slow implementation | Start with top-level diff and raw before/after; add specialized revision item diff |
| Existing frontend may depend on `actor` string and `detail` | Breaking UI | Add structured fields while keeping legacy aliases |

## 17. Bottom line
Current backend already stores enough data for requested audit logs:
- tanggal
- jam
- actor name/username source
- module source
- activity type source
- description source
- before/after JSON source

Needed implementation work:
- expose stored fields in audit list API
- fix activity type categorization for approval/rejection
- decode and return before/after changes
- add audit writes for user account management
- add audit writes for item/master barang management
- include parent transaction header/details for first stock revision submit
- add item-level diff formatter for stock revision details
- sync API contract docs from planned to implemented
