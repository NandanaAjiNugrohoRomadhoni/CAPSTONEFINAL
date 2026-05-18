<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ItemCategoryModel;
use App\Models\ItemModel;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Item Categories
 *
 * Inventory lookup resource for item category administration.
 */
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

    /**
     * @OA\Get(
     *     path="/api/v1/item-categories",
     *     operationId="listItemCategories",
     *     tags={"Item Categories"},
     *     summary="List item categories",
     *     description="Returns active item categories in the standard lookup collection envelope. Accessible to admin and gudang users from the inventory route group. Runtime supports page, perPage, q, search, sortBy, sortDir, created_at_from, created_at_to, updated_at_from, updated_at_to, and paginate=false for dropdown-style reads; unknown query parameters or invalid values return HTTP 400.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="paginate", in="query", description="Set to false or 0 to return all active rows while keeping the same data/meta/links envelope with meta.paginated=false.", @OA\Schema(type="string", enum={"true","false","1","0"}, example="false")),
     *     @OA\Parameter(name="q", in="query", description="Primary text search term. If q and search are both present, q wins.", @OA\Schema(type="string", example="KER")),
     *     @OA\Parameter(name="search", in="query", description="Fallback text search term when q is absent.", @OA\Schema(type="string", example="BAS")),
     *     @OA\Parameter(name="sortBy", in="query", @OA\Schema(type="string", enum={"id","name","created_at","updated_at"}, example="name")),
     *     @OA\Parameter(name="sortDir", in="query", @OA\Schema(type="string", enum={"ASC","DESC"}, example="ASC")),
     *     @OA\Parameter(name="created_at_from", in="query", @OA\Schema(type="string", example="2026-04-10")),
     *     @OA\Parameter(name="created_at_to", in="query", @OA\Schema(type="string", example="2026-04-18")),
     *     @OA\Parameter(name="updated_at_from", in="query", @OA\Schema(type="string", example="2026-04-10 00:00:00")),
     *     @OA\Parameter(name="updated_at_to", in="query", @OA\Schema(type="string", example="2026-04-18 23:59:59")),
     *     @OA\Response(response=200, description="Active item category collection.", @OA\JsonContent(ref="#/components/schemas/ItemCategoryCollectionResponse")),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/v1/item-categories/{id}",
     *     operationId="showItemCategory",
     *     tags={"Item Categories"},
     *     summary="Show one item category",
     *     description="Returns one active item category. Accessible to admin and gudang users. Soft-deleted rows are treated as not found.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Active item category resource.", @OA\JsonContent(ref="#/components/schemas/ItemCategoryResource")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Item category not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/item-categories",
     *     operationId="createItemCategory",
     *     tags={"Item Categories"},
     *     summary="Create item category",
     *     description="Creates a new item category. Admin only. Names are trimmed before persistence. The runtime will not auto-restore a soft-deleted row with the same case-insensitive name: it returns HTTP 400 with restore guidance and errors.restore_id instead.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name"}, @OA\Property(property="name", type="string", maxLength=50, example="MINUMAN"))),
     *     @OA\Response(response=201, description="Item category created successfully.", @OA\JsonContent(ref="#/components/schemas/ItemCategoryMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for missing name, duplicate active name, or deleted-name collision that must be restored explicitly.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while creating the item category.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/api/v1/item-categories/{id}",
     *     operationId="updateItemCategory",
     *     tags={"Item Categories"},
     *     summary="Update item category",
     *     description="Updates an existing active item category. Admin only. The runtime behaves like a partial update: if name is omitted, it returns HTTP 200 with the current row unchanged. Renaming to a soft-deleted name does not auto-restore that row and instead returns HTTP 400 with restore guidance.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="name", type="string", maxLength=50, example="MINUMAN"))),
     *     @OA\Response(response=200, description="Item category updated successfully, or returned unchanged when no name field was sent.", @OA\JsonContent(ref="#/components/schemas/ItemCategoryMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for invalid name, duplicate active name, or deleted-name collision that must be handled through the restore route.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Item category not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while updating the item category.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/v1/item-categories/{id}",
     *     operationId="deleteItemCategory",
     *     tags={"Item Categories"},
     *     summary="Delete item category",
     *     description="Soft-deletes an item category. Admin only. Deletion is blocked while active items still reference the category, in which case the runtime returns HTTP 400 with an item_category_id error.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Item category deleted successfully.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=400, description="Validation failed because active items still use this category.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Item category not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while deleting the item category.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/item-categories/{id}/restore",
     *     operationId="restoreItemCategory",
     *     tags={"Item Categories"},
     *     summary="Restore item category",
     *     description="Restores a soft-deleted item category. Admin only. The operation is idempotent for already-active rows and still returns HTTP 200 with the current category. Restore is conflict-checked: if an active category already uses the same name, runtime returns HTTP 400 and does not auto-rename or merge anything.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=4)),
     *     @OA\Response(response=200, description="Item category restored successfully, or the current active row was returned unchanged.", @OA\JsonContent(ref="#/components/schemas/ItemCategoryMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed because an active item category already uses the deleted row's name.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Item category not found, including among soft-deleted rows.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while restoring the item category.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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
