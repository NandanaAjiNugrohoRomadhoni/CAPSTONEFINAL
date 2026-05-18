<?php

namespace App\Services;

use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;

class ItemManagementService
{
    private const ALLOWED_QUERY_PARAMS = [
        'page', 'perPage',
        'item_category_id', 'is_active',
        'q', 'search',
        'sortBy', 'sortDir',
        'created_at_from', 'created_at_to',
        'updated_at_from', 'updated_at_to',
    ];
    public const FORBIDDEN_FIELDS = ['qty', 'id', 'created_at', 'updated_at', 'deleted_at'];

    protected ItemModel $itemModel;
    protected ItemCategoryModel $itemCategoryModel;
    protected ItemUnitModel $itemUnitModel;

    public function __construct()
    {
        $this->itemModel         = new ItemModel();
        $this->itemCategoryModel = new ItemCategoryModel();
        $this->itemUnitModel     = new ItemUnitModel();
    }

    public function getAllItems(array $queryParams): array
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
        $categoryId    = isset($queryParams['item_category_id']) ? (int) $queryParams['item_category_id'] : null;
        $isActive      = isset($queryParams['is_active']) ? filter_var($queryParams['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        $search        = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $sortBy        = (string) ($queryParams['sortBy'] ?? 'name');
        $sortDir       = (string) ($queryParams['sortDir'] ?? 'ASC');
        $createdAtFrom = $queryParams['created_at_from'] ?? null;
        $createdAtTo   = $queryParams['created_at_to'] ?? null;
        $updatedAtFrom = $queryParams['updated_at_from'] ?? null;
        $updatedAtTo   = $queryParams['updated_at_to'] ?? null;

        $result = $this->itemModel->getAllWithCategories(
            $page,
            $perPage,
            $categoryId,
            $isActive,
            $search,
            $sortBy,
            $sortDir,
            $createdAtFrom,
            $createdAtTo,
            $updatedAtFrom,
            $updatedAtTo,
        );

        return [
            'success' => true,
            'data'    => array_map(fn (array $item): array => $this->formatItemResponse($item), $result['items']),
            'meta'    => [
                'page'       => $result['page'],
                'perPage'    => $result['perPage'],
                'total'      => $result['total'],
                'totalPages' => $result['totalPages'],
            ],
        ];
    }

    public function getItemById(int $id): ?array
    {
        $item = $this->itemModel->findWithCategory($id);

        if ($item === null) {
            return null;
        }

        return $this->formatItemResponse($item);
    }

    public function createItem(array $data): array
    {
        $forbiddenErrors = $this->collectForbiddenFieldErrors($data);
        if ($forbiddenErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $forbiddenErrors,
            ];
        }

        if (isset($data['item_category_name']) && !isset($data['item_category_id'])) {
            $categoryId = $this->itemCategoryModel->getIdByName($data['item_category_name']);
            if ($categoryId === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['item_category_name' => 'The selected item category is invalid.'],
                ];
            }
            $data['item_category_id'] = $categoryId;
        }

        if (! $this->itemCategoryModel->exists((int) $data['item_category_id'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['item_category_id' => 'The selected item category is invalid.'],
            ];
        }

        // Check for active-name duplicate first, then deleted-name collision
        if ($this->itemModel->nameExists($data['name'], null, false)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['name' => 'The name has already been taken.'],
            ];
        }

