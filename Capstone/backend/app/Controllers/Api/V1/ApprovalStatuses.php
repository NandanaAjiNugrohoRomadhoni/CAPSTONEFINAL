<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\ApprovalStatusModel;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Approval Statuses
 *
 * Read-only inventory lookup resource for approval status resolution.
 */
class ApprovalStatuses extends BaseController
{
    private ApprovalStatusModel $approvalStatusModel;

    private const SORTABLE_COLUMNS = ['id', 'name', 'created_at', 'updated_at'];

    private const ALLOWED_PARAMS = [
        'paginate',
        'page',
        'perPage',
        'q',
        'search',
        'sortBy',
        'sortDir',
        'created_at_from',
        'created_at_to',
        'updated_at_from',
        'updated_at_to',
    ];

    public function __construct()
    {
        $this->approvalStatusModel = new ApprovalStatusModel();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/approval-statuses",
     *     operationId="listApprovalStatuses",
     *     tags={"Approval Statuses"},
     *     summary="List approval statuses",
     *     description="Returns active approval statuses in the standard lookup collection envelope. Accessible to admin and gudang users from the inventory route group. Runtime supports page, perPage, q, search, sortBy, sortDir, created_at_from, created_at_to, updated_at_from, updated_at_to, and paginate=false for dropdown-style reads.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="paginate", in="query", description="Set to false or 0 to return all active rows while keeping the same envelope with meta.paginated=false.", @OA\Schema(type="string", enum={"true","false","1","0"}, example="false")),
     *     @OA\Parameter(name="q", in="query", description="Primary text search term. If q and search are both present, q wins.", @OA\Schema(type="string", example="APP")),
     *     @OA\Parameter(name="search", in="query", description="Fallback text search term when q is absent.", @OA\Schema(type="string", example="PEND")),
     *     @OA\Parameter(name="sortBy", in="query", @OA\Schema(type="string", enum={"id","name","created_at","updated_at"}, example="name")),
     *     @OA\Parameter(name="sortDir", in="query", @OA\Schema(type="string", enum={"ASC","DESC"}, example="ASC")),
     *     @OA\Parameter(name="created_at_from", in="query", @OA\Schema(type="string", example="2026-04-10")),
     *     @OA\Parameter(name="created_at_to", in="query", @OA\Schema(type="string", example="2026-04-18")),
     *     @OA\Parameter(name="updated_at_from", in="query", @OA\Schema(type="string", example="2026-04-10 00:00:00")),
     *     @OA\Parameter(name="updated_at_to", in="query", @OA\Schema(type="string", example="2026-04-18 23:59:59")),
     *     @OA\Response(response=200, description="Active approval status collection.", @OA\JsonContent(ref="#/components/schemas/ApprovalStatusCollectionResponse")),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin or gudang role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function index(): ResponseInterface
    {
        $queryParams = $this->request->getGet();
        $errors      = $this->validateListParams($queryParams);

        if ($errors !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['message' => 'Validation failed.', 'errors' => $errors]);
        }

        $page    = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $paginate = $this->shouldPaginate($queryParams['paginate'] ?? null);
        $search  = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $requestedSortBy = (string) ($queryParams['sortBy'] ?? 'name');
        $sortBy  = in_array($requestedSortBy, self::SORTABLE_COLUMNS, true)
            ? $requestedSortBy
            : 'name';
        $sortDir = strtoupper((string) ($queryParams['sortDir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $builder = $this->approvalStatusModel->builder();
        $builder->where('approval_statuses.deleted_at', null);

        if ($search !== '') {
            $builder->like('approval_statuses.name', $search);
        }

        $this->applyDateRange($builder, 'approval_statuses.created_at', $queryParams['created_at_from'] ?? null, $queryParams['created_at_to'] ?? null);
        $this->applyDateRange($builder, 'approval_statuses.updated_at', $queryParams['updated_at_from'] ?? null, $queryParams['updated_at_to'] ?? null);

        $builder->orderBy('approval_statuses.' . $sortBy, $sortDir);
        if ($sortBy !== 'id') {
            $builder->orderBy('approval_statuses.id', 'ASC');
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        if ($paginate) {
            $data = $builder
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        } else {
            $data       = $builder->get()->getResultArray();
            $page       = 1;
            $perPage    = max(1, count($data));
            $total      = count($data);
            $totalPages = $total > 0 ? 1 : 0;
        }

        $meta = ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages, 'paginated' => $paginate];

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $data,
                'meta'  => $meta,
                'links' => $this->buildPaginationLinks($meta),
            ]);
    }

    private function validateListParams(array $params): array
    {
        $errors = [];

        $unknownParams = array_diff(array_keys($params), self::ALLOWED_PARAMS);
        if ($unknownParams !== []) {
            $errors['query'] = 'Unsupported query parameter(s): ' . implode(', ', $unknownParams);
        }

        if (isset($params['page']) && (! ctype_digit((string) $params['page']) || (int) $params['page'] < 1)) {
            $errors['page'] = 'The page field must be a positive integer.';
        }

        if (isset($params['perPage']) && (! ctype_digit((string) $params['perPage']) || (int) $params['perPage'] < 1 || (int) $params['perPage'] > 100)) {
            $errors['perPage'] = 'The perPage field must be an integer between 1 and 100.';
        }

        if (isset($params['paginate']) && ! in_array(strtolower((string) $params['paginate']), ['true', 'false', '1', '0'], true)) {
            $errors['paginate'] = 'The paginate field must be a boolean value.';
        }

        if (isset($params['sortBy']) && ! in_array($params['sortBy'], self::SORTABLE_COLUMNS, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', self::SORTABLE_COLUMNS) . '.';
        }

        if (isset($params['sortDir']) && ! in_array(strtoupper((string) $params['sortDir']), ['ASC', 'DESC'], true)) {
            $errors['sortDir'] = 'The sortDir field must be ASC or DESC.';
        }

        foreach (['created_at_from', 'created_at_to', 'updated_at_from', 'updated_at_to'] as $dateField) {
            if (isset($params[$dateField]) && strtotime((string) $params[$dateField]) === false) {
                $errors[$dateField] = sprintf('The %s field must be a valid date/datetime string.', $dateField);
            }
        }

        return $errors;
    }

    private function applyDateRange(object $builder, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null && $from !== '') {
            $builder->where($column . ' >=', $from);
        }

        if ($to !== null && $to !== '') {
            $builder->where($column . ' <=', $to);
        }
    }

    private function shouldPaginate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return ! in_array(strtolower((string) $value), ['false', '0'], true);
    }

    private function buildPaginationLinks(array $meta): array
    {
        $queryParams = $this->request->getGet();
        $path        = current_url();

        $buildLink = function (int $page) use ($path, $queryParams, $meta): string {
            return $path . '?' . http_build_query([...$queryParams, 'page' => $page, 'perPage' => $meta['perPage']]);
        };

        return [
            'self'     => $buildLink($meta['page']),
            'first'    => $buildLink(1),
            'last'     => $buildLink(max(1, $meta['totalPages'])),
            'next'     => $meta['page'] < $meta['totalPages'] ? $buildLink($meta['page'] + 1) : null,
            'previous' => $meta['page'] > 1 ? $buildLink($meta['page'] - 1) : null,
        ];
    }
}
