<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddItemUnitFKsToItems extends Migration
{
    public function up(): void
    {
        $platform = $this->db->getPlatform();

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
        if ($this->db->getPlatform() !== 'SQLite3') {
            $this->db->query('ALTER TABLE items DROP FOREIGN KEY fk_items_item_unit_base');
            $this->db->query('ALTER TABLE items DROP FOREIGN KEY fk_items_item_unit_convert');
        }

        $this->forge->dropColumn('items', ['item_unit_base_id', 'item_unit_convert_id']);
    }
}
