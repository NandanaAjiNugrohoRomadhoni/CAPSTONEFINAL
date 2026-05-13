<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateItemUnits extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('name', false, true);
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('item_units');
    }

    public function down(): void
    {
        $this->forge->dropTable('item_units', true);
    }
}