        $deletedMatch = $this->itemModel->findByNameIncludingDeleted($data['name']);
        if ($deletedMatch !== null && $deletedMatch['deleted_at'] !== null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'name'       => 'The name belongs to a deleted item. Restore it instead.',
                    'restore_id' => (string) $deletedMatch['id'],
                ],
            ];
        }

        if (array_key_exists('is_active', $data) && ! $this->isSupportedBooleanValue($data['is_active'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['is_active' => 'The is_active field must be a valid boolean.'],
            ];
        }

        $itemUnitBaseId    = $this->resolveItemUnitId($data['unit_base']);
        $itemUnitConvertId = $this->resolveItemUnitId($data['unit_convert']);

        if ($itemUnitBaseId === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['unit_base' => 'The unit_base value could not be resolved to an active item unit.'],
            ];
        }

        if ($itemUnitConvertId === null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['unit_convert' => 'The unit_convert value could not be resolved to an active item unit.'],
            ];
        }

        $insertData = [
            'item_category_id'     => (int) $data['item_category_id'],
            'name'                 => trim((string) $data['name']),
            'unit_base'            => trim((string) $data['unit_base']),
            'unit_convert'         => trim((string) $data['unit_convert']),
            'item_unit_base_id'    => $itemUnitBaseId,
            'item_unit_convert_id' => $itemUnitConvertId,
            'conversion_base'      => (int) $data['conversion_base'],
            'min_stock'            => isset($data['min_stock']) ? (int) $data['min_stock'] : 0,
            'is_active'            => array_key_exists('is_active', $data)
                ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true,
        ];

        $created = $this->itemModel->insert($insertData, true);

        if ($created === false) {
            return [
                'success' => false,
                'message' => 'Failed to create item.',
                'errors'  => $this->itemModel->errors(),
            ];
        }

        $item = $this->getItemById((int) $created);

        return [
            'success' => true,
            'item'    => $item,
        ];
    }

    public function updateItem(int $id, array $data): array
    {
        $existing = $this->itemModel->findWithCategory($id);

        if ($existing === null) {
            return [
                'success' => false,
                'message' => 'Item not found.',
            ];
        }

        $forbiddenErrors = $this->collectForbiddenFieldErrors($data);
        if ($forbiddenErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $forbiddenErrors,
            ];
        }

        if (isset($data['item_category_name']) && !isset($data['item_category_id'])) {
            $categoryId = $this->itemCategoryModel->getIdByName($data['item_category_name']);
            if ($categoryId === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['item_category_name' => 'The selected item category is invalid.'],
                ];
            }
            $data['item_category_id'] = $categoryId;
        }

        if (isset($data['item_category_id']) && ! $this->itemCategoryModel->exists((int) $data['item_category_id'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['item_category_id' => 'The selected item category is invalid.'],
            ];
        }

        if (isset($data['name'])) {
            // Check for active-name duplicate (excluding this item)
            if ($this->itemModel->nameExists($data['name'], $id, false)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name has already been taken.'],
                ];
            }

            // Check for deleted-name collision (excluding this item)
            $deletedMatch = $this->itemModel->findByNameIncludingDeleted($data['name']);
            if ($deletedMatch !== null && $deletedMatch['deleted_at'] !== null && (int) $deletedMatch['id'] !== $id) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'name'       => 'The name belongs to a deleted item. Restore it instead.',
                        'restore_id' => (string) $deletedMatch['id'],
                    ],
                ];
            }
        }

        if (array_key_exists('is_active', $data) && ! $this->isSupportedBooleanValue($data['is_active'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['is_active' => 'The is_active field must be a valid boolean.'],
            ];
        }

        $updateData = [];

        if (isset($data['item_category_id'])) {
            $updateData['item_category_id'] = (int) $data['item_category_id'];
        }
        if (isset($data['name'])) {
            $updateData['name'] = trim((string) $data['name']);
        }
        if (isset($data['unit_base'])) {
            $itemUnitBaseId = $this->resolveItemUnitId($data['unit_base']);
            if ($itemUnitBaseId === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['unit_base' => 'The unit_base value could not be resolved to an active item unit.'],
                ];
            }
            $updateData['unit_base']         = trim((string) $data['unit_base']);
            $updateData['item_unit_base_id'] = $itemUnitBaseId;
        }
        if (isset($data['unit_convert'])) {
            $itemUnitConvertId = $this->resolveItemUnitId($data['unit_convert']);
            if ($itemUnitConvertId === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['unit_convert' => 'The unit_convert value could not be resolved to an active item unit.'],
                ];
            }
            $updateData['unit_convert']            = trim((string) $data['unit_convert']);
            $updateData['item_unit_convert_id']    = $itemUnitConvertId;
        }
        if (isset($data['conversion_base'])) {
            $updateData['conversion_base'] = (int) $data['conversion_base'];
        }
        if (isset($data['min_stock'])) {
            $updateData['min_stock'] = (int) $data['min_stock'];
        }
        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($updateData === []) {
            return [
                'success' => true,
                'item'    => $this->formatItemResponse($existing),
            ];
        }

        $updated = $this->itemModel->update($id, $updateData);

        if (! $updated) {
            return [
                'success' => false,
                'message' => 'Failed to update item.',
                'errors'  => $this->itemModel->errors(),
            ];
        }

        $item = $this->getItemById($id);

        return [
            'success' => true,
            'item'    => $item,
        ];
    }

    public function deleteItem(int $id): array
    {
        $existing = $this->itemModel->find($id);

        if ($existing === null) {
            return [
                'success' => false,
                'message' => 'Item not found.',
            ];
        }

        if (! $this->itemModel->delete($id)) {
            return [
                'success' => false,
                'message' => 'Failed to delete item.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Item deleted successfully.',
        ];
    }

    public function restoreItem(int $id): array
    {
        $item = $this->itemModel->findByIdIncludingDeleted($id);

        if ($item === null) {
            return [
                'success' => false,
                'message' => 'Item not found.',
            ];
        }

        // Already active — idempotent: return current data
        if ($item['deleted_at'] === null) {
            $current = $this->itemModel->findWithCategory($id);
            return [
                'success' => true,
                'item'    => $this->formatItemResponse($current),
            ];
        }

        if (! $this->itemCategoryModel->exists((int) $item['item_category_id'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['item_category_id' => 'Cannot restore: the item category is no longer active.'],
            ];
        }

        if (! $this->itemUnitModel->exists((int) $item['item_unit_base_id'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['unit_base' => 'Cannot restore: the base unit is no longer active.'],
            ];
        }

        if (! $this->itemUnitModel->exists((int) $item['item_unit_convert_id'])) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['unit_convert' => 'Cannot restore: the converted unit is no longer active.'],
            ];
        }

        // Active duplicate name exists → block restore
        if ($this->itemModel->nameExists($item['name'], $id, false)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['name' => 'Cannot restore: an active item with this name already exists.'],
            ];
        }

        if (! $this->itemModel->restore($id)) {
            return [
                'success' => false,
                'message' => 'Failed to restore item.',
            ];
        }

        $restored = $this->itemModel->findWithCategory($id);

        return [
            'success' => true,
            'item'    => $this->formatItemResponse($restored),
        ];
    }

    private function resolveItemUnitId(string $unitName): ?int
    {
        return $this->itemUnitModel->getIdByName($unitName);
    }

    private function collectForbiddenFieldErrors(array $data): array
    {
        $errors = [];

        foreach (self::FORBIDDEN_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $errors[$field] = sprintf('The %s field cannot be modified directly.', $field);
            }
        }

        return $errors;
    }

    private function formatItemResponse(array $item): array
    {
        return [
            'id'                   => (int) $item['id'],
            'item_category_id'     => (int) $item['item_category_id'],
            'name'                 => $item['name'],
            'unit_base'            => $item['unit_base'],
            'unit_convert'         => $item['unit_convert'],
            'item_unit_base_id'    => isset($item['item_unit_base_id']) ? (int) $item['item_unit_base_id'] : null,
            'item_unit_convert_id' => isset($item['item_unit_convert_id']) ? (int) $item['item_unit_convert_id'] : null,
            'conversion_base'      => (int) $item['conversion_base'],
            'min_stock'            => (int) ($item['min_stock'] ?? 0),
            'qty'                  => number_format((float) $item['qty'], 2, '.', ''),
            'is_active'            => (bool) $item['is_active'],
            'created_at'           => $item['created_at'],
            'updated_at'           => $item['updated_at'],
            'category'             => [
                'id'   => (int) $item['item_category_id'],
                'name' => $item['category_name'] ?? null,
            ],
            'item_unit_base' => [
                'id'   => isset($item['item_unit_base_id']) ? (int) $item['item_unit_base_id'] : null,
                'name' => $item['item_unit_base_name'] ?? $item['unit_base'],
            ],
            'item_unit_convert' => [
                'id'   => isset($item['item_unit_convert_id']) ? (int) $item['item_unit_convert_id'] : null,
                'name' => $item['item_unit_convert_name'] ?? $item['unit_convert'],
            ],
        ];
    }

    private function isSupportedBooleanValue(mixed $value): bool
    {
        return in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true);
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

        if (isset($queryParams['item_category_id']) && (! ctype_digit((string) $queryParams['item_category_id']) || (int) $queryParams['item_category_id'] < 1)) {
            $errors['item_category_id'] = 'The item_category_id field must be a positive integer.';
        }

        if (isset($queryParams['is_active']) && ! in_array((string) $queryParams['is_active'], ['0', '1', 'true', 'false'], true)) {
            $errors['is_active'] = 'The is_active field must be a boolean value.';
        }

        if (isset($queryParams['sortBy']) && ! in_array($queryParams['sortBy'], ItemModel::SORTABLE_COLUMNS, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', ItemModel::SORTABLE_COLUMNS) . '.';
        }

        if (isset($queryParams['sortDir']) && ! in_array(strtoupper((string) $queryParams['sortDir']), ['ASC', 'DESC'], true)) {
            $errors['sortDir'] = 'The sortDir field must be ASC or DESC.';
        }

        foreach (['created_at_from', 'created_at_to', 'updated_at_from', 'updated_at_to'] as $dateParam) {
            if (isset($queryParams[$dateParam]) && ! $this->isValidDateTimeString($queryParams[$dateParam])) {
                $errors[$dateParam] = sprintf('The %s field must be a valid date/datetime string.', $dateParam);
            }
        }

        return $errors;
    }

    private function isValidDateTimeString(string $value): bool
    {
        return strtotime($value) !== false;
    }
}
