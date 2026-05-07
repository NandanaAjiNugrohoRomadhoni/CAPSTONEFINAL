<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\SpkStockInPrefillService;
use CodeIgniter\HTTP\ResponseInterface;

class SpkStockInPrefill extends BaseController
{
    protected SpkStockInPrefillService $prefillService;

    public function __construct()
    {
        $this->prefillService = new SpkStockInPrefillService();
    }

    public function show(int $spkId): ResponseInterface
    {
        $result = $this->prefillService->buildDraftFromSpk($spkId);

        if (! $result['success']) {
            return $this->response
                ->setStatusCode((int) ($result['status_code'] ?? 400))
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $result['data'],
            ]);
    }
}
