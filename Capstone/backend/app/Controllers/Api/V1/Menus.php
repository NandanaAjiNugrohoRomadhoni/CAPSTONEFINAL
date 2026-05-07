<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class Menus extends BaseController
{
    protected $menuService;

    public function __construct()
    {
        $serviceClass = 'App\\Services\\MenuPackageManagementService';
        $this->menuService = new $serviceClass();
    }

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
