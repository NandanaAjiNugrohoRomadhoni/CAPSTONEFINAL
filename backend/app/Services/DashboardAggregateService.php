<?php

namespace App\Services;

use App\Models\AppUserProvider;
use App\Models\ItemCategoryModel;
use App\Models\SpkCalculationModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;

class DashboardAggregateService
{
    protected BaseConnection $db;
    protected AppUserProvider $userProvider;
    protected ItemCategoryModel $itemCategoryModel;
    protected MenuScheduleManagementService $menuScheduleService;

    public function __construct()
    {
        $this->db                  = Database::connect();
        $this->userProvider        = new AppUserProvider();
        $this->itemCategoryModel   = new ItemCategoryModel();
        $this->menuScheduleService = new MenuScheduleManagementService();
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

        $stockSummary      = $this->buildStockSummary();
        $dryStockStatus    = $this->buildDryStockStatus();
        $latestSpkHistory  = $this->buildLatestSpkHistory();
        $currentMenuCycle  = $this->buildCurrentMenuCycle();

        $payload = match ($roleName) {
            'admin' => [
                'stock_summary'       => $stockSummary,
                'dry_stock_status'    => $dryStockStatus,
                'spending_trend'      => $this->buildSpendingTrend(),
                'current_menu_cycle'  => $currentMenuCycle,
                'latest_spk_history'  => $latestSpkHistory,
                'patient_fluctuation' => $this->buildPatientFluctuation(),
            ],
            'gudang' => [
                'stock_summary'       => $stockSummary,
                'dry_stock_status'    => $dryStockStatus,
                'spending_trend'      => $this->buildSpendingTrend(),
                'latest_spk_history'  => $latestSpkHistory,
                'patient_fluctuation' => $this->buildPatientFluctuation(),
            ],
            default => [
                'current_menu_cycle'       => $currentMenuCycle,
                'current_menu_composition' => $this->buildCurrentMenuComposition($currentMenuCycle['menu_id'] ?? null),
                'latest_spk_history'       => $latestSpkHistory,
                'stock_summary'            => $stockSummary,
                'dry_stock_status'         => $dryStockStatus,
            ],
        };

        return [
            'success' => true,
            'data'    => [
                'role'      => $roleName,
                'generated_at' => date('Y-m-d H:i:s'),
                'aggregates' => $payload,
            ],
        ];
    }

    private function buildStockSummary(): array
    {
        $rows = $this->db
            ->table('items')
            ->select('COUNT(*) AS total_items, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_items, SUM(CASE WHEN qty <= 0 THEN 1 ELSE 0 END) AS zero_stock_items, COALESCE(SUM(qty), 0) AS total_stock_qty')
            ->where('deleted_at', null)
            ->get()
            ->getRowArray() ?? [];

        return [
            'total_items'     => (int) ($rows['total_items'] ?? 0),
            'active_items'    => (int) ($rows['active_items'] ?? 0),
            'zero_stock_items' => (int) ($rows['zero_stock_items'] ?? 0),
            'total_stock_qty' => (float) ($rows['total_stock_qty'] ?? 0),
        ];
    }

    private function buildDryStockStatus(): array
    {
        $keringCategoryId = $this->itemCategoryModel->getIdByName(ItemCategoryModel::NAME_KERING);

        if ($keringCategoryId === null) {
            return [
                'status'          => 'AMAN',
                'total_items'     => 0,
                'zero_stock_items' => 0,
                'total_stock_qty' => 0.0,
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
            'status'          => $zeroStock > 0 ? 'KRITIS' : 'AMAN',
            'total_items'     => $totalItems,
            'zero_stock_items' => $zeroStock,
            'total_stock_qty' => (float) ($rows['total_stock_qty'] ?? 0),
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
            'date' => $row['transaction_date'],
            'total_out_qty' => (float) $row['total_out_qty'],
        ], $rows);
    }

    private function buildPatientFluctuation(): array
    {
        $rows = $this->db
            ->table('daily_patients')
            ->select('service_date, total_patients')
            ->orderBy('service_date', 'DESC')
            ->limit(7)
            ->get()
            ->getResultArray();

        $rows = array_reverse($rows);

        return array_map(static fn(array $row): array => [
            'service_date' => $row['service_date'],
            'total_patients' => (int) $row['total_patients'],
        ], $rows);
    }

    private function buildCurrentMenuCycle(): array
    {
        $date = date('Y-m-d');
        $resolved = $this->menuScheduleService->resolveCalendar(['date' => $date]);

        if (! ($resolved['success'] ?? false)) {
            return [
                'date'     => $date,
                'menu_id'  => null,
                'menu_name' => null,
            ];
        }

        $data = $resolved['data'];

        return [
            'date'      => $data['date'] ?? $date,
            'menu_id'   => isset($data['menu_id']) ? (int) $data['menu_id'] : null,
            'menu_name' => $data['menu_name'] ?? null,
        ];
    }

    private function buildCurrentMenuComposition(?int $menuId): array
    {
        if ($menuId === null) {
            return [];
        }

        $rows = $this->db
            ->table('menu_dishes md')
            ->select('mt.name AS meal_time, d.id AS dish_id, d.name AS dish_name, dc.item_id, i.name AS item_name, dc.qty_per_patient')
            ->join('meal_times mt', 'mt.id = md.meal_time_id', 'inner')
            ->join('dishes d', 'd.id = md.dish_id', 'inner')
            ->join('dish_compositions dc', 'dc.dish_id = d.id', 'left')
            ->join('items i', 'i.id = dc.item_id', 'left')
            ->where('md.menu_id', $menuId)
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
        ], $rows);
    }

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
            'basah' => null,
            'kering_pengemas' => null,
        ];

        foreach ($rows as $row) {
            $type = (string) $row['spk_type'];
            if (! array_key_exists($type, $indexed) || $indexed[$type] !== null) {
                continue;
            }

            $indexed[$type] = [
                'id'                => (int) $row['id'],
                'version'           => (int) $row['version'],
                'calculation_date'  => $row['calculation_date'],
                'target_date_start' => $row['target_date_start'],
                'target_date_end'   => $row['target_date_end'],
                'target_month'      => $row['target_month'],
                'created_at'        => $row['created_at'],
            ];
        }

        return $indexed;
    }
}
