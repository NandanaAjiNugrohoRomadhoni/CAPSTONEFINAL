<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\AppUserProvider;
use App\Models\StockTransactionDetailModel;
use App\Models\StockTransactionModel;
use App\Services\StockTransactionService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Stock Transactions
 *
 * Inventory transaction workflow surface for admin/gudang creation and admin-only correction/review actions.
 */
class StockTransactions extends BaseController
{
    protected StockTransactionService $transactionService;
    protected StockTransactionModel $transactionModel;
    protected StockTransactionDetailModel $detailModel;
    protected AppUserProvider $userProvider;

    public function __construct()
    {
        $this->transactionService = new StockTransactionService();
        $this->transactionModel   = new StockTransactionModel();
        $this->detailModel        = new StockTransactionDetailModel();
        $this->userProvider       = new AppUserProvider();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stock-transactions",
     *     operationId="listStockTransactions",
     *     tags={"Stock Transactions"},
     *     summary="List stock transactions",
     *     description="Returns stock transaction headers in the standard data/meta/links envelope. Accessible to admin and gudang users. Runtime accepts page, perPage, q, search, sortBy, sortDir, type_id, status_id, transaction_date_from, transaction_date_to, created_at_from, created_at_to, updated_at_from, and updated_at_to. Unknown query parameters return HTTP 400. List rows include user_name and approved_by_name resolved from user ids when available.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="q", in="query", description="Primary search term. If q and search are both sent, q wins. Runtime applies the search against stock_transactions.spk_id.", @OA\Schema(type="string", example="31")),
     *     @OA\Parameter(name="search", in="query", description="Fallback search term when q is absent.", @OA\Schema(type="string", example="29")),
     *     @OA\Parameter(name="sortBy", in="query", @OA\Schema(type="string", enum={"id","transaction_date","type_id","approval_status_id","created_at","updated_at"}, example="transaction_date")),
     *     @OA\Parameter(name="sortDir", in="query", @OA\Schema(type="string", enum={"ASC","DESC"}, example="DESC")),
     *     @OA\Parameter(name="type_id", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="status_id", in="query", @OA\Schema(type="integer", minimum=1, example=2)),
     *     @OA\Parameter(name="transaction_date_from", in="query", @OA\Schema(type="string", example="2026-04-01")),
     *     @OA\Parameter(name="transaction_date_to", in="query", @OA\Schema(type="string", example="2026-04-30")),
     *     @OA\Parameter(name="created_at_from", in="query", @OA\Schema(type="string", example="2026-04-01 00:00:00")),
     *     @OA\Parameter(name="created_at_to", in="query", @OA\Schema(type="string", example="2026-04-30 23:59:59")),
     *     @OA\Parameter(name="updated_at_from", in="query", @OA\Schema(type="string", example="2026-04-01 00:00:00")),
     *     @OA\Parameter(name="updated_at_to", in="query", @OA\Schema(type="string", example="2026-04-30 23:59:59")),
     *     @OA\Response(response=200, description="Stock transaction collection.", @OA\JsonContent(ref="#/components/schemas/StockTransactionCollectionResponse")),
     *     @OA\Response(response=400, description="Validation failed for unsupported or invalid query parameters.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function index(): ResponseInterface
    {
        $queryParams = $this->request->getGet();

        $allowedParams = [
            'page', 'perPage',
            'q', 'search',
            'sortBy', 'sortDir',
            'type_id', 'status_id',
            'transaction_date_from', 'transaction_date_to',
            'created_at_from', 'created_at_to',
            'updated_at_from', 'updated_at_to',
        ];
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

        $validationErrors = $this->validateTransactionListParams($queryParams);

        if ($validationErrors !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $validationErrors,
                ]);
        }

        $page     = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage  = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $search   = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $sortBy   = (string) ($queryParams['sortBy'] ?? 'transaction_date');
        $sortDir  = (string) ($queryParams['sortDir'] ?? 'DESC');
        $typeId   = isset($queryParams['type_id']) ? (int) $queryParams['type_id'] : null;
        $statusId = isset($queryParams['status_id']) ? (int) $queryParams['status_id'] : null;

        $transactionDateFrom = $queryParams['transaction_date_from'] ?? null;
        $transactionDateTo   = $queryParams['transaction_date_to'] ?? null;
        $createdAtFrom       = $queryParams['created_at_from'] ?? null;
        $createdAtTo         = $queryParams['created_at_to'] ?? null;
        $updatedAtFrom       = $queryParams['updated_at_from'] ?? null;
        $updatedAtTo         = $queryParams['updated_at_to'] ?? null;

        $result = $this->transactionModel->getAllPaginatedFiltered(
            $page, $perPage, $search, $sortBy, $sortDir,
            $typeId, $statusId,
            $transactionDateFrom, $transactionDateTo,
            $createdAtFrom, $createdAtTo,
            $updatedAtFrom, $updatedAtTo
        );

        $userMap = $this->buildUserNameMapFromTransactions($result['transactions']);
        $enrichedTransactions = array_map(function (array $transaction) use ($userMap): array {
            $transaction['user_name'] = $this->resolveUserName($transaction['user_id'] ?? null, $userMap);
            $transaction['approved_by_name'] = $this->resolveUserName($transaction['approved_by'] ?? null, $userMap);

            return $transaction;
        }, $result['transactions']);

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $enrichedTransactions,
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
     * @OA\Post(
     *     path="/api/v1/stock-transactions",
     *     operationId="createStockTransaction",
     *     tags={"Stock Transactions"},
     *     summary="Create stock transaction",
     *     description="Creates a normal stock transaction and its detail rows. Accessible to admin and gudang users. Clients must send exactly one of type_id or type_name; type_name lookup is trimmed and case-insensitive. Writable top-level fields are limited to type_id, type_name, transaction_date, spk_id, and details. Forbidden fields such as user_id, approval_status_id, is_revision, parent_transaction_id, approved_by, and timestamps return HTTP 400. Detail rows allow item_id, qty, and optional input_unit. input_unit defaults to base, may be base or convert only, and convert quantities are normalized to base units using items.conversion_base before persistence and stock mutation. Duplicate item_id rows, missing items, unsupported transaction types, and insufficient stock for OUT all return HTTP 400.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StockTransactionCreateRequest")),
     *     @OA\Response(response=201, description="Stock transaction created and auto-approved.", @OA\JsonContent(ref="#/components/schemas/StockTransactionCreateResponse")),
     *     @OA\Response(response=400, description="Validation failed for conflicting type fields, forbidden fields, invalid details, unknown fields, invalid type lookup, unsupported type, or insufficient stock.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $user = auth()->user();

        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $userId    = $user->id;
        $ipAddress = $this->request->getIPAddress();

        $result = $this->transactionService->createTransaction($data, $userId, $ipAddress);

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
                'message' => $result['message'],
                'data'    => $result['data'],
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stock-transactions/direct-corrections",
     *     operationId="createDirectStockCorrection",
     *     tags={"Stock Transactions"},
     *     summary="Create direct stock correction",
     *     description="Creates an admin-only direct stock correction for exactly one item. Runtime accepts only transaction_date, item_id, expected_current_qty, target_qty, and reason; any extra fields return HTTP 400. The server derives the transaction type from target_qty minus expected_current_qty, persists a final APPROVED non-revision stock transaction with reason, writes one detail row using base units, and rejects stale expected_current_qty values to avoid overwriting newer stock changes.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/DirectStockCorrectionRequest")),
     *     @OA\Response(response=201, description="Direct correction created as an auto-approved stock transaction.", @OA\JsonContent(ref="#/components/schemas/DirectStockCorrectionResponse")),
     *     @OA\Response(response=400, description="Validation failed for missing required fields, unknown fields, no-op target quantities, invalid items, or stale expected_current_qty.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function directCorrection(): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $user = auth()->user();

        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $userId    = $user->id;
        $ipAddress = $this->request->getIPAddress();

        $result = $this->transactionService->createDirectCorrection($data, $userId, $ipAddress);

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
                'message' => $result['message'],
                'data'    => $result['data'],
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stock-transactions/{id}",
     *     operationId="showStockTransaction",
     *     tags={"Stock Transactions"},
     *     summary="Show stock transaction header",
     *     description="Returns the stock transaction header only. Accessible to admin and gudang users. The response intentionally excludes detail rows and includes user_name and approved_by_name when resolvable.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=10)),
     *     @OA\Response(response=200, description="Stock transaction header resource.", @OA\JsonContent(ref="#/components/schemas/StockTransactionResource")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Stock transaction not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function show(int $id): ResponseInterface
    {
        $transaction = $this->transactionModel->findById($id);

        if ($transaction === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'Stock transaction not found.',
                ]);
        }

        $userMap = $this->buildUserNameMapFromTransactions([$transaction]);
        $transaction['user_name'] = $this->resolveUserName($transaction['user_id'] ?? null, $userMap);
        $transaction['approved_by_name'] = $this->resolveUserName($transaction['approved_by'] ?? null, $userMap);

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $transaction,
            ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stock-transactions/{id}/details",
     *     operationId="listStockTransactionDetails",
     *     tags={"Stock Transactions"},
     *     summary="Show stock transaction details",
     *     description="Returns only the item-line rows for one stock transaction. Accessible to admin and gudang users. Detail rows expose normalized qty, original input_qty, input_unit semantics, resolved item/category names, and satuan from items.unit_base.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=10)),
     *     @OA\Response(response=200, description="Detail rows for the selected stock transaction.", @OA\JsonContent(ref="#/components/schemas/StockTransactionDetailsResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Stock transaction not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function details(int $id): ResponseInterface
    {
        $transaction = $this->transactionModel->findById($id);

        if ($transaction === null) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'Stock transaction not found.',
                ]);
        }

        $details = $this->detailModel->getDetailsByTransactionId($id);

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $details,
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stock-transactions/{id}/submit-revision",
     *     operationId="submitStockTransactionRevision",
     *     tags={"Stock Transactions"},
     *     summary="Submit stock transaction revision",
     *     description="Creates a pending revision child transaction for an existing non-revision parent, or updates the existing pending child revision for that parent when one is already awaiting review. Accessible to admin and gudang users. Writable top-level fields are limited to transaction_date, spk_id, and details; forbidden fields and unknown fields return HTTP 400. Revision detail rows follow the same item_id/qty/input_unit rules and base-unit normalization as create. Submission does not mutate stock. Runtime rejects missing parents, revision-on-revision, duplicate items, and invalid input_unit values, while repeated submissions before admin action replace the same pending revision payload instead of creating another pending sibling.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Parent stock transaction id.", @OA\Schema(type="integer", minimum=1, example=15)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StockTransactionRevisionRequest")),
     *     @OA\Response(response=201, description="Pending revision created or replaced with the latest submitted payload.", @OA\JsonContent(ref="#/components/schemas/StockTransactionRevisionSubmitResponse")),
     *     @OA\Response(response=400, description="Validation failed for forbidden fields, unknown fields, invalid details, duplicate items, invalid input_unit, or invalid parent state.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Parent transaction not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function submitRevision(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];
        $user = auth()->user();

        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $userId    = $user->id;
        $ipAddress = $this->request->getIPAddress();

        $result = $this->transactionService->submitRevision($id, $data, $userId, $ipAddress);

        if (! $result['success']) {
            $statusCode = isset($result['errors']) && $result['errors'] === [] && $result['message'] === 'Parent transaction not found.'
                ? 404
                : 400;

            return $this->response
                ->setStatusCode($statusCode)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => $result['message'],
                'data'    => $result['data'],
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stock-transactions/{id}/approve",
     *     operationId="approveStockTransactionRevision",
     *     tags={"Stock Transactions"},
     *     summary="Approve stock transaction revision",
     *     description="Approves a pending revision transaction. Admin only. Runtime resolves the effective baseline as the latest approved sibling in the same lineage when present, otherwise the original parent, then applies only the net per-item delta between baseline details and revision details. Approval may add new items, reverse removed items, leave zero-delta revisions stock-neutral, and uses normalized base-unit detail quantities rather than input_qty. If stock has changed and an OUT-style delta cannot be applied, the revision remains pending and item quantities stay unchanged.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Revision transaction id.", @OA\Schema(type="integer", minimum=1, example=21)),
     *     @OA\Response(response=200, description="Revision approved successfully.", @OA\JsonContent(ref="#/components/schemas/StockTransactionRevisionDecisionResponse")),
     *     @OA\Response(response=400, description="Validation failed because the target is not a pending revision, has an invalid approval state, or the required stock delta cannot be applied safely.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Revision not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function approve(int $id): ResponseInterface
    {
        $user = auth()->user();

        if ($user === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthorized.',
                ]);
        }

        $userId    = $user->id;
        $ipAddress = $this->request->getIPAddress();

        $result = $this->transactionService->approveRevision($id, $userId, $ipAddress);

        if (! $result['success']) {
            $statusCode = isset($result['errors']) && $result['errors'] === [] && $result['message'] === 'Revision not found.'
                ? 404
                : 400;

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
                'message' => $result['message'],
                'data'    => $result['data'],
            ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/stock-transactions/{id}/reject",
     *     operationId="rejectStockTransactionRevision",
     *     tags={"Stock Transactions"},
     *     summary="Reject stock transaction revision",
     *     description="Rejects a pending revision transaction. Admin only. Rejecting a revision updates the revision approval state and approved_by metadata but does not mutate item stock quantities.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Revision transaction id.", @OA\Schema(type="integer", minimum=1, example=21)),
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/StockTransactionRevisionRejectRequest")),
     *     @OA\Response(response=200, description="Revision rejected successfully.", @OA\JsonContent(ref="#/components/schemas/StockTransactionRevisionRejectResponse")),
     *     @OA\Response(response=400, description="Validation failed because the target is not a pending revision or has an invalid approval state.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Revision not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function reject(int $id): ResponseInterface
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
        $userId    = $user->id;
        $ipAddress = $this->request->getIPAddress();

        $result = $this->transactionService->rejectRevision($id, $data, $userId, $ipAddress);

        if (! $result['success']) {
            $statusCode = isset($result['errors']) && $result['errors'] === [] && $result['message'] === 'Revision not found.'
                ? 404
                : 400;

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
                'message' => $result['message'],
                'data'    => $result['data'],
            ]);
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

    private function validateTransactionListParams(array $params): array
    {
        $errors = [];

        if (isset($params['page']) && (! ctype_digit((string) $params['page']) || (int) $params['page'] < 1)) {
            $errors['page'] = 'The page field must be a positive integer.';
        }

        if (isset($params['perPage']) && (! ctype_digit((string) $params['perPage']) || (int) $params['perPage'] < 1 || (int) $params['perPage'] > 100)) {
            $errors['perPage'] = 'The perPage field must be an integer between 1 and 100.';
        }

        if (isset($params['type_id']) && (! ctype_digit((string) $params['type_id']) || (int) $params['type_id'] < 1)) {
            $errors['type_id'] = 'The type_id field must be a positive integer.';
        }

        if (isset($params['status_id']) && (! ctype_digit((string) $params['status_id']) || (int) $params['status_id'] < 1)) {
            $errors['status_id'] = 'The status_id field must be a positive integer.';
        }

        $validSortColumns = \App\Models\StockTransactionModel::SORTABLE_COLUMNS;
        if (isset($params['sortBy']) && ! in_array($params['sortBy'], $validSortColumns, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', $validSortColumns) . '.';
        }

        if (isset($params['sortDir']) && ! in_array(strtoupper((string) $params['sortDir']), ['ASC', 'DESC'], true)) {
            $errors['sortDir'] = 'The sortDir field must be ASC or DESC.';
        }

        foreach (['transaction_date_from', 'transaction_date_to', 'created_at_from', 'created_at_to', 'updated_at_from', 'updated_at_to'] as $dateParam) {
            if (isset($params[$dateParam]) && strtotime($params[$dateParam]) === false) {
                $errors[$dateParam] = sprintf('The %s field must be a valid date/datetime string.', $dateParam);
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string,mixed>> $transactions
     *
     * @return array<int,string>
     */
    private function buildUserNameMapFromTransactions(array $transactions): array
    {
        $userIds = [];

        foreach ($transactions as $transaction) {
            if (isset($transaction['user_id']) && is_numeric((string) $transaction['user_id'])) {
                $userIds[] = (int) $transaction['user_id'];
            }

            if (isset($transaction['approved_by']) && is_numeric((string) $transaction['approved_by'])) {
                $userIds[] = (int) $transaction['approved_by'];
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds, static fn(int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $rows = $this->userProvider
            ->select(['id', 'name'])
            ->whereIn('id', $userIds)
            ->where('deleted_at', null)
            ->asArray()
            ->findAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = (string) $row['name'];
        }

        return $map;
    }

    /**
     * @param mixed $userId
     * @param array<int,string> $userMap
     */
    private function resolveUserName($userId, array $userMap): ?string
    {
        if (! is_numeric((string) $userId)) {
            return null;
        }

        $id = (int) $userId;

        return $id > 0 ? ($userMap[$id] ?? null) : null;
    }
}
