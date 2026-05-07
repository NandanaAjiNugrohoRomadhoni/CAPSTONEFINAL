<?php

namespace App\Services;

use App\Models\DishCompositionModel;
use App\Models\DishModel;
use App\Models\ItemModel;

class DishCompositionManagementService
{
    private const ALLOWED_QUERY_PARAMS = [
        'paginate',
        'page',
        'perPage',
        'dish_id',
        'item_id',
        'q',
        'search',
        'sortBy',
        'sortDir',
        'created_at_from',
        'created_at_to',
        'updated_at_from',
        'updated_at_to',
    ];

    protected DishCompositionModel $dishCompositionModel;
    protected DishModel $dishModel;
    protected ItemModel $itemModel;

    public function __construct()
    {
        $this->dishCompositionModel = new DishCompositionModel();
        $this->dishModel            = new DishModel();
        $this->itemModel            = new ItemModel();
    }

    public function getAllCompositions(array $queryParams): array
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

        $page      = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage   = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $paginate  = $this->shouldPaginate($queryParams['paginate'] ?? null);
        $dishId    = isset($queryParams['dish_id']) ? (int) $queryParams['dish_id'] : null;
        $itemId    = isset($queryParams['item_id']) ? (int) $queryParams['item_id'] : null;
        $search    = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $sortBy    = (string) ($queryParams['sortBy'] ?? 'id');
        $sortDir   = (string) ($queryParams['sortDir'] ?? 'ASC');

