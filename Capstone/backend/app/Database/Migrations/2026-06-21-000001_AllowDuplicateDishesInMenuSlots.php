<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AllowDuplicateDishesInMenuSlots extends Migration
{
    public function up()
    {
        $prefix = $this->db->getPrefix();

        try {
            $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX menu_id_meal_time_id_dish_id");
        } catch (\Throwable $e) {
            // Index may not exist on older databases.
        }

        try {
            $this->db->query("CREATE INDEX idx_menu_meal_time_dish ON {$prefix}menu_dishes (menu_id, meal_time_id, dish_id)");
        } catch (\Throwable $e) {
            // Keep migration idempotent if the non-unique index already exists.
        }
    }

    public function down()
    {
        $prefix = $this->db->getPrefix();

        try {
            $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX idx_menu_meal_time_dish");
        } catch (\Throwable $e) {
        }

        $this->db->query("ALTER TABLE {$prefix}menu_dishes ADD UNIQUE KEY menu_id_meal_time_id_dish_id (menu_id, meal_time_id, dish_id)");
    }
}
