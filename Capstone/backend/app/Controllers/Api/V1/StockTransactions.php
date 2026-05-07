<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\AppUserProvider;
use App\Models\StockTransactionDetailModel;
use App\Models\StockTransactionModel;
use App\Services\StockTransactionService;
use CodeIgniter\HTTP\ResponseInterface;

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

    public function index(): ResponseInterface
    {
        $queryParams = $this->request->getGet();

        $allowedParams = [
            'page', 'perPage',
            'q', 'search',
            'sortBy', 'sortDir',
            'type_id', 'status_id', 'is_revision',
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
        $isRevision = isset($queryParams['is_revision']) ? (bool) $queryParams['is_revision'] : null;

        $transactionDateFrom = $queryParams['transaction_date_from'] ?? null;
        $transactionDateTo   = $queryParams['transaction_date_to'] ?? null;
        $createdAtFrom       = $queryParams['created_at_from'] ?? null;
        $createdAtTo         = $queryParams['created_at_to'] ?? null;
        $updatedAtFrom       = $queryParams['updated_at_from'] ?? null;
        $updatedAtTo         = $queryParams['updated_at_to'] ?? null;

        $result = $this->transactionModel->getAllPaginatedFiltered(
            $page, $perPage, $search, $sortBy, $sortDir,
            $typeId, $statusId, $isRevision,
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

        $userId    = $user->id;
        $ipAddress = $this->request->getIPAddress();

        $result = $this->transactionService->rejectRevision($id, $userId, $ipAddress);

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
