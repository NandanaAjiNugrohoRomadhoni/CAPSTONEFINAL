<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\MealTimeModel;
use CodeIgniter\HTTP\ResponseInterface;

class MealTimes extends BaseController
{
    private MealTimeModel $mealTimeModel;

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
        $this->mealTimeModel = new MealTimeModel();
    }

    public function index(): ResponseInterface
    {
        $queryParams = $this->request->getGet();
        $errors      = $this->validateListParams($queryParams);

        if ($errors !== []) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['message' => 'Validation failed.', 'errors' => $errors]);
        }

        $page     = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage  = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $paginate = $this->shouldPaginate($queryParams['paginate'] ?? null);
        $search   = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $requestedSortBy = (string) ($queryParams['sortBy'] ?? 'name');
        $sortBy   = in_array($requestedSortBy, self::SORTABLE_COLUMNS, true)
            ? $requestedSortBy
            : 'name';
        $sortDir = strtoupper((string) ($queryParams['sortDir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        $builder = $this->mealTimeModel->builder();

        if ($search !== '') {
            $builder->like('meal_times.name', $search);
        }

        $this->applyDateRange($builder, 'meal_times.created_at', $queryParams['created_at_from'] ?? null, $queryParams['created_at_to'] ?? null);
        $this->applyDateRange($builder, 'meal_times.updated_at', $queryParams['updated_at_from'] ?? null, $queryParams['updated_at_to'] ?? null);

        $builder->orderBy('meal_times.' . $sortBy, $sortDir);
        if ($sortBy !== 'id') {
            $builder->orderBy('meal_times.id', 'ASC');
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