        $result = $this->dishCompositionModel->getAllCompositions(
            $page,
            $perPage,
            $paginate,
            $dishId,
            $itemId,
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
            'data'    => array_map(fn (array $row): array => $this->formatCompositionResponse($row), $result['compositions']),
            'meta'    => [
                'page'       => $result['page'],
                'perPage'    => $result['perPage'],
                'total'      => $result['total'],
                'totalPages' => $result['totalPages'],
                'paginated'  => $paginate,
            ],
        ];
    }

    public function getCompositionById(int $id): ?array
    {
        $composition = $this->dishCompositionModel->findById($id);
        if ($composition === null) {
            return null;
        }

        return $this->formatCompositionResponse($composition);
    }

    public function createComposition(array $data): array
    {
        $validation = service('validation');
        if (! $validation->setRules([
            'dish_id'          => 'required|is_natural_no_zero',
            'item_id'          => 'required|is_natural_no_zero',
            'qty_per_patient'  => 'required|decimal|greater_than[0]',
        ])->run($data)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validation->getErrors(),
            ];
        }

        $domainErrors = $this->validateDomainReferences((int) $data['dish_id'], (int) $data['item_id']);
        if ($domainErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $domainErrors,
            ];
        }

        if ($this->dishCompositionModel->existsByDishAndItem((int) $data['dish_id'], (int) $data['item_id'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['dish_id,item_id' => 'The dish_id and item_id combination has already been taken.'],
            ];
        }

        $created = $this->dishCompositionModel->insert([
            'dish_id'         => (int) $data['dish_id'],
            'item_id'         => (int) $data['item_id'],
            'qty_per_patient' => (string) $data['qty_per_patient'],
        ], true);

        if ($created === false) {
            return [
                'success' => false,
                'message' => 'Failed to create dish composition.',
                'errors'  => $this->dishCompositionModel->errors(),
            ];
        }

        return [
            'success'     => true,
            'composition' => $this->getCompositionById((int) $created),
        ];
    }

    public function updateComposition(int $id, array $data): array
    {
        $existing = $this->dishCompositionModel->findById($id);
        if ($existing === null) {
            return [
                'success' => false,
                'message' => 'Dish composition not found.',
            ];
        }

        $validation = service('validation');
        if (! $validation->setRules([
            'dish_id'         => 'permit_empty|is_natural_no_zero',
            'item_id'         => 'permit_empty|is_natural_no_zero',
            'qty_per_patient' => 'permit_empty|decimal|greater_than[0]',
        ])->run($data)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validation->getErrors(),
            ];
        }

        $resolvedDishId = isset($data['dish_id']) ? (int) $data['dish_id'] : (int) $existing['dish_id'];
        $resolvedItemId = isset($data['item_id']) ? (int) $data['item_id'] : (int) $existing['item_id'];

        $domainErrors = $this->validateDomainReferences($resolvedDishId, $resolvedItemId);
        if ($domainErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $domainErrors,
            ];
        }

        if ($this->dishCompositionModel->existsByDishAndItem($resolvedDishId, $resolvedItemId, $id)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['dish_id,item_id' => 'The dish_id and item_id combination has already been taken.'],
            ];
        }

        $updateData = [];
        if (isset($data['dish_id'])) {
            $updateData['dish_id'] = $resolvedDishId;
        }
        if (isset($data['item_id'])) {
            $updateData['item_id'] = $resolvedItemId;
        }
        if (isset($data['qty_per_patient'])) {
            $updateData['qty_per_patient'] = (string) $data['qty_per_patient'];
        }

        if ($updateData === []) {
            return [
                'success'     => true,
                'composition' => $this->formatCompositionResponse($existing),
            ];
        }

        if (! $this->dishCompositionModel->update($id, $updateData)) {
            return [
                'success' => false,
                'message' => 'Failed to update dish composition.',
                'errors'  => $this->dishCompositionModel->errors(),
            ];
        }

        return [
            'success'     => true,
            'composition' => $this->getCompositionById($id),
        ];
    }

    public function deleteComposition(int $id): array
    {
        $existing = $this->dishCompositionModel->find($id);
        if ($existing === null) {
            return [
                'success' => false,
                'message' => 'Dish composition not found.',
            ];
        }

        if (! $this->dishCompositionModel->delete($id)) {
            return [
                'success' => false,
                'message' => 'Failed to delete dish composition.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Dish composition deleted successfully.',
        ];
    }

    private function validateDomainReferences(int $dishId, int $itemId): array
    {
        $errors = [];

        if ($this->dishModel->findById($dishId) === null) {
            $errors['dish_id'] = 'The selected dish is invalid.';
        }

        $item = $this->itemModel->find($itemId);
        if ($item === null) {
            $errors['item_id'] = 'The selected item is invalid.';
        } elseif (! (bool) $item['is_active']) {
            $errors['item_id'] = 'The selected item is inactive.';
        }

        return $errors;
    }

    private function formatCompositionResponse(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'dish_id'         => (int) $row['dish_id'],
            'item_id'         => (int) $row['item_id'],
            'qty_per_patient' => number_format((float) $row['qty_per_patient'], 2, '.', ''),
            'created_at'      => $row['created_at'],
            'updated_at'      => $row['updated_at'],
            'dish'            => [
                'id'   => (int) $row['dish_id'],
                'name' => $row['dish_name'] ?? null,
            ],
            'item'            => [
                'id'        => (int) $row['item_id'],
                'name'      => $row['item_name'] ?? null,
                'unit_base' => $row['item_unit_base'] ?? null,
                'is_active' => isset($row['item_is_active']) ? (bool) $row['item_is_active'] : null,
            ],
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

        if (isset($queryParams['dish_id']) && (! ctype_digit((string) $queryParams['dish_id']) || (int) $queryParams['dish_id'] < 1)) {
            $errors['dish_id'] = 'The dish_id field must be a positive integer.';
        }

        if (isset($queryParams['item_id']) && (! ctype_digit((string) $queryParams['item_id']) || (int) $queryParams['item_id'] < 1)) {
            $errors['item_id'] = 'The item_id field must be a positive integer.';
        }

        if (isset($queryParams['paginate']) && ! in_array(strtolower((string) $queryParams['paginate']), ['true', 'false', '1', '0'], true)) {
            $errors['paginate'] = 'The paginate field must be a boolean value.';
        }

        if (isset($queryParams['sortBy']) && ! in_array($queryParams['sortBy'], DishCompositionModel::SORTABLE_COLUMNS, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', DishCompositionModel::SORTABLE_COLUMNS) . '.';
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
