# End-to-End Flow: Daily Patient → SPK → Stock

> System flow for the hospital kitchen stock management — Pasien Harian → SPK (Surat Perintah Kerja) → Transaksi Masuk (IN) → Transaksi Keluar (OUT).
>
> Two SPK workflows exist: **BASAH** (fresh ingredients, daily patient-driven) and **KERING_PENGEMAS** (dry goods/packaging, monthly historical consumption-driven). Stock transactions support **IN**, **OUT**, **RETURN_IN**, and **OPNAME_ADJUSTMENT** types with a draft→submit workflow for BASAH OUT.

---

## Table of Contents

- [System Architecture](#system-architecture)
- [Step 1: Input Pasien Harian](#step-1--input-pasien-harian)
- [Step 2: Generate SPK Basah](#step-2--generate-spk-basah)
  - [Reads (6 tables)](#reads-6-tables)
  - [Target Dates](#target-dates)
  - [Calculation Per Item Across Dates](#calculation-per-item-across-dates)
  - [Duplicate Scope Guard](#duplicate-scope-guard)
  - [Writes (2 tables)](#writes-2-tables)
- [Step 2a: Generate SPK Kering & Pengemas (monthly)](#step-2a--generate-spk-kering--pengemas-monthly)
- [Step 2b: Override SPK (optional)](#step-2b--override-spk-optional)
- [Step 3: Lihat Prefill (read-only)](#step-3--lihat-prefill-read-only)
- [Step 4: Transaksi Masuk (Stock IN)](#step-4--transaksi-masuk-stock-in)
  - [Path A: Automated via SPK Post](#path-a-automated-via-spk-post)
  - [Path B: Manual IN](#path-b-manual-in)
  - [Writes (3-4 tables)](#writes-3-4-tables)
  - [Normalization & Unit Conversion](#normalization--unit-conversion)
- [Step 5: Transaksi Keluar (Stock OUT)](#step-5--transaksi-keluar-stock-out)
  - [Path A: BASAH OUT (Draft → Submit)](#path-a-basah-out-draft--submit)
  - [Path B: Non-BASAH OUT (Direct APPROVED)](#path-b-non-basah-out-direct-approved)
  - [Writes](#writes)
- [Step 6: Stock Opname (Physical Count)](#step-6--stock-opname-physical-count)
- [Step 7: Direct Corrections (Admin)](#step-7--direct-corrections-admin)
- [Step 8: Revisions](#step-8--revisions)
- [Table States: Before / After Each Step](#table-states-before--after-each-step)
- [Table Write Summary](#table-write-summary)
- [Complete Table Reference](#complete-table-reference)

---

## System Architecture

```
   ┌──────────────────────────────────────────────────────────────────┐
   │                       SEQUENCE DIAGRAM                           │
   └──────────────────────────────────────────────────────────────────┘

Step 1     Daily Patient Input          service_date, total_patients
                                                │
Step 2     SPK Basah Generation                 │
           ┌──────────────────────┐             │
           │ Reads:               │             │
           │  daily_patients      │◄────────────┘
           │  menu_schedules      │
           │  menu_dishes         │
           │  dish_compositions   │
           │  items               │
           │  item_categories     │
           └──────────┬───────────┘
                      │ writes
                      ▼
           spk_calculations  (1 header row, scope=combined_window)
           spk_recommendations (N rows, per item × target_date)
                      │
Step 2a    SPK Kering/Pengemas Gen  (monthly, reads prior OUT txns)
           ┌────────────────────────────┐
           │ Reads:                     │
           │  stock_transactions (OUT)  │
           │  stock_tx_details          │
           │  items                     │
           └──────────┬─────────────────┘
                      │ writes
                      ▼
           spk_calculations  (1 header row, scope=monthly, target_month)
           spk_recommendations (N rows, per item, target_date=NULL)
                      │
Step 2b              │ (optional) override
  Override            ▼ recommended_qty
           spk_recommendations (update in place)
                      │
Step 3               │ read-only prefill
  Prefill             ▼
           returns draft IN payload
                      │
                      │  Path A: POST /spk/.../post-stock
                      ├──────────────────────────┐
                      │                          │
                      ▼                          ▼
Step 4     Stock IN (auto)             Stock IN (manual)
           ┌─────────────────────┐    ┌──────────────────────┐
           │ Writes:             │    │ Writes:              │
           │  stock_transactions │    │  stock_transactions  │
           │  stock_tx_details   │    │  stock_tx_details    │
           │  items.qty (+)      │    │  items.qty (+)       │
           │  spk_calc.is_finish │    │                      │
           │  monthly_snapshot   │    │  monthly_snapshot    │
           └─────────────────────┘    └──────────────────────┘
                      │
                      │  Two OUT paths:
                      ├──────────────────────┐
                      │                      │
                      ▼                      ▼
Step 5     OUT BASAH (Draft)        OUT non-BASAH (Direct)
           ┌──────────────────┐    ┌──────────────────────┐
           │ status=PENDING   │    │ status=APPROVED      │
           │ no stock change  │    │ items.qty (-)        │
           │  ↓ submit ↓      │    │ monthly_snapshot     │
           │ status=APPROVED  │    └──────────────────────┘
           │ items.qty (-)    │
           │ monthly_snapshot │
           └──────────────────┘
```

**Key**: The `spk_id` FK on `stock_transactions` only tracks the procurement origin for IN transactions. OUT transactions do not reference the SPK.

---

## Step 1 — Input Pasien Harian

**Endpoint**: `POST /api/v1/daily-patients`
**Service**: `DailyPatientService::createDailyPatient()`
**Role**: `admin, gudang`
**Controller**: `DailyPatients::create()`

| READ | WRITE → `daily_patients` |
|---|---|
| — | `service_date` (UNIQUE) |
|   | `total_patients` (INT UNSIGNED) |
|   | `notes` (TEXT, optional) |
|   | `created_at` / `updated_at` |

**Guard**: duplicate `service_date` rejected — checked via `findByServiceDate()` + unique DB index.

**Update endpoint**: `PUT /api/v1/daily-patients/{id}` — same role, service, updates totals/notes for an existing patient record.

### daily_patients table schema

| Column | Type | Constraints |
|---|---|---|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT |
| `service_date` | DATE | NOT NULL, UNIQUE |
| `total_patients` | INT UNSIGNED | NOT NULL |
| `notes` | TEXT | NULL |
| `created_at` | DATETIME | NULL |
| `updated_at` | DATETIME | NULL |

---

## Step 2 — Generate SPK Basah

**Endpoint**: `POST /api/v1/spk/basah/generate`
**Service**: `SpkBasahGenerationService::generate()` → `SpkPersistenceService::createVersionedSpk()`
**Role**: `admin, dapur, gudang`
**Controller**: `SpkBasah::generate()`
**Preview endpoint**: `POST /api/v1/spk/basah/operational-stock-preview` — resolves menu projection, item requirements, and projected shortage for a given service_date + meal_time + total_patients without persisting any SPK data. Read-only calculation preview.
**Controller**: `SpkBasah::operationalStockPreview()`

Generates fresh-item (BASAH category) procurement recommendations based on menu plan + daily patient count.

### Reads (6 tables)

| Table | What for |
|---|---|
| `daily_patients` | `total_patients` where `service_date = {request}` |
| `menu_schedules` | all rows where `day_of_month = {target_date.day}` (can be >1 per day) |
| `menu_dishes` | `dish_id` where `menu_id` matches each scheduled menu |
| `dish_compositions` | `item_id`, `qty_per_patient` for each dish |
| `items` | `qty` (current stock), `item_category_id` |
| `item_categories` | resolve `BASAH` category id via `getIdByName()` |

### Target Dates

- `requestedDate` (primary)
- `requestedDate + 1 day` (only if same calendar month)

### Calculation Per Item Across Dates

The buffered patient count is computed once, shared across all dates:

```
patientCountWithBuffer = patients + ceil(patients × 0.05)
```

For each item per target date:

```
required_qty = ceil(qty_per_patient × patientCountWithBuffer)
```

**Stock carry-forward across dates**: Items are processed sequentially by date. Stock remaining from one date carries forward to the next:

```
For each item (sorted by item_id):
    remainingStock = initialStock (from items.qty at generation time)

    For each targetDate (chronological):
        rawRequired = requiredByDate[date][item_id]
        if rawRequired <= 0: skip

        requiredQty = round(rawRequired, 4)
        stockBeforeDay = remainingStock
        systemRecommended = max(0, requiredQty - stockBeforeDay)
        remainingStock = max(0, remainingStock - requiredQty)

        row = {
            current_stock_qty:      stockBeforeDay,
            required_qty:           requiredQty,
            system_recommended_qty: systemRecommended,
            recommended_qty:        systemRecommended  # == max(0, system_recommended)
        }
```

**Per-assignment independent processing**: Dishes from multiple menus on the same date are not deduplicated — each menu assignment contributes its ingredients independently:

- Same menu assigned twice → ingredients counted ×2
- Two different menus sharing a dish → each contributes that dish's ingredients separately

### Duplicate Scope Guard

`SpkPersistenceService::createVersionedSpk()` builds a `scope_key`:

```
basah|combined_window|{target_date_start}|{target_date_end}|{category_id}
```

If an unfinished (`is_finish = false`) SPK exists for the same `scope_key`, returns **HTTP 409** unless `regenerate=true` is sent.

When regenerating, the previous `is_latest` version is demoted to `false`, and a new version row is inserted.

### Writes (2 tables)

#### 1 row → `spk_calculations`

| Column | Example |
|---|---|
| `spk_type` | `basah` |
| `calculation_scope` | `combined_window` |
| `scope_key` | `basah\|combined_window\|2026-04-15\|2026-04-16\|1` |
| `version` | auto-inc per `scope_key` |
| `is_latest` | `true` (prev version set `false`) |
| `calculation_date` | service_date |
| `target_date_start` | first target date |
| `target_date_end` | last target date |
| `target_month` | NULL (BASAH uses dates, not month) |
| `daily_patient_id` | FK → `daily_patients.id` |
| `user_id` | who generated |
| `category_id` | BASAH category id |
| `estimated_patients` | patient count used |
| `is_finish` | `false` (set `true` after posting) |
| `created_at` / `updated_at` | timestamps |

#### N rows → `spk_recommendations` (one per `item_id` × `target_date`)

| Column | Description |
|---|---|
| `spk_id` | FK → `spk_calculations.id` |
| `item_id` | which ingredient |
| `target_date` | which day this requirement covers |
| `current_stock_qty` | DECIMAL(12,4), stock remaining before this day's deduction |
| `required_qty` | DECIMAL(12,4), raw calculated need for this date |
| `system_recommended_qty` | DECIMAL(12,4), before floor-to-zero |
| `recommended_qty` | DECIMAL(12,4), final = `system_recommended_qty` (never negative due to max(0)) |
| `is_overridden` | BOOLEAN, default `false` |
| `override_reason` | TEXT, NULL |
| `overridden_by` | BIGINT, FK → `users.id`, NULL |
| `overridden_at` | DATETIME, NULL |
| `created_at` / `updated_at` | timestamps |

> Note: All DECIMAL qty columns were widened from `(12,2)` to `(12,4)` in migration `WidenSpkRecommendationDecimalPrecision` for fractional precision during carry-forward.

---

## Step 2a — Generate SPK Kering & Pengemas (monthly)

**Endpoint**: `POST /api/v1/spk/kering-pengemas/generate`
**Service**: `SpkKeringPengemasGenerationService::generate()` → `SpkPersistenceService::createVersionedSpk()`
**Role**: `admin, dapur, gudang`
**Controller**: `SpkKeringPengemas::generate()`

Generates dry-goods and packaging procurement recommendations based on prior-month consumption patterns.

### Reads

| Table | What for |
|---|---|
| `stock_transactions` | APPROVED OUT transactions for the previous calendar month |
| `stock_transaction_details` | qty per item from those OUT transactions |
| `items` | current stock qty for KERING and PENGEMAS categories |
| `item_categories` | resolve KERING and PENGEMAS category ids |

### Target

- `target_month` (YYYY-MM format required) — generates recommendations for the upcoming month
- Reads OUT transactions from the **previous month** to calculate consumption

### Calculation Per Item

```
For each item in category (KERING or PENGEMAS):
    previousMonthUsage = sum of OUT qty for this item in prior month
    bufferedUsage = previousMonthUsage × 1.10        (10% buffer)
    currentStock = items.qty

    systemRecommended = ceil(max(bufferedUsage - currentStock, 0))
    recommended_qty   = systemRecommended
```

### Scope Key

```
kering_pengemas|monthly|{target_month}|{category_id}
```

### Writes

Same tables as Step 2, but:
- `target_date` in `spk_recommendations` is `NULL` (month-level, not per-date)
- `target_month` in `spk_calculations` is set
- `daily_patient_id` in `spk_calculations` is `NULL` (not patient-driven)
- scope is `monthly`, not `combined_window`

---

## Step 2b — Override SPK (optional)

**Endpoint**: `POST /api/v1/spk/basah/history/{id}/override`, `POST /api/v1/spk/kering-pengemas/history/{id}/override`
**Service**: `SpkOverrideService::overrideItem()`
**Role**: `admin, gudang, dapur`
**Controller**: `SpkBasah::overrideItem()`, `SpkKeringPengemas::overrideItem()`

Operates on a **single recommendation row** per call (not batch). Requires:
- `recommendation_id` — the specific `spk_recommendations.id`
- `recommended_qty` — new override value (non-negative)
- `reason` — required string

**Updates `spk_recommendations`** for the matching row:

| Column | New value |
|---|---|
| `recommended_qty` | user-specified override |
| `is_overridden` | `true` |
| `override_reason` | why |
| `overridden_by` | user id |
| `overridden_at` | timestamp |

**Guard**: Cannot override a finished SPK (`is_finish = true`). Returns **HTTP 403**.

---

## Step 3 — Lihat Prefill (read-only)

**Endpoint**: `GET /api/v1/spk/stock-in-prefill/{spkId}`
**Service**: `SpkStockInPrefillService::buildDraftFromSpk()`
**Role**: `admin, dapur, gudang`
**Controller**: `SpkStockInPrefill::show()`

**Reads** `spk_recommendations` → aggregates `recommended_qty` per `item_id` across all rows, excludes negative qty items → returns draft IN payload:

```json
{
  "type_name": "IN",
  "transaction_date": "2026-04-15",
  "spk_id": 42,
  "details": [
    { "item_id": 5,  "qty": 10500.0 },
    { "item_id": 12, "qty": 525.5 }
  ]
}
```

**Zero writes** — purely a UI convenience to feed into the Manual IN endpoint.

---

## Step 4 — Transaksi Masuk (Stock IN)

Two paths converge on the same service (`StockTransactionService::createTransaction()`). Both produce identical table writes, except Path A additionally sets `is_finish`.

### Path A: Automated via SPK Post

**Endpoint**: `POST /api/v1/spk/basah/history/{id}/post-stock`, `POST /api/v1/spk/kering-pengemas/history/{id}/post-stock`
**Service**: `SpkStockPostingService::post()` → `StockTransactionService::createTransaction()`
**Role**: `admin, gudang`

Internal flow:
1. Reads SPK header (`spk_calculations`) — validates exists, not already finished
2. Reads all `spk_recommendations` for the SPK
3. Aggregates `recommended_qty` per `item_id` across all target dates (sum)
4. Filters out items with qty ≤ 0
5. Constructs IN payload with `input_unit: "base"`
6. Calls `createTransaction()` inside a DB transaction
7. Sets `is_finish = true` on the SPK header
8. Audit log written

### Path B: Manual IN

**Endpoint**: `POST /api/v1/stock-transactions` with `{ "type_name": "IN", ... }`
**Service**: `StockTransactionService::createTransaction()` directly
**Role**: `admin, gudang`

### Writes (3-4 tables + snapshot)

All writes happen inside a single DB transaction.

#### 1 row → `stock_transactions`

| Column | Value |
|---|---|
| `type_id` | IN (resolved from `transaction_types`) |
| `transaction_date` | from request or SPK's `calculation_date` |
| `is_revision` | `false` |
| `parent_transaction_id` | `null` |
| `approval_status_id` | APPROVED (auto-approved, no workflow) |
| `approved_by` | `null` |
| `user_id` | who performed the action |
| `spk_id` | SPK id (nullable, for audit trail) |
| `reason` | nullable VARCHAR(255) |
| `created_at` / `updated_at` | timestamps |
| `deleted_at` | NULL (soft delete support) |

#### N rows → `stock_transaction_details` (one per item)

| Column | Value |
|---|---|
| `transaction_id` | FK → `stock_transactions.id` |
| `item_id` | which item |
| `qty` | DECIMAL(12,2), normalized qty |
| `input_qty` | original submitted qty (before normalization) |
| `input_unit` | `base` or `convert` |

#### `items.qty` — atomic increment

```sql
UPDATE items
SET qty = qty + {qty}, updated_at = NOW()
WHERE id = {item_id}
```

Unconditional increment — no stock check for IN.

#### `spk_calculations.is_finish` (Path A only)

```sql
UPDATE spk_calculations SET is_finish = true WHERE id = {spkId}
```

#### `monthly_stock_snapshots` — idempotent trigger

`StockSnapshotService::ensureOpeningSnapshot()` is triggered before the transaction to capture the opening stock snapshot for the transaction's month. Idempotent — skips if a snapshot already exists for that month.

**Snapshot trigger scope**: The snapshot is triggered by ALL stock-mutating paths, not only IN transactions:
- Manual IN (Path B)
- SPK Post IN (Path A)
- BASAH OUT draft creation (Step 5 Path A)
- Non-BASAH OUT direct (Step 5 Path B)
- Direct Corrections (Step 7)
- Revision approval (Step 8)
- Stock Opname posting (Step 6)

### Normalization & Unit Conversion

When `input_unit = "convert"`, the submitted qty is multiplied by `items.conversion_base`:
```
normalizedQty = inputQty × conversion_base
```
The normalized value is stored in `qty` and used for stock mutation. The original `input_qty` and `input_unit` are saved for audit.

---

## Step 5 — Transaksi Keluar (Stock OUT)

**Endpoint**: `POST /api/v1/stock-transactions` with `{ "type_name": "OUT", ... }`
**Service**: `StockTransactionService::createTransaction()`
**Role**: `admin, gudang`

OUT has two distinct paths depending on item category:

### Path A: BASAH OUT (Draft → Submit)

When all items in the OUT request belong to the BASAH category:

1. **Validation**:
   - Cannot mix BASAH and non-BASAH items (HTTP 400)
   - One active non-REJECTED OUT per `transaction_date` for BASAH (HTTP 409 if exists)
   - Stock sufficiency is NOT checked at draft creation

2. **Draft creation** (PENDING status, no stock mutation):
   - `approval_status_id` = PENDING
   - No stock decrement
   - Draft can be updated (PUT), submitted (POST), or canceled (POST) via dedicated lifecycle endpoints
   - `monthly_stock_snapshots` triggered (idempotent)

3. **Draft lifecycle endpoints** (role: `admin, gudang`):
   - `PUT /api/v1/stock-transactions/{id}` — `updateDraft()`: replace details (delete+reinsert)
   - `POST /api/v1/stock-transactions/{id}/submit` — `submitDraft()`: atomic stock decrement + set APPROVED
   - `POST /api/v1/stock-transactions/{id}/cancel` — `cancelDraft()`: set REJECTED (no stock change)

4. **Submit flow**:
   - Locks transaction row with `FOR UPDATE`
   - Atomic conditional decrement per item: `UPDATE items SET qty = qty - {qty} WHERE id = {id} AND qty >= {qty}`
   - If any item has insufficient stock, entire transaction is rolled back
   - Sets `approval_status_id` = APPROVED, `approved_by` = submitter
   - Triggers `queueMinStockNotificationIfNeeded()` per item
   - Flushes queued MIN_STOCK notifications after successful commit

### Path B: Non-BASAH OUT (Direct APPROVED)

When items are not BASAH (e.g., KERING, PENGEMAS categories):

1. **Validation**: Pre-checks `items.qty >= requested_qty` for each detail
2. **Creates immediately APPROVED transaction** with stock decrement
3. Same atomic conditional decrement as submit: `WHERE qty >= {qty}`
4. Triggers min-stock notifications

### Writes

| Action | Tables Written |
|---|---|
| Draft creation | `stock_transactions`, `stock_transaction_details`, `monthly_stock_snapshots` |
| Draft submit | `items.qty` (conditional decrement), `stock_transactions.approval_status_id` |
| Draft cancel | `stock_transactions.approval_status_id` → REJECTED |
| Direct non-BASAH OUT | `stock_transactions`, `stock_transaction_details`, `items.qty`, `monthly_stock_snapshots` |

**Atomic SQL guard** for stock decrement (both submit and direct OUT):
```sql
UPDATE items
SET qty = qty - {qty}, updated_at = NOW()
WHERE id = {item_id} AND qty >= {qty}
```
If `affectedRows < total items`, the entire transaction is rolled back.

---

## Step 6 — Stock Opname (Physical Count)

**Endpoints**: managed under `StockOpnames` controller
**Role**: `admin, gudang` (create/update/submit), `admin` (approve/reject/post)

Stock opname provides a physical inventory count workflow:

1. **Create**: `POST /api/v1/stock-opnames` — creates an opname record with expected quantities
2. **Update**: `PUT /api/v1/stock-opnames/{id}` — update draft opname details
3. **Submit**: `POST /api/v1/stock-opnames/{id}/submit` — submit for approval
4. **Approve**: `POST /api/v1/stock-opnames/{id}/approve` — admin approval
5. **Reject**: `POST /api/v1/stock-opnames/{id}/reject` — admin rejection
6. **Post**: `POST /api/v1/stock-opnames/{id}/post` — creates **OPNAME_ADJUSTMENT** stock transactions:
   - Each item with `actual_qty ≠ expected_current_qty` generates one `OPNAME_ADJUSTMENT` transaction
   - Uses optimistic locking (`WHERE qty = expected_current_qty`) to detect concurrent changes
   - If delta is positive (actual > expected) → behaves as stock increase
   - If delta is negative (actual < expected) → behaves as stock decrease
   - Each gets a separate stock_transaction row with reason linking to opname

---

## Stock Snapshot Status

**Endpoint**: `GET /api/v1/stock-snapshots/current`
**Controller**: `StockSnapshots::current()`
**Role**: `admin, dapur, gudang`

Returns the current monthly stock snapshot status, showing which period's opening snapshot is active and whether it has been taken.

| READ |
|---|
| `monthly_stock_snapshots` |

---

## Step 7 — Direct Corrections (Admin)

**Endpoint**: `POST /api/v1/stock-transactions/direct-corrections`
**Service**: `StockTransactionService::createDirectCorrection()`
**Role**: `admin` only

Single-item stock correction with optimistic locking:

**Payload fields**: `transaction_date`, `item_id`, `expected_current_qty`, `target_qty`, `reason`
- Derives transaction type from delta: `target_qty - expected_current_qty` (positive = IN, negative = OUT)
- Optimistic lock: `UPDATE items SET qty = {target_qty} WHERE id = {item_id} AND qty = {expected_current_qty}`
- If current stock no longer matches `expected_current_qty`, returns **HTTP 400**
- Creates 1 `stock_transaction` + 1 `stock_transaction_detail` with the absolute delta
- Transaction is immediately APPROVED
- Requires `reason` (1-255 chars)

---

## Step 8 — Revisions

**Endpoint**: `POST /api/v1/stock-transactions/{id}/submit-revision`
**Service**: `StockTransactionService::submitRevision()`
**Role**: `admin, gudang`

Only APPROVED transactions can be revised. The revision workflow allows correcting quantities of a previously finalized transaction.

**Flow**:
1. Submit revision → creates new PENDING revision transaction with `is_revision = true`, `parent_transaction_id` linking to the original, and the corrected details
2. If a pending revision already exists for the same parent, it is updated in-place (resubmission)
3. Admin reviews and either approves or rejects:
   - **Approve** (`POST /api/v1/stock-transactions/{id}/approve`, role: `admin`): Calculates the signed qty delta between the baseline (latest approved revision or original) and the new revision per item, then applies delta to `items.qty` using `applySignedItemDelta()`. Direction depends on transaction type (IN = +1, OUT = -1). Notification sent to revision creator.
   - **Reject** (`POST /api/v1/stock-transactions/{id}/reject`, role: `admin`): Marks revision as REJECTED, no stock mutation. Optional `reason` field (VARCHAR 255). Notification sent to revision creator.

Revisions are single-level only (cannot revise a revision). The original parent retains its data; the revision replaces it as the effective record.

## Table States: Before / After Each Step

```
                       daily_patients         spk_calculations     spk_recommendations     stock_transactions   stock_tx_details    items.qty      monthly_snapshots
                       ───────────────         ────────────────     ──────────────────      ─────────────────   ─────────────────   ─────────      ─────────────────
Step 1 (Daily Patient) → service_date=15       (empty)              (empty)                  (empty)             (empty)             5.0            (empty)
                        total_patients=100

Step 2 (SPK Gen)                                    id=1                spk_id=1               (empty)             (empty)             5.0            (empty)
                                                     is_finish=0         item_id=5
                                                                         target_date=15
                                                                         required_qty=10000
                                                                         recommended_qty=9995
                                                                         current_stock_qty=5

Step 4 (IN via Post)                            id=1                (unchanged)               id=1                tx_id=1              5.0 → 10000.0  period_month=2026-04
                                                 is_finish=1                                  spk_id=1            item_id=5                               opening_qty=5
                                                                                              type_id=IN          qty=9995
                                                                                              status=APPROVED

Step 5a (OUT BASAH draft)                       (unchanged)         (unchanged)               id=1, id=2          tx_id=1, tx_id=2    10000.0        (unchanged)
                                                                                                                   item_id=5           (no change)
                                                                                                                   qty=5000

Step 5a (OUT BASAH submit)                      (unchanged)         (unchanged)               id=2 → APPROVED     (unchanged)          10000.0→5000.0 (unchanged)
                                                                                              status=PENDING
                                                                                                → APPROVED

Step 5b (OUT non-BASAH direct)                  (unchanged)         (unchanged)               id=2                tx_id=2              same as above  period_month=2026-04
                                                                                              status=APPROVED     item_id=5
                                                                                                                  qty=5000
```

---

## Table Write Summary

| Action | Tables Written | Type of Write |
|---|---|---|
| Create Daily Patient | `daily_patients` | INSERT |
| Update Daily Patient | `daily_patients` | UPDATE |
| Generate SPK Basah | `spk_calculations` | INSERT |
|   | `spk_recommendations` | INSERT (batch per item×date) |
| Generate SPK Kering/Pengemas | `spk_calculations` | INSERT |
|   | `spk_recommendations` | INSERT (batch per item, target_date=NULL) |
| Override SPK | `spk_recommendations` | UPDATE (per item) |
| Prefill (read-only) | — | none |
| Transaksi Masuk (IN) via SPK Post | `stock_transactions` | INSERT |
|   | `stock_transaction_details` | INSERT (batch) |
|   | `items.qty` | UPDATE (increment) |
|   | `spk_calculations.is_finish` | UPDATE |
|   | `monthly_stock_snapshots` | INSERT (idempotent) |
| Transaksi Masuk (IN) manual | `stock_transactions` | INSERT |
|   | `stock_transaction_details` | INSERT (batch) |
|   | `items.qty` | UPDATE (increment) |
|   | `monthly_stock_snapshots` | INSERT (idempotent) |
| Transaksi Keluar (OUT) BASAH draft | `stock_transactions` | INSERT (status=PENDING) |
|   | `stock_transaction_details` | INSERT (batch) |
|   | `monthly_stock_snapshots` | INSERT (idempotent) |
| Update OUT BASAH draft | `stock_transaction_details` | DELETE + INSERT |
| Submit OUT BASAH draft | `items.qty` | UPDATE (conditional decrement) |
|   | `stock_transactions.approval_status_id` | UPDATE (PENDING→APPROVED) |
| Cancel OUT BASAH draft | `stock_transactions.approval_status_id` | UPDATE (PENDING→REJECTED) |
| Transaksi Keluar (OUT) non-BASAH | `stock_transactions` | INSERT (status=APPROVED) |
|   | `stock_transaction_details` | INSERT (batch) |
|   | `items.qty` | UPDATE (conditional decrement) |
|   | `monthly_stock_snapshots` | INSERT (idempotent) |
| Direct Correction (admin) | `items.qty` | UPDATE (conditional overwrite) |
|   | `stock_transactions` | INSERT (status=APPROVED) |
|   | `stock_transaction_details` | INSERT |
| Stock Opname CRUD | `stock_opnames`, `stock_opname_details` | INSERT/UPDATE |
| Stock Opname Post | `stock_transactions` | N inserts (OPNAME_ADJUSTMENT) |
|   | `stock_transaction_details` | N inserts |
|   | `items.qty` | UPDATE (conditional overwrite) |
| Submit Revision | `stock_transactions` | INSERT (status=PENDING, is_revision) |
|   | `stock_transaction_details` | INSERT or DELETE+INSERT |
| Approve Revision (admin) | `items.qty` | UPDATE (signed delta from baseline) |
|   | `stock_transactions.approval_status_id` | UPDATE (PENDING→APPROVED) |
| Reject Revision (admin) | `stock_transactions.approval_status_id` | UPDATE (PENDING→REJECTED, no stock change) |

---

## Complete Table Reference

| Table | Created In Migration | Row Granularity | Written In | Read In |
|---|---|---|---|---|
| `daily_patients` | `CreateDailyPatients` | 1 row per `service_date` | Step 1 | Steps 2, 2a (indirectly) |
| `menu_schedules` | `CreateMenuSchedules` | 1 row per `(day_of_month, menu_id)` | Setup | Step 2 |
| `menu_dishes` | `CreateMenuDishes` | 1 row per `(menu_id, meal_time_id, dish_id)` | Setup | Step 2 |
| `dish_compositions` | `CreateDishCompositions` | 1 row per `(dish_id, item_id)` | Setup | Step 2 |
| `items` | `CreateItems` | 1 row per item | Steps 4, 5, 6, 7, 8 (qty mutation) | Steps 2, 2a, 4, 5, 6, 7 |
| `item_categories` | `CreateItemCategories` | 1 row per category | Setup | Steps 2, 2a, 5 |
| `item_units` | `CreateItemUnits` | 1 row per unit | Setup | Read |
| `spk_calculations` | `CreateSpkPersistenceTables` | 1 header per SPK version | Steps 2, 2a, 4 (is_finish) | Steps 2b, 3, 4 |
| `spk_recommendations` | `CreateSpkPersistenceTables` | 1 row per `(spk, item, [target_date])` | Steps 2, 2a, 2b (override) | Steps 3, 4 |
| `stock_transactions` | `CreateStockTransactions` | 1 header per transaction | Steps 4, 5, 6, 7, 8 | All steps |
| `stock_transaction_details` | `CreateStockTransactionDetails` | 1 row per `(tx, item)` | Steps 4, 5, 6, 7, 8 | All steps |
| `transaction_types` | `CreateTransactionTypes` | IN / OUT / RETURN_IN / OPNAME_ADJUSTMENT | Setup | Steps 4, 5, 6, 7 |
| `approval_statuses` | `CreateApprovalStatuses` | APPROVED / PENDING / REJECTED | Setup | Steps 4, 5, 6, 8 |
| `monthly_stock_snapshots` | `CreateMonthlyStockSnapshots` | 1 row per `(period_month, item_id)` | Steps 4, 5 (idempotent) | Reports |
| `stock_opnames` | `CreateStockOpnames` | 1 header per opname | Step 6 | Step 6 |
| `stock_opname_details` | `CreateStockOpnames` | 1 row per `(opname, item)` | Step 6 | Step 6 |
| `notifications` | `CreateNotifications` | 1 row per notification | Steps 5, 6, 7 (min-stock alert) | Notifications controller |
| `audit_logs` | `CreateAuditLogs` | 1 row per auditable action | All steps | Audit controller |
| `settings` | `CreateSettingsTable` | 1 row per key | Setup | App config |
