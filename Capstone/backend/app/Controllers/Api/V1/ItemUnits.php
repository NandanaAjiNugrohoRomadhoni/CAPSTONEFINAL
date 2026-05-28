<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ItemModel;
use App\Models\ItemUnitModel;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Item Units
 *
 * Inventory lookup resource for item unit administration.
 */
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

    /**
     * @OA\Get(
     *     path="/api/v1/item-units",
     *     operationId="listItemUnits",
     *     tags={"Item Units"},
     *     summary="List item units",
     *     description="Returns active item units in the standard lookup collection envelope. Accessible to admin, dapur, and gudang users from the inventory route group. Runtime supports page, perPage, q, search, sortBy, sortDir, created_at_from, created_at_to, updated_at_from, updated_at_to, and paginate=false for dropdown-style reads.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="paginate", in="query", description="Set to false or 0 to return all active rows while keeping the same envelope with meta.paginated=false.", @OA\Schema(type="string", enum={"true","false","1","0"}, example="false")),
     *     @OA\Parameter(name="q", in="query", description="Primary text search term. If q and search are both present, q wins.", @OA\Schema(type="string", example="kg")),
     *     @OA\Parameter(name="search", in="query", description="Fallback text search term when q is absent.", @OA\Schema(type="string", example="pack")),
     *     @OA\Parameter(name="sortBy", in="query", @OA\Schema(type="string", enum={"id","name","created_at","updated_at"}, example="name")),
     *     @OA\Parameter(name="sortDir", in="query", @OA\Schema(type="string", enum={"ASC","DESC"}, example="ASC")),
     *     @OA\Parameter(name="created_at_from", in="query", @OA\Schema(type="string", example="2026-04-10")),
     *     @OA\Parameter(name="created_at_to", in="query", @OA\Schema(type="string", example="2026-04-18")),
     *     @OA\Parameter(name="updated_at_from", in="query", @OA\Schema(type="string", example="2026-04-10 00:00:00")),
     *     @OA\Parameter(name="updated_at_to", in="query", @OA\Schema(type="string", example="2026-04-18 23:59:59")),
     *     @OA\Response(response=200, description="Active item unit collection.", @OA\JsonContent(ref="#/components/schemas/ItemUnitCollectionResponse")),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
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

    /**
     * @OA\Get(
     *     path="/api/v1/item-units/{id}",
     *     operationId="showItemUnit",
     *     tags={"Item Units"},
     *     summary="Show one item unit",
     *     description="Returns one active item unit. Accessible to admin, dapur, and gudang users. Soft-deleted rows are treated as not found.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Active item unit resource.", @OA\JsonContent(ref="#/components/schemas/ItemUnitResource")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Item unit not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/item-units",
     *     operationId="createItemUnit",
     *     tags={"Item Units"},
     *     summary="Create item unit",
     *     description="Creates a new item unit. Admin only. Names are trimmed before persistence and matched case-insensitively for duplicate checks. If the same name belongs to a soft-deleted item unit, runtime returns HTTP 400 with restore guidance instead of auto-restoring it.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name"}, @OA\Property(property="name", type="string", maxLength=50, example="liter"))),
     *     @OA\Response(response=201, description="Item unit created successfully.", @OA\JsonContent(ref="#/components/schemas/ItemUnitMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for missing name, duplicate active name, or deleted-name collision that requires explicit restore.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while creating the item unit.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
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

    /**
     * @OA\Put(
     *     path="/api/v1/item-units/{id}",
     *     operationId="updateItemUnit",
     *     tags={"Item Units"},
     *     summary="Update item unit",
     *     description="Updates an existing active item unit. Admin only. The runtime behaves like a partial update: if name is omitted, it returns HTTP 200 with the current row unchanged. Renaming to a soft-deleted name is blocked and requires the explicit restore flow instead.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="name", type="string", maxLength=50, example="kilogram"))),
     *     @OA\Response(response=200, description="Item unit updated successfully, or returned unchanged when no name field was sent.", @OA\JsonContent(ref="#/components/schemas/ItemUnitMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for invalid name, duplicate active name, or deleted-name collision that must be handled through restore.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Item unit not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while updating the item unit.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/v1/item-units/{id}",
     *     operationId="deleteItemUnit",
     *     tags={"Item Units"},
     *     summary="Delete item unit",
     *     description="Soft-deletes an item unit. Admin only. Deletion is blocked while active items still reference the unit, in which case the runtime returns HTTP 400 with an item_unit_id error.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Item unit deleted successfully.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=400, description="Validation failed because active items still use this unit.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Item unit not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while deleting the item unit.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/item-units/{id}/restore",
     *     operationId="restoreItemUnit",
     *     tags={"Item Units"},
     *     summary="Restore item unit",
     *     description="Restores a soft-deleted item unit. Admin only. The operation is idempotent for already-active rows and returns HTTP 200 with the current unit. Restore is conflict-checked: if an active item unit already uses the same name, runtime returns HTTP 400 and leaves the deleted row untouched.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=4)),
     *     @OA\Response(response=200, description="Item unit restored successfully, or the current active row was returned unchanged.", @OA\JsonContent(ref="#/components/schemas/ItemUnitMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed because an active item unit already uses the deleted row's name.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Item unit not found, including among soft-deleted rows.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while restoring the item unit.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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
