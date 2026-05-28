<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\StockOpnameService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

class StockOpnames extends BaseController
{
    protected StockOpnameService $stockOpnameService;

    public function __construct()
    {
        $this->stockOpnameService = new StockOpnameService();
    }

    public function create(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->createDraft(
            $this->request->getJSON(true) ?? [],
            (int) $user->id,
            $this->request->getIPAddress(),
        );

        if (! $result['success']) {
            return $this->response->setStatusCode(400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(201)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/stock-opnames/{id}",
     *     operationId="updateStockOpname",
     *     tags={"Stock Opnames"},
     *     summary="Update stock opname draft or rejected revision",
     *     description="Updates an existing stock opname while it is still editable. Accessible to admin and gudang users. Runtime only allows updates when the current state is DRAFT or REJECTED. Submitted, approved, and posted stock opnames are immutable. The update fully replaces the detail set inside one database transaction and rewrites system/count/variance snapshots before the record is re-submitted.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Stock opname identifier.", @OA\Schema(type="integer", minimum=1, example=12)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StockOpnameMutationRequest")),
     *     @OA\Response(response=200, description="Stock opname updated successfully.", @OA\JsonContent(ref="#/components/schemas/StockOpnameActionResponse")),
     *     @OA\Response(response=400, description="Validation failed for invalid payloads, duplicate items, invalid item references, or immutable workflow states.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Stock opname not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function update(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->updateDraft(
            $id,
            $this->request->getJSON(true) ?? [],
            (int) $user->id,
            $this->request->getIPAddress(),
        );

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $result = $this->stockOpnameService->findByIdWithDetails($id);
        if ($result === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => 'Stock opname not found.',
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'data' => $result,
        ]);
    }

    public function submit(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->submit($id, (int) $user->id, $this->request->getIPAddress());

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    public function approve(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->approve($id, (int) $user->id, $this->request->getIPAddress());

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    public function reject(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->reject(
            $id,
            $this->request->getJSON(true) ?? [],
            (int) $user->id,
            $this->request->getIPAddress(),
        );

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    public function post(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->post($id, (int) $user->id, $this->request->getIPAddress());

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }
}
