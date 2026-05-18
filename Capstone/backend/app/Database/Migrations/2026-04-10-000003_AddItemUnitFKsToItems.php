<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddItemUnitFKsToItems extends Migration
{
    public function up(): void
    {
        $platform = $this->db->getPlatform();
        $baseExists = $this->columnExists('items', 'item_unit_base_id');
        $convertExists = $this->columnExists('items', 'item_unit_convert_id');

        if ($baseExists xor $convertExists) {
            throw new RuntimeException(
                'Partial schema drift detected on items: item_unit_base_id and item_unit_convert_id must exist together.'
            );
        }

        if ($baseExists && $convertExists) {
            return;
        }

        $this->forge->addColumn('items', [
            'item_unit_base_id' => [
                'type' => 'BIGINT',
                'null' => true,
                'after' => $platform === 'SQLite3' ? null : 'unit_convert',
            ],
            'item_unit_convert_id' => [
                'type' => 'BIGINT',
                'null' => true,
                'after' => $platform === 'SQLite3' ? null : 'item_unit_base_id',
            ],
        ]);

        $this->forge->addKey('item_unit_base_id');
        $this->forge->processIndexes('items');

        $this->forge->addKey('item_unit_convert_id');
        $this->forge->processIndexes('items');

        if ($platform !== 'SQLite3') {
            $this->db->query('ALTER TABLE items ADD CONSTRAINT fk_items_item_unit_base FOREIGN KEY (item_unit_base_id) REFERENCES item_units(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
            $this->db->query('ALTER TABLE items ADD CONSTRAINT fk_items_item_unit_convert FOREIGN KEY (item_unit_convert_id) REFERENCES item_units(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
        }
    }

    public function down(): void
    {
        $baseExists = $this->columnExists('items', 'item_unit_base_id');
        $convertExists = $this->columnExists('items', 'item_unit_convert_id');

        if (! $baseExists && ! $convertExists) {
            return;
        }

        if ($baseExists xor $convertExists) {
            throw new RuntimeException(
                'Partial schema drift detected on rollback: item_unit_base_id and item_unit_convert_id must be removed together.'
            );
        }

        if ($this->db->getPlatform() === 'SQLite3') {
            return;
        }

        if ($this->db->getPlatform() !== 'SQLite3') {
            $this->db->query('ALTER TABLE items DROP FOREIGN KEY fk_items_item_unit_base');
            $this->db->query('ALTER TABLE items DROP FOREIGN KEY fk_items_item_unit_convert');
        }

        $this->forge->dropColumn('items', ['item_unit_base_id', 'item_unit_convert_id']);
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
