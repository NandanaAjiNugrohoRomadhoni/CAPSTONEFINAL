<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\DashboardAggregateService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Dashboard
 *
 * Role-conditioned operational summary endpoint for admin, dapur, and gudang users.
 */
class Dashboard extends BaseController
{
    protected DashboardAggregateService $dashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardAggregateService();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/dashboard",
     *     operationId="getDashboardAggregate",
     *     tags={"Dashboard"},
     *     summary="Get dashboard aggregate",
     *     description="Returns the minimum runtime dashboard envelope for the authenticated user's role. Accessible to admin, dapur, and gudang users through the protected dashboard route group. The response always contains data.role, data.generated_at, and data.aggregates; aggregate keys are role-conditioned and only document fields explicitly shaped by DashboardAggregateService.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Role-scoped dashboard aggregate response.",
     *         @OA\JsonContent(ref="#/components/schemas/DashboardResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         ref="#/components/responses/UnauthorizedMessageResponse"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authenticated account is inactive, deleted, or does not satisfy the dashboard access policy.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
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
