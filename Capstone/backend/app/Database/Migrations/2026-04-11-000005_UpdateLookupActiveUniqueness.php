<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateLookupActiveUniqueness extends Migration
{
    public function up(): void
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            return;
        }

        $this->dropUniqueIndexIfExists('item_categories', 'name');
        $this->dropUniqueIndexIfExists('item_units', 'name');

        $this->ensureGeneratedLookupColumn('item_categories');
        $this->ensureGeneratedLookupColumn('item_units');

        $this->ensureNormalizedActiveKey('item_categories', 'uq_item_categories_active_name', 'name_active_lookup');
        $this->ensureNormalizedActiveKey('item_units', 'uq_item_units_active_name', 'name_active_lookup');
    }

    public function down(): void
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            return;
        }

        $this->dropIndexIfExists('item_categories', 'uq_item_categories_active_name');
        $this->dropIndexIfExists('item_units', 'uq_item_units_active_name');

        $this->dropColumnIfExists('item_categories', 'name_active_lookup');
        $this->dropColumnIfExists('item_units', 'name_active_lookup');

        $this->db->query('ALTER TABLE item_categories ADD UNIQUE KEY name (name)');
        $this->db->query('ALTER TABLE item_units ADD UNIQUE KEY name (name)');
    }

    private function ensureGeneratedLookupColumn(string $table): void
    {
        if ($this->columnExists($table, 'name_active_lookup')) {
            return;
        }

        $this->db->query(
            sprintf(
                "ALTER TABLE %s ADD COLUMN name_active_lookup VARCHAR(50) GENERATED ALWAYS AS (IF(deleted_at IS NULL, LOWER(TRIM(name)), NULL)) VIRTUAL",
                $table,
            ),
        );
    }

    private function ensureNormalizedActiveKey(string $table, string $indexName, string $column): void
    {
        $query = $this->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);
        if ($query->getRowArray() !== null) {
            return;
        }

        $this->db->query(
            sprintf(
                'ALTER TABLE %s ADD UNIQUE INDEX %s (%s)',
                $table,
                $indexName,
                $column,
            ),
        );
    }

    private function dropUniqueIndexIfExists(string $table, string $indexName): void
    {
        $query = $this->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);
        if ($query->getRowArray() !== null) {
            $this->db->query('ALTER TABLE ' . $table . ' DROP INDEX ' . $indexName);
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $query = $this->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);
        if ($query->getRowArray() !== null) {
            $this->db->query('DROP INDEX ' . $indexName . ' ON ' . $table);
        }
    }

    private function dropColumnIfExists(string $table, string $column): void
    {
        if (! $this->columnExists($table, $column)) {
            return;
        }

        $this->db->query(sprintf('ALTER TABLE %s DROP COLUMN %s', $table, $column));
    }

    private function columnExists(string $table, string $column): bool
    {
        $query = $this->db->query('SHOW COLUMNS FROM ' . $table . ' LIKE ?', [$column]);

        return $query->getRowArray() !== null;
    }
}
