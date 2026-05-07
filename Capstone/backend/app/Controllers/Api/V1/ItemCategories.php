<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use CodeIgniter\HTTP\ResponseInterface;

class ItemCategories extends BaseController
{
    private ItemCategoryModel $itemCategoryModel;
    private ItemModel $itemModel;

    private const SORTABLE_COLUMNS = ['id', 'name', 'created_at', 'updated_at'];

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
        $this->itemCategoryModel = new ItemCategoryModel();
        $this->itemModel         = new ItemModel();
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
        $sortBy  = in_array($requestedSortBy, self::SORTABLE_COLUMNS, true)
            ? $requestedSortBy
            : 'name';
        $sortDir = strtoupper((string) ($queryParams['sortDir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $builder = $this->itemCategoryModel->builder();
        $builder->where('item_categories.deleted_at', null);

        if ($search !== '') {
            $builder->like('item_categories.name', $search);
        }

        $this->applyDateRange($builder, 'item_categories.created_at', $queryParams['created_at_from'] ?? null, $queryParams['created_at_to'] ?? null);
        $this->applyDateRange($builder, 'item_categories.updated_at', $queryParams['updated_at_from'] ?? null, $queryParams['updated_at_to'] ?? null);

        $builder->orderBy('item_categories.' . $sortBy, $sortDir);
        if ($sortBy !== 'id') {
            $builder->orderBy('item_categories.id', 'ASC');
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        if ($paginate) {
            $data = $builder
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        } else {
            $data       = $builder->get()->getResultArray();
            $page       = 1;
            $perPage    = max(1, count($data));
            $total      = count($data);
            $totalPages = $total > 0 ? 1 : 0;
        }

        $meta = ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages, 'paginated' => $paginate];

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $data,
                'meta'  => $meta,
                'links' => $this->buildPaginationLinks($meta),
            ]);
    }

    public function show(int $id): ResponseInterface
    {
        $itemCategory = $this->itemCategoryModel->find($id);

        if ($itemCategory === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['message' => 'Item category not found.']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON(['data' => $itemCategory]);
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

        if ($this->itemCategoryModel->nameExists($name, null, false)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name has already been taken.'],
                ]);
        }

        $deletedMatch = $this->itemCategoryModel->findByNameIncludingDeleted($name);
        if ($deletedMatch !== null && $deletedMatch['deleted_at'] !== null) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name belongs to a deleted item category. Restore it instead.', 'restore_id' => (string) $deletedMatch['id']],
                ]);
        }

        $created = $this->itemCategoryModel->insert(['name' => $name], true);

        if ($created === false) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['message' => 'Failed to create item category.']);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'Item category created successfully.',
                'data'    => $this->itemCategoryModel->find((int) $created),
            ]);
    }

    public function update(int $id): ResponseInterface
    {
        $itemCategory = $this->itemCategoryModel->find($id);

        if ($itemCategory === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['message' => 'Item category not found.']);
        }

        $data = $this->request->getJSON(true) ?? [];

        if (! $this->validateData($data, ['name' => 'permit_empty|max_length[50]'])) {
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
                    'message' => 'Item category updated successfully.',
                    'data'    => $itemCategory,
                ]);
        }

        $name = trim((string) $data['name']);

        if ($this->itemCategoryModel->nameExists($name, $id, false)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name has already been taken.'],
                ]);
        }

        $deletedMatch = $this->itemCategoryModel->findByNameIncludingDeleted($name);
        if ($deletedMatch !== null && (int) $deletedMatch['id'] !== $id && $deletedMatch['deleted_at'] !== null) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'The name belongs to a deleted item category. Restore it instead.', 'restore_id' => (string) $deletedMatch['id']],
                ]);
        }

        if (! $this->itemCategoryModel->update($id, ['name' => $name])) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['message' => 'Failed to update item category.']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'Item category updated successfully.',
                'data'    => $this->itemCategoryModel->find($id),
            ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $itemCategory = $this->itemCategoryModel->find($id);

        if ($itemCategory === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['message' => 'Item category not found.']);
        }

        if ($this->itemModel->countActiveItemsByCategoryId($id) > 0) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['item_category_id' => 'The item category is still used by active items.'],
                ]);
        }

        if (! $this->itemCategoryModel->delete($id)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['message' => 'Failed to delete item category.']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON(['message' => 'Item category deleted successfully.']);
    }

    public function restore(int $id): ResponseInterface
    {
        $itemCategory = $this->itemCategoryModel->findByIdIncludingDeleted($id);

        if ($itemCategory === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['message' => 'Item category not found.']);
        }

        if ($itemCategory['deleted_at'] === null) {
            return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'message' => 'Item category restored successfully.',
                    'data'    => $itemCategory,
                ]);
        }

        if ($this->itemCategoryModel->nameExists((string) $itemCategory['name'], $id, false)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => ['name' => 'An active item category already uses this name.'],
                ]);
        }

        if (! $this->itemCategoryModel->restore($id)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['message' => 'Failed to restore item category.']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'Item category restored successfully.',
                'data'    => $this->itemCategoryModel->find($id),
            ]);
    }

    private function validateListParams(array $params): array
    {
        $errors = [];

        $unknownParams = array_diff(array_keys($params), self::ALLOWED_PARAMS);
        if ($unknownParams !== []) {
            $errors['query'] = 'Unsupported query parameter(s): ' . implode(', ', $unknownParams);
        }

        if (isset($params['page']) && (! ctype_digit((string) $params['page']) || (int) $params['page'] < 1)) {
            $errors['page'] = 'The page field must be a positive integer.';
        }

        if (isset($params['perPage']) && (! ctype_digit((string) $params['perPage']) || (int) $params['perPage'] < 1 || (int) $params['perPage'] > 100)) {
            $errors['perPage'] = 'The perPage field must be an integer between 1 and 100.';
        }

        if (isset($params['paginate']) && ! in_array(strtolower((string) $params['paginate']), ['true', 'false', '1', '0'], true)) {
            $errors['paginate'] = 'The paginate field must be a boolean value.';
        }

        if (isset($params['sortBy']) && ! in_array($params['sortBy'], self::SORTABLE_COLUMNS, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', self::SORTABLE_COLUMNS) . '.';
        }

        if (isset($params['sortDir']) && ! in_array(strtoupper((string) $params['sortDir']), ['ASC', 'DESC'], true)) {
            $errors['sortDir'] = 'The sortDir field must be ASC or DESC.';
        }

        foreach (['created_at_from', 'created_at_to', 'updated_at_from', 'updated_at_to'] as $dateField) {
            if (isset($params[$dateField]) && strtotime((string) $params[$dateField]) === false) {
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
            return $path . '?' . http_build_query([...$queryParams, 'page' => $page, 'perPage' => $meta['perPage']]);
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
