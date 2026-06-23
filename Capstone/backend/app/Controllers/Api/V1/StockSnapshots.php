<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\MonthlyStockSnapshotModel;
use App\Services\StockSnapshotService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Stock Snapshots Controller
 *
 * Provides APIs to list, take, and check current status of monthly opening stock snapshots.
 */
class StockSnapshots extends BaseController
{
    protected StockSnapshotService $snapshotService;
    protected MonthlyStockSnapshotModel $snapshotModel;

    public function __construct()
    {
        $this->snapshotService = new StockSnapshotService();
        $this->snapshotModel   = new MonthlyStockSnapshotModel();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stock-snapshots",
     *     operationId="takeStockSnapshot",
     *     summary="Take opening stock snapshot for a month",
     *     tags={"Stock Snapshots"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="month", type="string", example="2026-06"),
     *             @OA\Property(property="force", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Snapshot created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="count", type="integer", example=42)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Snapshot already exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="count", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid month format or validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function take(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $data = $this->request->getJSON(true) ?? [];
        $allowedFields = ['month', 'force'];
        $unknownFields = array_diff(array_keys($data), $allowedFields);
        if ($unknownFields !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'body' => 'Unsupported request body field(s): ' . implode(', ', $unknownFields),
                    ],
                ]);
        }

        $month = $data['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'month' => 'The month field must be in YYYY-MM format and be a valid calendar month.'
                    ]
                ]);
        }

        $force = isset($data['force']) ? (bool) $data['force'] : false;

        $result = $force
            ? $this->snapshotService->retakeOpeningSnapshot($month)
            : $this->snapshotService->takeOpeningSnapshot($month);

        if (!$result['success']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        // Return 201 Created on new snapshot or forced retake (meaning items were deleted and added)
        // Return 200 OK if snapshot already exists and force was false (skipped)
        $statusCode = ($force || $result['count'] > 0) ? 201 : 200;

        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON([
                'success' => true,
                'message' => $result['message'],
                'count'   => $result['count'],
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stock-snapshots",
     *     operationId="listStockSnapshots",
     *     tags={"Stock Snapshots"},
     *     summary="List stock snapshots",
     *     description="Returns monthly stock snapshots in the standard data/meta/links envelope. Accessible to admin and gudang users. Accepts page, perPage, period_month, item_id, and item_category_id query parameters.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="period_month", in="query", description="Filter by period month (YYYY-MM-DD format)", @OA\Schema(type="string", example="2026-06-01")),
     *     @OA\Parameter(name="item_id", in="query", description="Filter by item ID", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="item_category_id", in="query", description="Filter by category ID", @OA\Schema(type="integer", example=2)),
     *     @OA\Response(response=200, description="Stock snapshot collection.",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="period_month", type="string", example="2026-06-01"),
     *                 @OA\Property(property="item_id", type="integer", example=1),
     *                 @OA\Property(property="item_name", type="string", example="Nasi Putih"),
     *                 @OA\Property(property="category_name", type="string", example="Bahan Pokok"),
     *                 @OA\Property(property="opening_qty", type="number", format="float", example=120.5),
     *                 @OA\Property(property="created_at", type="string", example="2026-06-01 00:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2026-06-01 00:00:00")
     *             )),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="page", type="integer", example=1),
     *                 @OA\Property(property="perPage", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=42),
     *                 @OA\Property(property="totalPages", type="integer", example=5)
     *             ),
     *             @OA\Property(property="links", type="object",
     *                 @OA\Property(property="self", type="string", example="http://localhost/api/v1/stock-snapshots?page=1&perPage=10"),
     *                 @OA\Property(property="first", type="string", example="http://localhost/api/v1/stock-snapshots?page=1&perPage=10"),
     *                 @OA\Property(property="last", type="string", example="http://localhost/api/v1/stock-snapshots?page=5&perPage=10"),
     *                 @OA\Property(property="next", type="string", example="http://localhost/api/v1/stock-snapshots?page=2&perPage=10", nullable=true),
     *                 @OA\Property(property="previous", type="string", example=null, nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation failed for query parameters.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
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

        $queryParams = $this->request->getGet();
        $allowedParams = ['page', 'perPage', 'period_month', 'item_id', 'item_category_id'];
        $unknownParams = array_diff(array_keys($queryParams), $allowedParams);

        if ($unknownParams !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'query' => 'Unsupported query parameter(s): ' . implode(', ', $unknownParams),
                    ],
                ]);
        }

        $validationErrors = $this->validateSnapshotListParams($queryParams);

        if ($validationErrors !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $validationErrors,
                ]);
        }

        $page       = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage    = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $periodMonth = $queryParams['period_month'] ?? null;
        $itemId     = isset($queryParams['item_id']) ? (int) $queryParams['item_id'] : null;
        $categoryId = isset($queryParams['item_category_id']) ? (int) $queryParams['item_category_id'] : null;

        $result = $this->snapshotModel->getAllPaginatedFiltered(
            $page,
            $perPage,
            $periodMonth,
            $itemId,
            $categoryId
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $result['snapshots'],
                'meta'  => [
                    'page'       => $result['page'],
                    'perPage'    => $result['perPage'],
                    'total'      => $result['total'],
                    'totalPages' => $result['totalPages'],
                ],
                'links' => $this->buildPaginationLinks($result),
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stock-snapshots/current",
     *     operationId="getCurrentStockSnapshotStatus",
     *     tags={"Stock Snapshots"},
     *     summary="Get current month's stock snapshot status",
     *     description="Checks if an opening stock snapshot exists for the current calendar month.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Current month snapshot status",
     *         @OA\JsonContent(
     *             @OA\Property(property="month", type="string", example="2026-06"),
     *             @OA\Property(property="has_snapshot", type="boolean", example=true),
     *             @OA\Property(property="item_count", type="integer", example=42, nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function current(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $status = $this->snapshotService->getCurrentMonthStatus();

        return $this->response
            ->setStatusCode(200)
            ->setJSON($status);
    }

    private function buildPaginationLinks(array $result): array
    {
        $queryParams = $this->request->getGet();
        $path        = current_url();

        $buildLink = function (int $page) use ($path, $queryParams, $result): string {
            return $path . '?' . http_build_query([
                ...$queryParams,
                'page'    => $page,
                'perPage' => $result['perPage'],
            ]);
        };

        return [
            'self'     => $buildLink($result['page']),
            'first'    => $buildLink(1),
            'last'     => $buildLink(max(1, $result['totalPages'])),
            'next'     => $result['page'] < $result['totalPages'] ? $buildLink($result['page'] + 1) : null,
            'previous' => $result['page'] > 1 ? $buildLink($result['page'] - 1) : null,
        ];
    }

    private function validateSnapshotListParams(array $params): array
    {
        $errors = [];

        if (isset($params['page']) && (!ctype_digit((string) $params['page']) || (int) $params['page'] < 1)) {
            $errors['page'] = 'The page field must be a positive integer.';
        }

        if (isset($params['perPage']) && (!ctype_digit((string) $params['perPage']) || (int) $params['perPage'] < 1 || (int) $params['perPage'] > 100)) {
            $errors['perPage'] = 'The perPage field must be an integer between 1 and 100.';
        }

        if (isset($params['period_month']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['period_month'])) {
            $errors['period_month'] = 'The period_month field must be in YYYY-MM-DD format.';
        }

        if (isset($params['item_id']) && (!ctype_digit((string) $params['item_id']) || (int) $params['item_id'] < 1)) {
            $errors['item_id'] = 'The item_id field must be a positive integer.';
        }

        if (isset($params['item_category_id']) && (!ctype_digit((string) $params['item_category_id']) || (int) $params['item_category_id'] < 1)) {
            $errors['item_category_id'] = 'The item_category_id field must be a positive integer.';
        }

        return $errors;
    }
}
