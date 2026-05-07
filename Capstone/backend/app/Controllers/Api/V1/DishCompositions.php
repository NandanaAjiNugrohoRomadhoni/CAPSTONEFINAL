<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\DishCompositionManagementService;
use CodeIgniter\HTTP\ResponseInterface;

class DishCompositions extends BaseController
{
    protected DishCompositionManagementService $compositionService;

    public function __construct()
    {
        $this->compositionService = new DishCompositionManagementService();
    }

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

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->compositionService->createComposition($data);

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

    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->compositionService->updateComposition($id, $data);

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

    public function delete(int $id): ResponseInterface
    {
        $result = $this->compositionService->deleteComposition($id);

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
