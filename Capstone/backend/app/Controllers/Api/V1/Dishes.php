<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\DishManagementService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Dishes
 *
 * Module   : Menu Core
 * Route    : /api/v1/dishes
 * Access   : admin, dapur, gudang (read); admin, dapur (write)
 * Canonical: backend/docs/reference/api-contract.md §5.6
 */
class Dishes extends BaseController
{
    protected DishManagementService $dishService;

    public function __construct()
    {
        $this->dishService = new DishManagementService();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dishes",
     *     operationId="listDishes",
     *     tags={"Dishes"},
     *     summary="List dishes",
     *     description="Returns the dish collection in the standard data/meta/links envelope. Accessible to admin, dapur, and gudang users. Runtime accepts paginate, page, perPage, q, search, sortBy, sortDir, created_at_from, created_at_to, updated_at_from, updated_at_to, and is_active. Unknown query parameters return HTTP 400.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", description="Positive page number. Defaults to 1.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", description="Rows per page. Runtime allows 1 through 100 and defaults to 10.", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="paginate", in="query", description="Set false or 0 to return all matching rows while keeping the same envelope and meta.paginated=false.", @OA\Schema(type="string", enum={"true","false","1","0"}, example="false")),
     *     @OA\Parameter(name="q", in="query", description="Primary text search term for dish names. If both q and search are present, q wins.", @OA\Schema(type="string", example="na")),
     *     @OA\Parameter(name="search", in="query", description="Fallback text search term when q is omitted.", @OA\Schema(type="string", example="ayam")),
     *     @OA\Parameter(name="sortBy", in="query", description="Sortable columns allowlisted by DishModel::SORTABLE_COLUMNS.", @OA\Schema(type="string", enum={"id","name","created_at","updated_at"}, example="name")),
     *     @OA\Parameter(name="sortDir", in="query", description="Sort direction.", @OA\Schema(type="string", enum={"ASC","DESC"}, example="DESC")),
     *     @OA\Parameter(name="created_at_from", in="query", description="Include rows created on or after this date/datetime string.", @OA\Schema(type="string", example="2026-05-01 00:00:00")),
     *     @OA\Parameter(name="created_at_to", in="query", description="Include rows created on or before this date/datetime string.", @OA\Schema(type="string", example="2026-05-31 23:59:59")),
     *     @OA\Parameter(name="updated_at_from", in="query", description="Include rows updated on or after this date/datetime string.", @OA\Schema(type="string", example="2026-05-01 00:00:00")),
     *     @OA\Parameter(name="updated_at_to", in="query", description="Include rows updated on or before this date/datetime string.", @OA\Schema(type="string", example="2026-05-31 23:59:59")),
     *     @OA\Parameter(name="is_active", in="query", description="Filter dishes by lifecycle state using true, false, 1, or 0.", @OA\Schema(type="string", enum={"true","false","1","0"}, example="true")),
     *     @OA\Response(response=200, description="Dish collection response.", @OA\JsonContent(ref="#/components/schemas/DishCollectionResponse")),
     *     @OA\Response(response=400, description="Validation failed for unsupported query parameters or invalid query values.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the required role for this read operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function index(): ResponseInterface
    {
        $result = $this->dishService->getAllDishes($this->request->getGet());

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
     *     path="/api/v1/dishes/{id}",
     *     operationId="showDish",
     *     tags={"Dishes"},
     *     summary="Show one dish",
     *     description="Returns one dish resource. Accessible to admin, dapur, and gudang users.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Dish identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Dish resource.", @OA\JsonContent(ref="#/components/schemas/DishResource")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the required role for this read operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Dish not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function show(int $id): ResponseInterface
    {
        $dish = $this->dishService->getDishById($id);

        if ($dish === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'Dish not found.',
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $dish,
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/dishes",
     *     operationId="createDish",
     *     tags={"Dishes"},
     *     summary="Create dish",
     *     description="Creates a new dish master row. Accessible to admin and dapur users. Dish names are trimmed before persistence and duplicate dish names are rejected with HTTP 400 validation errors.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=100, example="Bubur Kacang Hijau")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Dish created successfully.", @OA\JsonContent(ref="#/components/schemas/DishMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for missing or duplicate names.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="The runtime attempted persistence but dish creation failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->dishService->createDish($data);

        if (! $result['success']) {
            return $this->response
                ->setStatusCode($result['message'] === 'Failed to create dish.' ? 422 : 400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'Dish created successfully.',
                'data'    => $result['dish'],
            ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/dishes/{id}",
     *     operationId="updateDish",
     *     tags={"Dishes"},
     *     summary="Update dish",
     *     description="Updates an existing dish. Accessible to admin and dapur users. The route behaves like a partial update: omitting name returns the current dish unchanged. Duplicate names return HTTP 400, missing rows return HTTP 404, and low-level persistence failure returns HTTP 422.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Dish identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", maxLength=100, example="Bubur Ayam Spesial")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Dish updated successfully or returned unchanged when no updatable field is sent.", @OA\JsonContent(ref="#/components/schemas/DishMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for invalid or duplicate names.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Dish not found.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse")),
     *     @OA\Response(response=422, description="The runtime attempted persistence but dish update failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->dishService->updateDish($id, $data);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Dish not found.' => 404,
                'Failed to update dish.' => 422,
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
                'message' => 'Dish updated successfully.',
                'data'    => $result['dish'],
            ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/dishes/{id}",
     *     operationId="deleteDish",
     *     tags={"Dishes"},
     *     summary="Delete dish",
     *     description="Deletes a dish row. Accessible to admin and dapur users. Runtime blocks deletion while the dish is active or still referenced by menu slots. Inactive dishes detached from menu slots can be deleted; associated dish compositions are removed by database cascade on final delete.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Dish identifier.", @OA\Schema(type="integer", minimum=1, example=3)),
     *     @OA\Response(response=200, description="Dish deleted successfully.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=400, description="Validation failed because the dish is active or still referenced by menu slots.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Dish not found.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse")),
     *     @OA\Response(response=422, description="The runtime attempted deletion but persistence failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function delete(int $id): ResponseInterface
    {
        $result = $this->dishService->deleteDish($id);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Dish not found.' => 404,
                'Failed to delete dish.' => 422,
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
     * @OA\Patch(
     *     path="/api/v1/dishes/{id}/deactivate",
     *     operationId="deactivateDish",
     *     tags={"Dishes"},
     *     summary="Deactivate dish",
     *     description="Deactivates a dish. Accessible to admin and dapur users. Deactivation preserves the dish row and its compositions but removes all associated menu slot assignments. Returns 200 with the updated dish state.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Dish identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Dish deactivated successfully.", @OA\JsonContent(ref="#/components/schemas/DishMutationResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Dish not found.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse")),
     *     @OA\Response(response=422, description="The runtime attempted deactivation but persistence failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function deactivate(int $id): ResponseInterface
    {
        $result = $this->dishService->deactivateDish($id);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Dish not found.' => 404,
                'Failed to deactivate dish.' => 422,
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
                'message' => 'Dish deactivated successfully.',
                'data'    => $this->normalizeDishMutationPayload($result['dish']),
            ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/dishes/{id}/reactivate",
     *     operationId="reactivateDish",
     *     tags={"Dishes"},
     *     summary="Reactivate dish",
     *     description="Reactivates a dish. Accessible to admin and dapur users. Reactivation only sets is_active to true, allowing the dish to be assigned to menu slots again. It does not restore prior menu slot assignments. Returns 200 with the updated dish state.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Dish identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Dish reactivated successfully.", @OA\JsonContent(ref="#/components/schemas/DishMutationResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Dish not found.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse")),
     *     @OA\Response(response=422, description="The runtime attempted reactivation but persistence failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function reactivate(int $id): ResponseInterface
    {
        $result = $this->dishService->reactivateDish($id);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Dish not found.' => 404,
                'Failed to reactivate dish.' => 422,
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
                'message' => 'Dish reactivated successfully.',
                'data'    => $this->normalizeDishMutationPayload($result['dish']),
            ]);
    }

    private function normalizeDishMutationPayload(array $dish): array
    {
        if (array_key_exists('is_active', $dish)) {
            $dish['is_active'] = (bool) $dish['is_active'];
        }

        return $dish;
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
