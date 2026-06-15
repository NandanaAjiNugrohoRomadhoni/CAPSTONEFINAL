<?php

namespace App\Services;

use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class OperationalStockPreviewService
{
    protected MenuScheduleManagementService $menuScheduleService;
    protected ItemCategoryModel $itemCategoryModel;
    protected ItemModel $itemModel;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->menuScheduleService = new MenuScheduleManagementService();
        $this->itemCategoryModel   = new ItemCategoryModel();
        $this->itemModel           = new ItemModel();
        $this->db                  = Database::connect();
    }

    public function previewSameDay(array $data): array
    {
        $validated = $this->validatePayload($data);
        if (! $validated['success']) {
            return $validated;
        }

        $serviceDate   = $validated['service_date'];
        $mealTime      = $validated['meal_time'];
        $totalPatients = $validated['total_patients'];

        $mealTimeId = $this->resolveMealTimeId($mealTime);
        if ($mealTimeId === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'meal_time' => 'The selected meal_time is invalid.',
                ],
            ];
        }

        $basahCategoryId = $this->itemCategoryModel->getIdByName(ItemCategoryModel::NAME_BASAH);
        if ($basahCategoryId === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'category' => 'BASAH item category is not configured.',
                ],
            ];
        }

        $menuProjection = $this->menuScheduleService->resolveCalendar(['date' => $serviceDate]);
        if (! $menuProjection['success']) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'menu_schedule' => sprintf('Menu schedule could not be resolved for service date %s.', $serviceDate),
                ],
            ];
        }

        $assignments = $menuProjection['data']['assignments'] ?? [];
        if (empty($assignments)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'menu_mapping' => sprintf('No menu mapping found for service date %s.', $serviceDate),
                ],
            ];
        }

        $requiredByItem = [];
        $menuInfo       = [];

        foreach ($assignments as $assignment) {
            $menuId = (int) $assignment['menu_id'];

            // Additive model: all patients get all menus
            $patientsForThisMenu = $totalPatients;

            if ($patientsForThisMenu <= 0) {
                continue;
            }

            // Capture menu info for response summary
            $menuRow = $this->db->table('menus')->select('name')->where('id', $menuId)->get()->getRowArray();
            $menuInfo[] = [
                'id'            => $menuId,
                'name'          => $menuRow['name'] ?? ('Paket ' . $menuId),
                'patient_count' => $patientsForThisMenu,
            ];

            $menuDishes = $this->db
                ->table('menu_dishes')
                ->select('dish_id')
                ->where('menu_id', $menuId)
                ->where('meal_time_id', $mealTimeId)
                ->get()
                ->getResultArray();

            foreach ($menuDishes as $menuDish) {
                $dishId = (int) $menuDish['dish_id'];
                $compositions = $this->db
                    ->table('dish_compositions')
                    ->select('item_id, qty_per_patient')
                    ->where('dish_id', $dishId)
                    ->get()
                    ->getResultArray();

                foreach ($compositions as $composition) {
                    $itemId = (int) $composition['item_id'];
                    $item   = $this->itemModel->find($itemId);

                    if ($item === null || (int) $item['item_category_id'] !== $basahCategoryId) {
                        continue;
                    }

                    $requiredQty = ((float) $composition['qty_per_patient']) * $patientsForThisMenu;
                    $requiredByItem[$itemId] = ($requiredByItem[$itemId] ?? 0.0) + $requiredQty;
                }
            }
        }

        if ($requiredByItem === []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'menu_mapping' => sprintf('No required items found for meal_time %s on %s.', $mealTime, $serviceDate),
                ],
            ];
        }

        ksort($requiredByItem);

        $items             = [];
        $totalRequiredQty  = 0.0;
        $totalProjectedOut = 0.0;
        $totalShortageQty  = 0.0;

        foreach ($requiredByItem as $itemId => $requiredQty) {
            $item = $this->itemModel->find((int) $itemId);
            if ($item === null) {
                continue;
            }

            $currentStockQty     = (float) ($item['qty'] ?? 0.0);
            $projectedRemaining  = max(0.0, $currentStockQty - $requiredQty);
            $projectedShortage   = max(0.0, $requiredQty - $currentStockQty);

            $totalRequiredQty  += $requiredQty;
            $totalProjectedOut += $requiredQty;
            $totalShortageQty  += $projectedShortage;

            $items[] = [
                'item_id'                        => (int) $itemId,
                'item_name'                      => (string) ($item['name'] ?? ''),
                'item_unit_base'                 => $item['unit_base'] ?? null,
                'item_unit_convert'              => $item['unit_convert'] ?? null,
                'current_stock_qty'              => $currentStockQty,
                'required_qty'                   => $requiredQty,
                'projected_stock_out_qty'        => $requiredQty,
                'projected_remaining_stock_qty'  => $projectedRemaining,
                'projected_shortage_qty'         => $projectedShortage,
            ];
        }

        return [
            'success' => true,
            'data'    => [
                'service_date'       => $serviceDate,
                'meal_time'          => $mealTime,
                'total_patients'     => $totalPatients,
                'menu'               => $menuInfo[0] ?? null,
                'menus'              => $menuInfo,
                'items'              => $items,
                'summary'            => [
                    'total_items'              => count($items),
                    'total_required_qty'       => $totalRequiredQty,
                    'total_projected_stock_out_qty' => $totalProjectedOut,
                    'total_projected_shortage_qty'  => $totalShortageQty,
                ],
            ],
        ];
    }

    private function validatePayload(array $data): array
    {
        $mealTime = strtoupper(trim((string) ($data['meal_time'] ?? '')));
        $rules = [
            'service_date'   => 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
            'meal_time'      => 'required|max_length[50]',
            'total_patients' => 'required|integer|greater_than_equal_to[0]',
        ];

        $validation = service('validation');
        $payload    = [
            'service_date'   => (string) ($data['service_date'] ?? ''),
            'meal_time'      => $mealTime,
            'total_patients' => $data['total_patients'] ?? null,
        ];

        if (! $validation->setRules($rules)->run($payload)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validation->getErrors(),
            ];
        }

        if (! $this->isValidDate($payload['service_date'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'service_date' => 'The service_date field must be a valid date in Y-m-d format.',
                ],
            ];
        }

        return [
            'success'        => true,
            'service_date'   => $payload['service_date'],
            'meal_time'      => $mealTime,
            'total_patients' => (int) $payload['total_patients'],
        ];
    }

    private function resolveMealTimeId(string $mealTime): ?int
    {
        $rows = $this->db
            ->table('meal_times')
            ->select('id, name')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            if (strtoupper(trim((string) ($row['name'] ?? ''))) === $mealTime) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    private function isValidDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }
}
