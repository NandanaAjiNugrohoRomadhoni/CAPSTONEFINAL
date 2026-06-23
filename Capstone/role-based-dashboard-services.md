# Role-Based Dashboard Services — Implementation Plan

> **Status:** Draft (revised after council review)  
> **Target:** Backend `DashboardAggregateService` refactor + Frontend consumption optimization  
> **Estimate:** 4-6 days total (backend 2-2.5d, frontend 2d, cleanup/verify 1-1.5d)

---

## 1. Current State

### Backend (`DashboardAggregateService`)
- 7 aggregate builders querying 10 tables (`items`, `item_categories`, `stock_transactions`, `stock_transaction_details`, `daily_patients`, `menus`, `menu_schedules`, `menu_dishes`, `dishes`, `dish_compositions`, `spk_calculations`)
- Role-conditioned dispatch: admin gets 6 keys, gudang 5, dapur 5
- OpenAPI schemas in `DashboardSchemas.php`
- Response envelope: `{ data: { role, generated_at, aggregates } }`

### Frontend (gudang-app)
- Admin: 6 API calls in 2-phase waterfall
- Gudang/Dapur: 9 API calls each via `OperationalDashboardPage` with mode branching
- **6 of 9 calls redundant** — data exists in aggregates or could be computed server-side
- Client-side computations: stock tones (`getStockTone()`), patient deltas, trend stats, ingredient requirements, OUT transaction enrichment
- SDK types all `?unknown` — no contract safety

### Known Gaps
1. Stock health only computed for KERING — BASAH/PENGEMAS missing per-category status
2. `spending_trend` computed but never displayed on any dashboard card
3. N+1 SPK detail fetches: aggregate returns IDs → frontend fires individual `getBasah(id)` / `getKeringPengemas(id)`
4. Full `dishCompositions` + `menuSlots` paginated fetches for one menu's ingredient requirement
5. Patient data redundancy: aggregate returns last 7 days + frontend fetches all dailyPatients as fallback
6. No pending actions summary: pending stock opnames, unposted SPKs, unread notifications
7. No notification data on dashboard
8. Stock tone logic client-side only — no backend equivalent, risk of inconsistency

---

## 2. Target Architecture

### Backend Service Layer

Kept flat in `app/Services/` — matches codebase convention (28 files, no subdirectories). Ref `ReportingService` (32KB), `StockTransactionService` (65KB) for pattern.

```
DashboardAggregateService
├── existing methods preserved + enhanced with:
│   ├── buildStockSummary()          ← +by_category, +tone_summary, +stock_alerts
│   ├── buildDryStockStatus()        ← unchanged
│   ├── buildSpendingTrend()         ← relocated from inline, preserved format
│   ├── buildPatientFluctuation()    ← +delta, +patient_fluctuation_meta
│   ├── buildCurrentMenuCycle()      ← +top_shortages, ingredient totals
│   ├── buildCurrentMenuComposition()← unchanged
│   ├── buildLatestSpkHistory()      ← +summary_items inline
│   └── buildTodayOutgoing()         ← new, gudang-specific
│
└── PendingActionCounter             ← new helper for cross-table pending counts
    └── delegates unread count to NotificationService::countUnread()
```

Note: No separate `MenuAggregateBuilder`, `SpkAggregateBuilder`, `TransactionAggregateBuilder`, or `PatientAggregateBuilder` classes. All aggregate logic stays as methods on `DashboardAggregateService`. Only extracted files:
- `PendingActionCounter.php` — cross-table pending counts (avoids bloating the service)
- `StockToneCalculator.php` — tone CASE logic in reusable constants/helpers (optional, can stay inline)

### Principle: Ship computed data, not raw rows
- Stock tones computed server-side via SQL `CASE` with config-driven thresholds
- Patient deltas, trend direction, and stats computed in SQL window functions or PHP
- SPK summaries (top 3 recommendation items) inlined — no N+1
- Menu ingredient requirements computed server-side (dish_composition × patient_count)

### Error Isolation
Each builder call is wrapped in try/catch. If one builder fails, its aggregate key is `null` and the remaining aggregates still return. This prevents a single query failure from blanking the entire dashboard.

### Endpoint Strategy
- `GET /api/v1/dashboard` — primary, enhanced with new keys, backward compatible
- `GET /api/v1/dashboard/reports` — optional sub-endpoint for heavy report data (if main dashboard grows too large)

---

## 3. Builder Specifications

### 3.1 Stock Aggregate Methods (REFACTOR existing)
**Tables:** `items`, `item_categories`

**Returns:**
```php
[
    'stock_summary' => [
        'total_items'     => 45,
        'active_items'    => 42,
        'zero_stock_items' => 3,
        'total_stock_qty' => 12500.00,
        // NEW: per-category breakdown
        'by_category' => [
            ['category' => 'BASAH',    'total' => 18, 'active' => 17, 'zero' => 1, 'qty' => 4500.00],
            ['category' => 'KERING',   'total' => 15, 'active' => 14, 'zero' => 2, 'qty' => 5000.00],
            ['category' => 'PENGEMAS', 'total' => 12, 'active' => 11, 'zero' => 0, 'qty' => 3000.00],
        ],
        // NEW: tone breakdown
        'tone_summary' => [
            'safe'     => 30,
            'warning'  => 8,
            'critical' => 4,
            'danger'   => 3,
        ],
    ],
    'dry_stock_status' => [
        'status'          => 'KRITIS',  // 'AMAN'|'KRITIS'
        'total_items'     => 15,
        'zero_stock_items' => 2,
        'total_stock_qty' => 5000.00,
    ],
    'stock_alerts' => [
        'total_critical' => 4,
        'total_danger'   => 3,
        'items' => [
            ['item_id' => 7, 'item_name' => 'Telur', 'category' => 'BASAH',
             'qty' => 0.5, 'unit' => 'kg', 'min_stock' => 10, 'tone' => 'danger'],
        ],
    ],
]
```

