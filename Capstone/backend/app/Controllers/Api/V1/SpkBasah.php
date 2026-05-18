<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\SpkCalculationModel;
use App\Models\SpkRecommendationModel;
use App\Services\MenuScheduleManagementService;
use App\Services\OperationalStockPreviewService;
use App\Services\SpkBasahGenerationService;
use App\Services\SpkOverrideService;
use App\Services\SpkStockPostingService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * SPK Basah
 *
 * Fresh-item planning workflow surface for basah menu projection, preview, generation, history review, override, and post-stock finalization.
 */
class SpkBasah extends BaseController
{
    protected MenuScheduleManagementService $menuScheduleService;
    protected SpkBasahGenerationService $spkBasahGenerationService;
    protected OperationalStockPreviewService $operationalStockPreviewService;
    protected SpkOverrideService $spkOverrideService;
    protected SpkCalculationModel $spkCalculationModel;
    protected SpkRecommendationModel $spkRecommendationModel;
    protected SpkStockPostingService $spkStockPostingService;

    public function __construct()
    {
        $this->menuScheduleService        = new MenuScheduleManagementService();
        $this->spkBasahGenerationService  = new SpkBasahGenerationService();
        $this->operationalStockPreviewService = new OperationalStockPreviewService();
        $this->spkOverrideService         = new SpkOverrideService();
        $this->spkCalculationModel        = new SpkCalculationModel();
        $this->spkRecommendationModel     = new SpkRecommendationModel();
        $this->spkStockPostingService     = new SpkStockPostingService();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/spk/basah/operational-stock-preview",
     *     operationId="previewSpkBasahOperationalStock",
     *     tags={"SPK Basah"},
     *     summary="Preview same-day operational stock",
     *     description="Builds a same-day draft stock-out preview for the requested basah meal context without mutating stock, creating SPK history rows, or creating stock transactions. Accessible to admin, dapur, and gudang users. Runtime requires service_date, meal_time, and total_patients. meal_time is normalized to uppercase and must resolve against the meal_times table. Errors are returned when the meal_time, menu mapping, recipe mapping, or BASAH category configuration is missing.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/OperationalStockPreviewRequest")),
     *     @OA\Response(response=200, description="Operational preview response. This is a draft preview only and does not finalize or reserve stock.", @OA\JsonContent(ref="#/components/schemas/OperationalStockPreviewResponse")),
     *     @OA\Response(response=400, description="Validation failed for invalid dates, meal times, patient counts, or missing menu/recipe/category configuration.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function operationalStockPreview(): ResponseInterface
    {
        $result = $this->operationalStockPreviewService->previewSameDay(
            $this->request->getJSON(true) ?? []
        );

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
                'data' => $result['data'],
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spk/basah/menu-calendar",
     *     operationId="getSpkBasahMenuCalendarProjection",
     *     tags={"SPK Basah"},
     *     summary="Resolve SPK basah menu calendar projection",
     *     description="Returns the protected SPK basah menu-calendar projection wrapper around the shared MenuScheduleManagementService resolver. Accessible to admin, dapur, and gudang users. Runtime requires exactly one resolver mode: date, month, or start_date plus end_date. The payload shape intentionally mirrors the canonical /api/v1/menu-calendar runtime response for the same query.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="date", in="query", description="Single-day resolver mode in Y-m-d format. Mutually exclusive with month and start_date/end_date.", @OA\Schema(type="string", example="2026-03-12")),
     *     @OA\Parameter(name="month", in="query", description="Month resolver mode in Y-m format. Mutually exclusive with date and start_date/end_date.", @OA\Schema(type="string", example="2026-03")),
     *     @OA\Parameter(name="start_date", in="query", description="Range resolver start date in Y-m-d format. Must be sent together with end_date.", @OA\Schema(type="string", example="2026-03-12")),
     *     @OA\Parameter(name="end_date", in="query", description="Range resolver end date in Y-m-d format. Must be sent together with start_date.", @OA\Schema(type="string", example="2026-03-15")),
     *     @OA\Response(response=200, description="Resolver response for one of the supported modes.", @OA\JsonContent(ref="#/components/schemas/MenuCalendarProjectionResponse")),
     *     @OA\Response(response=400, description="Validation failed because no resolver mode, multiple resolver modes, or invalid date formatting was provided.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function menuCalendarProjection(): ResponseInterface
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

    /**
     * @OA\Post(
     *     path="/api/v1/spk/basah/generate",
     *     operationId="generateSpkBasah",
     *     tags={"SPK Basah"},
     *     summary="Generate SPK basah",
     *     description="Generates a new versioned SPK basah history row and recommendation set for the requested service_date. Accessible to admin, dapur, and gudang users. Runtime requires a valid service_date in Y-m-d format and an existing daily-patient row for that date, then applies a 5 percent patient buffer (ceiling) and targets the requested day plus the next day when both remain in the same calendar month. Generation creates SPK calculation and recommendation history only; it does not create stock transactions or finalize stock movement.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SpkBasahGenerateRequest")),
     *     @OA\Response(response=201, description="SPK basah generated successfully. Response contains versioning and target-date metadata, not stock-posting artifacts.", @OA\JsonContent(ref="#/components/schemas/SpkBasahGenerateResponse")),
     *     @OA\Response(response=400, description="Validation failed for invalid service dates, missing daily-patient input, missing BASAH category, unresolved menu schedule, or missing dish/item recipe mapping.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function generate(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $result = $this->spkBasahGenerationService->generate(
            $this->request->getJSON(true) ?? [],
            (int) $user->id
        );

        if (! $result['success']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'SPK basah generated successfully.',
                'data'    => $result['data'],
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spk/basah/history",
     *     operationId="listSpkBasahHistory",
     *     tags={"SPK Basah"},
     *     summary="List SPK basah history",
     *     description="Returns persisted SPK basah history rows in newest-first order by calculation_date descending and id descending. Accessible to admin, dapur, and gudang users. The response preserves version history across regeneration for the same scope and exposes user/category metadata plus total row count.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="SPK basah history collection.", @OA\JsonContent(ref="#/components/schemas/SpkBasahHistoryCollectionResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function history(): ResponseInterface
    {
        $rows = $this->spkCalculationModel
            ->select('spk_calculations.id, spk_calculations.version, spk_calculations.scope_key, spk_calculations.is_latest, spk_calculations.calculation_scope, spk_calculations.calculation_date, spk_calculations.target_date_start, spk_calculations.target_date_end, spk_calculations.target_month, spk_calculations.estimated_patients, spk_calculations.is_finish, spk_calculations.created_at, users.id AS user_id, users.name AS user_name, users.username AS user_username, item_categories.id AS category_id, item_categories.name AS category_name')
            ->join('users', 'users.id = spk_calculations.user_id', 'left')
            ->join('item_categories', 'item_categories.id = spk_calculations.category_id', 'left')
            ->where('spk_calculations.spk_type', SpkCalculationModel::TYPE_BASAH)
            ->orderBy('spk_calculations.calculation_date', 'DESC')
            ->orderBy('spk_calculations.id', 'DESC')
            ->findAll();

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => array_map(static function (array $row): array {
                    return [
                        'id'                => (int) $row['id'],
                        'version'           => (int) $row['version'],
                        'scope_key'         => (string) $row['scope_key'],
                        'is_latest'         => (bool) $row['is_latest'],
                        'calculation_scope' => (string) $row['calculation_scope'],
                        'calculation_date'  => $row['calculation_date'],
                        'target_date_start' => $row['target_date_start'],
                        'target_date_end'   => $row['target_date_end'],
                        'target_month'      => $row['target_month'],
                        'estimated_patients' => (int) $row['estimated_patients'],
                        'is_finish'         => (bool) $row['is_finish'],
                        'created_at'        => $row['created_at'],
                        'user'              => [
                            'id'       => isset($row['user_id']) ? (int) $row['user_id'] : null,
                            'name'     => $row['user_name'] ?? null,
                            'username' => $row['user_username'] ?? null,
                        ],
                        'category'          => [
                            'id'   => isset($row['category_id']) ? (int) $row['category_id'] : null,
                            'name' => $row['category_name'] ?? null,
                        ],
                    ];
                }, $rows),
                'meta' => [
                    'total' => count($rows),
                ],
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/spk/basah/history/{id}",
     *     operationId="showSpkBasahHistory",
     *     tags={"SPK Basah"},
     *     summary="Show SPK basah history detail",
     *     description="Returns one persisted SPK basah history row with recommendation items and print_ready payload. Accessible to admin, dapur, and gudang users. The response is a read-only projection of stored history and override metadata; it does not recompute stock or mutate any rows.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="SPK basah history identifier.", @OA\Schema(type="integer", minimum=1, example=31)),
     *     @OA\Response(response=200, description="Persisted SPK basah detail response.", @OA\JsonContent(ref="#/components/schemas/SpkBasahShowResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="SPK basah history not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function show(int $id): ResponseInterface
    {
        $header = $this->spkCalculationModel
            ->select('spk_calculations.*, users.name AS user_name, users.username AS user_username, item_categories.name AS category_name')
            ->join('users', 'users.id = spk_calculations.user_id', 'left')
            ->join('item_categories', 'item_categories.id = spk_calculations.category_id', 'left')
            ->where('spk_calculations.id', $id)
            ->where('spk_calculations.spk_type', SpkCalculationModel::TYPE_BASAH)
            ->first();

        if ($header === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'SPK basah history not found.',
                ]);
        }

        $details = $this->spkRecommendationModel
            ->select('spk_recommendations.*, items.name AS item_name, items.unit_base AS item_unit_base, items.unit_convert AS item_unit_convert')
            ->join('items', 'items.id = spk_recommendations.item_id', 'left')
            ->where('spk_recommendations.spk_id', $id)
            ->orderBy('spk_recommendations.target_date', 'ASC')
            ->orderBy('spk_recommendations.item_id', 'ASC')
            ->findAll();

        $normalizedDetails = array_map(static function (array $row): array {
            return [
                'id'                     => (int) $row['id'],
                'item_id'                => (int) $row['item_id'],
                'item_name'              => $row['item_name'] ?? null,
                'item_unit_base'         => $row['item_unit_base'] ?? null,
                'item_unit_convert'      => $row['item_unit_convert'] ?? null,
                'target_date'            => $row['target_date'],
                'current_stock_qty'      => (float) $row['current_stock_qty'],
                'required_qty'           => (float) $row['required_qty'],
                'system_recommended_qty' => (float) $row['system_recommended_qty'],
                'final_recommended_qty'  => (float) $row['recommended_qty'],
                'override'               => [
                    'is_overridden' => (bool) $row['is_overridden'],
                    'reason'        => $row['override_reason'],
                    'overridden_by' => isset($row['overridden_by']) ? (int) $row['overridden_by'] : null,
                    'overridden_at' => $row['overridden_at'],
                ],
            ];
        }, $details);

        $targetDates = [];
        foreach ($normalizedDetails as $detail) {
            if ($detail['target_date'] !== null) {
                $targetDates[$detail['target_date']] = true;
            }
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => [
                    'id'                => (int) $header['id'],
                    'version'           => (int) $header['version'],
                    'scope_key'         => (string) $header['scope_key'],
                    'is_latest'         => (bool) $header['is_latest'],
                    'spk_type'          => (string) $header['spk_type'],
                    'calculation_scope' => (string) $header['calculation_scope'],
                    'calculation_date'  => $header['calculation_date'],
                    'target_date_start' => $header['target_date_start'],
                    'target_date_end'   => $header['target_date_end'],
                    'target_month'      => $header['target_month'],
                    'estimated_patients' => (int) $header['estimated_patients'],
                    'is_finish'         => (bool) $header['is_finish'],
                    'created_at'        => $header['created_at'],
                    'updated_at'        => $header['updated_at'],
                    'user'              => [
                        'id'       => (int) $header['user_id'],
                        'name'     => $header['user_name'] ?? null,
                        'username' => $header['user_username'] ?? null,
                    ],
                    'category'          => [
                        'id'   => (int) $header['category_id'],
                        'name' => $header['category_name'] ?? null,
                    ],
                    'items'             => $normalizedDetails,
                    'print_ready'       => [
                        'spk_id'              => (int) $header['id'],
                        'spk_type'            => (string) $header['spk_type'],
                        'version'             => (int) $header['version'],
                        'calculation_date'    => $header['calculation_date'],
                        'target_date_start'   => $header['target_date_start'],
                        'target_date_end'     => $header['target_date_end'],
                        'target_dates'        => array_keys($targetDates),
                        'estimated_patients'  => (int) $header['estimated_patients'],
                        'category_name'       => $header['category_name'] ?? null,
                        'generated_by'        => $header['user_name'] ?? $header['user_username'] ?? null,
                        'recommendations'     => $normalizedDetails,
                    ],
                ],
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/spk/basah/history/{id}/post-stock",
     *     operationId="postSpkBasahToStock",
     *     tags={"SPK Basah"},
     *     summary="Post SPK basah into stock transactions",
     *     description="Finalizes an SPK basah history row by aggregating positive recommendation quantities into a stock transaction and marking the SPK as finished. Accessible to admin and gudang users. Runtime rejects missing SPK rows, already-posted SPKs, empty recommendation sets, and all-nonpositive recommendation sets. The created stock transaction uses the server-side posting workflow; generate itself never posts stock.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="SPK basah history identifier.", @OA\Schema(type="integer", minimum=1, example=31)),
     *     @OA\Response(response=200, description="SPK basah posted successfully and finalized.", @OA\JsonContent(ref="#/components/schemas/SpkBasahPostStockResponse")),
     *     @OA\Response(response=400, description="Validation failed because the SPK is already posted, has no recommendation rows, or has no positive quantities to post.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="SPK history not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function postStock(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $result = $this->spkStockPostingService->post(
            $id,
            SpkCalculationModel::TYPE_BASAH,
            (int) $user->id,
            $this->request->getIPAddress()
        );

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
                'message' => 'SPK posted to stock transaction successfully.',
                'data'    => $result['data'],
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/spk/basah/history/{id}/override",
     *     operationId="overrideSpkBasahRecommendation",
     *     tags={"SPK Basah"},
     *     summary="Override one SPK basah recommendation item",
     *     description="Overrides one stored SPK basah recommendation before finalization. Accessible to admin, dapur, and gudang users. Runtime accepts only recommendation_id, recommended_qty, and reason. It rejects unknown fields, negative quantities, blank reasons, mismatched recommendation ids, missing SPK rows, and finalized SPKs. The response always returns message, errors, and data keys; successful responses include the original system_recommended_qty and the persisted override metadata.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="SPK basah history identifier.", @OA\Schema(type="integer", minimum=1, example=31)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/SpkRecommendationOverrideRequest")),
     *     @OA\Response(response=200, description="Recommendation overridden successfully.", @OA\JsonContent(ref="#/components/schemas/SpkRecommendationOverrideResponse")),
     *     @OA\Response(response=400, description="Validation failed for unknown fields, invalid recommendation ids, invalid quantities, or missing reasons.", @OA\JsonContent(ref="#/components/schemas/SpkRecommendationOverrideResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin, dapur, or gudang role required by the route group, or the SPK is already finalized.", @OA\JsonContent(ref="#/components/schemas/SpkRecommendationOverrideResponse")),
     *     @OA\Response(response=404, description="SPK history or recommendation item not found.", @OA\JsonContent(ref="#/components/schemas/SpkRecommendationOverrideResponse"))
     * )
     */
    public function overrideItem(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $result = $this->spkOverrideService->overrideItem(
            $id,
            SpkCalculationModel::TYPE_BASAH,
            $this->request->getJSON(true) ?? [],
            (int) $user->id,
            $this->request->getIPAddress()
        );

        return $this->response
            ->setStatusCode((int) $result['status_code'])
            ->setJSON([
                'message' => $result['message'],
                'errors' => $result['errors'] ?? [],
                'data' => $result['data'] ?? null,
            ]);
    }
}
