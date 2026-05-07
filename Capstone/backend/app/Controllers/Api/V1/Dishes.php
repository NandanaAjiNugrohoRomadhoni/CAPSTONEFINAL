<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\DishManagementService;
use CodeIgniter\HTTP\ResponseInterface;

class Dishes extends BaseController
{
    protected DishManagementService $dishService;

    public function __construct()
    {
        $this->dishService = new DishManagementService();
    }

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
