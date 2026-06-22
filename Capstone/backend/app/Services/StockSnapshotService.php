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
}
