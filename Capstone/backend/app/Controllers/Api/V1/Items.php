<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ItemManagementService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Items
 *
 * Module   : Items
 * Route    : /api/v1/items
 * Access   : admin, gudang (list/show/create/update); dapur (list/show); admin (delete/restore)
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
     * Access   : admin, dapur, gudang
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
     *
     * @OA\Get(
     *     path="/api/v1/items",
     *     operationId="listItems",
     *     tags={"Items"},
     *     summary="List items",
     *     description="Returns the active item collection in the standard data/meta/links envelope. Accessible to admin, dapur, and gudang users. Runtime accepts page, perPage, item_category_id, is_active, q, search, sortBy, sortDir, created_at_from, created_at_to, updated_at_from, and updated_at_to. The older controller comment mentioning paginate=false is legacy only: the current runtime rejects paginate as an unsupported query parameter with HTTP 400.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Positive page number. Defaults to 1.",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page. Runtime allows 1 through 100 and defaults to 10.",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="paginate",
     *         in="query",
     *         required=false,
     *         deprecated=true,
     *         description="Legacy comment-only flag. The current item runtime does not accept this parameter and returns HTTP 400 if it is sent.",
     *         @OA\Schema(type="string", enum={"true","false","1","0"}, example="false")
     *     ),
     *     @OA\Parameter(
     *         name="sortBy",
     *         in="query",
     *         description="Sortable columns allowlisted by ItemModel::SORTABLE_COLUMNS.",
     *         @OA\Schema(type="string", enum={"id","name","item_category_id","created_at","updated_at"}, example="name")
     *     ),
     *     @OA\Parameter(
     *         name="sortDir",
     *         in="query",
     *         description="Sort direction. Runtime accepts ASC or DESC.",
     *         @OA\Schema(type="string", enum={"ASC","DESC"}, example="ASC")
     *     ),
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Primary text search term for item name matching. If both q and search are sent, q wins.",
     *         @OA\Schema(type="string", example="Ber")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Fallback text search term for item name matching when q is not provided.",
     *         @OA\Schema(type="string", example="Ayam")
     *     ),
     *     @OA\Parameter(
     *         name="item_category_id",
     *         in="query",
     *         description="Filter by active item category id.",
     *         @OA\Schema(type="integer", minimum=1, example=2)
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active state. Runtime accepts 0, 1, true, or false as strings.",
     *         @OA\Schema(type="string", enum={"0","1","true","false"}, example="1")
     *     ),
     *     @OA\Parameter(
     *         name="created_at_from",
     *         in="query",
     *         description="Include items created on or after this date/datetime string.",
     *         @OA\Schema(type="string", example="2026-05-01 00:00:00")
     *     ),
     *     @OA\Parameter(
     *         name="created_at_to",
     *         in="query",
     *         description="Include items created on or before this date/datetime string.",
     *         @OA\Schema(type="string", example="2026-05-31 23:59:59")
     *     ),
     *     @OA\Parameter(
     *         name="updated_at_from",
     *         in="query",
     *         description="Include items updated on or after this date/datetime string.",
     *         @OA\Schema(type="string", example="2026-05-01 00:00:00")
     *     ),
     *     @OA\Parameter(
     *         name="updated_at_to",
     *         in="query",
     *         description="Include items updated on or before this date/datetime string.",
     *         @OA\Schema(type="string", example="2026-05-31 23:59:59")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated item collection. The envelope always contains data, meta, and links, and meta only includes page, perPage, total, and totalPages.",
     *         @OA\JsonContent(ref="#/components/schemas/ItemCollectionResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed for unsupported query parameters or invalid query values.",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Missing or invalid bearer token.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authenticated user lacks the admin, dapur, or gudang role required for this operation.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
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
     * Access   : admin, dapur, gudang
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
     *
     * @OA\Get(
     *     path="/api/v1/items/{id}",
     *     operationId="showItem",
     *     tags={"Items"},
     *     summary="Show one item",
     *     description="Returns one active item resource. Accessible to admin, dapur, and gudang users. Soft-deleted items are treated as not found.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Item identifier.",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Active item resource.",
     *         @OA\JsonContent(ref="#/components/schemas/ItemResource")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Missing or invalid bearer token.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authenticated user lacks the admin, dapur, or gudang role required for this operation.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="The requested active item does not exist.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
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
     *
     * @OA\Post(
     *     path="/api/v1/items",
     *     operationId="createItem",
     *     tags={"Items"},
     *     summary="Create item",
     *     description="Creates a new item master record. Accessible to admin and gudang users. Provide either item_category_id or item_category_name, never both. Category names are trimmed and matched case-insensitively. unit_base and unit_convert must resolve to active item units. Direct mutation of qty, id, created_at, updated_at, and deleted_at is rejected with the validation-error envelope.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Create payload with dual category lookup support. One of item_category_id or item_category_name is required.",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     required={"name","unit_base","unit_convert","conversion_base","item_category_id"},
     *                     @OA\Property(property="name", type="string", maxLength=100, example="Minyak"),
     *                     @OA\Property(property="item_category_id", type="integer", minimum=1, example=3),
     *                     @OA\Property(property="unit_base", type="string", maxLength=20, example="ml"),
     *                     @OA\Property(property="unit_convert", type="string", maxLength=20, example="liter"),
     *                     @OA\Property(property="conversion_base", type="integer", minimum=1, example=1000),
     *                     @OA\Property(property="min_stock", type="integer", minimum=0, example=10),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 ),
     *                 @OA\Schema(
     *                     required={"name","unit_base","unit_convert","conversion_base","item_category_name"},
     *                     @OA\Property(property="name", type="string", maxLength=100, example="Telur"),
     *                     @OA\Property(property="item_category_name", type="string", maxLength=50, example="  BASAH  "),
     *                     @OA\Property(property="unit_base", type="string", maxLength=20, example="butir"),
     *                     @OA\Property(property="unit_convert", type="string", maxLength=20, example="pack"),
     *                     @OA\Property(property="conversion_base", type="integer", minimum=1, example=10),
     *                     @OA\Property(property="min_stock", type="integer", minimum=0, example=0),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item created successfully.",
     *         @OA\JsonContent(ref="#/components/schemas/ItemMutationResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed. Includes forbidden direct qty mutation, missing-or-conflicting category lookup fields, invalid category/unit lookup, duplicate active names, or deleted-name restore guidance.",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Missing or invalid bearer token.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authenticated user lacks the admin or gudang role required for this operation.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
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
     *
     * @OA\Put(
     *     path="/api/v1/items/{id}",
     *     operationId="updateItem",
     *     tags={"Items"},
     *     summary="Update item",
     *     description="Partially updates an active item master record even though the route uses PUT. Accessible to admin and gudang users. Omitted fields are left unchanged. Provide at most one of item_category_id or item_category_name. Direct mutation of qty, id, created_at, updated_at, and deleted_at is rejected with the validation-error envelope.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Item identifier.",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Partial update payload. All fields are optional and only supplied fields are applied. If no updatable fields remain after validation, runtime returns the current item unchanged.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=100, example="Beras Premium"),
     *             @OA\Property(property="item_category_id", type="integer", minimum=1, example=1, description="Mutually exclusive with item_category_name."),
     *             @OA\Property(property="item_category_name", type="string", maxLength=50, example="kering", description="Mutually exclusive with item_category_id and resolved case-insensitively after trimming."),
     *             @OA\Property(property="unit_base", type="string", maxLength=20, example="gram"),
     *             @OA\Property(property="unit_convert", type="string", maxLength=20, example="kg"),
     *             @OA\Property(property="conversion_base", type="integer", minimum=1, example=1000),
     *             @OA\Property(property="min_stock", type="integer", minimum=0, example=5),
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item updated successfully, or the current item is returned unchanged when no updatable fields are supplied.",
     *         @OA\JsonContent(ref="#/components/schemas/ItemMutationResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed for forbidden fields, conflicting category lookup fields, invalid category/unit lookup, invalid booleans, duplicate names, or deleted-name restore guidance.",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Missing or invalid bearer token.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authenticated user lacks the admin or gudang role required for this operation.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="The requested item does not exist.",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"message","errors"},
     *             @OA\Property(property="message", type="string", example="Item not found."),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"), example={})
     *         )
     *     )
     * )
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
     *
     * @OA\Delete(
     *     path="/api/v1/items/{id}",
     *     operationId="deleteItem",
     *     tags={"Items"},
     *     summary="Delete item",
     *     description="Soft-deletes an item master row. Admin only. Successful and failed responses are message-only envelopes. Runtime maps any service failure to HTTP 404, including a missing item.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Item identifier.",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item soft-deleted successfully.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Missing or invalid bearer token.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authenticated user lacks the admin role required for this operation.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="The item could not be deleted because it was not found by the runtime service.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
    public function delete(int $id): ResponseInterface
    {
        $result = $this->itemService->deleteItem($id);

        if (! $result['success']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => $result['message'],
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
     *
     * @OA\Patch(
     *     path="/api/v1/items/{id}/restore",
     *     operationId="restoreItem",
     *     tags={"Items"},
     *     summary="Restore item",
     *     description="Restores a soft-deleted item after validating that its category and units are still active. Admin only. The operation is idempotent for already-active items and still returns HTTP 200 with the current item. Runtime returns HTTP 404 for a missing item, HTTP 422 when the low-level restore action itself fails, and HTTP 400 for validation-style conflicts such as inactive foreign keys or an active duplicate name.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Item identifier.",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item restored successfully, or the currently active item was returned unchanged.",
     *         @OA\JsonContent(ref="#/components/schemas/ItemMutationResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Missing or invalid bearer token.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authenticated user lacks the admin role required for this operation.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="The requested item does not exist, including among soft-deleted rows.",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"message","errors"},
     *             @OA\Property(property="message", type="string", example="Item not found."),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"), example={})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Restore validation failed because a referenced category or unit is inactive, or an active item with the same name already exists.",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="The runtime attempted the restore but the persistence layer reported failure.",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"message","errors"},
     *             @OA\Property(property="message", type="string", example="Failed to restore item."),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"), example={})
     *         )
     *     )
     * )
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
