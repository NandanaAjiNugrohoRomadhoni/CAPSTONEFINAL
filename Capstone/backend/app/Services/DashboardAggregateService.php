<?php

namespace App\Services;

use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\SpkCalculationModel;
use App\Models\TransactionTypeModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use Throwable;

class DashboardAggregateService
{
    protected BaseConnection $db;
    protected AppUserProvider $userProvider;
    protected ItemCategoryModel $itemCategoryModel;
    protected MenuScheduleManagementService $menuScheduleService;
    protected PendingActionCounter $pendingCounter;
    protected TransactionTypeModel $transactionTypeModel;

    /** Stock tone thresholds — aligned with frontend getStockTone(). */
    private const TONE_DANGER   = 0.0; // qty <= 0
    private const TONE_CRITICAL = 0.5; // qty < min_stock * 0.5
    // qty < min_stock * 1.0 → 'warning'; else 'safe'

    public function __construct()
    {
        $this->db                   = Database::connect();
        $this->userProvider         = new AppUserProvider();
        $this->itemCategoryModel    = new ItemCategoryModel();
        $this->menuScheduleService  = new MenuScheduleManagementService();
        $this->pendingCounter       = new PendingActionCounter();
        $this->transactionTypeModel = new TransactionTypeModel();
    }

    public function getDashboardAggregateForUser(int $userId): array
    {
        $userWithRole = $this->userProvider->getActiveUserWithRole($userId);
        if ($userWithRole === null) {
            return [
                'success' => false,
                'message' => 'Account is inactive or has been deleted.',
                'status'  => 403,
            ];
        }

        $roleName = (string) ($userWithRole['role_name'] ?? '');
        if (! in_array($roleName, ['admin', 'gudang', 'dapur'], true)) {
            return [
                'success' => false,
                'message' => 'Insufficient permissions.',
                'status'  => 403,
            ];
        }

        // Resolve menu cycle once — shared by admin and dapur
        $currentMenuCycle = null;
        if (in_array($roleName, ['admin', 'dapur'], true)) {
            $currentMenuCycle = $this->safeCall(fn() => $this->buildCurrentMenuCycle());
        }

        $payload = match ($roleName) {
            'admin' => [
                'stock_summary'            => $this->safeCall(fn() => $this->buildStockSummary()),
                'dry_stock_status'         => $this->safeCall(fn() => $this->buildDryStockStatus()),
                'stock_alerts'             => $this->safeCall(fn() => $this->buildStockAlerts()),
                'spending_trend'           => $this->safeCall(fn() => $this->buildSpendingTrend()),
                'current_menu_cycle'       => $currentMenuCycle,
                'latest_spk_history'       => $this->safeCall(fn() => $this->buildLatestSpkHistory()),
                'patient_fluctuation'      => $this->safeCall(fn() => $this->buildPatientFluctuation()),
                'patient_fluctuation_meta' => $this->safeCall(fn() => $this->buildPatientStats()),
                'pending_actions'          => $this->safeCall(fn() => $this->pendingCounter->buildForAdmin($userId)),
            ],
            'gudang' => [
                'stock_summary'            => $this->safeCall(fn() => $this->buildStockSummary()),
                'dry_stock_status'         => $this->safeCall(fn() => $this->buildDryStockStatus()),
                'stock_alerts'             => $this->safeCall(fn() => $this->buildStockAlerts()),
                'spending_trend'           => $this->safeCall(fn() => $this->buildSpendingTrend()),
                'latest_spk_history'       => $this->safeCall(fn() => $this->buildLatestSpkHistory()),
                'patient_fluctuation'      => $this->safeCall(fn() => $this->buildPatientFluctuation()),
                'patient_fluctuation_meta' => $this->safeCall(fn() => $this->buildPatientStats()),
                'today_outgoing'           => $this->safeCall(fn() => $this->buildTodayOutgoing()),
                'pending_actions'          => $this->safeCall(fn() => $this->pendingCounter->buildForGudang($userId)),
            ],
            default => [
                'current_menu_cycle'       => $currentMenuCycle,
                'current_menu_composition' => $this->safeCall(
                    fn() => $this->buildCurrentMenuComposition($currentMenuCycle['assignments'] ?? [])
                ),
                'menu_ingredient_summary'  => $this->safeCall(fn() => $this->buildIngredientSummary($currentMenuCycle)),
                'latest_spk_history'       => $this->safeCall(fn() => $this->buildLatestSpkHistory()),
                'stock_summary'            => $this->safeCall(fn() => $this->buildStockSummary()),
                'dry_stock_status'         => $this->safeCall(fn() => $this->buildDryStockStatus()),
                'pending_actions'          => $this->safeCall(fn() => $this->pendingCounter->buildForDapur($userId)),
            ],
        };

        return [
            'success' => true,
            'data'    => [
                'role'         => $roleName,
                'generated_at' => date('Y-m-d H:i:s'),
                'aggregates'   => $payload,
            ],
        ];
    }

