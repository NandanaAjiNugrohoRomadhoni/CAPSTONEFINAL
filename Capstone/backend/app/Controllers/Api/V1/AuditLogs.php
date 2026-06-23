<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Models\AuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Audit Logs
 *
 * Admin-only audit trail surface.
 */
class AuditLogs extends BaseController
{
    private AuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->auditLogModel = new AuditLogModel();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/audit-logs",
     *     operationId="listAuditLogs",
     *     tags={"Audit Logs"},
     *     summary="List audit logs",
     *     description="Returns audit log entries in the standard collection envelope. Admin only. Runtime supports page, perPage, paginate, q, action_type, table_name, sortBy, and sortDir.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="paginate", in="query", description="Set to false or 0 to return all matched rows while keeping the same envelope with meta.paginated=false.", @OA\Schema(type="string", enum={"true","false","1","0"}, example="true")),
     *     @OA\Parameter(name="q", in="query", description="Primary text search term. Matches message, table name, action type, and actor fields.", @OA\Schema(type="string", example="stok")),
     *     @OA\Parameter(name="action_type", in="query", @OA\Schema(type="string", example="stock_opname_post")),
     *     @OA\Parameter(name="table_name", in="query", @OA\Schema(type="string", example="stock_opnames")),
     *     @OA\Parameter(name="sortBy", in="query", @OA\Schema(type="string", enum={"id","created_at","action_type","table_name","record_id"}, example="created_at")),
     *     @OA\Parameter(name="sortDir", in="query", @OA\Schema(type="string", enum={"ASC","DESC"}, example="DESC")),
     *     @OA\Response(response=200, description="Audit log collection.", @OA\JsonContent(type="object")),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
    public function index(): ResponseInterface
    {
        $queryParams = $this->request->getGet();
        $errors = $this->validateListParams($queryParams);

        if ($errors !== []) {
            return $this->response->setStatusCode(400)->setJSON([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ]);
        }

        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $paginate = $this->shouldPaginate($queryParams['paginate'] ?? null);
        $search = trim((string) ($queryParams['q'] ?? ''));
        $sortBy = (string) ($queryParams['sortBy'] ?? 'created_at');
        $sortDir = strtoupper((string) ($queryParams['sortDir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $builder = $this->auditLogModel
            ->builder()
            ->select('audit_logs.id, audit_logs.user_id, audit_logs.action_type, audit_logs.table_name, audit_logs.record_id, audit_logs.message, audit_logs.old_values, audit_logs.new_values, audit_logs.ip_address, audit_logs.created_at, users.name AS user_name, users.username AS user_username, roles.name AS user_role')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->join('roles', 'roles.id = users.role_id', 'left');

        if ($search !== '') {
            $builder->groupStart()
                ->like('audit_logs.message', $search)
                ->orLike('audit_logs.table_name', $search)
                ->orLike('audit_logs.action_type', $search)
                ->orLike('users.name', $search)
                ->orLike('users.username', $search)
                ->groupEnd();
        }

        if (!empty($queryParams['action_type'])) {
            $builder->where('audit_logs.action_type', (string) $queryParams['action_type']);
        }

        if (!empty($queryParams['table_name'])) {
            $builder->where('audit_logs.table_name', (string) $queryParams['table_name']);
        }

        if (!empty($queryParams['start_date'])) {
            $builder->where('audit_logs.created_at >=', (string) $queryParams['start_date'] . ' 00:00:00');
        }

        if (!empty($queryParams['end_date'])) {
            $builder->where('audit_logs.created_at <=', (string) $queryParams['end_date'] . ' 23:59:59');
        }

        if (!empty($queryParams['user_id'])) {
            $builder->where('audit_logs.user_id', (int) $queryParams['user_id']);
        }

        $allowedSortColumns = ['id', 'created_at', 'action_type', 'table_name', 'record_id'];
        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? "audit_logs.{$sortBy}" : 'audit_logs.created_at';
        $builder->orderBy($sortColumn, $sortDir)->orderBy('audit_logs.id', $sortDir);

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        if ($paginate) {
            $rows = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        } else {
            $rows = $builder->get()->getResultArray();
            $page = 1;
            $perPage = max(1, count($rows));
            $total = count($rows);
            $totalPages = $total > 0 ? 1 : 0;
        }

        $meta = [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'paginated' => $paginate,
        ];

        return $this->response->setStatusCode(200)->setJSON([
            'data' => array_map([$this, 'transformRow'], $rows),
            'meta' => $meta,
            'links' => $this->buildPaginationLinks($meta),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/audit-logs/types",
     *     operationId="getAuditLogTypes",
     *     tags={"Audit Logs"},
     *     summary="Get audit log filter metadata",
     *     description="Returns distinct action types, module types, and audited table names for UI filter dropdowns.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Filter metadata with actionTypes, moduleTypes, tableNames.", @OA\JsonContent(type="object", @OA\Property(property="actionTypes", type="array", @OA\Items(type="string")), @OA\Property(property="moduleTypes", type="array", @OA\Items(type="string")), @OA\Property(property="tableNames", type="array", @OA\Items(type="string")))),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
     public function types(): ResponseInterface
    {
        $actionTypes = $this->auditLogModel
            ->builder()
            ->select('action_type')
            ->distinct()
            ->orderBy('action_type', 'ASC')
            ->get()
            ->getResultArray();

        $moduleTypes = [
            'Transaksi',
            'Master Barang',
            'Menu',
            'Pengguna',
            'SPK',
            'Stok',
            'Laporan',
            'Data Sistem',
        ];

        return $this->response->setStatusCode(200)->setJSON([
            'actionTypes' => array_column($actionTypes, 'action_type'),
            'moduleTypes' => $moduleTypes,
            'tableNames' => $this->getAuditedTables(),
        ]);
    }

    public function summary(): ResponseInterface
    {
        $actionTypeSummary = $this->auditLogModel
            ->builder()
            ->select('audit_logs.action_type, COUNT(*) AS count')
            ->groupBy('audit_logs.action_type')
            ->get()
            ->getResultArray();

        $moduleSummary = $this->auditLogModel
            ->builder()
            ->select('audit_logs.table_name, COUNT(*) AS count')
            ->groupBy('audit_logs.table_name')
            ->get()
            ->getResultArray();

        $roleSummary = $this->auditLogModel
            ->builder()
            ->select('roles.name AS role_name, COUNT(*) AS count')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->join('roles', 'roles.id = users.role_id', 'left')
            ->groupBy('roles.name')
            ->get()
            ->getResultArray();

        $total = $this->auditLogModel->builder()->countAllResults();

        $byActionType = [];
        foreach ($actionTypeSummary as $row) {
            $byActionType[$row['action_type']] = (int) $row['count'];
        }

        $byModule = [];
        foreach ($moduleSummary as $row) {
            $module = $this->resolveModule($row['table_name']);
            $byModule[$module] = ($byModule[$module] ?? 0) + (int) $row['count'];
        }

        $byRole = [];
        foreach ($roleSummary as $row) {
            if ($row['role_name'] !== null) {
                $byRole[$row['role_name']] = (int) $row['count'];
            }
        }

        return $this->response->setStatusCode(200)->setJSON([
            'data' => [
                'total' => $total,
                'byRole' => $byRole,
                'byActionType' => $byActionType,
                'byModule' => $byModule,
            ],
        ]);
    }

    private function validateListParams(array $queryParams): array
    {
        $allowedParams = [
            'page',
            'perPage',
            'paginate',
            'q',
            'action_type',
            'table_name',
            'sortBy',
            'sortDir',
            'start_date',
            'end_date',
            'user_id',
        ];

        $unknownParams = array_diff(array_keys($queryParams), $allowedParams);

        if ($unknownParams === []) {
            return [];
        }

        return [
            'query' => 'Unsupported query parameter(s): ' . implode(', ', $unknownParams),
        ];
    }

    private function shouldPaginate(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return !in_array(strtolower((string) $value), ['false', '0'], true);
    }

    private function buildPaginationLinks(array $meta): array
    {
        $queryParams = $this->request->getGet();
        $path = current_url();

        $buildLink = function (int $page) use ($path, $queryParams, $meta): string {
            return $path . '?' . http_build_query([
                ...$queryParams,
                'page' => $page,
                'perPage' => $meta['perPage'],
            ]);
        };

        return [
            'self' => $buildLink($meta['page']),
            'first' => $buildLink(1),
            'last' => $buildLink(max(1, $meta['totalPages'])),
            'next' => $meta['page'] < $meta['totalPages'] ? $buildLink($meta['page'] + 1) : null,
            'previous' => $meta['page'] > 1 ? $buildLink($meta['page'] - 1) : null,
        ];
    }

    private function transformRow(array $row): array
    {
        $timestamp = strtotime((string) ($row['created_at'] ?? ''));
        $actorId = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $actorName = $row['user_name'] ?: 'Sistem';
        $actorUsername = $row['user_username'] ?: null;
        $actorRole = $row['user_role'] ?? null;
        $activityType = $this->resolveActivityType((string) $row['action_type']);
        $before = $this->decodeJsonObject($row['old_values'] ?? null);
        $after = $this->decodeJsonObject($row['new_values'] ?? null);
        $description = $this->resolveDetail((string) $row['action_type'], (string) $row['table_name'], $row['message'] ?? null);

        return [
            'id' => (int) $row['id'],
            'date' => $timestamp ? date('Y-m-d', $timestamp) : null,
            'time' => $timestamp ? date('H:i', $timestamp) : null,
            'actor' => $actorName,
            'actorInfo' => [
                'id' => $actorId,
                'name' => $actorName,
                'username' => $actorUsername,
                'role' => $actorRole,
            ],
            'activityType' => $activityType,
            'activityLabel' => $this->resolveActivityLabel($activityType),
            'module' => $this->resolveModule((string) $row['table_name']),
            'detail' => $description,
            'description' => $description,
            'target' => [
                'table' => $row['table_name'] ?? null,
                'recordId' => isset($row['record_id']) ? (int) $row['record_id'] : null,
            ],
            'changes' => [
                'before' => $before,
                'after' => $after,
                'diff' => $this->buildChangeDiff($before, $after),
            ] + (
                $row['table_name'] === 'stock_transactions' && str_contains(strtolower((string) $row['action_type']), 'revision')
                    ? ['itemDiff' => $this->buildRevisionItemDiff($before, $after)]
                    : []
            ),
            'ipAddress' => $row['ip_address'] ?? null,
            'rawActionType' => $row['action_type'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function resolveActivityType(string $actionType): string
    {
        $normalized = strtolower($actionType);

        if (str_contains($normalized, 'reject')) {
            return 'rejection';
        }

        if (str_contains($normalized, 'approve')) {
            return 'approval';
        }

        if (str_contains($normalized, 'delete') || str_contains($normalized, 'remove')) {
            return 'delete';
        }

        if (str_contains($normalized, 'create') || str_contains($normalized, 'draft') || str_contains($normalized, 'insert')) {
            return 'create';
        }

        if (str_contains($normalized, 'submit')) {
            return 'update';
        }

        if (str_contains($normalized, 'post')) {
            return 'update';
        }

        if (str_contains($normalized, 'override')) {
            return 'update';
        }

        if (str_contains($normalized, 'change')) {
            return 'update';
        }

        if (str_contains($normalized, 'revision')) {
            return 'update';
        }

        return 'update';
    }

    private function resolveActivityLabel(string $activityType): string
    {
        return match ($activityType) {
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'approval' => 'Approval',
            'rejection' => 'Rejection',
            default => 'Update',
        };
    }

    private function decodeJsonObject(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function buildChangeDiff(?array $before, ?array $after): array
    {
        $before ??= [];
        $after ??= [];
        $fields = array_unique(array_merge(array_keys($before), array_keys($after)));
        $diff = [];

        foreach ($fields as $field) {
            $beforeValue = $before[$field] ?? null;
            $afterValue = $after[$field] ?? null;

            if ($beforeValue !== $afterValue) {
                $diff[] = [
                    'field' => $field,
                    'before' => $beforeValue,
                    'after' => $afterValue,
                ];
            }
        }

        return $diff;
    }

    /**
     * Build item-level diff for stock transaction revision details.
     * Compares details by item_id.
     *
     * @param array|null $before Old values (parent transaction or previous revision)
     * @param array|null $after New values (current revision)
     * @return list<array{item_id: int, label: string, qty_before: ?string, qty_after: ?string, unit_before: ?string, unit_after: ?string, status: string}>
     */
    private function buildRevisionItemDiff(?array $before, ?array $after): array
    {
        $before ??= [];
        $after ??= [];
        $beforeDetails = $before['parent_details'] ?? $before['revision_details'] ?? [];
        $afterDetails = $after['revision_details'] ?? [];

        /** @var array<int, array<string, mixed>> $beforeIndexed */
        $beforeIndexed = [];
        foreach ($beforeDetails as $detail) {
            $itemId = (int) ($detail['item_id'] ?? 0);
            if ($itemId > 0) {
                $beforeIndexed[$itemId] = $detail;
            }
        }

        /** @var array<int, array<string, mixed>> $afterIndexed */
        $afterIndexed = [];
        foreach ($afterDetails as $detail) {
            $itemId = (int) ($detail['item_id'] ?? 0);
            if ($itemId > 0) {
                $afterIndexed[$itemId] = $detail;
            }
        }

        /** @var list<int> $allItemIds */
        $allItemIds = array_values(array_unique(array_merge(array_keys($beforeIndexed), array_keys($afterIndexed))));
        $diffs = [];

        foreach ($allItemIds as $itemId) {
            $b = $beforeIndexed[$itemId] ?? null;
            $a = $afterIndexed[$itemId] ?? null;

            if ($b === null && $a !== null) {
                $diffs[] = [
                    'item_id' => $itemId,
                    'label' => $a['item_name'] ?? "Item #{$itemId}",
                    'qty_before' => null,
                    'qty_after' => (string) ($a['qty'] ?? ''),
                    'unit_before' => null,
                    'unit_after' => (string) ($a['input_unit'] ?? ''),
                    'status' => 'added',
                ];
            } elseif ($b !== null && $a === null) {
                $diffs[] = [
                    'item_id' => $itemId,
                    'label' => $b['item_name'] ?? "Item #{$itemId}",
                    'qty_before' => (string) ($b['qty'] ?? ''),
                    'qty_after' => null,
                    'unit_before' => (string) ($b['input_unit'] ?? ''),
                    'unit_after' => null,
                    'status' => 'removed',
                ];
            } elseif ($b !== null && $a !== null && (
                (string) ($b['qty'] ?? '') !== (string) ($a['qty'] ?? '') ||
                ($b['input_unit'] ?? '') !== ($a['input_unit'] ?? '')
            )) {
                $diffs[] = [
                    'item_id' => $itemId,
                    'label' => $a['item_name'] ?? $b['item_name'] ?? "Item #{$itemId}",
                    'qty_before' => (string) ($b['qty'] ?? ''),
                    'qty_after' => (string) ($a['qty'] ?? ''),
                    'unit_before' => (string) ($b['input_unit'] ?? ''),
                    'unit_after' => (string) ($a['input_unit'] ?? ''),
                    'status' => 'changed',
                ];
            }
        }

        return $diffs;
    }

    private function getAuditedTables(): array
    {
        return [
            'stock_transactions',
            'stock_opnames',
            'spk_calculations',
            'spk_recommendations',
            'daily_patients',
            'dishes',
            'dish_compositions',
            'menu_dishes',
            'menu_schedules',
            'users',
            'items',
            'monthly_stock_snapshots',
        ];
    }

    private function resolveModule(string $tableName): string
    {
        return match ($tableName) {
            'stock_transactions', 'daily_patients' => 'Transaksi',
            'stock_opnames', 'monthly_stock_snapshots' => 'Stok',
            'dishes', 'dish_compositions', 'menus', 'menu_dishes', 'menu_schedules', 'meal_times' => 'Menu',
            'users' => 'Pengguna',
            'spk_calculations', 'spk_recommendations' => 'SPK',
            'stock_opnames' => 'Stok',
            'reports' => 'Laporan',
            default => 'Data Sistem',
        };
    }

    private function resolveDetail(string $actionType, string $tableName, ?string $message): string
    {
        if ($message !== null && trim($message) !== '') {
            return trim($message);
        }

        $activityType = $this->resolveActivityType($actionType);

        return match ($activityType) {
            'approval' => 'Menyetujui ' . $this->resolveSubjectLabel($tableName),
            'rejection' => 'Menolak ' . $this->resolveSubjectLabel($tableName),
            'delete' => 'Menghapus ' . $this->resolveSubjectLabel($tableName),
            default => 'Mengubah ' . $this->resolveSubjectLabel($tableName),
        };
    }

    private function resolveSubjectLabel(string $tableName): string
    {
        return match ($tableName) {
            'stock_transactions' => 'transaksi barang',
            'stock_opnames' => 'penyesuaian stok',
            'spk_calculations', 'spk_recommendations' => 'rekomendasi SPK',
            'dishes' => 'menu makanan',
            'dish_compositions' => 'komposisi menu',
            'menus', 'menu_dishes', 'menu_schedules' => 'paket menu',
            'items' => 'bahan',
            'item_categories' => 'kategori bahan',
            'item_units' => 'satuan',
            'users' => 'pengguna',
            'daily_patients' => 'data pasien harian',
            default => 'data sistem',
        };
    }
}
