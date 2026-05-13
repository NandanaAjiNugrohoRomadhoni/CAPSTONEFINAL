<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLegacyOpnameBackfillMarkersToStockTransactions extends Migration
{
    private const TABLE_NAME = 'stock_transactions';
    private const UNIQUE_INDEX_NAME = 'uq_stock_transactions_legacy_source_detail';
    private const LOOKUP_INDEX_NAME = 'idx_stock_transactions_legacy_source';

    public function up(): void
    {
        if (! $this->hasTable(self::TABLE_NAME)) {
            return;
        }

        $resolvedTable = $this->resolveTableName(self::TABLE_NAME);

        $this->addNullableColumnIfMissing(self::TABLE_NAME, 'legacy_source_table', 'VARCHAR(64)');
        $this->addNullableColumnIfMissing(self::TABLE_NAME, 'legacy_source_id', 'BIGINT');
        $this->addNullableColumnIfMissing(self::TABLE_NAME, 'legacy_source_detail_id', 'BIGINT');

        if (! $this->indexExists(self::TABLE_NAME, self::UNIQUE_INDEX_NAME)) {
            // Intentionally global uniqueness (including soft-deleted rows) so each
            // historical legacy detail marker maps to exactly one ledger transaction.
            // Backfill idempotency is deterministic and does not recreate deleted markers.
            $this->db->query(
                sprintf(
                    'CREATE UNIQUE INDEX %s ON %s (legacy_source_table, legacy_source_detail_id)',
                    self::UNIQUE_INDEX_NAME,
                    $resolvedTable,
                ),
            );
        }

        if (! $this->indexExists(self::TABLE_NAME, self::LOOKUP_INDEX_NAME)) {
            $this->db->query(
                sprintf(
                    'CREATE INDEX %s ON %s (legacy_source_table, legacy_source_id)',
                    self::LOOKUP_INDEX_NAME,
                    $resolvedTable,
                ),
            );
        }

    }

    public function down(): void
    {
        if (! $this->hasTable(self::TABLE_NAME)) {
            return;
        }

        if ($this->indexExists(self::TABLE_NAME, self::UNIQUE_INDEX_NAME)) {
            $this->dropIndex(self::TABLE_NAME, self::UNIQUE_INDEX_NAME);
        }

        if ($this->indexExists(self::TABLE_NAME, self::LOOKUP_INDEX_NAME)) {
            $this->dropIndex(self::TABLE_NAME, self::LOOKUP_INDEX_NAME);
        }

        $dropColumns = [];

        if ($this->columnExists(self::TABLE_NAME, 'legacy_source_table')) {
            $dropColumns[] = 'legacy_source_table';
        }

        if ($this->columnExists(self::TABLE_NAME, 'legacy_source_id')) {
            $dropColumns[] = 'legacy_source_id';
        }

        if ($this->columnExists(self::TABLE_NAME, 'legacy_source_detail_id')) {
            $dropColumns[] = 'legacy_source_detail_id';
        }

        if ($dropColumns !== []) {
            $this->forge->dropColumn(self::TABLE_NAME, $dropColumns);
        }

    }

    private function hasTable(string $table): bool
    {
        $resolvedTable = $this->resolveTableName($table);
        $platform = $this->db->getPlatform();

        if ($platform === 'SQLite3') {
            $result = $this->db->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$resolvedTable],
            )->getRowArray();

            return $result !== null;
        }

        if ($platform === 'MySQLi') {
            $result = $this->db->query(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$resolvedTable],
            )->getRowArray();

            return $result !== null;
        }

        try {
            $this->db->query('SELECT 1 FROM ' . $resolvedTable . ' LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $resolvedTable = $this->resolveTableName($table);
        $platform = $this->db->getPlatform();

        if ($platform === 'SQLite3') {
            $rows = $this->db->query('PRAGMA index_list(' . $resolvedTable . ')')->getResultArray();

            foreach ($rows as $row) {
                if (isset($row['name']) && (string) $row['name'] === $indexName) {
                    return true;
                }
            }

            return false;
        }

        if ($platform === 'MySQLi') {
            $row = $this->db->query(
                'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
                [$resolvedTable, $indexName],
            )->getRowArray();

            return $row !== null;
        }

        return false;
    }

    private function columnExists(string $table, string $column): bool
    {
        $resolvedTable = $this->resolveTableName($table);
        $platform = $this->db->getPlatform();

        if ($platform === 'SQLite3') {
            $rows = $this->db->query('PRAGMA table_info(' . $resolvedTable . ')')->getResultArray();

            foreach ($rows as $row) {
                if (isset($row['name']) && (string) $row['name'] === $column) {
                    return true;
                }
            }

            return false;
        }

        if ($platform === 'MySQLi') {
            $row = $this->db->query(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$resolvedTable, $column],
            )->getRowArray();

            return $row !== null;
        }

        return false;
    }

    private function addNullableColumnIfMissing(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $resolvedTable = $this->resolveTableName($table);
        $this->db->query(sprintf('ALTER TABLE %s ADD COLUMN %s %s NULL', $resolvedTable, $column, $definition));
    }

    private function dropIndex(string $table, string $indexName): void
    {
        $platform = $this->db->getPlatform();
        $resolvedTable = $this->resolveTableName($table);

        if ($platform === 'SQLite3') {
            $this->db->query(sprintf('DROP INDEX %s', $indexName));

            return;
        }

        $this->db->query(sprintf('DROP INDEX %s ON %s', $indexName, $resolvedTable));
    }

    private function resolveTableName(string $table): string
    {
        $prefix = (string) ($this->db->DBPrefix ?? '');

        return $prefix . $table;
    }
}