**Key SQL:**
```sql
-- Per-category breakdown
SELECT ic.name AS category_name,
       COUNT(*) AS total,
       SUM(CASE WHEN i.is_active = 1 THEN 1 ELSE 0 END) AS active,
       SUM(CASE WHEN i.qty <= 0 THEN 1 ELSE 0 END) AS zero,
       COALESCE(SUM(i.qty), 0) AS qty
FROM items i
JOIN item_categories ic ON ic.id = i.item_category_id
WHERE i.deleted_at IS NULL
GROUP BY ic.id, ic.name;

-- Tone breakdown
SELECT CASE
         WHEN i.qty <= 0 THEN 'danger'
         WHEN i.qty < i.min_stock * 0.5 THEN 'critical'
         WHEN i.qty < i.min_stock THEN 'warning'
         ELSE 'safe'
       END AS tone,
       COUNT(*) AS count
FROM items i
WHERE i.deleted_at IS NULL AND i.is_active = 1
GROUP BY tone;

-- Stock alerts (items below min_stock, ordered by severity)
SELECT i.id, i.name, ic.name AS category, i.qty, i.unit_base AS unit, i.min_stock,
       CASE
         WHEN i.qty <= 0 THEN 'danger'
         WHEN i.qty < i.min_stock * 0.5 THEN 'critical'
         WHEN i.qty < i.min_stock THEN 'warning'
         ELSE 'safe'
       END AS tone
FROM items i
JOIN item_categories ic ON ic.id = i.item_category_id
WHERE i.deleted_at IS NULL AND i.is_active = 1 AND i.qty < i.min_stock
ORDER BY i.qty ASC
LIMIT 10;
```

**Tone threshold config** (new config file or constant):
```php
const TONE_THRESHOLDS = [
    'danger'   => 0.0,    // qty <= 0
    'critical' => 0.5,    // qty < min_stock * 0.5  (aligned with frontend getStockTone)
    'warning'  => 1.0,    // qty < min_stock * 1.0
    // else 'safe'
];
```

---



**Tables:** `menus`, `menu_schedules`, `menu_dishes`, `meal_times`, `dishes`, `dish_compositions`, `items`

**Returns:**
```php
[
    'current_menu_cycle' => [
        'date'       => '2026-06-23',
        'menu_id'    => 3,
        'menu_name'  => 'Paket 3',
        'assignments' => [...],
        // NEW: ingredient totals
        'total_ingredient_items' => 12,
        'total_required_qty'     => 4560.00,
        'sufficient_items'      => 9,
        'insufficient_items'    => 3,
        // NEW: top 5 shortages
        'top_shortages' => [
            ['item_id' => 7, 'item_name' => 'Telur', 'unit_base' => 'gram',
             'current_stock' => 200, 'required' => 1500, 'tone' => 'critical'],
        ],
    ],
    'current_menu_composition' => [
        // EXISTING: [{meal_time, dish_id, dish_name, item_id, item_name, qty_per_patient, menu_name}]
    ],
    // NEW: for dapur — compact ingredient summary
    'menu_ingredient_summary' => [
        ['item_id' => 1, 'item_name' => 'Beras', 'unit' => 'gram',
         'current_stock' => 2000, 'required' => 4500,
         'deficit' => 2500, 'tone' => 'critical'],
    ],
]
```

**Key SQL:**
```sql
-- Ingredient requirements for current menu
-- Step 1: get dish_ids for current menu (delegates menu resolution to MenuScheduleManagementService)
SELECT dish_id FROM menu_dishes WHERE menu_id = :menu_id;

-- Step 2: aggregate ingredients × patient count
SELECT dc.item_id, i.name, i.unit_base, i.qty AS current_stock,
       SUM(dc.qty_per_patient) * :patient_count AS required_qty
FROM dish_compositions dc
JOIN items i ON i.id = dc.item_id
WHERE dc.dish_id IN (:dish_ids)
  AND i.deleted_at IS NULL
GROUP BY dc.item_id, i.name, i.unit_base, i.qty
ORDER BY required_qty DESC;
```

**Patient count resolution:**
```php
$patientCount = $this->db->table('daily_patients')
    ->select('total_patients')
    ->where('service_date', date('Y-m-d'))
    ->get()
    ->getRow()->total_patients ?? 0;

// Edge case: no daily_patients entry → return empty summary
if ($patientCount <= 0) {
    return [
        'top_shortages' => [],
        'total_ingredient_items' => 0,
        'total_required_qty' => 0,
        'sufficient_items' => 0,
        'insufficient_items' => 0,
    ];
}
```

**Menu resolution dependency:** `MenuAggregateBuilder` MUST NOT duplicate the menu-schedule-day resolution logic. It receives either `MenuScheduleManagementService` (as constructor dependency) or pre-resolved `$assignments` from the orchestrator. The `buildCurrentMenuCycle` method continues delegating to `MenuScheduleManagementService::resolveCalendar()`.

---

### 3.3 SPK History Methods (ENHANCE existing)

**Tables:** `spk_calculations`, `spk_recommendations`

