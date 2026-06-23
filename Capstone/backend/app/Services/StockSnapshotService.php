<?php

namespace App\Services;

use App\Enums\AuditActionType;
use App\Models\ItemModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;

class StockSnapshotService
{
    protected ItemModel $itemModel;
    protected AuditService $auditService;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->itemModel = new ItemModel();
        $this->auditService = new AuditService();
        $this->db = Database::connect();
    }

    /**
     * Take an opening snapshot for a specific month.
     * Iterates through all active, non-deleted items and records their current qty.
     *
     * @param string $month Format: YYYY-MM
     * @return array
     */
    public function takeOpeningSnapshot(string $month): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return [
                'success' => false,
                'message' => 'Invalid month format. Expected YYYY-MM.',
            ];
        }

        $periodMonth = $month . '-01';

        // Check if snapshot already exists for this month to ensure idempotency
        $existing = $this->db->table('monthly_stock_snapshots')
            ->where('period_month', $periodMonth)
            ->countAllResults();

        if ($existing > 0) {
            return [
                'success' => true,
                'message' => "Snapshot for month {$month} already exists. Skipping.",
                'count' => 0,
            ];
        }

        // Get all active, non-deleted items
        $items = $this->itemModel
            ->where('is_active', 1)
            ->where('deleted_at', null)
            ->findAll();

        if (empty($items)) {
            return [
                'success' => true,
                'message' => 'No active items found to snapshot.',
                'count' => 0,
            ];
        }

        $batch = [];
        $now = date('Y-m-d H:i:s');

        $this->db->transStart();
        foreach ($items as $item) {
            $batch[] = [
                'period_month' => $periodMonth,
                'item_id' => (int) $item['id'],
                'opening_qty' => (float) $item['qty'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($batch)) {
            $this->db->table('monthly_stock_snapshots')->insertBatch($batch);
        }

        if (!$this->auditService->log(null, AuditActionType::Create, 'monthly_stock_snapshots', 0, 'Monthly opening stock snapshot taken.', null, ['period' => $periodMonth, 'item_count' => count($batch)], null)) {
            $this->db->transRollback();
            return [
                'success' => false,
                'message' => 'Failed to log audit trail.',
            ];
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            return [
                'success' => false,
                'message' => 'Failed to complete stock snapshot transaction.',
            ];
        }

        return [
            'success' => true,
            'message' => "Successfully took opening snapshot for {$month}.",
            'count' => count($batch),
        ];
    }

    /**
     * Return the current month's snapshot status.
     *
     * @return array{month: string, has_snapshot: bool, item_count: int|null}
     */
    public function getCurrentMonthStatus(): array
    {
        $month = date('Y-m');
        $periodMonth = $month . '-01';

        $count = $this->db->table('monthly_stock_snapshots')
            ->where('period_month', $periodMonth)
            ->countAllResults();

        return [
            'month'        => $month,
            'has_snapshot'  => $count > 0,
            'item_count'   => $count > 0 ? $count : null,
        ];
    }

    /**
     * Ensure a snapshot exists for the given month. Idempotent, failure-safe.
     * Designed to be called from auto-trigger hooks (transactions, login).
     * NEVER throws — all errors are logged and swallowed.
     *
     * @param string $month YYYY-MM format
     */
    public function ensureOpeningSnapshot(string $month): void
    {
        try {
            $periodMonth = $month . '-01';
            $exists = $this->db->table('monthly_stock_snapshots')
                ->where('period_month', $periodMonth)
                ->countAllResults();

            if ($exists === 0) {
                $this->takeOpeningSnapshot($month);
            }
        } catch (\Throwable $e) {
            log_message('error', '[StockSnapshot] Auto-trigger failed for {month}: {error}', [
                'month' => $month,
                'error' => $e->getMessage(),
            ]);
            // Intentionally swallowed — never block the calling operation
        }
    }

    /**
     * Force-retake: deletes existing rows for the month, then re-snapshots.
     * Use when snapshot data is incorrect (e.g., captured after a data fix).
     *
     * @param string $month YYYY-MM format
     * @return array Same shape as takeOpeningSnapshot()
     */
    public function retakeOpeningSnapshot(string $month): array
    {
        $periodMonth = $month . '-01';

        $this->db->transStart();
        $this->db->table('monthly_stock_snapshots')
            ->where('period_month', $periodMonth)
            ->delete();
        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            return ['success' => false, 'message' => 'Failed to delete existing snapshot.', 'count' => 0];
        }

        return $this->takeOpeningSnapshot($month);
    }
}
