<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\StockOpnameService;
use CodeIgniter\HTTP\ResponseInterface;

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