**Returns:**
```php
[
    'latest_spk_history' => [
        'basah' => [
            'id'                => 12,
            'version'           => 1,
            'calculation_date'  => '2026-06-23',
            'target_date_start' => '2026-06-23',
            'target_date_end'   => '2026-06-24',
            'target_month'      => null,
            'created_at'        => '2026-06-23 09:00:00',
            // NEW: inline top 3 recommendation items
            'summary_items' => [
                ['item_id' => 1, 'item_name' => 'Ikan Segar', 'recommended_qty' => 45.0, 'unit' => 'kg'],
                ['item_id' => 5, 'item_name' => 'Tahu',       'recommended_qty' => 30.0, 'unit' => 'kg'],
                ['item_id' => 3, 'item_name' => 'Wortel',     'recommended_qty' => 20.0, 'unit' => 'kg'],
            ],
        ],
        'kering_pengemas' => [
            'id'                => 13,
            'version'           => 1,
            'calculation_date'  => '2026-06-01',
            'target_date_start' => '2026-06-01',
            'target_date_end'   => '2026-06-30',
            'target_month'      => '2026-06',
            'created_at'        => '2026-06-01 08:00:00',
            'summary_items' => [
                ['item_id' => 2, 'item_name' => 'Beras', 'recommended_qty' => 500.0, 'unit' => 'kg'],
            ],
        ],
    ],
]
```

**Key SQL:**
```sql
-- For each latest SPK, get top recommendation items
SELECT sr.spk_id, sr.item_id, i.name AS item_name,
       sr.recommended_qty, i.unit_base AS unit
FROM spk_recommendations sr
JOIN items i ON i.id = sr.item_id
WHERE sr.spk_id IN (:spk_ids)
ORDER BY sr.spk_id, sr.recommended_qty DESC, sr.item_id ASC
LIMIT 3;
```

**Note:** The `LIMIT 3` applies per result set, not per spk_id. In PHP, group by `spk_id` and slice to 3 per group after fetch.

**Implementation:** After fetching latest SPK history, collect IDs and batch-query recommendations. Group by `spk_id` in PHP.

---

### 3.4 Patient Aggregate Methods (ENHANCE existing)

**Tables:** `daily_patients`

**Returns:**
```php
[
    'patient_fluctuation' => [
        ['service_date' => '2026-06-17', 'total_patients' => 120, 'delta' => null],
        ['service_date' => '2026-06-18', 'total_patients' => 125, 'delta' => 5],
        ['service_date' => '2026-06-19', 'total_patients' => 118, 'delta' => -7],
        // ... last 7 days
    ],
    'patient_fluctuation_meta' => [
        'average' => 122,
        'highest' => 130,
        'lowest'  => 110,
    ],
]
```

**Key SQL (MariaDB 11+ with window function):**
```sql
SELECT service_date, total_patients,
       total_patients - LAG(total_patients) OVER (ORDER BY service_date) AS delta
FROM daily_patients
ORDER BY service_date DESC
LIMIT 7;
```

**Fallback (PHP loop if window function not supported):**
```php
// Only runs when DB does not support LAG/OVER (not needed for MariaDB 11)
$rows = array_reverse($rows); // SQL returns DESC → flip to ASC for delta computation
$prev = null;
foreach ($rows as &$row) {
    $row['delta'] = $prev !== null ? $row['total_patients'] - $prev : null;
    $prev = $row['total_patients'];
}
```

**Stats:**
```php
$values = array_column($rows, 'total_patients');
$patient_fluctuation_meta = [
    'average' => (int) round(array_sum($values) / count($values)),
    'highest' => max($values),
    'lowest'  => min($values),
];
```

---

### 3.5 Transaction Aggregate Methods (NEW)

**Tables:** `stock_transactions`, `stock_transaction_details`, `items`, `transaction_types`

**Returns:**
```php
[
    'spending_trend' => [
        // EXISTING: [{date, total_out_qty}]
        // Format preserved — WoW% not included (not currently displayed)
        ['date' => '2026-06-17', 'total_out_qty' => 65],
        ['date' => '2026-06-18', 'total_out_qty' => 72],
        // ... last 7 days
    ],
    // NEW: gudang-specific
    'today_outgoing' => [
        'total_items' => 8,
        'total_qty'   => 345.00,
        'recent' => [
            ['item_id' => 1, 'item_name' => 'Beras', 'qty' => 50, 'unit' => 'kg',
             'remaining_stock' => 200, 'tone' => 'safe'],
        ],
    ],
]
```

**Key SQL — today outgoing:**
```sql
SELECT std.item_id, i.name AS item_name, SUM(std.qty) AS qty,
       i.unit_base AS unit, i.qty AS remaining_stock
FROM stock_transactions st
JOIN stock_transaction_details std ON std.transaction_id = st.id
JOIN items i ON i.id = std.item_id
JOIN transaction_types tt ON tt.id = st.type_id
WHERE st.transaction_date = CURDATE()
  AND tt.name = 'OUT'
  AND st.deleted_at IS NULL
  AND i.deleted_at IS NULL
GROUP BY std.item_id, i.name, i.unit_base, i.qty
ORDER BY qty DESC
LIMIT 10;
```

---
### 3.6 Notifications (handled by NotificationService)

The unread notifications count is fetched via `NotificationService::countUnread(int $userId)` — a 3-line method added to the existing service:
```php
public function countUnread(int $userId): int {
    return (int) $this->notificationModel
        ->where('user_id', $userId)
        ->where('is_read', 0)
        ->countAllResults();
}
```
This avoids a raw subquery on `notifications` and keeps notification access centralized. `PendingActionCounter` calls this method instead of embedding SQL.

No separate `NotificationAggregateBuilder` class. The top-level `unread_notifications` key is eliminated — it lives inside `pending_actions.unread_notifications`.


### 3.7 PendingActionCounter (NEW)

**Tables:** `stock_opnames`, `stock_transactions`, `approval_statuses`, `spk_calculations`

Unread notifications counted via `NotificationService::countUnread()` instead of raw SQL.

