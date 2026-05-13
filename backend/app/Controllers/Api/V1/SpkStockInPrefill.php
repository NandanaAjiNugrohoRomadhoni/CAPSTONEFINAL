<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\SpkStockInPrefillService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * SPK Stock-In Prefill
 *
 * Shared helper that converts one SPK history row into an editable stock-IN draft payload.
 */
class SpkStockInPrefill extends BaseController
{
    protected SpkStockInPrefillService $prefillService;

    public function __construct()
    {
        $this->prefillService = new SpkStockInPrefillService();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spk/stock-in-prefill/{id}",
     *     operationId="getSpkStockInPrefill",
     *     tags={"SPK Shared"},
     *     summary="Build stock-in prefill draft from SPK",
     *     description="Returns an editable stock-IN draft payload derived from one persisted SPK history row. Accessible to admin, dapur, and gudang users. This endpoint does not create stock transactions or finalize the SPK by itself.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="SPK history identifier.", @OA\Schema(type="integer", minimum=1, example=31)),
     *     @OA\Response(response=200, description="Editable stock-in draft payload.", @OA\JsonContent(type="object", required={"data"}, @OA\Property(property="data", type="object", additionalProperties=true))),
     *     @OA\Response(response=400, description="Validation failed for an invalid or unsupported SPK draft.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="SPK history not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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
