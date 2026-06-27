<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterMenuArchitecture extends Migration
{
    public function up()
    {
        $isSQLite = $this->db->getPlatform() === 'SQLite3';
        $prefix = $this->db->getPrefix();

        // 1. Drop the unique constraint blocking multiple dishes per slot
        try {
            if ($isSQLite) {
                $tableNameWithPrefix = $this->db->getPrefix() . 'menu_dishes';
                $query = $this->db->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='{$tableNameWithPrefix}' AND (name LIKE '%menu_id_meal_time_id%' OR name LIKE '%menu_id%' OR name LIKE '%meal_time_id%')");
                foreach ($query->getResultArray() as $row) {
                    $this->db->query("DROP INDEX IF EXISTS \"{$row['name']}\"");
                }
            } else {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX menu_id_meal_time_id");
            }
        } catch (\Exception $e) {}
        
        // Add a standard index to replace the unique one for performance
        try {
            if ($isSQLite) {
                $this->db->query('DROP INDEX IF EXISTS idx_menu_meal_time');
            } else {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX idx_menu_meal_time");
            }
        } catch (\Exception $e) {}

        try {
            $this->db->query("CREATE INDEX idx_menu_meal_time ON {$prefix}menu_dishes (menu_id, meal_time_id)");
        } catch (\Exception $e) {}

        // 2. Drop unique constraint on menu_schedules(day_of_month)
        try {
            if ($isSQLite) {
                $tableNameWithPrefix = $this->db->getPrefix() . 'menu_schedules';
                $query = $this->db->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='{$tableNameWithPrefix}' AND name LIKE '%day_of_month%'");
                foreach ($query->getResultArray() as $row) {
                    $this->db->query("DROP INDEX IF EXISTS \"{$row['name']}\"");
                }
            } else {
                $this->db->query("ALTER TABLE {$prefix}menu_schedules DROP INDEX day_of_month");
            }
        } catch (\Exception $e) {}

        // Add standard index
        try {
            if ($isSQLite) {
                $this->db->query('DROP INDEX IF EXISTS idx_day_of_month');
            } else {
                $this->db->query("ALTER TABLE {$prefix}menu_schedules DROP INDEX idx_day_of_month");
            }
        } catch (\Exception $e) {}

        try {
            $this->db->query("CREATE INDEX idx_day_of_month ON {$prefix}menu_schedules (day_of_month)");
        } catch (\Exception $e) {}
        
        // 3. Add patient_count to menu_schedules
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
        
        // 4. Update foreign key constraints to CASCADE on DELETE for menu_dishes
        if (! $isSQLite) {
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP FOREIGN KEY menu_dishes_menu_id_foreign");
            } catch (\Exception $e) {}
            
            $this->db->query("ALTER TABLE {$prefix}menu_dishes ADD CONSTRAINT menu_dishes_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES {$prefix}menus(id) ON DELETE CASCADE ON UPDATE CASCADE");
            
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_schedules DROP FOREIGN KEY menu_schedules_menu_id_foreign");
            } catch (\Exception $e) {}

            $this->db->query("ALTER TABLE {$prefix}menu_schedules ADD CONSTRAINT menu_schedules_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES {$prefix}menus(id) ON DELETE CASCADE ON UPDATE CASCADE");
        }
    }

    public function down()
    {
        $isSQLite = $this->db->getPlatform() === 'SQLite3';
        $prefix = $this->db->getPrefix();

        // 1. Remove patient_count column (if present — it may already be absent
        // when this down() is invoked during test teardown before the later
        // RemoveMenuSchedulePatientCount migration has had a chance to re-add it).
        try {
            $this->forge->dropColumn('menu_schedules', 'patient_count');
        } catch (\Exception $e) {}

        // 2. Revert foreign keys to RESTRICT
        if (! $isSQLite) {
            try {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP FOREIGN KEY menu_dishes_menu_id_foreign");
            } catch (\Exception $e) {}
            
            $this->db->query("ALTER TABLE {$prefix}menu_dishes ADD CONSTRAINT menu_dishes_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES {$prefix}menus(id) ON DELETE RESTRICT ON UPDATE CASCADE");

            try {
                $this->db->query("ALTER TABLE {$prefix}menu_schedules DROP FOREIGN KEY menu_schedules_menu_id_foreign");
            } catch (\Exception $e) {}

            $this->db->query("ALTER TABLE {$prefix}menu_schedules ADD CONSTRAINT menu_schedules_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES {$prefix}menus(id) ON DELETE RESTRICT ON UPDATE CASCADE");
        }

        // 3. Drop non-unique indexes
        try {
            if ($isSQLite) {
                $this->db->query('DROP INDEX IF EXISTS idx_menu_meal_time');
            } else {
                $this->db->query("ALTER TABLE {$prefix}menu_dishes DROP INDEX idx_menu_meal_time");
            }
        } catch (\Exception $e) {}

        try {
            if ($isSQLite) {
                $this->db->query('DROP INDEX IF EXISTS idx_day_of_month');
            } else {
                $this->db->query("ALTER TABLE {$prefix}menu_schedules DROP INDEX idx_day_of_month");
            }
        } catch (\Exception $e) {}

        // 4. Restore unique constraints
        try {
            $this->forge->addUniqueKey(['menu_id', 'meal_time_id'], 'menu_id_meal_time_id');
            $this->forge->processIndexes('menu_dishes');
        } catch (\Exception $e) {}

        try {
            $this->forge->addUniqueKey('day_of_month', 'day_of_month');
            $this->forge->processIndexes('menu_schedules');
        } catch (\Exception $e) {}
    }
}
