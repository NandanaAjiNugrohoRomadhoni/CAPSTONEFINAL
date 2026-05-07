<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;
use CodeIgniter\HTTP\ResponseInterface;

class ItemUnits extends BaseController
{
    private ItemUnitModel $itemUnitModel;
    private ItemModel $itemModel;

    private const ALLOWED_PARAMS = [
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

    public function __construct()
    {
        $this->itemUnitModel = new ItemUnitModel();
        $this->itemModel     = new ItemModel();
    }

    public function index(): ResponseInterface
    {
        $queryParams = $this->request->getGet();
        $errors      = $this->validateListParams($queryParams);

        if ($errors !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $errors,
                ]);
        }

        $page    = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $paginate = $this->shouldPaginate($queryParams['paginate'] ?? null);
        $search  = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $requestedSortBy = (string) ($queryParams['sortBy'] ?? 'name');
        $sortBy  = in_array($requestedSortBy, ItemUnitModel::SORTABLE_COLUMNS, true)
            ? $requestedSortBy
            : 'name';
        $sortDir = strtoupper((string) ($queryParams['sortDir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $builder = $this->itemUnitModel->builder();
        $builder->where('item_units.deleted_at', null);

        if ($search !== '') {
            $builder->like('item_units.name', $search);
        }

        $this->applyDateRange($builder, 'item_units.created_at', $queryParams['created_at_from'] ?? null, $queryParams['created_at_to'] ?? null);
        $this->applyDateRange($builder, 'item_units.updated_at', $queryParams['updated_at_from'] ?? null, $queryParams['updated_at_to'] ?? null);

        $builder->orderBy('item_units.' . $sortBy, $sortDir);
        if ($sortBy !== 'id') {
            $builder->orderBy('item_units.id', 'ASC');
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        if ($paginate) {
            $itemUnits = $builder
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        } else {
            $itemUnits  = $builder->get()->getResultArray();
            $page       = 1;
            $perPage    = max(1, count($itemUnits));
            $total      = count($itemUnits);
            $totalPages = $total > 0 ? 1 : 0;
        }

        $meta = [
            'page'       => $page,
            'perPage'    => $perPage,
            'total'      => $total,
            'totalPages' => $totalPages,
            'paginated'  => $paginate,
        ];

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $itemUnits,
                'meta'  => $meta,
                'links' => $this->buildPaginationLinks($meta),
            ]);
    }

    public function show(int $id): ResponseInterface
    {
        $itemUnit = $this->itemUnitModel->find($id);

        if ($itemUnit === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['message' => 'Item unit not found.']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON(['data' => $itemUnit]);
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];

        if (! $this->validateData($data, ['name' => 'required|max_length[50]'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $name = trim((string) $data['name']);
        if ($this->itemUnitModel->nameExists($name, null, false)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name has already been taken.'],
                ]);
        }

        $deletedMatch = $this->itemUnitModel->findByNameIncludingDeleted($name);
        if ($deletedMatch !== null && $deletedMatch['deleted_at'] !== null) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name belongs to a deleted item unit. Restore it instead.', 'restore_id' => (string) $deletedMatch['id']],
                ]);
        }

        $created = $this->itemUnitModel->insert(['name' => $name], true);

