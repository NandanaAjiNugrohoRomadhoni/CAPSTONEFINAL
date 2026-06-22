diff --git a/Capstone/backend/app/Config/Routes.php b/Capstone/backend/app/Config/Routes.php
index 95c0c9c..0c32cde 100644
--- a/Capstone/backend/app/Config/Routes.php
+++ b/Capstone/backend/app/Config/Routes.php
@@ -255,6 +255,10 @@ $routes->group(
             "reports/monthly-stock-export",
             static fn() => service("response")->setStatusCode(204),
         );
+        $routes->options(
+            "audit-logs",
+            static fn() => service("response")->setStatusCode(204),
+        );
 
         if (ENVIRONMENT === "testing") {
             $routes->get(
@@ -299,6 +303,7 @@ $routes->group(
                         "reports/monthly-stock-export",
                         "Reports::monthlyStockExport",
                     );
+                    $routes->get("audit-logs", "AuditLogs::index");
                 },
             );
 
diff --git a/Capstone/backend/app/Controllers/Api/V1/AuditLogs.php b/Capstone/backend/app/Controllers/Api/V1/AuditLogs.php
new file mode 100644
index 0000000..3c6ef76
--- /dev/null
+++ b/Capstone/backend/app/Controllers/Api/V1/AuditLogs.php
@@ -0,0 +1,255 @@
+<?php
+
+namespace App\Controllers\Api\V1;
+
+use App\Controllers\BaseController;
+use App\Models\AuditLogModel;
+use CodeIgniter\HTTP\ResponseInterface;
+
+class AuditLogs extends BaseController
+{
+    private AuditLogModel $auditLogModel;
+
+    public function __construct()
+    {
+        $this->auditLogModel = new AuditLogModel();
+    }
+
+    public function index(): ResponseInterface
+    {
+        $queryParams = $this->request->getGet();
+        $page = max(1, (int) ($queryParams['page'] ?? 1));
+        $perPage = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
+        $paginate = ! in_array(strtolower((string) ($queryParams['paginate'] ?? '')), ['false', '0'], true);
+
+        $sortBy = (string) ($queryParams['sortBy'] ?? 'created_at');
+        $sortDir = strtoupper((string) ($queryParams['sortDir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
+
+        $builder = $this->auditLogModel
+            ->builder()
+            ->select(
+                'audit_logs.id, audit_logs.user_id, audit_logs.action_type, audit_logs.table_name, audit_logs.record_id, audit_logs.message, audit_logs.old_values, audit_logs.new_values, audit_logs.ip_address, audit_logs.created_at, users.name AS user_name, users.username AS user_username'
+            )
+            ->join('users', 'users.id = audit_logs.user_id AND users.deleted_at IS NULL', 'left');
+
+        if (! empty($queryParams['q'])) {
+            $search = trim((string) $queryParams['q']);
+            $builder->groupStart()
+                ->like('audit_logs.message', $search)
+                ->orLike('audit_logs.table_name', $search)
+                ->orLike('audit_logs.action_type', $search)
+                ->orLike('users.name', $search)
+                ->orLike('users.username', $search)
+            ->groupEnd();
+        }
+
+        if (! empty($queryParams['action_type'])) {
+            $builder->where('audit_logs.action_type', (string) $queryParams['action_type']);
+        }
+
+        if (! empty($queryParams['table_name'])) {
+            $builder->where('audit_logs.table_name', (string) $queryParams['table_name']);
+        }
+
+        $allowedSortColumns = ['id', 'created_at', 'action_type', 'table_name', 'record_id'];
+        $sortColumn = in_array($sortBy, $allowedSortColumns, true) ? "audit_logs.{$sortBy}" : 'audit_logs.created_at';
+        $builder->orderBy($sortColumn, $sortDir)->orderBy('audit_logs.id', $sortDir);
+
+        $total = (clone $builder)->countAllResults(false);
+
+        if ($paginate) {
+            $builder->limit($perPage, ($page - 1) * $perPage);
+        }
+
+        $rows = $builder->get()->getResultArray();
+        $data = array_map([$this, 'transformRow'], $rows);
+
+        if ($paginate) {
+            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
+        } else {
+            $page = 1;
+            $perPage = max(1, count($data));
+            $totalPages = $total > 0 ? 1 : 0;
+        }
+
+        return $this->response->setStatusCode(200)->setJSON([
+            'data' => $data,
+            'meta' => [
+                'page' => $page,
+                'perPage' => $perPage,
+                'total' => $total,
+                'totalPages' => $totalPages,
+                'paginated' => $paginate,
+            ],
+            'links' => [
+                'self' => current_url(),
+                'first' => current_url(),
+                'last' => current_url(),
+                'next' => null,
+                'previous' => null,
+            ],
+        ]);
+    }
+
+    private function transformRow(array $row): array
+    {
+        $timestamp = strtotime((string) ($row['created_at'] ?? ''));
+        $date = $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
+        $time = $timestamp ? date('H.i', $timestamp) : date('H.i');
+        $actor = $row['user_name'] ?: ($row['user_username'] ?: 'Sistem');
+
+        return [
+            'id' => (int) $row['id'],
+            'date' => $date,
+            'time' => $time,
+            'actor' => $actor,
+            'actorInitials' => $this->buildInitials($actor),
+            'activityType' => $this->resolveActivityType((string) $row['action_type']),
+            'module' => $this->resolveModule((string) $row['table_name']),
+            'detail' => $this->resolveDetail((string) $row['action_type'], (string) $row['table_name'], $row['message'] ?? null),
+            'created_at' => $row['created_at'] ?? null,
+        ];
+    }
+
+    private function resolveActivityType(string $actionType): string
+    {
+        $normalized = strtolower($actionType);
+        if (str_contains($normalized, 'delete') || str_contains($normalized, 'remove') || str_contains($normalized, 'reject')) {
+            return 'Delete';
+        }
+        if (str_contains($normalized, 'update') || str_contains($normalized, 'approve') || str_contains($normalized, 'post') || str_contains($normalized, 'submit') || str_contains($normalized, 'override') || str_contains($normalized, 'change')) {
+            return 'Update';
+        }
+        return 'Create';
+    }
+
+    private function resolveModule(string $tableName): string
+    {
+        return match ($tableName) {
+            'stock_transactions', 'daily_patients' => 'Transaksi',
+            'items', 'item_categories', 'item_units', 'approval_statuses' => 'Master Barang',
+            'dishes', 'dish_compositions', 'menus', 'menu_dishes', 'menu_schedules', 'meal_times' => 'Menu',
+            'users' => 'Pengguna',
+            'spk_calculations', 'spk_recommendations' => 'SPK',
+            'stock_opnames' => 'Stok',
+            'reports' => 'Laporan',
+            default => 'Laporan',
+        };
+    }
+
+    private function resolveDetail(string $actionType, string $tableName, ?string $message): string
+    {
+        $normalizedAction = strtolower($actionType);
+
+        if ($tableName === 'stock_transactions') {
+            if (str_contains($normalizedAction, 'revision_submit')) {
+                return 'Mengajukan revisi transaksi barang';
+            }
+
+            if (str_contains($normalizedAction, 'revision_approve')) {
+                return 'Menyetujui revisi transaksi barang';
+            }
+
+            if (str_contains($normalizedAction, 'revision_reject')) {
+                return 'Menolak revisi transaksi barang';
+            }
+
+            if (str_contains($normalizedAction, 'opname_adjustment')) {
+                return 'Menginputkan penyesuaian stok dari stock opname';
+            }
+
+            if (str_contains($normalizedAction, 'direct_correction')) {
+                return 'Menginputkan koreksi stok langsung';
+            }
+
+            return str_contains($normalizedAction, 'delete')
+                ? 'Menghapus transaksi barang'
+                : (str_contains($normalizedAction, 'update') ? 'Mengubah transaksi barang' : 'Menginputkan transaksi barang');
+        }
+
+        if ($tableName === 'stock_opnames') {
+            return match (true) {
+                str_contains($normalizedAction, 'approve') => 'Menyetujui penyesuaian stok',
+                str_contains($normalizedAction, 'reject') => 'Menolak penyesuaian stok',
+                str_contains($normalizedAction, 'post') => 'Menerapkan penyesuaian stok',
+                str_contains($normalizedAction, 'submit') => 'Mengajukan penyesuaian stok',
+                str_contains($normalizedAction, 'update') => 'Mengubah penyesuaian stok',
+                default => 'Membuat penyesuaian stok',
+            };
+        }
+
+        if ($tableName === 'spk_calculations' || $tableName === 'spk_recommendations') {
+            return match (true) {
+                str_contains($normalizedAction, 'override') => 'Mengubah rekomendasi SPK',
+                str_contains($normalizedAction, 'generate') => 'Melakukan generate SPK',
+                default => 'Memproses SPK',
+            };
+        }
+
+        if ($message !== null && trim($message) !== '') {
+            return $this->translateMessage($message);
+        }
+
+        $subject = $this->resolveSubjectLabel($tableName);
+
+        return match ($this->resolveActivityType($actionType)) {
+            'Update' => "Mengubah {$subject}",
+            'Delete' => "Menghapus {$subject}",
+            default => "Menambahkan {$subject}",
+        };
+    }
+
+    private function resolveSubjectLabel(string $tableName): string
+    {
+        return match ($tableName) {
+            'stock_transactions' => 'transaksi barang',
+            'stock_opnames' => 'penyesuaian stok',
+            'spk_calculations', 'spk_recommendations' => 'rekomendasi SPK',
+            'dishes' => 'menu makanan',
+            'dish_compositions' => 'komposisi menu',
+            'menus', 'menu_dishes', 'menu_schedules' => 'paket menu',
+            'items' => 'bahan',
+            'item_categories' => 'kategori bahan',
+            'item_units' => 'satuan',
+            'users' => 'pengguna',
+            'daily_patients' => 'data pasien harian',
+            default => 'data sistem',
+        };
+    }
+
+    private function translateMessage(string $message): string
+    {
+        $normalized = trim($message);
+
+        $replacements = [
+            'Stock transaction created.' => 'Menginputkan transaksi barang.',
+            'Stock transaction updated.' => 'Mengubah transaksi barang.',
+            'Stock transaction deleted.' => 'Menghapus transaksi barang.',
+            'Stock transaction created successfully.' => 'Transaksi barang berhasil dibuat.',
+            'Stock opname draft created.' => 'Membuat draft penyesuaian stok.',
+            'Stock opname updated.' => 'Mengubah penyesuaian stok.',
+            'Stock opname submitted.' => 'Mengajukan penyesuaian stok.',
+            'Stock opname approved.' => 'Menyetujui penyesuaian stok.',
+            'Stock opname rejected.' => 'Menolak penyesuaian stok.',
+            'Stock opname posted.' => 'Menerapkan penyesuaian stok.',
+            'SPK recommendation item overridden before finalization.' => 'Mengubah rekomendasi SPK.',
+        ];
+
+        return $replacements[$normalized] ?? $normalized;
+    }
+
+    private function buildInitials(string $name): string
+    {
+        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
+        if ($parts === []) {
+            return 'SI';
+        }
+
+        $initials = '';
+        foreach (array_slice($parts, 0, 2) as $part) {
+            $initials .= strtoupper(substr($part, 0, 1));
+        }
+
+        return $initials !== '' ? $initials : 'SI';
+    }
+}
diff --git a/Capstone/backend/app/Services/SpkBasahGenerationService.php b/Capstone/backend/app/Services/SpkBasahGenerationService.php
index f9a1474..31571c2 100644
--- a/Capstone/backend/app/Services/SpkBasahGenerationService.php
+++ b/Capstone/backend/app/Services/SpkBasahGenerationService.php
@@ -156,14 +156,10 @@ class SpkBasahGenerationService
      */
     private function resolveBasahTargetDates(DateTimeImmutable $requestedDate): array
     {
-        $dates = [$requestedDate->format('Y-m-d')];
-        $next  = $requestedDate->modify('+1 day');
-
-        if ($requestedDate->format('Y-m') === $next->format('Y-m')) {
-            $dates[] = $next->format('Y-m-d');
-        }
-
-        return $dates;
+        return [
+            $requestedDate->modify('+1 day')->format('Y-m-d'),
+            $requestedDate->modify('+2 day')->format('Y-m-d'),
+        ];
     }
 
     private function buildPerDateRequirements(array $targetDates, int $estimatedPatients, int $basahCategoryId): array
diff --git a/Capstone/backend/app/Services/StockTransactionService.php b/Capstone/backend/app/Services/StockTransactionService.php
index ec9e2f2..58a8473 100644
--- a/Capstone/backend/app/Services/StockTransactionService.php
+++ b/Capstone/backend/app/Services/StockTransactionService.php
@@ -41,6 +41,7 @@ class StockTransactionService
     private const ALLOWED_REVISION_TOP_LEVEL_FIELDS = [
         'transaction_date',
         'spk_id',
+        'reason',
         'details',
     ];
 
@@ -1172,6 +1173,7 @@ class StockTransactionService
         $revisionData = [
             'type_id'               => $parent['type_id'],
             'transaction_date'      => $data['transaction_date'],
+            'reason'                => trim((string) ($data['reason'] ?? '')),
             'is_revision'           => true,
             'parent_transaction_id' => $parentTransactionId,
             'approval_status_id'    => $pendingStatusId,
