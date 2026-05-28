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

        if ($this->hasDependentParentTransactionForeignKey()) {
            return;
        }

        $this->db->query('DROP INDEX ' . self::INDEX_NAME . ' ON ' . $this->tableName());
    }

    private function hasDependentParentTransactionForeignKey(): bool
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS fk_count ' .
            'FROM information_schema.key_column_usage kcu ' .
            'WHERE kcu.constraint_schema = DATABASE() ' .
            'AND kcu.table_name = ? ' .
            'AND kcu.column_name = ? ' .
            'AND kcu.referenced_table_name = ? ' .
            'AND kcu.referenced_column_name = ?',
            [$this->tableName(false), 'parent_transaction_id', $this->tableName(false), 'id']
        )->getRowArray();

        return (int) ($row['fk_count'] ?? 0) > 0;
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
        $table = 'stock_transactions';

        if (! $withPrefix) {
            return $table;
        }

        $prefix = (string) ($this->db->DBPrefix ?? '');

        return $prefix . $table;
    }
}
