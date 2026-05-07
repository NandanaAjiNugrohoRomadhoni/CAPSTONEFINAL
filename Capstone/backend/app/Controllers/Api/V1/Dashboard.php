<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\DashboardAggregateService;
use CodeIgniter\HTTP\ResponseInterface;

class Dashboard extends BaseController
{
    protected DashboardAggregateService $dashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardAggregateService();
    }

    public function index(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $result = $this->dashboardService->getDashboardAggregateForUser((int) $user->id);
        if (! $result['success']) {
            return $this->response
                ->setStatusCode((int) ($result['status'] ?? 400))
                ->setJSON([
                    'message' => $result['message'],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $result['data'],
            ]);
    }
}
