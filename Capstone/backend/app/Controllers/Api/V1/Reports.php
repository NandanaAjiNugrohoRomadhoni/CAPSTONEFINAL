<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ReportingService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

class Reports extends BaseController
{
    protected ReportingService $reportingService;

    public function __construct()
    {
        $this->reportingService = new ReportingService();
    }

    public function stocks(): ResponseInterface
    {
        return $this->handleReportResponse(
            $this->reportingService->getStockReport($this->request->getGet())
        );
    }

    public function transactions(): ResponseInterface
    {
        return $this->handleReportResponse(
            $this->reportingService->getTransactionReport($this->request->getGet())
        );
    }

    public function spkHistory(): ResponseInterface
    {
        return $this->handleReportResponse(
            $this->reportingService->getSpkHistoryReport($this->request->getGet())
        );
    }

    public function evaluation(): ResponseInterface
    {
        return $this->handleReportResponse(
            $this->reportingService->getEvaluationReport($this->request->getGet())
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/reports/monthly-stock-export",
     *     operationId="getMonthlyStockExport",
     *     tags={"Reports"},
     *     summary="Get monthly stock export",
     *     description="Returns the monthly per-item stock movement export dataset grouped by transaction_date. Accessible to admin, dapur, and gudang users. The report uses transaction_date as the business date, applies category filtering at item level, and reads stok_awal from monthly_stock_snapshots when available.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="period_start", in="query", required=true, description="Inclusive start date in Y-m-d format.", @OA\Schema(type="string", example="2026-04-01")),
     *     @OA\Parameter(name="period_end", in="query", required=true, description="Inclusive end date in Y-m-d format.", @OA\Schema(type="string", example="2026-04-30")),
     *     @OA\Parameter(name="category_id", in="query", required=false, description="Optional item category filter.", @OA\Schema(type="integer", minimum=1, example=3)),
     *     @OA\Parameter(name="item_id", in="query", required=false, description="Optional item filter.", @OA\Schema(type="integer", minimum=1, example=9)),
     *     @OA\Response(
     *         response=200,
     *         description="Monthly stock export response.",
     *         @OA\JsonContent(ref="#/components/schemas/MonthlyStockExportResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation failed for missing, malformed, reversed, or unsupported query parameters.",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         ref="#/components/responses/UnauthorizedMessageResponse"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
    public function monthlyStockExport(): ResponseInterface
    {
        return $this->handleReportResponse(
            $this->reportingService->getMonthlyStockExport($this->request->getGet())
        );
    }

    private function handleReportResponse(array $result): ResponseInterface
    {
        if (! ($result['success'] ?? false)) {
            return $this->response
                ->setStatusCode((int) ($result['status'] ?? 400))
                ->setJSON([
                    'message' => $result['message'] ?? 'Validation failed.',
                    'errors' => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $result['data'],
            ]);
    }
}
