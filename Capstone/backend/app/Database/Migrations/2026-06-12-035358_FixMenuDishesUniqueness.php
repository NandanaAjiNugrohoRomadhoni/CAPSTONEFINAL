<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixMenuDishesUniqueness extends Migration
{
    public function up()
    {
        $isSQLite = $this->db->getPlatform() === 'SQLite3';
        $prefix = $this->db->getPrefix();

        if (!$isSQLite) {
            // 1. Fix menu_dishes
            // The previous migration used DROP INDEX IF EXISTS which fails on MySQL < 8.0
            // and might have used the wrong index name.
            
            $indices = ['menu_id_meal_time_id', 'menu_dishes_menu_id_meal_time_id'];
            foreach ($indices as $index) {
                try {
                    $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX {$index}");
                } catch (\Exception $e) {
                    // Index might not exist, that's fine
                }
            }

            // 2. Fix menu_schedules
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_schedules DROP INDEX day_of_month");
            } catch (\Exception $e) {
                // Index might not exist
            }
        }

        // Ensure non-unique indices exist for performance
        try {
            // Drop non-unique if somehow created as unique or wrong
            // but we usually want them to stay. 
            // If they already exist from AlterMenuArchitecture, CREATE INDEX will fail, which we catch.
            $this->db->query("CREATE INDEX idx_menu_meal_time ON {$prefix}menu_dishes (menu_id, meal_time_id)");
        } catch (\Exception $e) {}

        try {
            $this->db->query("CREATE INDEX idx_day_of_month ON {$prefix}menu_schedules (day_of_month)");
        } catch (\Exception $e) {}
    }

    public function down()
    {
        // No easy way to revert cleanly without knowing which unique index was there originally,
        // but we can try to restore the most likely one.
        $isSQLite = $this->db->getPlatform() === 'SQLite3';
        $prefix = $this->db->getPrefix();
        
        if (!$isSQLite) {
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX idx_menu_meal_time");
            } catch (\Exception $e) {}
            
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes ADD UNIQUE KEY menu_id_meal_time_id (menu_id, meal_time_id)");
            } catch (\Exception $e) {}

            try {
                $this->db->query("ALTER TABLE {$prefix}menu_schedules DROP INDEX idx_day_of_month");
            } catch (\Exception $e) {}
            
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_schedules ADD UNIQUE KEY day_of_month (day_of_month)");
            } catch (\Exception $e) {}
        }
    }
}
