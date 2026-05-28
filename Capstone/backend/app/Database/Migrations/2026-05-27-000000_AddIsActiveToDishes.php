<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsActiveToDishes extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('dishes', 'id')) {
            return;
        }

        if (! $this->columnExists('dishes', 'is_active')) {
            $this->forge->addColumn('dishes', [
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                    'null'       => false,
                    'after'      => 'name',
                ],
            ]);
        }

        $this->db->query('UPDATE ' . $this->resolveTableName('dishes') . ' SET is_active = 1');
    }

    public function down(): void
    {
        if ($this->columnExists('dishes', 'is_active')) {
            $this->forge->dropColumn('dishes', 'is_active');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $resolvedTable = $this->resolveTableName($table);

        if ($this->db->getPlatform() === 'SQLite3') {
            $result = $this->db->query("PRAGMA table_info('{$resolvedTable}')")->getResultArray();

            foreach ($result as $field) {
                if (($field['name'] ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        $query = $this->db->query('SHOW COLUMNS FROM ' . $resolvedTable . ' LIKE ?', [$column]);

        return $query->getRowArray() !== null;
    }

    private function resolveTableName(string $table): string
    {
        $prefix = (string) ($this->db->DBPrefix ?? '');

        return $prefix . $table;
    }
}
