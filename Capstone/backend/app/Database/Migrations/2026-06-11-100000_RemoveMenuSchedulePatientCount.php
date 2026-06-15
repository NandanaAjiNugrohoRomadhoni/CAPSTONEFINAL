<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveMenuSchedulePatientCount extends Migration
{
    public function up()
    {
        $this->forge->dropColumn('menu_schedules', 'patient_count');
    }

    public function down()
    {
        $this->forge->addColumn('menu_schedules', [
            'patient_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'menu_id'
            ]
        ]);
    }
}
