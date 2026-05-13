<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class EnforceStockTransactionSpkForeignKey extends Migration
{
    private const CONSTRAINT_NAME = 'fk_stock_transactions_spk_id';

    public function up(): void
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            return;
        }

        if ($this->hasConstraint()) {
            return;
        }

        $orphanCount = $this->getOrphanCount();

        if ($orphanCount > 0) {
            throw new RuntimeException(
                'Cannot enforce FK stock_transactions.spk_id -> spk_calculations.id while orphan rows exist. ' .
                'Deterministic strategy required before migration: set orphan non-null spk_id to NULL only for rows proven manual/non-SPK, ' .
                'or backfill valid parent spk_calculations rows. Current orphan_count=' . $orphanCount
            );
        }

        $this->db->query(
            'ALTER TABLE stock_transactions ' .
            'ADD CONSTRAINT ' . self::CONSTRAINT_NAME . ' ' .
            'FOREIGN KEY (spk_id) REFERENCES spk_calculations(id) ' .
            'ON DELETE SET NULL ON UPDATE CASCADE'
        );
    }

    public function down(): void
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            return;
        }

        if (! $this->hasConstraint()) {
            return;
        }

        $this->db->query('ALTER TABLE stock_transactions DROP FOREIGN KEY ' . self::CONSTRAINT_NAME);
    }

    private function getOrphanCount(): int
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS orphan_count ' .
            'FROM stock_transactions st ' .
            'LEFT JOIN spk_calculations sc ON sc.id = st.spk_id ' .
            'WHERE st.spk_id IS NOT NULL AND sc.id IS NULL'
        )->getRowArray();

        return (int) ($row['orphan_count'] ?? 0);
    }

    private function hasConstraint(): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS fk_count ' .
            'FROM information_schema.table_constraints tc ' .
            'WHERE tc.constraint_schema = DATABASE() ' .
            "AND tc.table_name = 'stock_transactions' " .
            "AND tc.constraint_name = '" . self::CONSTRAINT_NAME . "' " .
            "AND tc.constraint_type = 'FOREIGN KEY'"
        )->getRowArray();

        return (int) ($row['fk_count'] ?? 0) > 0;
    }
}