**Returns (admin):**
```php
[
    'pending_actions' => [
        'total'                                  => 5,
        'stock_opnames_pending_approval'         => 2,
        'transaction_revisions_pending_approval'  => 1,
        'spks_ready_to_post'                     => 2,
        'unread_notifications'                   => 3,
    ],
]
```

**Returns (gudang):**
```php
[
    'pending_actions' => [
        'total'                          => 3,
        'stock_opnames_pending_submit'   => 1,
        'spks_ready_to_post'             => 2,
        'unread_notifications'           => 2,
    ],
]
```

**Returns (dapur):**
```php
[
    'pending_actions' => [
        'total'                    => 2,
        'spks_ready_to_generate'   => 1,
        'unread_notifications'     => 1,
    ],
]
```

**Key SQL (single round-trip with subqueries):**
```sql
-- Admin
SELECT
  (SELECT COUNT(*) FROM stock_opnames WHERE state = 'SUBMITTED') AS opnames_pending_approval,
  (SELECT COUNT(*) FROM stock_transactions st
   JOIN approval_statuses ast ON ast.id = st.approval_status_id
   WHERE st.is_revision = 1 AND ast.name = 'PENDING') AS revisions_pending,
  -- Uses is_finish=0 (not yet posted to stock). is_finish is the sole posting flag
  -- in SpkStockPostingService — no separate stock_posted column exists.
  (SELECT COUNT(*) FROM spk_calculations WHERE is_finish = 0 AND is_latest = 1) AS spks_ready_to_post,
  -- Unread count delegated to NotificationService::countUnread($userId) — not a raw subquery here.
```

**Schema note:** No migration required. The `spk_calculations` table uses `is_finish` as the sole posted flag (`is_finish=0` = generated but not posted, `is_finish=1` = already posted). This matches `SpkStockPostingService` behavior which sets `is_finish=true` on post (line 149) and checks `is_finish` to block re-posting (line 58).

**Index note (deferred):** No composite index needed on `notifications(user_id, is_read)`. MariaDB auto-indexes FK columns, so `user_id` is indexed. At current scale (<10k rows), the residual `is_read` filter on an indexed `user_id` range scan is sub-millisecond. Revisit if production monitoring shows table-scan.

**Gudang subquery:**
```sql
(SELECT COUNT(*) FROM stock_opnames WHERE state = 'DRAFT' AND created_by = :user_id) AS opnames_pending_submit
```

## 4. Role Payload Assembly

Payload composition in `DashboardAggregateService::getDashboardAggregateForUser()`:

All aggregate builder methods stay on `DashboardAggregateService` (no separate builder class instances). `PendingActionCounter` is the only extracted helper.

```php
// Each top-level method call wrapped in try/catch for error isolation
// A failing method returns null for its key; remaining keys unaffected
match ($roleName) {
    'admin' => [
        'stock_summary'           => $this->buildStockSummary(),
        'dry_stock_status'        => $this->buildDryStockStatus(),
        'stock_alerts'            => $this->buildStockAlerts(),
        'spending_trend'          => $this->buildSpendingTrend(),
        'current_menu_cycle'      => $this->buildCurrentMenuCycle(),
        'latest_spk_history'      => $this->buildLatestSpkHistory(),
        'patient_fluctuation'     => $this->buildPatientFluctuation(),
        'patient_fluctuation_meta'=> $this->buildPatientStats(),
        'pending_actions'         => $this->pendingCounter->buildForAdmin($userId),
    ],
    'gudang' => [
        'stock_summary'           => $this->buildStockSummary(),
        'dry_stock_status'        => $this->buildDryStockStatus(),
        'stock_alerts'            => $this->buildStockAlerts(),
        'spending_trend'          => $this->buildSpendingTrend(),
        'latest_spk_history'      => $this->buildLatestSpkHistory(),
        'patient_fluctuation'     => $this->buildPatientFluctuation(),
        'patient_fluctuation_meta'=> $this->buildPatientStats(),
        'today_outgoing'          => $this->buildTodayOutgoing(),
        'pending_actions'         => $this->pendingCounter->buildForGudang($userId),
    ],
    'dapur' => [
        'current_menu_cycle'          => $this->buildCurrentMenuCycle(),
        'current_menu_composition'    => $this->buildCurrentMenuComposition($assignments),
        'menu_ingredient_summary'     => $this->buildIngredientSummary(),
        'latest_spk_history'          => $this->buildLatestSpkHistory(),
        'stock_summary'               => $this->buildStockSummary(),
        'dry_stock_status'            => $this->buildDryStockStatus(),
        'pending_actions'             => $this->pendingCounter->buildForDapur($userId),
    ],
};
```

Note: `unread_notifications` is NOT a top-level key. It lives inside `pending_actions.unread_notifications`.

### 5.1 API Call Optimization

| Current Call | After | Eliminated For |
|---|---|---|
| `sdk.reports.getStocks(period)` | `stock_summary.by_category` + `stock_alerts.items` | All roles |
| `sdk.reports.getTransactions(period)` | `today_outgoing.recent` | Gudang |
| `sdk.dailyPatients.list()` | `patient_fluctuation` (enhanced + reliable) | Gudang, Dapur |
| `sdk.dishCompositions.list()` | `menu_ingredient_summary` / `current_menu_cycle.top_shortages` | Dapur |
| `sdk.spk.getBasah(id)` | `latest_spk_history.basah.summary_items` | All (dashboard only) |
| `sdk.spk.getKeringPengemas(id)` | `latest_spk_history.kering_pengemas.summary_items` | All (dashboard only) |

**Post-migration API calls:**

