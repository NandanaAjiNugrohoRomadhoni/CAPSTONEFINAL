<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\DishCompositionManagementService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Dish Compositions
 *
 * Module   : Menu Core
 * Route    : /api/v1/dish-compositions
 * Access   : admin, dapur, gudang (read); admin, dapur (write)
 * Canonical: backend/docs/reference/api-contract.md §5.6
 */
class DishCompositions extends BaseController
{
    protected DishCompositionManagementService $compositionService;

    public function __construct()
    {
        $this->compositionService = new DishCompositionManagementService();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dish-compositions",
     *     operationId="listDishCompositions",
     *     tags={"Dish Compositions"},
     *     summary="List dish compositions",
     *     description="Returns dish compositions in the standard data/meta/links envelope. Accessible to admin, dapur, and gudang users. Runtime accepts paginate, page, perPage, dish_id, item_id, q, search, sortBy, sortDir, created_at_from, created_at_to, updated_at_from, and updated_at_to. Unknown query parameters return HTTP 400.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Positive page number. Defaults to 1.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", description="Rows per page. Runtime allows 1 through 100 and defaults to 10.", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="paginate", in="query", description="Set false or 0 to return all matching rows while keeping the same envelope and meta.paginated=false.", @OA\Schema(type="string", enum={"true","false","1","0"}, example="true")),
     *     @OA\Parameter(name="dish_id", in="query", description="Filter by dish identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="item_id", in="query", description="Filter by item identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="q", in="query", description="Primary text search term. If both q and search are present, q wins.", @OA\Schema(type="string", example="beras")),
     *     @OA\Parameter(name="search", in="query", description="Fallback text search term when q is omitted.", @OA\Schema(type="string", example="tim")),
     *     @OA\Parameter(name="sortBy", in="query", description="Sortable columns allowlisted by DishCompositionModel::SORTABLE_COLUMNS.", @OA\Schema(type="string", enum={"id","dish_id","item_id","qty_per_patient","created_at","updated_at"}, example="id")),
     *     @OA\Parameter(name="sortDir", in="query", description="Sort direction.", @OA\Schema(type="string", enum={"ASC","DESC"}, example="ASC")),
     *     @OA\Parameter(name="created_at_from", in="query", description="Include rows created on or after this date/datetime string.", @OA\Schema(type="string", example="2026-05-01 00:00:00")),
     *     @OA\Parameter(name="created_at_to", in="query", description="Include rows created on or before this date/datetime string.", @OA\Schema(type="string", example="2026-05-31 23:59:59")),
     *     @OA\Parameter(name="updated_at_from", in="query", description="Include rows updated on or after this date/datetime string.", @OA\Schema(type="string", example="2026-05-01 00:00:00")),
     *     @OA\Parameter(name="updated_at_to", in="query", description="Include rows updated on or before this date/datetime string.", @OA\Schema(type="string", example="2026-05-31 23:59:59")),
     *     @OA\Response(response=200, description="Dish composition collection response.", @OA\JsonContent(ref="#/components/schemas/DishCompositionCollectionResponse")),
     *     @OA\Response(response=400, description="Validation failed for unsupported query parameters or invalid query values.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the required role for this read operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function index(): ResponseInterface
    {
        $result = $this->compositionService->getAllCompositions($this->request->getGet());

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
     * @OA\Get(
     *     path="/api/v1/dish-compositions/{id}",
     *     operationId="showDishComposition",
     *     tags={"Dish Compositions"},
     *     summary="Show one dish composition",
     *     description="Returns one dish composition including nested dish and item summaries. Accessible to admin, dapur, and gudang users.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Dish composition identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Dish composition resource.", @OA\JsonContent(ref="#/components/schemas/DishCompositionResource")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the required role for this read operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Dish composition not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function show(int $id): ResponseInterface
    {
        $composition = $this->compositionService->getCompositionById($id);

        if ($composition === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'Dish composition not found.',
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $composition,
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dish-compositions",
     *     operationId="createDishComposition",
     *     tags={"Dish Compositions"},
     *     summary="Create dish composition",
     *     description="Creates a dish composition row linking one dish to one active item with a per-patient quantity. Accessible to admin and dapur users. Compositions can be created for both active and inactive dishes, and they persist even if a dish is later deactivated. Duplicate dish_id + item_id pairs return HTTP 400 with the composite validation key dish_id,item_id.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"dish_id","item_id","qty_per_patient"},
     *             @OA\Property(property="dish_id", type="integer", minimum=1, example=1),
     *             @OA\Property(property="item_id", type="integer", minimum=1, example=1),
     *             @OA\Property(property="qty_per_patient", type="string", example="125.50", description="Positive decimal string accepted by the runtime validation rules.")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Dish composition created successfully.", @OA\JsonContent(ref="#/components/schemas/DishCompositionMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for missing fields, invalid references, inactive item references, or duplicate dish_id + item_id combinations.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="The runtime attempted persistence but dish composition creation failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function create(): ResponseInterface
    {
    $data = $this->request->getJSON(true) ?? [];
    $actor = auth()->user();
    $actorId = $actor?->id;
    $ipAddress = $this->request->getIPAddress();
    $result = $this->compositionService->createComposition($data, $actorId, $ipAddress);

        if (! $result['success']) {
            return $this->response
                ->setStatusCode($result['message'] === 'Failed to create dish composition.' ? 422 : 400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'Dish composition created successfully.',
                'data'    => $result['composition'],
            ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/dish-compositions/{id}",
     *     operationId="updateDishComposition",
     *     tags={"Dish Compositions"},
     *     summary="Update dish composition",
     *     description="Updates an existing dish composition. Accessible to admin and dapur users. Compositions persist and remain editable even while a dish is inactive. The route behaves like a partial update: omitted fields keep their existing values, and an empty payload returns the current composition unchanged. Duplicate dish_id + item_id pairs return HTTP 400 with the composite validation key dish_id,item_id.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Dish composition identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="dish_id", type="integer", minimum=1, example=1),
     *             @OA\Property(property="item_id", type="integer", minimum=1, example=1),
     *             @OA\Property(property="qty_per_patient", type="string", example="140.75")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Dish composition updated successfully or returned unchanged when no updatable fields are sent.", @OA\JsonContent(ref="#/components/schemas/DishCompositionMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for invalid references, inactive item references, invalid decimals, or duplicate dish_id + item_id combinations.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Dish composition not found.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse")),
     *     @OA\Response(response=422, description="The runtime attempted persistence but dish composition update failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
    $actor = auth()->user();
    $actorId = $actor?->id;
    $ipAddress = $this->request->getIPAddress();
    $result = $this->compositionService->updateComposition($id, $data, $actorId, $ipAddress);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Dish composition not found.' => 404,
                'Failed to update dish composition.' => 422,
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
                'message' => 'Dish composition updated successfully.',
                'data'    => $result['composition'],
            ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/dish-compositions/{id}",
     *     operationId="deleteDishComposition",
     *     tags={"Dish Compositions"},
     *     summary="Delete dish composition",
     *     description="Deletes one dish composition row. Accessible to admin and dapur users.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Dish composition identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Dish composition deleted successfully.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Dish composition not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="The runtime attempted deletion but persistence failed.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function delete(int $id): ResponseInterface
    {
    $actor = auth()->user();
    $actorId = $actor?->id;
    $ipAddress = $this->request->getIPAddress();
    $result = $this->compositionService->deleteComposition($id, $actorId, $ipAddress);

        if (! $result['success']) {
            $statusCode = $result['message'] === 'Dish composition not found.' ? 404 : 422;

            return $this->response
                ->setStatusCode($statusCode)
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
