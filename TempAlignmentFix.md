# Temp Alignment Fix — SDK Frontend Analysis & BASAH Bug Status

> Analysis date: 2026-06-26
> Scope: `Capstone/frontend/` (SDK library) ↔ `gudang-app/` (Next.js app) ↔ `Capstone/FLOW.md` (spec) ↔ `anal.md` (bug report)

---

## 1. SDK_MAP.md vs Actual SDK — Cross-Reference

**Method**: Every SDK resource class read, every type file read, every SDK_MAP.md row verified.

| Result | Count | Detail |
|--------|-------|--------|
| **OK** | 108/114 | Method name, return type, endpoint path all match |
| **MISMATCH** | 3 | `updateDraft`, `submitDraft`, `cancelDraft` — code calls `this.client.request(...)` without generic type arg `<ApiMessageDataResponse<StockTransactionCreateResult>>`. Return type annotation correct but internal inference unchecked. Cosmetic, no runtime impact. |
| **MISSING** | 0 | Every SDK_MAP.md method exists in actual resource class |
| **EXTRA** | 3 | `StockSnapshotsResource` complete (`.list()`, `.take()`, `.current()`) not in SDK_MAP.md |

### Fix: SDK_MAP.md — Add `stockSnapshots` section

Insert after Stock Opnames section (before Transaction Types):

```markdown
| **Stock Snapshots** | | |
| `GET /api/v1/stock-snapshots` | `sdk.stockSnapshots.list(query?)` | `ApiListResponse<StockSnapshotRow>` |
| `POST /api/v1/stock-snapshots/take` | `sdk.stockSnapshots.take(request?)` | `CreateSnapshotResponse` |
| `GET /api/v1/stock-snapshots/current` | `sdk.stockSnapshots.current()` | `CurrentSnapshotStatus` |
```

### Fix: 3 Draft Methods — Add missing generic type arg

**Files**: `Capstone/frontend/src/sdk/resources/stockTransactions.ts` and `gudang-app/src/sdk/resources/stockTransactions.ts` (both copies)

```typescript
// updateDraft (line ~197)
return this.client.request<ApiMessageDataResponse<StockTransactionCreateResult>>({
// Was: this.client.request({

// submitDraft (line ~213)
return this.client.request<ApiMessageDataResponse<StockTransactionCreateResult>>({
// Was: this.client.request({

// cancelDraft (line ~227)
return this.client.request<ApiMessageDataResponse<StockTransactionCreateResult>>({
// Was: this.client.request({
```

**Verdict**: SDK_MAP.md 94.7% accurate. Only real gap: `stockSnapshots` undocumented.

---

## 2. Frontend SDK Usage — Actually Called vs Available

### Methods Called (by app at `gudang-app/`)

| Resource | Methods | Call Sites |
|---|---|---|
| `spk` | `operationalStockPreview`, `listBasah`, `getBasah`, `generateBasah`, `listKeringPengemas`, `getKeringPengemas`, `generateKeringPengemas` | ~25 |
| `stockTransactions` | `list`, `get`, `details`, `create`, `submitRevision`, `approve`, `reject` | ~15 |
| `stockOpnames` | `create`, `submit`, `approve`, `post`, `get`, `reject` | ~10 |
| `items` | `list`, `create`, `update`, `delete` | ~10 |
| `users` | `list`, `create`, `update`, `changePassword`, `activate`, `deactivate`, `delete` | ~8 |
| `menus` | `slots`, `list`, `assignSlot`, `updateSlot`, `deleteSlot` | ~8 |
| `dishCompositions` | `create`, `update`, `delete` | ~8 |
| `itemCategories` | `list`, `create`, `update`, `delete` | ~8 |
| `dishes` | `create`, `update` | ~6 |
| `reports` | `getStocks`, `getTransactions`, `getSpkHistory` | ~6 |
| `itemUnits` | `list`, `create`, `update`, `delete` | ~6 |
| `notifications` | `list`, `markAllAsRead`, `markAsRead`, `deleteAll` | ~5 |
| `menuSchedules` | `calendarProjection` | ~3 |
| `mealTimes` | `list` | ~3 |
| `dashboard` | `getAggregate` | ~2 |
| `dailyPatients` | `create` | ~2 |
| `roles` | `list` | ~1 |
| `transactionTypes` | `list` | ~2 |
| `approvalStatuses` | `list` | ~2 |