| Dashboard | Before | After |
|---|---|---|
| Admin | 6 | 3 (`dashboard.getAggregate`, `menus.slots`, `menuSchedules.calendarProjection`) |
| Gudang | 9 | 3 (aggregate-only) |
| Dapur | 9 | 3 (aggregate-only) |

`menus.slots()` and `menuSchedules.calendarProjection()` kept for package menu layout rendering. `dailyPatients.list()` is NOT retained — the aggregate endpoint is the single source of truth for patient data.

### 5.2 Client-Side Computation Elimination

| Computation | Location | Replace With |
|---|---|---|
| `getStockTone(qty, threshold)` | `admin-utils.ts` | Backend `tone` field in `stock_alerts.items` / `top_shortages` |
| `patientDelta` | `OperationalDashboardPage.tsx` | `patient_fluctuation[n].delta` |
| `patientStats` | `OperationalDashboardPage.tsx` | `patient_fluctuation_meta` |
| `menuIngredientRows` | `OperationalDashboardPage.tsx` | `menu_ingredient_summary` or `top_shortages` |
| `todayOutRows` | `OperationalDashboardPage.tsx` | `today_outgoing.recent` |
| `stockSummaryBoxes` | Both dashboards | `stock_summary.tone_summary` |

### 5.3 SDK Type Contract Update

**Current** (`gudang-app/src/sdk/types/dashboard.ts`): all `?unknown`

**Target** — concrete per-role interfaces:

```typescript
interface StockCategorySummary {
  category: string;
  total: number;
  active: number;
  zero: number;
  qty: number;
}

interface StockToneSummary {
  safe: number;
  warning: number;
  critical: number;
  danger: number;
}

interface StockAlertItem {
  item_id: number;
  item_name: string;
  category: string;
  qty: number;
  unit: string;
  min_stock: number;
  tone: 'safe' | 'warning' | 'critical' | 'danger';
}

interface StockSummaryEnhanced {
  total_items: number;
  active_items: number;
  zero_stock_items: number;
  total_stock_qty: number;
  by_category: StockCategorySummary[];
  tone_summary: StockToneSummary;
}

interface PatientFluctuationPoint {
  service_date: string;
  total_patients: number;
  delta: number | null;
}

interface PatientStats {
  average: number;
  highest: number;
  lowest: number;
}

interface SpkSummaryItem {
  item_id: number;
  item_name: string;
  recommended_qty: number;
  unit: string;
}

interface SpkHistoryEntryEnhanced {
  id: number;
  version: number;
  calculation_date: string | null;
  target_date_start: string | null;
  target_date_end: string | null;
  target_month: string | null;
  created_at: string | null;
  summary_items?: SpkSummaryItem[];
}

interface MenuShortageItem {
  item_id: number;
  item_name: string;
  unit_base: string;
  current_stock: number;
  required: number;
  tone: string;
}

interface CurrentMenuCycleEnhanced {
  date: string;
  menu_id: number | null;
  menu_name: string | null;
  total_ingredient_items?: number;
  total_required_qty?: number;
  sufficient_items?: number;
  insufficient_items?: number;
  top_shortages?: MenuShortageItem[];
  assignments: any[];
}

interface TodayOutgoing {
  total_items: number;
  total_qty: number;
  recent: Array<{
    item_id: number;
    item_name: string;
    qty: number;
    unit: string;
    remaining_stock: number;
    tone: string;
  }>;
}

interface PendingActions {
  total: number;
  stock_opnames_pending_approval?: number;
  stock_opnames_pending_submit?: number;
  transaction_revisions_pending_approval?: number;
  spks_ready_to_post?: number;
  spks_ready_to_generate?: number;
  unread_notifications: number;
}

interface MenuIngredientSummaryItem {
  item_id: number;
  item_name: string;
  unit: string;
  current_stock: number;
  required: number;
  deficit: number;
  tone: string;
}

// Per-role aggregate interfaces
interface AdminAggregates {
  stock_summary: StockSummaryEnhanced;
  dry_stock_status: DryStockStatus;
  stock_alerts: { total_critical: number; total_danger: number; items: StockAlertItem[] };
  spending_trend: Array<{ date: string; total_out_qty: number }>;
  current_menu_cycle: CurrentMenuCycleEnhanced;
  latest_spk_history: { basah: SpkHistoryEntryEnhanced; kering_pengemas: SpkHistoryEntryEnhanced };
  patient_fluctuation: PatientFluctuationPoint[];
  patient_fluctuation_meta: PatientStats;
  pending_actions: PendingActions;
}

interface GudangAggregates extends AdminAggregates {
  today_outgoing: TodayOutgoing;
  pending_actions: PendingActions & { stock_opnames_pending_submit: number };
}

interface DapurAggregates extends Omit<AdminAggregates, 'spending_trend' | 'patient_fluctuation' | 'patient_fluctuation_meta'> {
  current_menu_composition: MenuCompositionItem[];
  menu_ingredient_summary: MenuIngredientSummaryItem[];
}
```

### 5.4 Dashboard Component Changes

**Super Admin (`super-admin/page.tsx`):**
- Remove `sdk.reports.getStocks(period)` — consume `stock_alerts` instead
- Remove `sdk.spk.getBasah(id)` / `sdk.spk.getKeringPengemas(id)` — consume `summary_items`
- Remove `stockRows` state, `getStockTone()` call, `warningRows` computation
- Replace `stockFocusRows` with `stock_alerts.items`
- Replace `stockSummaryBoxes` computation with `stock_summary.tone_summary`

