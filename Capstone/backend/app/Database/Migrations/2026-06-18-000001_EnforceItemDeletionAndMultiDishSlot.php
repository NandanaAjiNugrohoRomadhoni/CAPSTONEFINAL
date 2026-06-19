<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnforceItemDeletionAndMultiDishSlot extends Migration
{
    public function up()
    {
        $prefix = $this->db->getPrefix();
        $isSQLite = $this->db->getPlatform() === 'SQLite3';

        if (!$isSQLite) {
            // 1. Update menu_dishes unique constraint
            // First drop existing non-unique index from FixMenuDishesUniqueness if it exists
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX idx_menu_meal_time");
            } catch (\Exception $e) {}

            // Add new unique index (menu_id, meal_time_id, dish_id)
            $this->db->query("ALTER TABLE {$prefix}menu_dishes ADD UNIQUE KEY menu_id_meal_time_id_dish_id (menu_id, meal_time_id, dish_id)");

            // 2. Update dish_compositions item_id foreign key
            // First drop existing CASCADE foreign key
            try {
                $this->db->query("ALTER TABLE {$prefix}dish_compositions DROP FOREIGN KEY {$prefix}dish_compositions_item_id_foreign");
            } catch (\Exception $e) {
                // If the name is different, try to find it or ignore if not found
            }

            // Add RESTRICT foreign key
            $this->db->query("ALTER TABLE {$prefix}dish_compositions ADD CONSTRAINT {$prefix}dish_compositions_item_id_foreign FOREIGN KEY (item_id) REFERENCES {$prefix}items(id) ON DELETE RESTRICT ON UPDATE CASCADE");
        }
    }

    public function down()
    {
        $prefix = $this->db->getPrefix();
        $isSQLite = $this->db->getPlatform() === 'SQLite3';

        if (!$isSQLite) {
            // Revert menu_dishes
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX menu_id_meal_time_id_dish_id");
            } catch (\Exception $e) {}
            
            try {
                $this->db->query("CREATE INDEX idx_menu_meal_time ON {$prefix}menu_dishes (menu_id, meal_time_id)");
            } catch (\Exception $e) {}

            // Revert dish_compositions item_id foreign key
            try {
                $this->db->query("ALTER TABLE {$prefix}dish_compositions DROP FOREIGN KEY {$prefix}dish_compositions_item_id_foreign");
            } catch (\Exception $e) {}

            $this->db->query("ALTER TABLE {$prefix}dish_compositions ADD CONSTRAINT {$prefix}dish_compositions_item_id_foreign FOREIGN KEY (item_id) REFERENCES {$prefix}items(id) ON DELETE CASCADE ON UPDATE CASCADE");
        }
    }
}
