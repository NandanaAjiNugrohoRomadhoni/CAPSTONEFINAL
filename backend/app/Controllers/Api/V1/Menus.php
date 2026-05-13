<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Menus
 *
 * Module   : Menu Core
 * Route    : /api/v1/menus and /api/v1/menu-dishes
 * Access   : admin, dapur, gudang (read); admin, dapur (menu-dishes write)
 * Canonical: backend/docs/reference/api-contract.md §5.6
 */
class Menus extends BaseController
{
    protected $menuService;

    public function __construct()
    {
        $serviceClass = 'App\\Services\\MenuPackageManagementService';
        $this->menuService = new $serviceClass();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/menus",
     *     operationId="listMenus",
     *     tags={"Menus"},
     *     summary="List menu package headers",
     *     description="Returns the fixed package header collection used by the menu core. Accessible to admin, dapur, and gudang users. This surface is read-only and collection-only: unsupported menu header detail or CRUD routes are intentionally not documented here.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Non-paginated menu package collection.", @OA\JsonContent(ref="#/components/schemas/MenuCollectionResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the required role for this read operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function index(): ResponseInterface
    {
        $result = $this->menuService->getAllMenus();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $result['data'],
                'meta'  => $result['meta'],
                'links' => $this->buildStaticLinks(),
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/menu-dishes",
     *     operationId="listMenuDishes",
     *     tags={"Menus"},
     *     summary="List menu slot assignments",
     *     description="Returns all current menu slot assignments with nested menu, meal_time, and dish summaries. Accessible to admin, dapur, and gudang users. Runtime returns a non-paginated data/meta/links envelope.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Non-paginated menu slot collection.", @OA\JsonContent(ref="#/components/schemas/MenuDishCollectionResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the required role for this read operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function slots(): ResponseInterface
    {
        $result = $this->menuService->getAllSlots();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $result['data'],
                'meta'  => $result['meta'],
                'links' => $this->buildStaticLinks(),
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/menu-dishes",
     *     operationId="createMenuDish",
     *     tags={"Menus"},
     *     summary="Assign dish to menu slot",
     *     description="Assigns a dish to one menu and meal-time slot. Accessible to admin and dapur users. Runtime rejects duplicate occupied slots with HTTP 400 using the composite validation key menu_id,meal_time_id.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"menu_id","meal_time_id","dish_id"},
     *             @OA\Property(property="menu_id", type="integer", minimum=1, example=1, description="Must resolve to one of the fixed menu package ids 1 through 11."),
     *             @OA\Property(property="meal_time_id", type="integer", minimum=1, example=1),
     *             @OA\Property(property="dish_id", type="integer", minimum=1, example=2)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Menu slot assigned successfully.", @OA\JsonContent(ref="#/components/schemas/MenuDishMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for missing fields, invalid menu/meal time/dish references, or an already occupied menu_id + meal_time_id slot.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="The runtime attempted persistence but menu slot creation failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function assignSlot(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->menuService->assignDishToSlot($data);

        if (! $result['success']) {
            return $this->response
                ->setStatusCode($result['message'] === 'Failed to assign menu slot.' ? 422 : 400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'Menu slot assigned successfully.',
                'data'    => $result['slot'],
            ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/menu-dishes/{id}",
     *     operationId="updateMenuDish",
     *     tags={"Menus"},
     *     summary="Update menu slot assignment",
     *     description="Updates an existing menu slot assignment. Accessible to admin and dapur users. At least one of menu_id, meal_time_id, or dish_id must be provided. Duplicate occupied slots return HTTP 400 with the composite validation key menu_id,meal_time_id, missing rows return HTTP 404, and persistence failure returns HTTP 422.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Menu slot identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="menu_id", type="integer", minimum=1, example=4),
     *             @OA\Property(property="meal_time_id", type="integer", minimum=1, example=2),
     *             @OA\Property(property="dish_id", type="integer", minimum=1, example=3)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Menu slot updated successfully.", @OA\JsonContent(ref="#/components/schemas/MenuDishMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for empty payloads, invalid references, or occupied menu_id + meal_time_id combinations.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Menu slot not found.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse")),
     *     @OA\Response(response=422, description="The runtime attempted persistence but menu slot update failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function updateSlot(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->menuService->updateSlotAssignment($id, $data);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Menu slot not found.' => 404,
                'Failed to update menu slot.' => 422,
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
                'data'    => $result['data'],
            ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/menu-dishes/{id}",
     *     operationId="deleteMenuDish",
     *     tags={"Menus"},
     *     summary="Delete menu slot assignment",
     *     description="Deletes an existing menu slot assignment. Accessible to admin and dapur users.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Menu slot identifier.", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Menu slot deleted successfully.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=401, description="Missing or invalid bearer token.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or dapur role required for this write operation.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Menu slot not found.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse")),
     *     @OA\Response(response=422, description="The runtime attempted deletion but menu slot persistence failed.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
    public function deleteSlot(int $id): ResponseInterface
    {
        $result = $this->menuService->deleteSlotAssignment($id);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Menu slot not found.' => 404,
                'Failed to delete menu slot.' => 422,
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

    private function buildStaticLinks(): array
    {
        $self = current_url();

        return [
            'self'     => $self,
            'first'    => $self,
            'last'     => $self,
            'next'     => null,
            'previous' => null,
        ];
    }
}
