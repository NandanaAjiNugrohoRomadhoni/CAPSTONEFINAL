<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ItemManagementService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Items
 *
 * Module   : Items
 * Route    : /api/v1/items
 * Access   : admin, gudang (list/show/create/update); admin (delete/restore)
 * Canonical: backend/docs/reference/api-contract.md §5.4
 *
 * Manages item master records while keeping qty mutations confined to stock workflows.
 */
class Items extends BaseController
{
    protected ItemManagementService $itemService;

    public function __construct()
    {
        $this->itemService = new ItemManagementService();
    }

    /**
     * Returns the item collection with canonical filtering and pagination rules.
     *
     * HTTP     : GET /api/v1/items
     * Access   : admin, gudang
     * Service  : ItemManagementService::getAllItems()
     * Contract : api-contract.md §5.4.2
     *
     * Supports: page, perPage, paginate (false = all rows, same envelope, meta.paginated=false),
     *           sortBy, sortDir, q/search (q takes priority), date range filters.
     * Unknown query params → 400.
     * Soft-deleted rows are excluded.
     *
     * @return ResponseInterface JSON — data/meta/links envelope of active item rows.
     *
     * @throws \RuntimeException if downstream query assembly fails
     *
     * @sideeffect none
     */
    public function index(): ResponseInterface
    {
        $result = $this->itemService->getAllItems($this->request->getGet());

        if (! $result['success']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $result['data'],
                'meta'  => $result['meta'],
                'links' => $this->buildPaginationLinks($result['meta']),
            ]);
    }

    /**
     * Returns one active item by identifier.
     *
     * HTTP     : GET /api/v1/items/{id}
     * Access   : admin, gudang
     * Service  : ItemManagementService::getItemById()
     * Contract : api-contract.md §5.4.4
     *
     * @param int $id Item identifier.
     * @return ResponseInterface JSON — data envelope containing one active item.
     *
     * @throws \DomainException if the item lookup fails in the persistence layer
     * @throws \RuntimeException if response serialization fails
     *
     * @sideeffect none
     */
    public function show(int $id): ResponseInterface
    {
        $item = $this->itemService->getItemById($id);

        if ($item === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'Item not found.',
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $item,
            ]);
    }

    /**
     * Creates a new item master row with category and unit lookup resolution.
     *
     * HTTP     : POST /api/v1/items
     * Access   : admin, gudang
     * Service  : ItemManagementService::createItem()
     * Contract : api-contract.md §5.4.3
     *
     * Accepts EITHER item_category_id OR item_category_name — not both.
     * Name matching is case-insensitive and trimmed.
     * Sending both returns 400.
     *
     * @return ResponseInterface JSON — message + data envelope for the created item.
     *
     * @throws \InvalidArgumentException if forbidden writable fields such as qty are sent
     * @throws \DomainException if category or unit lookups cannot resolve to active rows
     * @throws \RuntimeException if persistence fails
     *
     * @sideeffect none; items.qty remains read-only in this module.
     */
    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];

        $forbiddenFieldErrors = $this->collectForbiddenFieldErrors($data);
        if ($forbiddenFieldErrors !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $forbiddenFieldErrors,
                ]);
        }

        // Check for conflicting item_category_id and item_category_name
        if (isset($data['item_category_id']) && isset($data['item_category_name'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'item_category_id' => 'Cannot specify both item_category_id and item_category_name.',
                        'item_category_name' => 'Cannot specify both item_category_id and item_category_name.',
                    ],
                ]);
        }

        // Require at least one category field
        if (!isset($data['item_category_id']) && !isset($data['item_category_name'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'item_category_id' => 'Either item_category_id or item_category_name is required.',
                    ],
                ]);
        }

        $rules = [
            'name'             => 'required|max_length[100]',
            'unit_base'        => 'required|max_length[20]',
            'unit_convert'     => 'required|max_length[20]',
            'conversion_base'  => 'required|is_natural_no_zero',
            'min_stock'        => 'permit_empty|is_natural',
            'is_active'        => 'permit_empty',
        ];

        if (isset($data['item_category_id'])) {
            $rules['item_category_id'] = 'required|is_natural_no_zero';
        }

        if (isset($data['item_category_name'])) {
            $rules['item_category_name'] = 'required|max_length[50]';
        }

        if (! $this->validateData($data, $rules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $result = $this->itemService->createItem($data);

        if (! $result['success']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'Item created successfully.',
                'data'    => $result['item'],
            ]);
    }

    /**
     * Applies partial-update semantics to an active item master row.
     *
     * HTTP     : PUT /api/v1/items/{id}
     * Access   : admin, gudang
     * Service  : ItemManagementService::updateItem()
     * Contract : api-contract.md §5.4.5
     *
     * Accepts EITHER item_category_id OR item_category_name — not both.
     * Name matching is case-insensitive and trimmed.
     * Sending both returns 400.
     *
     * @param int $id Item identifier.
     * @return ResponseInterface JSON — message + data envelope for the updated item.
     *
     * @throws \InvalidArgumentException if forbidden writable fields such as qty are sent
     * @throws \DomainException if the item, category, or unit lookup is invalid
     * @throws \RuntimeException if persistence fails
     *
     * @sideeffect none; items.qty remains read-only in this module.
     */
    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];

        $forbiddenFieldErrors = $this->collectForbiddenFieldErrors($data);
        if ($forbiddenFieldErrors !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $forbiddenFieldErrors,
                ]);
        }

        // Check for conflicting item_category_id and item_category_name
        if (isset($data['item_category_id']) && isset($data['item_category_name'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'item_category_id' => 'Cannot specify both item_category_id and item_category_name.',
                        'item_category_name' => 'Cannot specify both item_category_id and item_category_name.',
                    ],
                ]);
        }

        $validationData = [
            ...$data,
            'id' => $id,
        ];

        $rules = [
            'id'               => 'required|is_natural_no_zero',
            'name'             => 'permit_empty|max_length[100]',
            'unit_base'        => 'permit_empty|max_length[20]',
            'unit_convert'     => 'permit_empty|max_length[20]',
            'conversion_base'  => 'permit_empty|is_natural_no_zero',
            'min_stock'        => 'permit_empty|is_natural',
            'is_active'        => 'permit_empty',
        ];

        if (isset($data['item_category_id'])) {
            $rules['item_category_id'] = 'permit_empty|is_natural_no_zero';
        }

        if (isset($data['item_category_name'])) {
            $rules['item_category_name'] = 'permit_empty|max_length[50]';
        }

        if (! $this->validateData($validationData, $rules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $result = $this->itemService->updateItem($id, $data);

        if (! $result['success']) {
            $statusCode = $result['message'] === 'Item not found.' ? 404 : 400;

            return $this->response
                ->setStatusCode($statusCode)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'Item updated successfully.',
                'data'    => $result['item'],
            ]);
    }

    /**
     * Soft-deletes an item master row.
     *
     * HTTP     : DELETE /api/v1/items/{id}
     * Access   : admin
     * Service  : ItemManagementService::deleteItem()
     * Contract : api-contract.md §5.4.6
     *
     * @param int $id Item identifier.
     * @return ResponseInterface JSON — message envelope confirming deletion.
     *
     * @throws \DomainException if the active item does not exist
     * @throws \RuntimeException if soft-delete persistence fails
     *
     * @sideeffect Soft-deletes row (sets deleted_at).
     */
    public function delete(int $id): ResponseInterface
    {
        $result = $this->itemService->deleteItem($id);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Item not found.' => 404,
                default => 400,
            };

            return $this->response
                ->setStatusCode($statusCode)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => $result['message'],
            ]);
    }

    /**
     * Restores a soft-deleted item after validating active FK dependencies.
     *
     * HTTP     : PATCH /api/v1/items/{id}/restore
     * Access   : admin
     * Service  : ItemManagementService::restoreItem()
     * Contract : api-contract.md §5.4.8
     *
     * @param int $id Item identifier.
     * @return ResponseInterface JSON — message + data envelope for the restored item.
     *
     * @throws \DomainException if the item does not exist or an active duplicate name exists
     * @throws \RuntimeException if restore persistence fails
     *
     * @sideeffect Clears deleted_at, validates FK refs still active.
     */
    public function restore(int $id): ResponseInterface
    {
        $result = $this->itemService->restoreItem($id);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Item not found.' => 404,
                'Failed to restore item.' => 422,
                default => 400,
            };

            return $this->response
                ->setStatusCode($statusCode)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'Item restored successfully.',
                'data'    => $result['item'],
            ]);
    }

    private function collectForbiddenFieldErrors(array $data): array
    {
        $forbiddenFields = ItemManagementService::FORBIDDEN_FIELDS;
        $errors          = [];

        foreach ($forbiddenFields as $field) {
            if (array_key_exists($field, $data)) {
                $errors[$field] = sprintf('The %s field cannot be modified directly.', $field);
            }
        }

        return $errors;
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
