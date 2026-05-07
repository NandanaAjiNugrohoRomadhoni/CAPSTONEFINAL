<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMenuDishes extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'menu_id' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'null'       => false,
            ],
            'meal_time_id' => [
                'type'       => 'BIGINT',
                'null'       => false,
            ],
            'dish_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['menu_id', 'meal_time_id']);
        $this->forge->addForeignKey('menu_id', 'menus', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('meal_time_id', 'meal_times', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('dish_id', 'dishes', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->createTable('menu_dishes');
    }

    public function down(): void
    {
        $this->forge->dropTable('menu_dishes', true);
    }
}