### SDK Methods Unused (Available but Zero Call Sites)

| Resource | Unused Methods |
|---|---|
| `spk` | `basahMenuCalendar`, `keringPengemasMenuCalendar`, `overrideBasah`, `overrideKeringPengemas`, `postBasahStock`, `postKeringPengemasStock`, **`stockInPrefill`** |
| `stockTransactions` | **`updateDraft`**, **`submitDraft`**, **`cancelDraft`**, **`directCorrection`** |
| `dishes` | `list`, `get`, `deactivate`, `reactivate`, `delete` |
| `auth` | `login`, `me`, `logout`, `changePassword` |
| `auditLogs` | All methods |
| `stockSnapshots` | All methods |
| `reports` | `getEvaluation`, `getMonthlyStockExport` |
| `dailyPatients` | `list`, `get`, `update` |
| `users` | `get`, `restore` |
| `items` | `get`, `restore` |
| `itemCategories` | `get`, `restore` |

---

## 3. Architectural Misalignments

### 3a. BASAH OUT Uses Direct APPROVED (Path B) Instead of Draft→Submit (Path A)

**FLOW.md spec (Step 5 Path A)**: BASAH OUT should use draft→submit lifecycle.
1. `POST /stock-transactions` with BASAH items → creates PENDING draft (no stock change)
2. `POST /stock-transactions/{id}/submit` → atomic stock decrement, status→APPROVED
3. `POST /stock-transactions/{id}/cancel` → status→REJECTED (no stock change)

**Actual frontend (both gudang & super-admin `transaksi/keluar/page.tsx`)**:
```typescript
await sdk.stockTransactions.create({
  type_name: "OUT",
  transaction_date: serviceDate,
  details,
});
```
This hits **Path B** (direct APPROVED) — stock decremented immediately, no draft state.

