<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\MenuScheduleManagementService;
use CodeIgniter\HTTP\ResponseInterface;

class MenuSchedules extends BaseController
{
    protected MenuScheduleManagementService $menuScheduleService;

    public function __construct()
    {
        $this->menuScheduleService = new MenuScheduleManagementService();
    }

    public function index(): ResponseInterface
    {
        $result = $this->menuScheduleService->getAllSchedules();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $result['data'],
                'meta'  => $result['meta'],
                'links' => $this->buildStaticLinks(),
            ]);
    }

    public function show(int $id): ResponseInterface
    {
        $schedule = $this->menuScheduleService->getScheduleById($id);

        if ($schedule === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'Menu schedule not found.',
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $schedule,
            ]);
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->menuScheduleService->createSchedule($data);

        if (! $result['success']) {
            return $this->response
                ->setStatusCode($result['message'] === 'Failed to create menu schedule.' ? 422 : 400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'Menu schedule created successfully.',
                'data'    => $result['schedule'],
            ]);
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $result = $this->menuScheduleService->updateSchedule($id, $data);

        if (! $result['success']) {
            $statusCode = match ($result['message']) {
                'Menu schedule not found.' => 404,
                'Failed to update menu schedule.' => 422,
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
                'message' => 'Menu schedule updated successfully.',
                'data'    => $result['schedule'],
            ]);
    }

    public function calendarProjection(): ResponseInterface
    {
        $result = $this->menuScheduleService->resolveCalendar($this->request->getGet());

        if (! $result['success']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        $payload = [
            'data' => $result['data'],
        ];

        if (isset($result['meta'])) {
            $payload['meta'] = $result['meta'];
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON($payload);
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