**OperationalDashboardPage (`OperationalDashboardPage.tsx`):**
- Remove `sdk.reports.getStocks(period)` — consume `stock_alerts`
- Remove `sdk.reports.getTransactions(period)` (gudang) — consume `today_outgoing`
- Remove `sdk.dailyPatients.list()` — rely on `patient_fluctuation`
- Remove `sdk.dishCompositions.list()` (dapur) — consume `menu_ingredient_summary`
- Remove `sdk.spk.getBasah(id)` / `sdk.spk.getKeringPengemas(id)` — consume `summary_items`
- Remove `StockReportRow`, `TransactionReportRow`, `DailyPatientRow` types from component
- Remove `getStockTone()` calls from useMemo chains
- Remove `todayOutRows` computation — consume `today_outgoing.recent`
- Remove `menuIngredientRows` computation — consume `menu_ingredient_summary` or `top_shortages`
- Remove `listAllPaginatedRows` usage entirely — aggregate provides all data
- Keep `menuSlots` and `menuCalendarResponse` for package menu card layout
- **Error handling:** restructure to handle aggregate failure independently from menus/calendar failure. Each section renders empty state if its data source fails.

---

## 6. Implementation Sequence
### Phase A — Backend (2-2.5 days)

| Step | Task | Files |
|---|---|---|
| A0 | Validate all plan SQL against actual DB schema before writing code | — |
| A1 | Enhance `DashboardAggregateService` — add `by_category`, `tone_summary`, `stock_alerts` to `buildStockSummary()` | `app/Services/DashboardAggregateService.php` |
| A2 | Add `delta` + `patient_fluctuation_meta` to `buildPatientFluctuation()` | `app/Services/DashboardAggregateService.php` |
| A3 | Add `summary_items` (batch query on `spk_recommendations`, group in PHP) to `buildLatestSpkHistory()` | `app/Services/DashboardAggregateService.php` |
| A4 | Add `today_outgoing` + `ingredient_summary` + `top_shortages` menu enhancements | `app/Services/DashboardAggregateService.php` |
| A5 | Create `PendingActionCounter` — cross-table pending counts (admin/gudang/dapur), delegates unread to `NotificationService::countUnread()` | `app/Services/PendingActionCounter.php` (new) |
| A5a | Add `NotificationService::countUnread(int $userId)` method | `app/Services/NotificationService.php` |
| A5b | Backfill `min_stock` values in `ItemSeeder` so tone thresholds produce realistic results | `app/Database/Seeds/ItemSeeder.php` |
| A6 | Wire error isolation (try/catch per method) in `getDashboardAggregateForUser()` | `app/Services/DashboardAggregateService.php` |
| A7 | Update OpenAPI schemas in `DashboardSchemas.php` | `app/OpenApi/DashboardSchemas.php` |
| A8 | Update `api-contract.md` §5.8 with new response keys | `docs/reference/api-contract.md` |
| A9 | Add unit tests (tone boundaries, delta computation, pending subqueries) + update integration tests | `tests/feature/Api/V1/DashboardTest.php` |

### Phase B — Frontend (2 days)

| Step | Task | Files |
|---|---|---|
| B1 | Update SDK types — concrete interfaces per role (remove `wow_pct`, remove top-level `unread_notifications`) | `gudang-app/src/sdk/types/dashboard.ts` |
| B2 | Refactor super-admin dashboard — remove reports/stocks, SPK detail calls | `gudang-app/src/app/(dashboard)/super-admin/page.tsx` |
| B3 | Refactor OperationalDashboardPage — remove 6 redundant calls, use new aggregate fields, add section-level error handling | `gudang-app/src/components/dashboard/OperationalDashboardPage.tsx` |
| B4 | Remove unused functions (`getStockTone` from dashboard context) | `gudang-app/src/lib/admin-utils.ts` |
| B5 | Update `DashboardState` types to match new shape (remove `unread_notifications` as top-level, read from `pending_actions`) | Both dashboard files |
| B6 | Remove `listAllPaginatedRows` usage from dashboard | Both dashboard files |
| B7 | Implement section-level error handling: aggregate failure ≠ blank dashboard | Both dashboard files |

### Phase C — Cleanup & Verify (1.5-2 days)

| Step | Task |
|---|---|
| C1 | Run backend test suite — `DashboardTest.php` + builder unit tests |
| C2 | Update SDK tests (`gudang-app/src/sdk/tests/dashboard.test.ts`) for new type shapes |
| C3 | Build frontend — check TypeScript compilation |
| C4 | Manual E2E: login as admin, verify all 4 stat cards + charts + panels |
| C5 | Manual E2E: login as gudang, verify outgoing table + stock warnings |
| C6 | Manual E2E: login as dapur, verify menu composition + ingredient summary |
| C7 | Update `SDK_MAP.md` with enhanced dashboard response fields |

---

## 7. Backward Compatibility

| Item | Strategy |
|---|---|
| Old aggregate keys | All preserved — new keys added alongside |
| Old frontend | Continues working until Phase B migration. Old keys still present in response |
| `unread_notifications` as top-level key | **Removed.** Now inside `pending_actions`. Old frontend ignores unknown keys |
| `spending_trend.wow_pct` | **Not added** (dropped from scope). Old format `{date, total_out_qty}` preserved |
| Removed endpoints | Not removed — only dashboard stops calling them. Other pages may still use them |
| SDK types | Old `?unknown` type kept, new concrete types added alongside |
| `getStockTone()` | Kept in `admin-utils.ts` for non-dashboard pages that still need it |

---

## 8. Risk & Mitigation

