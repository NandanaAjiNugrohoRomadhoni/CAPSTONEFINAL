<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\ReportingService;
use CodeIgniter\HTTP\ResponseInterface;

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
