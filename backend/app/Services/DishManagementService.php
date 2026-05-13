<?php

namespace App\Services;

use App\Models\DishCompositionModel;
use App\Models\DishModel;
use App\Models\MenuDishModel;

class DishManagementService
{
    private const ALLOWED_QUERY_PARAMS = [
        'paginate',
        'page',
        'perPage',
        'q',
        'search',
        'sortBy',
        'sortDir',
        'created_at_from',
        'created_at_to',
        'updated_at_from',
        'updated_at_to',
    ];

    protected DishModel $dishModel;
    protected DishCompositionModel $dishCompositionModel;
    protected MenuDishModel $menuDishModel;

    public function __construct()
    {
        $this->dishModel           = new DishModel();
        $this->dishCompositionModel = new DishCompositionModel();
        $this->menuDishModel       = new MenuDishModel();
    }

    public function getAllDishes(array $queryParams): array
    {
        $unknownParams = array_diff(array_keys($queryParams), self::ALLOWED_QUERY_PARAMS);

        if ($unknownParams !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'query' => 'Unsupported query parameter(s): ' . implode(', ', $unknownParams),
                ],
            ];
        }

        $queryErrors = $this->validateListQueryValues($queryParams);
        if ($queryErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $queryErrors,
            ];
        }

        $page          = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage       = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $paginate      = $this->shouldPaginate($queryParams['paginate'] ?? null);
        $search        = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $requestedSortBy = (string) ($queryParams['sortBy'] ?? 'name');
        $sortBy        = in_array($requestedSortBy, DishModel::SORTABLE_COLUMNS, true)
            ? $requestedSortBy
            : 'name';
        $sortDir       = strtoupper((string) ($queryParams['sortDir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $result = $this->dishModel->getAllDishes(
            $page,
            $perPage,
            $paginate,
            $search,
            $sortBy,
            $sortDir,
            $queryParams['created_at_from'] ?? null,
            $queryParams['created_at_to'] ?? null,
            $queryParams['updated_at_from'] ?? null,
            $queryParams['updated_at_to'] ?? null,
        );

        return [
            'success' => true,
            'data'    => $result['dishes'],
            'meta'    => [
                'page'       => $result['page'],
                'perPage'    => $result['perPage'],
                'total'      => $result['total'],
                'totalPages' => $result['totalPages'],
                'paginated'  => $paginate,
            ],
        ];
    }

    public function getDishById(int $id): ?array
    {
        return $this->dishModel->findById($id);
    }

    public function createDish(array $data): array
    {
        $validation = service('validation');

        if (! $validation->setRules(['name' => 'required|max_length[100]'])->run($data)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validation->getErrors(),
            ];
        }

        $name = trim((string) $data['name']);

        if ($this->dishModel->nameExists($name)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['name' => 'The name has already been taken.'],
            ];
        }

        $created = $this->dishModel->insert(['name' => $name], true);

        if ($created === false) {
            return [
                'success' => false,
                'message' => 'Failed to create dish.',
                'errors'  => $this->dishModel->errors(),
            ];
        }

        return [
            'success' => true,
            'dish'    => $this->dishModel->find((int) $created),
        ];
    }

    public function updateDish(int $id, array $data): array
    {
        $existing = $this->dishModel->findById($id);

        if ($existing === null) {
            return [
                'success' => false,
                'message' => 'Dish not found.',
            ];
        }

        $validation = service('validation');

        if (! $validation->setRules(['name' => 'permit_empty|max_length[100]'])->run($data)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validation->getErrors(),
            ];
        }

        if (! array_key_exists('name', $data)) {
            return [
                'success' => true,
                'dish'    => $existing,
            ];
        }

        $name = trim((string) $data['name']);
        if ($name === '') {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['name' => 'The name field is required.'],
            ];
        }

        if ($this->dishModel->nameExists($name, $id)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['name' => 'The name has already been taken.'],
            ];
        }

        if (! $this->dishModel->update($id, ['name' => $name])) {
            return [
                'success' => false,
                'message' => 'Failed to update dish.',
                'errors'  => $this->dishModel->errors(),
            ];
        }

        return [
            'success' => true,
            'dish'    => $this->dishModel->findById($id),
        ];
    }

    public function deleteDish(int $id): array
    {
        $existing = $this->dishModel->findById($id);

        if ($existing === null) {
            return [
                'success' => false,
                'message' => 'Dish not found.',
            ];
        }

        if ($this->dishCompositionModel->countByDishId($id) > 0 || $this->menuDishModel->countByDishId($id) > 0) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['dish_id' => 'The dish is still referenced by menu compositions or menu slots.'],
            ];
        }

        if (! $this->dishModel->delete($id)) {
            return [
                'success' => false,
                'message' => 'Failed to delete dish.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Dish deleted successfully.',
        ];
    }

    private function validateListQueryValues(array $queryParams): array
    {
        $errors = [];

        if (isset($queryParams['page']) && (! ctype_digit((string) $queryParams['page']) || (int) $queryParams['page'] < 1)) {
            $errors['page'] = 'The page field must be a positive integer.';
        }

        if (isset($queryParams['perPage']) && (! ctype_digit((string) $queryParams['perPage']) || (int) $queryParams['perPage'] < 1 || (int) $queryParams['perPage'] > 100)) {
            $errors['perPage'] = 'The perPage field must be an integer between 1 and 100.';
        }

        if (isset($queryParams['paginate']) && ! in_array(strtolower((string) $queryParams['paginate']), ['true', 'false', '1', '0'], true)) {
            $errors['paginate'] = 'The paginate field must be a boolean value.';
        }

        if (isset($queryParams['sortBy']) && ! in_array($queryParams['sortBy'], DishModel::SORTABLE_COLUMNS, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', DishModel::SORTABLE_COLUMNS) . '.';
        }

        if (isset($queryParams['sortDir']) && ! in_array(strtoupper((string) $queryParams['sortDir']), ['ASC', 'DESC'], true)) {
            $errors['sortDir'] = 'The sortDir field must be ASC or DESC.';
        }

        foreach (['created_at_from', 'created_at_to', 'updated_at_from', 'updated_at_to'] as $dateField) {
            if (isset($queryParams[$dateField]) && strtotime((string) $queryParams[$dateField]) === false) {
                $errors[$dateField] = sprintf('The %s field must be a valid date/datetime string.', $dateField);
            }
        }

        return $errors;
    }

    private function shouldPaginate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return ! in_array(strtolower((string) $value), ['false', '0'], true);
    }
}
