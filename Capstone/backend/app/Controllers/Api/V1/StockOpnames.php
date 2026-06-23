<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\StockOpnameService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

class StockOpnames extends BaseController
{
    protected StockOpnameService $stockOpnameService;

    public function __construct()
    {
        $this->stockOpnameService = new StockOpnameService();
    }

    public function create(): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->createDraft(
            $this->request->getJSON(true) ?? [],
            (int) $user->id,
            $this->request->getIPAddress(),
        );

        if (! $result['success']) {
            return $this->response->setStatusCode(400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(201)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/stock-opnames/{id}",
     *     operationId="updateStockOpname",
     *     tags={"Stock Opnames"},
     *     summary="Update stock opname draft or rejected revision",
     *     description="Updates an existing stock opname while it is still editable. Accessible to admin and gudang users. Runtime only allows updates when the current state is DRAFT or REJECTED. Submitted, approved, and posted stock opnames are immutable. The update fully replaces the detail set inside one database transaction and rewrites system/count/variance snapshots before the record is re-submitted.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Stock opname identifier.", @OA\Schema(type="integer", minimum=1, example=12)),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/StockOpnameMutationRequest")),
     *     @OA\Response(response=200, description="Stock opname updated successfully.", @OA\JsonContent(ref="#/components/schemas/StockOpnameActionResponse")),
     *     @OA\Response(response=400, description="Validation failed for invalid payloads, duplicate items, invalid item references, or immutable workflow states.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="Stock opname not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function update(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->updateDraft(
            $id,
            $this->request->getJSON(true) ?? [],
            (int) $user->id,
            $this->request->getIPAddress(),
        );

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    public function show(int $id): ResponseInterface
    {
        $result = $this->stockOpnameService->findByIdWithDetails($id);
        if ($result === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'message' => 'Stock opname not found.',
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'data' => $result,
        ]);
    }

    public function submit(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->submit($id, (int) $user->id, $this->request->getIPAddress());

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    public function approve(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->approve($id, (int) $user->id, $this->request->getIPAddress());

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    public function reject(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->reject(
            $id,
            $this->request->getJSON(true) ?? [],
            (int) $user->id,
            $this->request->getIPAddress(),
        );

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    public function post(int $id): ResponseInterface
    {
        $user = auth()->user();
        if ($user === null) {
            return $this->response->setStatusCode(401)->setJSON([
                'message' => 'Unauthorized.',
            ]);
        }

        $result = $this->stockOpnameService->post($id, (int) $user->id, $this->request->getIPAddress());

        if (! $result['success']) {
            return $this->response->setStatusCode($result['status'] ?? 400)->setJSON([
                'message' => $result['message'],
                'errors'  => $result['errors'] ?? [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => $result['message'],
            'data'    => $result['data'],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/stock-opnames",
     *     operationId="listStockOpnames",
     *     tags={"Stock Opnames"},
     *     summary="List stock opnames",
     *     description="Returns paginated stock opname headers. Admin sees all opnames; gudang sees only their own. Runtime accepts page, perPage, q, search, sortBy, sortDir, state, opname_date_from, opname_date_to, created_at_from, created_at_to, updated_at_from, and updated_at_to. Unknown query parameters return HTTP 400. List rows include created_by_name through posted_by_name resolved from user joins.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="q", in="query", description="Primary search term. If q and search are both sent, q wins. Runtime searches against id, notes, rejection_reason, and creator name.", @OA\Schema(type="string", example="monthly")),
     *     @OA\Parameter(name="search", in="query", description="Fallback search term when q is absent.", @OA\Schema(type="string", example="audit")),
     *     @OA\Parameter(name="sortBy", in="query", @OA\Schema(type="string", enum={"id","opname_date","state","created_at","updated_at"}, example="opname_date")),
     *     @OA\Parameter(name="sortDir", in="query", @OA\Schema(type="string", enum={"ASC","DESC"}, example="DESC")),
     *     @OA\Parameter(name="state", in="query", description="Filter by opname state.", @OA\Schema(type="string", enum={"DRAFT","SUBMITTED","APPROVED","REJECTED","POSTED"}, example="SUBMITTED")),
     *     @OA\Parameter(name="opname_date_from", in="query", @OA\Schema(type="string", example="2026-06-01")),
     *     @OA\Parameter(name="opname_date_to", in="query", @OA\Schema(type="string", example="2026-06-30")),
     *     @OA\Parameter(name="created_at_from", in="query", @OA\Schema(type="string", example="2026-06-01 00:00:00")),
     *     @OA\Parameter(name="created_at_to", in="query", @OA\Schema(type="string", example="2026-06-30 23:59:59")),
     *     @OA\Parameter(name="updated_at_from", in="query", @OA\Schema(type="string", example="2026-06-01 00:00:00")),
     *     @OA\Parameter(name="updated_at_to", in="query", @OA\Schema(type="string", example="2026-06-30 23:59:59")),
     *     @OA\Response(response=200, description="Stock opname collection.", @OA\JsonContent(ref="#/components/schemas/StockOpnameCollectionResponse")),
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
            'state',
            'opname_date_from', 'opname_date_to',
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

        $validationErrors = $this->validateOpnameListParams($queryParams);

        if ($validationErrors !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $validationErrors,
                ]);
        }

        $page    = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $search  = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $sortBy  = (string) ($queryParams['sortBy'] ?? 'opname_date');
        $sortDir = (string) ($queryParams['sortDir'] ?? 'DESC');
        $state   = isset($queryParams['state']) ? (string) $queryParams['state'] : null;

        $opnameDateFrom = $queryParams['opname_date_from'] ?? null;
        $opnameDateTo   = $queryParams['opname_date_to'] ?? null;
        $createdAtFrom  = $queryParams['created_at_from'] ?? null;
        $createdAtTo    = $queryParams['created_at_to'] ?? null;
        $updatedAtFrom  = $queryParams['updated_at_from'] ?? null;
        $updatedAtTo    = $queryParams['updated_at_to'] ?? null;

        // Role-based scoping: gudang sees only their own opnames
        $user = auth()->user();
        $createdBy = null;
        if ($user !== null) {
            $userProvider = new \App\Models\AppUserProvider();
            $userData = $userProvider->getActiveUserWithRole((int) $user->id);
            $roleName = $userData['role_name'] ?? '';
            if ($roleName !== 'admin') {
                $createdBy = (int) $user->id;
            }
        }

        $result = $this->stockOpnameService->list(
            $page, $perPage, $search, $sortBy, $sortDir,
            $state,
            $opnameDateFrom, $opnameDateTo,
            $createdBy,
            $createdAtFrom, $createdAtTo,
            $updatedAtFrom, $updatedAtTo,
        );

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $result['stockOpnames'],
                'meta'  => [
                    'page'       => $result['page'],
                    'perPage'    => $result['perPage'],
                    'total'      => $result['total'],
                    'totalPages' => $result['totalPages'],
                ],
                'links' => $this->buildOpnamePaginationLinks($result),
            ]);
    }

    private function buildOpnamePaginationLinks(array $result): array
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

    private function validateOpnameListParams(array $params): array
    {
        $errors = [];

        if (isset($params['page']) && (! ctype_digit((string) $params['page']) || (int) $params['page'] < 1)) {
            $errors['page'] = 'The page field must be a positive integer.';
        }

        if (isset($params['perPage']) && (! ctype_digit((string) $params['perPage']) || (int) $params['perPage'] < 1 || (int) $params['perPage'] > 100)) {
            $errors['perPage'] = 'The perPage field must be an integer between 1 and 100.';
        }

        if (isset($params['state']) && ! in_array(strtoupper((string) $params['state']), ['DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'POSTED'], true)) {
            $errors['state'] = 'The state field must be one of: DRAFT, SUBMITTED, APPROVED, REJECTED, POSTED.';
        }

        $validSortColumns = \App\Models\StockOpnameModel::SORTABLE_COLUMNS;
        if (isset($params['sortBy']) && ! in_array($params['sortBy'], $validSortColumns, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', $validSortColumns) . '.';
        }

        if (isset($params['sortDir']) && ! in_array(strtoupper((string) $params['sortDir']), ['ASC', 'DESC'], true)) {
            $errors['sortDir'] = 'The sortDir field must be ASC or DESC.';
        }

        foreach (['opname_date_from', 'opname_date_to', 'created_at_from', 'created_at_to', 'updated_at_from', 'updated_at_to'] as $dateParam) {
            if (isset($params[$dateParam]) && strtotime($params[$dateParam]) === false) {
                $errors[$dateParam] = sprintf('The %s field must be a valid date/datetime string.', $dateParam);
            }
        }

        return $errors;
    }
}