        if ($created === false) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['message' => 'Failed to create item unit.']);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'Item unit created successfully.',
                'data'    => $this->itemUnitModel->find((int) $created),
            ]);
    }

    public function update(int $id): ResponseInterface
    {
        $itemUnit = $this->itemUnitModel->find($id);

        if ($itemUnit === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['message' => 'Item unit not found.']);
        }

        $data = $this->request->getJSON(true) ?? [];

        if (! $this->validateData(['id' => $id, ...$data], ['name' => 'permit_empty|max_length[50]'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        if (! array_key_exists('name', $data)) {
            return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'message' => 'Item unit updated successfully.',
                    'data'    => $itemUnit,
                ]);
        }

        $name = trim((string) $data['name']);
        if ($this->itemUnitModel->nameExists($name, $id, false)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name has already been taken.'],
                ]);
        }

        $deletedMatch = $this->itemUnitModel->findByNameIncludingDeleted($name);
        if ($deletedMatch !== null && (int) $deletedMatch['id'] !== $id && $deletedMatch['deleted_at'] !== null) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name belongs to a deleted item unit. Restore it instead.', 'restore_id' => (string) $deletedMatch['id']],
                ]);
        }

        $updated = $this->itemUnitModel->update($id, ['name' => $name]);

        if (! $updated) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['message' => 'Failed to update item unit.']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'Item unit updated successfully.',
                'data'    => $this->itemUnitModel->find($id),
            ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $itemUnit = $this->itemUnitModel->find($id);

        if ($itemUnit === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['message' => 'Item unit not found.']);
        }

        if ($this->itemModel->countActiveItemsByItemUnitId($id) > 0) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['item_unit_id' => 'The item unit is still used by active items.'],
                ]);
        }

        if (! $this->itemUnitModel->delete($id)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['message' => 'Failed to delete item unit.']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON(['message' => 'Item unit deleted successfully.']);
    }

    public function restore(int $id): ResponseInterface
    {
        $itemUnit = $this->itemUnitModel->findByIdIncludingDeleted($id);

        if ($itemUnit === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['message' => 'Item unit not found.']);
        }

        if ($itemUnit['deleted_at'] === null) {
            return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'message' => 'Item unit restored successfully.',
                    'data'    => $itemUnit,
                ]);
        }

        if ($this->itemUnitModel->nameExists((string) $itemUnit['name'], $id, false)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'An active item unit already uses this name.'],
                ]);
        }

        if (! $this->itemUnitModel->restore($id)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['message' => 'Failed to restore item unit.']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'Item unit restored successfully.',
                'data'    => $this->itemUnitModel->find($id),
            ]);
    }

    private function validateListParams(array $queryParams): array
    {
        $errors        = [];
        $unknownParams = array_diff(array_keys($queryParams), self::ALLOWED_PARAMS);

        if ($unknownParams !== []) {
            $errors['query'] = 'Unsupported query parameter(s): ' . implode(', ', $unknownParams);
        }

        if (isset($queryParams['page']) && (! ctype_digit((string) $queryParams['page']) || (int) $queryParams['page'] < 1)) {
            $errors['page'] = 'The page field must be a positive integer.';
        }

        if (isset($queryParams['perPage']) && (! ctype_digit((string) $queryParams['perPage']) || (int) $queryParams['perPage'] < 1 || (int) $queryParams['perPage'] > 100)) {
            $errors['perPage'] = 'The perPage field must be an integer between 1 and 100.';
        }

        if (isset($queryParams['paginate']) && ! in_array(strtolower((string) $queryParams['paginate']), ['true', 'false', '1', '0'], true)) {
            $errors['paginate'] = 'The paginate field must be a boolean value.';
        }

        if (isset($queryParams['sortBy']) && ! in_array($queryParams['sortBy'], ItemUnitModel::SORTABLE_COLUMNS, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', ItemUnitModel::SORTABLE_COLUMNS) . '.';
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

    private function applyDateRange(object $builder, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $builder->where($column . ' >=', $from);
        }

        if ($to !== null && $to !== '') {
            $builder->where($column . ' <=', $to);
        }
    }

    private function shouldPaginate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return ! in_array(strtolower((string) $value), ['false', '0'], true);
    }

    private function buildPaginationLinks(array $meta): array
    {
        $queryParams = $this->request->getGet();
        $path        = current_url();

        $buildLink = function (int $page) use ($path, $queryParams, $meta): string {
            return $path . '?' . http_build_query([
                ...$queryParams,
                'page'    => $page,
                'perPage' => $meta['perPage'],
            ]);
        };

        return [
            'self'     => $buildLink($meta['page']),
            'first'    => $buildLink(1),
            'last'     => $buildLink(max(1, $meta['totalPages'])),
            'next'     => $meta['page'] < $meta['totalPages'] ? $buildLink($meta['page'] + 1) : null,
            'previous' => $meta['page'] > 1 ? $buildLink($meta['page'] - 1) : null,
        ];
    }
}