| Risk | Impact | Likelihood | Severity | Mitigation |
|---|---|---|---|---|
| `stock_opnames` column named `state` not `status` | SQL error | High | **High** | Use correct column name `state` |
| `min_stock` unseeded in `ItemSeeder` (all items = 0) | Tone thresholds `min_stock * 0.5` = 0 — nothing ever critical/warning | High | **High** | Backfill `min_stock` in seeder before testing tone logic |
| Tone threshold mismatch (0.5 vs 0.25) between backend and frontend | Inconsistent stock alerts across pages | High | **Medium** | Align backend `critical` threshold to 0.5 to match frontend `getStockTone()` |
| Single aggregate endpoint = single point of failure | Blank dashboard if one method throws | Medium | **Medium** | Wrap each method in try/catch; return null per key; frontend handles per-section |
| Dashboard endpoint slows with more queries | UI latency | Medium | **Low** | Use file-based cache (already configured, zero-infrastructure) with 1-min TTL. Add Redis only if production latency exceeds 1s |
| Inline SPK summary_items increases response size | Bandwidth | Low | **Low** | Limit to 3 items per SPK type; detail page endpoint still available for full list |
| No `stock_posted` column (using `is_finish` instead) | Cannot distinguish finalized-but-not-posted from already-posted | Low | **Low** | `is_finish` correctly tracks posted state per SpkStockPostingService; no separate column needed |
| Deployment timing mismatch | Frontend expects new keys before backend deploys | Low | **Low** | Backend-first deploy (new keys additive), then frontend. Rollback: revert frontend, old code works |
| No daily_patients entry for today | All-zero ingredient requirements | Low | **Low** | Early-return guard when `patientCount <= 0` |

---

## 9. Deployment Sequencing

### Required Order
```
Step 1: Backend Phase A — deploy first
  - Enhanced DashboardAggregateService deployed (autoloader picks up new methods)
  - New PendingActionCounter.php file deployed
  - NotificationService::countUnread() added
  - ItemSeeder backfill for min_stock values
  - Existing API shape preserved, new keys ADDED
  - Old frontend continues working (backward compatible per §7)

Step 2: Frontend Phase B — deploy second (may be same release cycle)
  - Consumes new aggregate keys
  - Drops old redundant API calls
  - TypeScript compilation catches any drift from backend contract

Step 3: Phase C cleanup — post-deploy
  - E2E tests on production/staging
  - Docs update
```

### Risk Period
Between Step 1 and Step 2, the frontend continues making old redundant API calls. This is safe — old endpoints are not removed — but the full optimization benefit is not realized until Step 2.

### Rollback
- If Phase B causes issues: revert frontend deploy → old code works with new backend (old keys preserved)
- If Phase A causes issues: revert backend deploy → reverts enhanced service and new helper files



---

> **Audit baseline (2026-06-23):** Verified against actual code in `gudang-app` and `Capstone/backend`. Implementation progress: **0%** — all new builder files, enhanced aggregate keys, and frontend refactors are pending.

### 10.1 Verified Current State (from audit)

| Dashboard | API Calls | Parallel Phase 1 | Sequential Phase 2 | Redundant Calls |
|---|---|---:|---:|---:|
| Admin (`super-admin/page.tsx`) | 6 | 4 (`getAggregate`, `getStocks`, `slots`, `calendarProjection`) | 2 (`getBasah`, `getKeringPengemas`) | 3 (`getStocks` + 2 SPK detail) |
| Gudang (`OperationalDashboardPage`) | 9 | 6 (`getAggregate`, `dailyPatients.list` via paginator, `getTransactions`, `getStocks`, `slots`, `calendarProjection`) | 3 (`dishCompositions` via paginator + 2 SPK detail) | 6 (patients, transactions, stocks, dishCompositions, 2 SPK detail) |
| Dapur (`OperationalDashboardPage`) | 9 | Same as gudang | Same as gudang | 6 (same redundant set) |

### 10.2 Client-Side Computation Chains (verified)

| Computation | File | Plan Replacement |
|---|---|---|
| `getStockTone()` | `super-admin/page.tsx` — 3 call sites (`warningRows`, `stockSummaryBoxes`, `warningRows.map` render) | Backend `tone` field in `stock_alerts.items` |
| `patientStats` (avg/high/low) | Both dashboards — `useMemo` over `patient_fluctuation` | `patient_fluctuation_meta` |
| `patientDelta` (%) | `OperationalDashboardPage.tsx` — `((latest - prev) / prev) * 100` | `patient_fluctuation[].delta` |
| `stockSummaryBoxes` (tone buckets) | `super-admin/page.tsx` — counts by tone from raw stock rows | `stock_summary.tone_summary` |
| `warningRows` (critical+danger filter) | `super-admin/page.tsx` — filter + slice(0,5) | `stock_alerts.items` |
| `stockFocusRows` (top 6) | `super-admin/page.tsx` — `stockRows.slice(0, 6)` | `stock_alerts.items` |
| `todayOutRows` (outgoing enrichment) | `OperationalDashboardPage.tsx` — from raw `transactionRows` | `today_outgoing.recent` |
| `menuIngredientRows` | `OperationalDashboardPage.tsx` (dapur) — cross-joins dishCompositions × menuSlots × patient count | `menu_ingredient_summary` |

### 10.3 Aggregate Key Coverage Gap

