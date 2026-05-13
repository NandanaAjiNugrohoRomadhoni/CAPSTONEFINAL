<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDailyPatients extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'service_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'total_patients' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => false,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('service_date');
        $this->forge->createTable('daily_patients');
    }

    public function down(): void
    {
        $this->forge->dropTable('daily_patients', true);
    }
}