    /**
     * Wraps a builder call so one failing aggregate does not blank the entire dashboard.
     * Returns null for that key if the builder throws.
     */
    private function safeCall(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // A1: Stock Summary — enhanced with by_category + tone_summary
    // -------------------------------------------------------------------------

    private function buildStockSummary(): array
    {
        $totals = $this->db
            ->table('items')
            ->select(
                'COUNT(*) AS total_items,'
                . ' SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_items,'
                . ' SUM(CASE WHEN qty <= 0 THEN 1 ELSE 0 END) AS zero_stock_items,'
                . ' COALESCE(SUM(qty), 0) AS total_stock_qty'
            )
            ->where('deleted_at', null)
            ->get()
            ->getRowArray() ?? [];

        $byCategory = $this->db
            ->table('items i')
            ->select('ic.name AS category, COUNT(*) AS total,'
                . ' SUM(CASE WHEN i.is_active = 1 THEN 1 ELSE 0 END) AS active,'
                . ' SUM(CASE WHEN i.qty <= 0 THEN 1 ELSE 0 END) AS zero,'
                . ' COALESCE(SUM(i.qty), 0) AS qty')
            ->join('item_categories ic', 'ic.id = i.item_category_id', 'inner')
            ->where('i.deleted_at', null)
            ->groupBy('ic.id, ic.name')
            ->orderBy('ic.name', 'ASC')
            ->get()
            ->getResultArray();

        $toneSummary = $this->db
            ->table('items i')
            ->select(
                'CASE'
                . ' WHEN i.qty <= 0 THEN \'danger\''
                . ' WHEN i.min_stock > 0 AND i.qty < i.min_stock * 0.5 THEN \'critical\''
                . ' WHEN i.min_stock > 0 AND i.qty < i.min_stock THEN \'warning\''
                . ' ELSE \'safe\' END AS tone,'
                . ' COUNT(*) AS cnt'
            )
            ->where('i.deleted_at', null)
            ->where('i.is_active', 1)
            ->groupBy('tone')
            ->get()
            ->getResultArray();

        $toneMap = ['safe' => 0, 'warning' => 0, 'critical' => 0, 'danger' => 0];
        foreach ($toneSummary as $row) {
            $t = $row['tone'];
            if (array_key_exists($t, $toneMap)) {
                $toneMap[$t] = (int) $row['cnt'];
            }
        }

        return [
            'total_items'      => (int) ($totals['total_items'] ?? 0),
            'active_items'     => (int) ($totals['active_items'] ?? 0),
            'zero_stock_items' => (int) ($totals['zero_stock_items'] ?? 0),
            'total_stock_qty'  => (float) ($totals['total_stock_qty'] ?? 0),
            'by_category'      => array_map(static fn(array $r): array => [
                'category' => $r['category'],
                'total'    => (int) $r['total'],
                'active'   => (int) $r['active'],
                'zero'     => (int) $r['zero'],
                'qty'      => (float) $r['qty'],
            ], $byCategory),
            'tone_summary'     => $toneMap,
        ];
    }

    // -------------------------------------------------------------------------
    // Stock Alerts (new)
    // -------------------------------------------------------------------------

    private function buildStockAlerts(): array
    {
        $rows = $this->db
            ->table('items i')
            ->select(
                'i.id AS item_id, i.name AS item_name, ic.name AS category,'
                . ' i.qty, i.unit_base AS unit, i.min_stock,'
                . ' CASE'
                . ' WHEN i.qty <= 0 THEN \'danger\''
                . ' WHEN i.min_stock > 0 AND i.qty < i.min_stock * 0.5 THEN \'critical\''
                . ' WHEN i.min_stock > 0 AND i.qty < i.min_stock THEN \'warning\''
                . ' ELSE \'safe\' END AS tone'
            )
            ->join('item_categories ic', 'ic.id = i.item_category_id', 'inner')
            ->where('i.deleted_at', null)
            ->where('i.is_active', 1)
            ->where('(i.qty <= 0 OR (i.min_stock > 0 AND i.qty < i.min_stock))')
            ->orderBy('i.qty', 'ASC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $critical = 0;
        $danger   = 0;
        $items    = [];

        foreach ($rows as $row) {
            $tone = $row['tone'];
            if ($tone === 'critical') {
                $critical++;
            } elseif ($tone === 'danger') {
                $danger++;
            }
            $items[] = [
                'item_id'   => (int) $row['item_id'],
                'item_name' => $row['item_name'],
                'category'  => $row['category'],
                'qty'       => (float) $row['qty'],
                'unit'      => $row['unit'],
                'min_stock' => (float) $row['min_stock'],
                'tone'      => $tone,
            ];
        }

        return [
            'total_critical' => $critical,
            'total_danger'   => $danger,
            'items'          => $items,
        ];
    }

    private function buildDryStockStatus(): array
    {
        $keringCategoryId = $this->itemCategoryModel->getIdByName(ItemCategoryModel::NAME_KERING);

        if ($keringCategoryId === null) {
            return [
                'status'           => 'AMAN',
                'total_items'      => 0,
                'zero_stock_items' => 0,
                'total_stock_qty'  => 0.0,
            ];
        }

        $rows = $this->db
            ->table('items')
            ->select('COUNT(*) AS total_items, SUM(CASE WHEN qty <= 0 THEN 1 ELSE 0 END) AS zero_stock_items, COALESCE(SUM(qty), 0) AS total_stock_qty')
            ->where('deleted_at', null)
            ->where('item_category_id', $keringCategoryId)
            ->get()
            ->getRowArray() ?? [];

        $totalItems = (int) ($rows['total_items'] ?? 0);
        $zeroStock  = (int) ($rows['zero_stock_items'] ?? 0);

        return [
            'status'           => $zeroStock > 0 ? 'KRITIS' : 'AMAN',
            'total_items'      => $totalItems,
            'zero_stock_items' => $zeroStock,
            'total_stock_qty'  => (float) ($rows['total_stock_qty'] ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // A2: Patient Fluctuation — enhanced with delta + meta
    // -------------------------------------------------------------------------

    private function buildPatientFluctuation(): array
    {
        $rows = $this->db
            ->table('daily_patients')
            ->select('service_date, total_patients')
            ->orderBy('service_date', 'DESC')
            ->limit(7)
            ->get()
            ->getResultArray();

        $rows = array_reverse($rows); // oldest → newest for delta computation

        $prev   = null;
        $result = [];
        foreach ($rows as $row) {
            $count = (int) $row['total_patients'];
            $result[] = [
                'service_date'   => $row['service_date'],
                'total_patients' => $count,
                'delta'          => $prev !== null ? $count - $prev : null,
            ];
            $prev = $count;
        }

        return $result;
    }

    private function buildPatientStats(): array
    {
        $rows = $this->db
            ->table('daily_patients')
            ->select('total_patients')
            ->orderBy('service_date', 'DESC')
            ->limit(7)
            ->get()
            ->getResultArray();

        if (empty($rows)) {
            return ['average' => 0, 'highest' => 0, 'lowest' => 0];
        }

        $values = array_column($rows, 'total_patients');
        $values = array_map('intval', $values);

        return [
            'average' => (int) round(array_sum($values) / count($values)),
            'highest' => (int) max($values),
            'lowest'  => (int) min($values),
        ];
    }

    // -------------------------------------------------------------------------
    // A3: SPK History — enhanced with summary_items
    // -------------------------------------------------------------------------

    private function buildLatestSpkHistory(): array
    {
        $rows = $this->db
            ->table('spk_calculations')
            ->select('id, spk_type, version, calculation_date, target_date_start, target_date_end, target_month, created_at')
            ->where('is_latest', 1)
            ->whereIn('spk_type', [SpkCalculationModel::TYPE_BASAH, SpkCalculationModel::TYPE_KERING_PENGEMAS])
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        $indexed = [
            'basah'            => null,
            'kering_pengemas'  => null,
        ];

        $latestByType = [];
        foreach ($rows as $row) {
            $type = (string) $row['spk_type'];
            if (! array_key_exists($type, $indexed) || isset($latestByType[$type])) {
                continue;
            }
            $latestByType[$type] = $row;
        }

        if (empty($latestByType)) {
            return $indexed;
        }

        // Batch-fetch top recommendations for all found SPK IDs
        $spkIds = array_column(array_values($latestByType), 'id');

        $recRows = $this->db
            ->table('spk_recommendations sr')
            ->select('sr.spk_id, sr.item_id, i.name AS item_name, sr.recommended_qty, i.unit_base AS unit')
            ->join('items i', 'i.id = sr.item_id', 'inner')
            ->whereIn('sr.spk_id', $spkIds)
            ->orderBy('sr.spk_id', 'ASC')
            ->orderBy('sr.recommended_qty', 'DESC')
            ->orderBy('sr.item_id', 'ASC')
            ->get()
            ->getResultArray();

        // Group recommendations by spk_id, keep top 3 per group
        $recBySpk = [];
        foreach ($recRows as $rec) {
            $sid = (int) $rec['spk_id'];
            if (! isset($recBySpk[$sid])) {
                $recBySpk[$sid] = [];
            }
            if (count($recBySpk[$sid]) < 3) {
                $recBySpk[$sid][] = [
                    'item_id'         => (int) $rec['item_id'],
                    'item_name'       => $rec['item_name'],
                    'recommended_qty' => (float) $rec['recommended_qty'],
                    'unit'            => $rec['unit'],
                ];
            }
        }

        foreach ($latestByType as $type => $row) {
            $spkId           = (int) $row['id'];
            $indexed[$type]  = [
                'id'                => $spkId,
                'version'           => (int) $row['version'],
                'calculation_date'  => $row['calculation_date'],
                'target_date_start' => $row['target_date_start'],
                'target_date_end'   => $row['target_date_end'],
                'target_month'      => $row['target_month'],
                'created_at'        => $row['created_at'],
                'summary_items'     => $recBySpk[$spkId] ?? [],
            ];
        }

        return $indexed;
    }

    // -------------------------------------------------------------------------
    // A4: Today Outgoing (new — gudang)
    // -------------------------------------------------------------------------

    private function buildTodayOutgoing(): array
    {
        $outTypeId = $this->transactionTypeModel->getIdByName(TransactionTypeModel::NAME_OUT);

        if ($outTypeId === null) {
            return ['total_items' => 0, 'total_qty' => 0.0, 'recent' => []];
        }

        $rows = $this->db
            ->table('stock_transactions st')
            ->select(
                'std.item_id, i.name AS item_name, SUM(std.qty) AS qty,'
                . ' i.unit_base AS unit, i.qty AS remaining_stock, i.min_stock,'
                . ' CASE'
                . ' WHEN i.qty <= 0 THEN \'danger\''
                . ' WHEN i.min_stock > 0 AND i.qty < i.min_stock * 0.5 THEN \'critical\''
                . ' WHEN i.min_stock > 0 AND i.qty < i.min_stock THEN \'warning\''
                . ' ELSE \'safe\' END AS tone'
            )
            ->join('stock_transaction_details std', 'std.transaction_id = st.id', 'inner')
            ->join('items i', 'i.id = std.item_id', 'inner')
            ->where('st.transaction_date', date('Y-m-d'))
            ->where('st.type_id', $outTypeId)
            ->where('st.deleted_at', null)
            ->where('i.deleted_at', null)
            ->groupBy('std.item_id, i.name, i.unit_base, i.qty, i.min_stock')
            ->orderBy('qty', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $totalQty = 0.0;
        $recent   = [];
        foreach ($rows as $row) {
            $qty = (float) $row['qty'];
            $totalQty += $qty;
            $recent[] = [
                'item_id'         => (int) $row['item_id'],
                'item_name'       => $row['item_name'],
                'qty'             => $qty,
                'unit'            => $row['unit'],
                'remaining_stock' => (float) $row['remaining_stock'],
                'tone'            => $row['tone'],
            ];
        }

        return [
            'total_items' => count($recent),
            'total_qty'   => $totalQty,
            'recent'      => $recent,
        ];
    }

    // -------------------------------------------------------------------------
    // A4: Current Menu Cycle — enhanced with ingredient shortages
    // -------------------------------------------------------------------------

    private function buildCurrentMenuCycle(): array
    {
        $date     = date('Y-m-d');
        $resolved = $this->menuScheduleService->resolveCalendar(['date' => $date]);

        if (! ($resolved['success'] ?? false)) {
            return [
                'date'                    => $date,
                'menu_id'                 => null,
                'menu_name'               => null,
                'assignments'             => [],
                'total_ingredient_items'  => 0,
                'total_required_qty'      => 0.0,
                'sufficient_items'        => 0,
                'insufficient_items'      => 0,
                'top_shortages'           => [],
            ];
        }

        $data        = $resolved['data'];
        $assignments = $data['assignments'] ?? [];

        $firstMenuId   = null;
        $firstMenuName = null;

        if (! empty($assignments)) {
            $firstMenuId = (int) $assignments[0]['menu_id'];
            $menuRow     = $this->db->table('menus')->select('name')->where('id', $firstMenuId)->get()->getRowArray();
            $firstMenuName = $menuRow['name'] ?? ('Paket ' . $firstMenuId);
        }

        // Ingredient shortage calculations
        $shortageData = $this->buildIngredientShortageData($assignments);

        return [
            'date'                   => $data['date'] ?? $date,
            'menu_id'                => $firstMenuId,
            'menu_name'              => $firstMenuName,
            'assignments'            => $assignments,
            'total_ingredient_items' => $shortageData['total_ingredient_items'],
            'total_required_qty'     => $shortageData['total_required_qty'],
            'sufficient_items'       => $shortageData['sufficient_items'],
            'insufficient_items'     => $shortageData['insufficient_items'],
            'top_shortages'          => $shortageData['top_shortages'],
        ];
    }

    private function buildCurrentMenuComposition(array $assignments): array
    {
        if (empty($assignments)) {
            return [];
        }

        $menuIds = array_map(static fn(array $a): int => (int) $a['menu_id'], $assignments);

        $rows = $this->db
            ->table('menu_dishes md')
            ->select('mt.name AS meal_time, d.id AS dish_id, d.name AS dish_name, dc.item_id, i.name AS item_name, dc.qty_per_patient, m.name AS menu_name')
            ->join('meal_times mt', 'mt.id = md.meal_time_id', 'inner')
            ->join('dishes d', 'd.id = md.dish_id', 'inner')
            ->join('menus m', 'm.id = md.menu_id', 'inner')
            ->join('dish_compositions dc', 'dc.dish_id = d.id', 'left')
            ->join('items i', 'i.id = dc.item_id', 'left')
            ->whereIn('md.menu_id', $menuIds)
            ->orderBy('md.meal_time_id', 'ASC')
            ->orderBy('d.id', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn(array $row): array => [
            'meal_time'       => $row['meal_time'],
            'dish_id'         => (int) $row['dish_id'],
            'dish_name'       => $row['dish_name'],
            'item_id'         => $row['item_id'] !== null ? (int) $row['item_id'] : null,
            'item_name'       => $row['item_name'],
            'qty_per_patient' => $row['qty_per_patient'] !== null ? (float) $row['qty_per_patient'] : null,
            'menu_name'       => $row['menu_name'],
        ], $rows);
    }

    // -------------------------------------------------------------------------
    // A4: Ingredient Summary (new — dapur)
    // -------------------------------------------------------------------------

    /**
     * Builds the compact ingredient summary for the dapur dashboard.
     * Accepts the pre-resolved menu cycle array to avoid double-resolving.
     */
    private function buildIngredientSummary(?array $menuCycle): array
    {
        if (empty($menuCycle['assignments'])) {
            return [];
        }

        return $this->buildIngredientRows($menuCycle['assignments']);
    }

    /**
     * Shared ingredient row builder — used by both menu cycle shortages and ingredient summary.
     * Returns all ingredient rows with required/deficit/tone computed server-side.
     */
    private function buildIngredientRows(array $assignments): array
    {
        $menuIds = array_map(static fn(array $a): int => (int) $a['menu_id'], $assignments);

        // Get dish IDs for the menus
        $dishRows = $this->db
            ->table('menu_dishes')
            ->distinct()
            ->select('dish_id')
            ->whereIn('menu_id', $menuIds)
            ->get()
            ->getResultArray();

        $dishIds = array_column($dishRows, 'dish_id');

        if (empty($dishIds)) {
            return [];
        }

        // Get today's patient count
        $patientRow = $this->db
            ->table('daily_patients')
            ->select('total_patients')
            ->where('service_date', date('Y-m-d'))
            ->get()
            ->getRowArray();

        $patientCount = (int) ($patientRow['total_patients'] ?? 0);

        if ($patientCount <= 0) {
            return [];
        }

        $ingredientRows = $this->db
            ->table('dish_compositions dc')
            ->select(
                'dc.item_id, i.name AS item_name, i.unit_base AS unit,'
                . ' i.qty AS current_stock, i.min_stock,'
                . ' SUM(dc.qty_per_patient) * ' . $patientCount . ' AS required_qty'
            )
            ->join('items i', 'i.id = dc.item_id', 'inner')
            ->whereIn('dc.dish_id', $dishIds)
            ->where('i.deleted_at', null)
            ->groupBy('dc.item_id, i.name, i.unit_base, i.qty, i.min_stock')
            ->orderBy('required_qty', 'DESC')
            ->get()
            ->getResultArray();

        return array_map(static fn(array $row): array => [
            'item_id'       => (int) $row['item_id'],
            'item_name'     => $row['item_name'],
            'unit'          => $row['unit'],
            'current_stock' => (float) $row['current_stock'],
            'required'      => (float) $row['required_qty'],
            'deficit'       => max(0.0, (float) $row['required_qty'] - (float) $row['current_stock']),
            'tone'          => self::computeTone((float) $row['current_stock'], (float) $row['min_stock']),
        ], $ingredientRows);
    }

    /**
     * Builds ingredient shortage data for buildCurrentMenuCycle() — for admin view.
     */
    private function buildIngredientShortageData(array $assignments): array
    {
        if (empty($assignments)) {
            return [
                'total_ingredient_items' => 0,
                'total_required_qty'     => 0.0,
                'sufficient_items'       => 0,
                'insufficient_items'     => 0,
                'top_shortages'          => [],
            ];
        }

        $ingredientRows = $this->buildIngredientRows($assignments);

        if (empty($ingredientRows)) {
            return [
                'total_ingredient_items' => 0,
                'total_required_qty'     => 0.0,
                'sufficient_items'       => 0,
                'insufficient_items'     => 0,
                'top_shortages'          => [],
            ];
        }

        $totalRequired = array_sum(array_column($ingredientRows, 'required'));
        $sufficient    = count(array_filter($ingredientRows, static fn($r) => $r['deficit'] <= 0));
        $insufficient  = count($ingredientRows) - $sufficient;

        // Top 5 shortages by deficit descending
        $shortages = array_filter($ingredientRows, static fn($r) => $r['deficit'] > 0);
        usort($shortages, static fn($a, $b) => $b['deficit'] <=> $a['deficit']);
        $topShortages = array_slice(array_values($shortages), 0, 5);

        return [
            'total_ingredient_items' => count($ingredientRows),
            'total_required_qty'     => (float) $totalRequired,
            'sufficient_items'       => $sufficient,
            'insufficient_items'     => $insufficient,
            'top_shortages'          => array_map(static fn($r): array => [
                'item_id'       => $r['item_id'],
                'item_name'     => $r['item_name'],
                'unit_base'     => $r['unit'],
                'current_stock' => $r['current_stock'],
                'required'      => $r['required'],
                'tone'          => $r['tone'],
            ], $topShortages),
        ];
    }

    private function buildSpendingTrend(): array
    {
        $today = new DateTimeImmutable('today');
        $from  = $today->modify('-6 days')->format('Y-m-d');
        $to    = $today->format('Y-m-d');

        $rows = $this->db
            ->table('stock_transactions st')
            ->select('st.transaction_date, COALESCE(SUM(std.qty), 0) AS total_out_qty')
            ->join('stock_transaction_details std', 'std.transaction_id = st.id', 'inner')
            ->where('st.deleted_at', null)
            ->where('st.type_id', 2)
            ->where('st.transaction_date >=', $from)
            ->where('st.transaction_date <=', $to)
            ->groupBy('st.transaction_date')
            ->orderBy('st.transaction_date', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(static fn(array $row): array => [
            'date'          => $row['transaction_date'],
            'total_out_qty' => (float) $row['total_out_qty'],
        ], $rows);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private static function computeTone(float $qty, float $minStock): string
    {
        if ($qty <= self::TONE_DANGER) {
            return 'danger';
        }
        if ($minStock > 0 && $qty < $minStock * self::TONE_CRITICAL) {
            return 'critical';
        }
        if ($minStock > 0 && $qty < $minStock) {
            return 'warning';
        }

        return 'safe';
    }
}