**Impact**: 
- No separation between draft creation and submission
- No opportunity for admin to review pending OUT transactions
- Draft lifecycle SDK methods (`updateDraft`, `submitDraft`, `cancelDraft`) entirely unused
- One active non-REJECTED OUT per transaction_date guard (FLOW.md §5 Path A validation #2) is never triggered

**Fix**: Convert BASAH OUT to use draft→submit flow:
1. `POST /stock-transactions` with `type_name: "OUT"`, BASAH items → backend returns PENDING transaction ID (already happens per FLOW.md Path A, but frontend ignores this because it uses direct path)
2. After user confirms save → call `sdk.stockTransactions.submitDraft(id)`

**But**: This changes the UX significantly. Current frontend validates rows + saves in one action. Draft→submit would require either:
- Two-step UI (create draft → then submit), or
- Auto-submit after draft creation (defeats the purpose of draft)

**Recommendation**: Keep as-is unless admin review workflow for OUT transactions is a real requirement. The frontend pattern (direct APPROVED) is simpler and the draft lifecycle only matters if there's a PENDING→APPROVED review step needed.

### 3b. `stockInPrefill` SDK Method Unused

**SDK provides**: `sdk.spk.stockInPrefill(spkId)` → returns pre-built IN payload with aggregated `recommended_qty` per item, excluding negative qty items.

**Frontend does instead**: `sdk.spk.getBasah(id)` / `sdk.spk.getKeringPengemas(id)`, then manually maps `items` array to build IN payload.

**Impact**: Frontend duplicates server-side aggregation logic. The server-side `stockInPrefill` endpoint is tested and correct per FLOW.md Step 3.

**Fix**: Replace in `transaksi/masuk/page.tsx`:
```typescript
// Before (manual prefill):
const detailResponse = activeTab === "basah"
  ? await sdk.spk.getBasah(selectedSpkId)
  : await sdk.spk.getKeringPengemas(selectedSpkId);
details = normalizePrefillDetails(detailResponse);

// After (use dedicated endpoint):
const prefill = await sdk.spk.stockInPrefill(selectedSpkId);
details = prefill.data.details;
```

### 3c. Dish Deactivate/Reactivate Bypasses SDK

**3 management pages** (`AdminMenuManagementPage.tsx`, `GiziMenuManagementPage.tsx`, `MenuManagementPage.tsx`) call:
```typescript
await sdk.client.request({
  method: "PATCH",
  path: `/dishes/${selectedMenu.id}/reactivate`,
});
```

**SDK provides**: `sdk.dishes.reactivate(id)` and `sdk.dishes.deactivate(id)`

**Fix**: Replace raw calls with SDK methods:
```typescript
// Before:
await sdk.client.request({ method: "PATCH", path: `/dishes/${id}/reactivate` });

// After:
await sdk.dishes.reactivate(id);
```

---

## 4. BASAH Deadlock Bug Status (anal.md vs Actual Code)

### anal.md Claimed Bugs

| # | Issue | Original Code | Status |
|---|---|---|---|
| Bug 1 | `setValidatedRows([])` empties rows after preview | `prepareRecommendationPreview()` line ~226 | **INTENTIONAL** — popup uses separate `recommendationRows` state |
| Bug 2 | `useEffect` doesn't restore `validatedRows` from localStorage | Missing `setValidatedRows(...)` | **FIXED** — both pages now have line 139/136 |
| Root Cause | `basahValidationLocked = Boolean(savedRecommendation)` locks immediately | Guard deadlocks after preview | **FIXED** — now `Boolean(savedRecommendation?.submittedAt ?? savedRecommendation?.submitted)` |

### Current Code (Verified Working)

**Gudang page** (`gudang-app/src/app/(dashboard)/gudang/transaksi/keluar/page.tsx`):
- Line 85: `basahValidationLocked = Boolean(savedRecommendation?.submittedAt ?? savedRecommendation?.submitted)` — only locks after save ✓
- Line 139: `setValidatedRows(saved.rows.map((row) => ({ ...row })))` — restores rows on serviceDate change ✓
- Line 251: `setValidatedRows([])` — intentional, popup uses `recommendationRows` ✓

**Super-admin page** (`gudang-app/src/app/(dashboard)/super-admin/transaksi/keluar/page.tsx`):
- No `basahValidationLocked` variable (uses different guard)
- Line 136: `setValidatedRows(saved.rows.map((row) => ({ ...row })))` ✓
- Line 248: `setValidatedRows([])` — same intentional design ✓

### Current Working Flow

```
1. Input pasien → klik Simpan
   → prepareRecommendationPreview()
   → sdk.spk.operationalStockPreview() (per meal_time)
   → sdk.dailyPatients.create({ service_date, total_patients })
   → saves to localStorage ✓
   → setSavedRecommendation({..., submitted: false})   // not locked
   → setValidatedRows([])                                // popup only
   → setRecommendationRows(aggregated)                   // popup rows
   → show popup

2. Close popup
   → validatedRows = [] (intentional)
   → savedRecommendation exists, submitted = false

3. Klik Validasi
   → basahValidationLocked = Boolean(undefined) = false  ← PASSES ✓
   → loadValidatedBasahFromSavedRecommendation()
   → reads localStorage → setValidatedRows(saved.rows)
   → Table populated ✓

4. Edit qty_actual → klik Simpan
   → validatedRows.length > 0 ← PASSES ✓
   → sdk.stockTransactions.create({ type_name: "OUT", ... })
   → setSavedRecommendation({...saved, submitted: true})  // locked for next visit
```

**Deadlock does not exist.** The `basahValidationLocked` fix and `useEffect` fix resolved both root causes.

### Remaining UX Observation

`setValidatedRows([])` at line 251 is intentional (separates popup table from main table), but creates mandatory extra click: **Simpan → close popup → Validasi → edit → Simpan**. The first "Simpan" button click triggers preview, only second "Simpan" saves transaction. This is by design per UI text: "Simpan rekomendasi harian terlebih dahulu, lalu gunakan tombol validasi untuk mengisi tabel bahan basah."

---

## 5. FLOW.md vs Frontend Implementation Gaps

| FLOW.md § | Spec | Actual Frontend | Gap |
|---|---|---|---|
| Step 5 Path A | BASAH OUT uses draft→submit (PENDING → APPROVED) | Direct APPROVED via `sdk.stockTransactions.create({ type_name: "OUT" })` | No draft lifecycle, no PENDING state |
| Step 5 Path A #2 | One active non-REJECTED OUT per transaction_date guard | Never hit — frontend creates one transaction, always directly APPROVED | Guard ineffective |
| Step 5 Path A #3 | Draft can be updated, submitted, canceled | `updateDraft`, `submitDraft`, `cancelDraft` SDK methods exist but never called | Draft lifecycle not wired |
| Step 3 | Prefill endpoint provides aggregated IN payload | `stockInPrefill` SDK method exists but unused | Frontend manually builds IN payload |
| Step 2b | Override SPK via `overrideBasah`/`overrideKeringPengemas` | SPK override UI not built in frontend | SDK methods unused |
| Step 4 Path A | Post stock via `postBasahStock`/`postKeringPengemasStock` | SPK post-stock UI not built in frontend | SDK methods unused |

---

## 6. Fix Priority Summary

| Priority | Fix | Impact | Effort |
|---|---|---|---|
| **P0** | None — SDK_MAP.md 94.7% accurate; BASAH deadlock already fixed | — | — |
| **P1** | Add `stockSnapshots` to SDK_MAP.md | Documentation gap closed | 5 min |
| **P2** | Add generic type arg to `updateDraft`/`submitDraft`/`cancelDraft` | Type safety | 5 min |
| **P2** | Replace `sdk.client.request({PATCH, path})` with `sdk.dishes.deactivate/reactivate` | Pattern consistency | 15 min |
| **P3** | Use `sdk.spk.stockInPrefill(id)` in transaksi/masuk page | Correctness (server-side aggregation) | 30 min |
| **P3** | Wire SPK override UI using `sdk.spk.overrideBasah`/`overrideKeringPengemas` | Feature parity | 1-2 days |
| **P4** | Convert BASAH OUT to draft→submit (Path A) per FLOW.md | Spec compliance | 2-3 days (blocks: UX redesign needed) |

---

## Appendix: Key Files Referenced

| File | Role |
|---|---|
| `Capstone/frontend/src/sdk/SDK_MAP.md` | SDK method ↔ endpoint map |
| `Capstone/frontend/src/sdk/resources/stockTransactions.ts` | StockTransactions SDK resource |
| `Capstone/frontend/src/sdk/resources/spk.ts` | SPK SDK resource |
| `Capstone/frontend/src/sdk/resources/menuSchedules.ts` | MenuSchedules SDK resource |
| `gudang-app/src/sdk/` | Runtime SDK copy (identical) |
| `gudang-app/src/app/(dashboard)/gudang/transaksi/keluar/page.tsx` | Gudang BASAH OUT page |
| `gudang-app/src/app/(dashboard)/super-admin/transaksi/keluar/page.tsx` | Super-admin BASAH OUT page |
| `gudang-app/src/app/(dashboard)/super-admin/transaksi/masuk/page.tsx` | Stock IN (transaksi masuk) page |
| `gudang-app/src/lib/index.ts` | SDK instantiation entry |
| `Capstone/FLOW.md` | System flow specification |
| `anal.md` | Original BASAH deadlock analysis |
