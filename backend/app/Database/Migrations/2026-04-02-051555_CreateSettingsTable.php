<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 9, 'auto_increment' => true],
            'class'      => ['type' => 'varchar', 'constraint' => 255],
            'key'        => ['type' => 'varchar', 'constraint' => 255],
            'value'      => ['type' => 'text', 'null' => true],
            'type'       => ['type' => 'varchar', 'constraint' => 31, 'default' => 'string'],
            'context'    => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'datetime', 'null' => false],
            'updated_at' => ['type' => 'datetime', 'null' => false],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['class', 'key', 'context']);
        $this->forge->createTable('settings', true);
    }

    public function down()
    {
        $this->forge->dropTable('settings', true);
    }
}
