<?php

namespace App\Services;

use App\Models\DailyPatientModel;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\SpkCalculationModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use InvalidArgumentException;

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
        $this->dailyPatientModel = new DailyPatientModel();
        $this->itemCategoryModel = new ItemCategoryModel();
        $this->itemModel = new ItemModel();
        $this->menuScheduleService = new MenuScheduleManagementService();
        $this->spkPersistenceService = new SpkPersistenceService();
        $this->db = Database::connect();
    }

    public function generate(array $data, int $userId): array
    {
        $validationResult = $this->validateGeneratePayload($data);
        if (!$validationResult['success']) {
            return $validationResult;
        }

        $serviceDate = $validationResult['service_date'];

        $dailyPatient = $this->dailyPatientModel->findByServiceDate($serviceDate);
        if ($dailyPatient === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'service_date' => 'Daily patient input for the requested service_date is required before generating SPK basah.',
                ],
            ];
        }

        $requestedDate = new DateTimeImmutable($serviceDate);
        try {
            $targetDates = $this->resolveBasahTargetDates($requestedDate);
        } catch (InvalidArgumentException $exception) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'service_date' => $exception->getMessage(),
                ],
            ];
        }

        $estimatedPatients = (int) $dailyPatient['total_patients'];
        $basahCategoryId = $this->itemCategoryModel->getIdByName(ItemCategoryModel::NAME_BASAH);

        if ($basahCategoryId === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'category' => 'BASAH item category is not configured.',
                ],
            ];
        }

        $requirementsBuild = $this->buildPerDateRequirements($targetDates, $estimatedPatients, $basahCategoryId);
        if (!$requirementsBuild['success']) {
            return $requirementsBuild;
        }

        $recommendations = $this->buildRecommendations(
            $targetDates,
            $requirementsBuild['required_by_date'],
            $requirementsBuild['current_stock_by_item']
        );

        $persisted = $this->spkPersistenceService->createVersionedSpk([
            'spk_type' => SpkCalculationModel::TYPE_BASAH,
            'calculation_scope' => SpkCalculationModel::SCOPE_COMBINED_WINDOW,
            'calculation_date' => $serviceDate,
            'target_date_start' => $targetDates[0],
            'target_date_end' => $targetDates[count($targetDates) - 1],
            'daily_patient_id' => (int) $dailyPatient['id'],
            'user_id' => $userId,
            'category_id' => $basahCategoryId,
            'estimated_patients' => $estimatedPatients,
            'is_finish' => false,
            'regenerate' => (bool) ($data['regenerate'] ?? false),
        ], $recommendations);

        if (!$persisted['success']) {
            return $persisted;
        }

        return [
            'success' => true,
            'data' => [
                'id' => (int) $persisted['data']['id'],
                'version' => (int) $persisted['data']['version'],
                'scope_key' => (string) $persisted['data']['scope_key'],
                'target_dates' => $targetDates,
                'estimated_patients' => $estimatedPatients,
            ],
        ];
    }

    private function validateGeneratePayload(array $data): array
    {
        if (array_key_exists('regenerate', $data) && !is_bool($data['regenerate'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'regenerate' => 'The regenerate field must be boolean.',
                ],
            ];
        }

        $rules = [
            'service_date' => 'required|regex_match[/^\d{4}-\d{2}-\d{2}$/]',
        ];

        $validation = service('validation');
        $payload = [
            'service_date' => (string) ($data['service_date'] ?? ''),
        ];

        if (!$validation->setRules($rules)->run($payload)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validation->getErrors(),
            ];
        }

        if (!$this->isValidDate($payload['service_date'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'service_date' => 'The service_date field must be a valid date in Y-m-d format.',
                ],
            ];
        }

        $serviceDate = new DateTimeImmutable($payload['service_date']);
        if (!$this->isAllowedBasahGenerationDate($serviceDate)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'service_date' => 'SPK basah generation is allowed on even days, or on special dates (31 and leap-year February 29).',
                ],
            ];
        }

        return [
            'success' => true,
            'service_date' => $payload['service_date'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveBasahTargetDates(DateTimeImmutable $requestedDate): array
    {
        $day = (int) $requestedDate->format('j');
        $month = (int) $requestedDate->format('n');
        $year = (int) $requestedDate->format('Y');
        $daysInMonth = (int) $requestedDate->format('t');
        $isLeapFebruary = $month === 2 && checkdate(2, 29, $year);

        // 31-day month boundary: day 30 only prepares day 31.
        if ($day === 30 && $daysInMonth === 31) {
            return [$requestedDate->modify('+1 day')->format('Y-m-d')];
        }

        // Leap-year February boundary: day 28 only prepares day 29.
        if ($day === 28 && $isLeapFebruary) {
            return [$requestedDate->modify('+1 day')->format('Y-m-d')];
        }

        if ($day % 2 === 0 || $day === 31 || ($month === 2 && $day === 29)) {
            return [
                $requestedDate->modify('+1 day')->format('Y-m-d'),
                $requestedDate->modify('+2 day')->format('Y-m-d'),
            ];
        }

        throw new InvalidArgumentException('The selected service_date is not a valid SPK basah generation date.');
    }

    private function isAllowedBasahGenerationDate(DateTimeImmutable $requestedDate): bool
    {
        $day = (int) $requestedDate->format('j');
        $month = (int) $requestedDate->format('n');

        return $day % 2 === 0 || $day === 31 || ($month === 2 && $day === 29);
    }

    private function buildPerDateRequirements(array $targetDates, int $estimatedPatients, int $basahCategoryId): array
    {
        $requiredByDate = [];
        $currentStockByItem = [];
        // Compute the buffered patient count once — it is constant for the entire SPK generation.
        $patientCountWithBuffer = $estimatedPatients + (int) ceil($estimatedPatients * 0.05);

        foreach ($targetDates as $targetDate) {
            $menuProjection = $this->menuScheduleService->resolveCalendar(['date' => $targetDate]);
            if (!$menuProjection['success']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        'menu_schedule' => sprintf('Menu schedule could not be resolved for target date %s.', $targetDate),
                    ],
                ];
            }

            $assignments = $menuProjection['data']['assignments'] ?? [];
            if (empty($assignments)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => [
                        'menu_mapping' => sprintf('No menu mapping found for target date %s.', $targetDate),
                    ],
                ];
            }

            // Process each assignment independently so that:
            // - the same menu assigned twice contributes its ingredients twice, and
            // - two different menus sharing a dish each contribute that dish's ingredients separately.
            foreach ($assignments as $assignment) {
                $menuId = (int) $assignment['menu_id'];
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
                        'errors' => [
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
                            'errors' => [
                                'recipe_mapping' => sprintf('Dish %d has no item composition for target date %s.', $dishId, $targetDate),
                            ],
                        ];
                    }

                    foreach ($compositions as $composition) {
                        $itemId = (int) $composition['item_id'];
                        $item = $this->itemModel->find($itemId);

                        if ($item === null) {
                            return [
                                'success' => false,
                                'message' => 'Validation failed.',
                                'errors' => [
                                    'recipe_mapping' => sprintf('Dish %d references unavailable item %d.', $dishId, $itemId),
                                ],
                            ];
                        }

                        if ((int) $item['item_category_id'] !== $basahCategoryId) {
                            continue;
                        }

                        $requiredQty = ceil(((float) $composition['qty_per_patient']) * $patientCountWithBuffer);
                        if (!isset($requiredByDate[$targetDate])) {
                            $requiredByDate[$targetDate] = [];
                        }

                        $requiredByDate[$targetDate][$itemId] = ($requiredByDate[$targetDate][$itemId] ?? 0.0) + $requiredQty;

                        if (!isset($currentStockByItem[$itemId])) {
                            $currentStockByItem[$itemId] = (float) ($item['qty'] ?? 0);
                        }
                    }
                }
            }
        }

        return [
            'success' => true,
            'required_by_date' => $requiredByDate,
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
            $initialStock = (float) $currentStockByItem[$itemId];
            $remainingStock = $initialStock;

            foreach ($targetDates as $targetDate) {
                $rawRequiredQty = (float) ($requiredByDate[$targetDate][$itemId] ?? 0.0);
                if ($rawRequiredQty <= 0.0) {
                    continue;
                }

                // Carry forward required qty already buffered per patient count.
                $requiredQty = round($rawRequiredQty, 4);

                $stockBeforeDay = $remainingStock;
                $systemRecommended = max(0.0, $requiredQty - $stockBeforeDay);
                $remainingStock = max(0.0, $remainingStock - $requiredQty);

                $rows[] = [
                    'item_id' => (int) $itemId,
                    'target_date' => $targetDate,
                    'current_stock_qty' => $stockBeforeDay,
                    'required_qty' => $requiredQty,
                    'system_recommended_qty' => $systemRecommended,
                    'recommended_qty' => $systemRecommended,
                ];
            }
        }

        return $rows;
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }
}