| Aggregate Key | Current Backend | Plan Requires | Status |
|---|---|---:|---:|
| `stock_summary` | 4 fields (total_items, active_items, zero_stock_items, total_stock_qty) | +2 fields (by_category, tone_summary) | **Missing** |
| `stock_alerts` | Does not exist | New: `{total_critical, total_danger, items[]}` | **Missing** |
| `spending_trend` | ✓ Exists, correct format | Preserved (relocated to builder) | ✓ Exists |
| `spending_trend.wow_pct` | Never existed | Explicitly dropped | No-op |
| `patient_fluctuation[].delta` | Missing (no LAG, no PHP loop) | Required per point | **Missing** |
| `patient_fluctuation_meta` | Does not exist | New: `{average, highest, lowest}` | **Missing** |
| `today_outgoing` | Does not exist | New: `{total_items, total_qty, recent[]}` | **Missing** |
| `pending_actions` | Does not exist | New: `{total, sub-counts, unread_notifications}` | **Missing** |
| `menu_ingredient_summary` | Does not exist | New: `[{item_id, item_name, deficit, tone}]` | **Missing** |
| `current_menu_cycle` enhanced | 4 fields (date, menu_id, menu_name, assignments) | +5 fields (total_ingredient_items, total_required_qty, sufficient_items, insufficient_items, top_shortages) | **Missing** |
| `latest_spk_history.basah.summary_items` | Not returned (N+1 fallback) | Inline top-3 recommendations | **Missing** |
| `latest_spk_history.kering_pengemas.summary_items` | Not returned (N+1 fallback) | Inline top-3 recommendations | **Missing** |
| `unread_notifications` top-level | Never existed in frontend | Removed, inside `pending_actions` | No-op |

### 10.4 Remaining Infrastructure Blockers

| Blocker | Blocked Feature | Severity | Status |
|---|---|---|---|
| `min_stock` unseeded (all items = 0) | Tone logic — thresholds `min_stock * 0.5` = 0, nothing is ever critical/warning | **High** | Column exists, needs seeder backfill (A5b) |
| No notification records seeded | `unread_notifications` always 0 in test/dev | **Medium** | Seeders bypass NotificationService |

**Resolved blockers:**
- `stock_posted` column — no migration needed. `is_finish` is the correct flag per `SpkStockPostingService`. Plan's SQL fixed to `is_finish = 0 AND is_latest = 1`.
- `idx_notifications_user_unread` — deferred. FK index on `user_id` already covers at current scale.

### 10.5 Post-Migration Metrics (target)

| Metric | Before | After | Improvement |
|---|---|---|---|
| Admin API calls per page load | 6 | 3 | 50% reduction |
| Gudang API calls per page load | 9 | 3 | 67% reduction |
| Dapur API calls per page load | 9 | 3 | 67% reduction |
| Waterfall phases | 2 | 1 | Single phase |
| Client-side computation locations | 8 | 0 | 100% eliminated |
| Data redundancy (patient fetches) | 2 | 0 | Eliminated |
| N+1 SPK detail fetches | 2 per dashboard load | 0 | Eliminated |
| Aggregate keys returned | 7 (admin) / 5 (gudang, dapur) | 12 (admin) / 11 (gudang) / 11 (dapur) | +70% richer payload |
| SDK type safety | All `?unknown` | Fully typed per role | Compile-time safety |
| New files created | 0 | 1 (`PendingActionCounter.php`) | Minimal surface area |

### 10.6 "After" Call Breakdown (per role)

**Admin (3 calls):**
1. `GET /api/v1/dashboard` — 12 aggregate keys
2. `GET /api/v1/menus/slots` — package menu card layout (rendering only)
3. `GET /api/v1/menu-schedules/calendar-projection` — today's menu resolution

**Gudang (3 calls):**
1. `GET /api/v1/dashboard` — 11 aggregate keys (adds `today_outgoing`, drops menu cycle)
2. `GET /api/v1/menus/slots` — card layout
3. `GET /api/v1/menu-schedules/calendar-projection` — menu resolution

**Dapur (3 calls):**
1. `GET /api/v1/dashboard` — 11 aggregate keys (includes `menu_ingredient_summary`, drops `spending_trend`/`patient_fluctuation`)
2. `GET /api/v1/menus/slots` — card layout
3. `GET /api/v1/menu-schedules/calendar-projection` — menu resolution

All three roles **eliminate** these calls:
- `reports.getStocks()` — replaced by `stock_alerts` + `stock_summary.by_category`
- `reports.getTransactions()` — replaced by `today_outgoing`
- `dailyPatients.list()` — replaced by `patient_fluctuation`
- `dishCompositions.list()` — replaced by `menu_ingredient_summary`
- `spk.getBasah(id)` — replaced by `latest_spk_history.basah.summary_items`
- `spk.getKeringPengemas(id)` — replaced by `latest_spk_history.kering_pengemas.summary_items`

---
## Appendix: File Change Summary
### Backend — New Files
- `app/Services/PendingActionCounter.php` — cross-table pending action counts, delegates unread to `NotificationService::countUnread()`

### Backend — Modified Files
- `app/Services/DashboardAggregateService.php` — add 4 new methods (`buildStockAlerts`, `buildTodayOutgoing`, `buildIngredientSummary`, `buildPatientStats`), enhance 3 existing (`buildStockSummary` +by_category/tone_summary, `buildPatientFluctuation` +delta, `buildLatestSpkHistory` +summary_items), add try/catch error isolation
- `app/Services/NotificationService.php` — add `countUnread(int $userId): int` method
- `app/OpenApi/DashboardSchemas.php` — new schema definitions for enhanced keys
- `app/Database/Seeds/ItemSeeder.php` — backfill `min_stock` values for realistic tone thresholds
- `docs/reference/api-contract.md` — §5.8 enhanced response spec

### Frontend — Modified Files
- `gudang-app/src/sdk/types/dashboard.ts` — concrete per-role types (remove `wow_pct`, remove top-level `unread_notifications`)
- `gudang-app/src/sdk/tests/dashboard.test.ts` — update SDK tests for new type shapes
- `gudang-app/src/app/(dashboard)/super-admin/page.tsx` — remove redundant calls, add section-level error handling
- `gudang-app/src/components/dashboard/OperationalDashboardPage.tsx` — remove redundant calls, use new fields, remove `listAllPaginatedRows`
- `gudang-app/src/lib/admin-utils.ts` — keep `getStockTone()` for non-dashboard pages
