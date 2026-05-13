<?php

namespace App\Services;

use App\Models\DailyPatientModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\SpkCalculationModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;

class SpkBasahGenerationService
{
    protected DailyPatientModel $dailyPatientModel;
    protected ItemCategoryModel $itemCategoryModel;
    protected ItemModel $itemModel;
    protected MenuScheduleManagementService $menuScheduleService;
    protected SpkPersistenceService $spkPersistenceService;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->dailyPatientModel      = new DailyPatientModel();
        $this->itemCategoryModel      = new ItemCategoryModel();
        $this->itemModel              = new ItemModel();
        $this->menuScheduleService    = new MenuScheduleManagementService();
        $this->spkPersistenceService  = new SpkPersistenceService();
        $this->db                     = Database::connect();
    }

    public function generate(array $data, int $userId): array
    {
        $validationResult = $this->validateGeneratePayload($data);
        if (! $validationResult['success']) {
            return $validationResult;
        }

        $serviceDate = $validationResult['service_date'];

        $dailyPatient = $this->dailyPatientModel->findByServiceDate($serviceDate);
        if ($dailyPatient === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'service_date' => 'Daily patient input for the requested service_date is required before generating SPK basah.',
                ],
            ];
        }

        $requestedDate = new DateTimeImmutable($serviceDate);
        $targetDates   = $this->resolveBasahTargetDates($requestedDate);

        $adjustedPatients = (int) ceil(((int) $dailyPatient['total_patients']) * 1.05);
        $basahCategoryId  = $this->itemCategoryModel->getIdByName(ItemCategoryModel::NAME_BASAH);

        if ($basahCategoryId === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'category' => 'BASAH item category is not configured.',
                ],
            ];
        }

        $requirementsBuild = $this->buildPerDateRequirements($targetDates, $adjustedPatients, $basahCategoryId);
        if (! $requirementsBuild['success']) {
            return $requirementsBuild;
        }

        $recommendations = $this->buildRecommendations(
            $targetDates,
            $requirementsBuild['required_by_date'],
            $requirementsBuild['current_stock_by_item']
        );

        $persisted = $this->spkPersistenceService->createVersionedSpk([
            'spk_type'           => SpkCalculationModel::TYPE_BASAH,
            'calculation_scope'  => SpkCalculationModel::SCOPE_COMBINED_WINDOW,
            'calculation_date'   => $serviceDate,
            'target_date_start'  => $targetDates[0],
            'target_date_end'    => $targetDates[count($targetDates) - 1],
            'daily_patient_id'   => (int) $dailyPatient['id'],
            'user_id'            => $userId,
            'category_id'        => $basahCategoryId,
            'estimated_patients' => $adjustedPatients,
            'is_finish'          => false,
        ], $recommendations);

        if (! $persisted['success']) {
            return $persisted;
        }

        return [
            'success' => true,
            'data'    => [
                'id'                => (int) $persisted['data']['id'],
                'version'           => (int) $persisted['data']['version'],
                'scope_key'         => (string) $persisted['data']['scope_key'],
                'target_dates'      => $targetDates,
                'estimated_patients' => $adjustedPatients,
            ],
        ];
    }

    private function validateGeneratePayload(array $data): array
    {
        $rules = [
            'service_date' => 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
        ];

        $validation = service('validation');
        $payload    = [
            'service_date' => (string) ($data['service_date'] ?? ''),
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
            'success'      => true,
            'service_date' => $payload['service_date'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveBasahTargetDates(DateTimeImmutable $requestedDate): array
    {
        $dates = [$requestedDate->format('Y-m-d')];
        $next  = $requestedDate->modify('+1 day');

        if ($requestedDate->format('Y-m') === $next->format('Y-m')) {
            $dates[] = $next->format('Y-m-d');
        }

        return $dates;
    }

    private function buildPerDateRequirements(array $targetDates, int $adjustedPatients, int $basahCategoryId): array
    {
        $requiredByDate   = [];
        $currentStockByItem = [];

        foreach ($targetDates as $targetDate) {
            $menuProjection = $this->menuScheduleService->resolveCalendar(['date' => $targetDate]);
            if (! $menuProjection['success']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'menu_schedule' => sprintf('Menu schedule could not be resolved for target date %s.', $targetDate),
                    ],
                ];
            }

            $menuId = (int) ($menuProjection['data']['menu_id'] ?? 0);
            $menuDishes = $this->db
                ->table('menu_dishes')
                ->select('id, dish_id')
                ->where('menu_id', $menuId)
                ->get()
                ->getResultArray();

            if ($menuDishes === []) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'menu_mapping' => sprintf('Menu %d has no dish mapping for target date %s.', $menuId, $targetDate),
                    ],
                ];
            }

            foreach ($menuDishes as $menuDish) {
                $dishId = (int) $menuDish['dish_id'];
                $compositions = $this->db
                    ->table('dish_compositions')
                    ->select('item_id, qty_per_patient')
                    ->where('dish_id', $dishId)
                    ->get()
                    ->getResultArray();

                if ($compositions === []) {
                    return [
                        'success' => false,
                        'message' => 'Validation failed.',
                        'errors'  => [
                            'recipe_mapping' => sprintf('Dish %d has no item composition for target date %s.', $dishId, $targetDate),
                        ],
                    ];
                }

                foreach ($compositions as $composition) {
                    $itemId = (int) $composition['item_id'];
                    $item   = $this->itemModel->find($itemId);

                    if ($item === null) {
                        return [
                            'success' => false,
                            'message' => 'Validation failed.',
                            'errors'  => [
                                'recipe_mapping' => sprintf('Dish %d references unavailable item %d.', $dishId, $itemId),
                            ],
                        ];
                    }

                    if ((int) $item['item_category_id'] !== $basahCategoryId) {
                        continue;
                    }

                    $requiredQty = ((float) $composition['qty_per_patient']) * $adjustedPatients;
                    if (! isset($requiredByDate[$targetDate])) {
                        $requiredByDate[$targetDate] = [];
                    }

                    $requiredByDate[$targetDate][$itemId] = ($requiredByDate[$targetDate][$itemId] ?? 0.0) + $requiredQty;

                    if (! isset($currentStockByItem[$itemId])) {
                        $currentStockByItem[$itemId] = (float) ($item['qty'] ?? 0);
                    }
                }
            }
        }

        return [
            'success'              => true,
            'required_by_date'     => $requiredByDate,
            'current_stock_by_item' => $currentStockByItem,
        ];
    }

    /**
     * @param array<int, string> $targetDates
     * @param array<string, array<int, float>> $requiredByDate
     * @param array<int, float> $currentStockByItem
     * @return array<int, array<string, mixed>>
     */
    private function buildRecommendations(array $targetDates, array $requiredByDate, array $currentStockByItem): array
    {
        $itemIds = array_keys($currentStockByItem);
        sort($itemIds);

        $rows = [];

        foreach ($itemIds as $itemId) {
            $initialStock   = (float) $currentStockByItem[$itemId];
            $remainingStock = $initialStock;

            foreach ($targetDates as $targetDate) {
                $requiredQty = (float) ($requiredByDate[$targetDate][$itemId] ?? 0.0);
                if ($requiredQty <= 0.0) {
                    continue;
                }

                $systemRecommended = max(0.0, $requiredQty - $remainingStock);
                $remainingStock    = max(0.0, $remainingStock - $requiredQty);

                $rows[] = [
                    'item_id'                => (int) $itemId,
                    'target_date'            => $targetDate,
                    'current_stock_qty'      => $initialStock,
                    'required_qty'           => $requiredQty,
                    'system_recommended_qty' => $systemRecommended,
                    'recommended_qty'        => $systemRecommended,
                ];
            }
        }

        return $rows;
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
