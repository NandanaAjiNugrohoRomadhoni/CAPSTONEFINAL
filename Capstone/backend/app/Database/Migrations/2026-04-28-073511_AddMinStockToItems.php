<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMinStockToItems extends Migration
{
    public function up(): void
    {
        if ($this->columnExists('items', 'min_stock')) {
            return;
        }

        $this->forge->addColumn('items', [
            'min_stock' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
                'after'      => 'qty'
            ]
        ]);
    }

    public function down(): void
    {
        if (! $this->columnExists('items', 'min_stock')) {
            return;
        }

        if ($this->db->getPlatform() === 'SQLite3') {
            return;
        }

        $this->forge->dropColumn('items', 'min_stock');
    }

    private function columnExists(string $table, string $column): bool
    {
        if ($this->db->getPlatform() === 'SQLite3') {
            $result = $this->db->query("PRAGMA table_info('{$table}')")->getResultArray();

            foreach ($result as $field) {
                if (($field['name'] ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        $query = $this->db->query('SHOW COLUMNS FROM ' . $table . ' LIKE ?', [$column]);

        return $query->getRowArray() !== null;
    }
}
