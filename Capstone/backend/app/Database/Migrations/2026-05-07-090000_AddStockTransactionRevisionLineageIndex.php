<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStockTransactionRevisionLineageIndex extends Migration
{
    private const INDEX_NAME = 'idx_st_lineage_parent_revision_status_deleted';

    public function up(): void
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            return;
        }

        if ($this->hasIndex(self::INDEX_NAME)) {
            return;
        }

        $this->db->query(
            'CREATE INDEX ' . self::INDEX_NAME . ' ON ' . $this->tableName() . ' ' .
            '(parent_transaction_id, is_revision, approval_status_id, deleted_at, id)'
        );
    }

    public function down(): void
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            return;
        }

        if (! $this->hasIndex(self::INDEX_NAME)) {
            return;
        }

        $this->db->query('DROP INDEX ' . self::INDEX_NAME . ' ON ' . $this->tableName());
    }

    private function hasIndex(string $indexName): bool
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            return false;
        }

        $row = $this->db->query(
            'SELECT COUNT(*) AS idx_count ' .
            'FROM information_schema.statistics ' .
            'WHERE table_schema = DATABASE() ' .
            'AND table_name = ? ' .
            'AND index_name = ?',
            [$this->tableName(false), $indexName]
        )->getRowArray();

        return (int) ($row['idx_count'] ?? 0) > 0;
    }

    private function tableName(bool $withPrefix = true): string
    {
        return $withPrefix ? $this->db->prefixTable('stock_transactions') : 'stock_transactions';
    }
}
