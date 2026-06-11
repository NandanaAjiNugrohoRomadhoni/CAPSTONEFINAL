<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterMenuArchitecture extends Migration
{
    public function up()
    {
        $isSQLite = $this->db->getPlatform() === 'SQLite3';

        // 1. Drop the unique constraint blocking multiple dishes per slot
        try {
            if ($isSQLite) {
                $tableNameWithPrefix = $this->db->getPrefix() . 'menu_dishes';
                $query = $this->db->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='{$tableNameWithPrefix}' AND (name LIKE '%menu_id_meal_time_id%' OR name LIKE '%menu_id%' OR name LIKE '%meal_time_id%')");
                foreach ($query->getResultArray() as $row) {
                    $this->db->query("DROP INDEX IF EXISTS \"{$row['name']}\"");
                }
            } else {
                $this->db->query('ALTER TABLE menu_dishes DROP INDEX IF EXISTS menu_id_meal_time_id');
            }
        } catch (\Exception $e) {}
        
        // Add a standard index to replace the unique one for performance
        try {
            $dropQuery = $isSQLite
                ? 'DROP INDEX IF EXISTS idx_menu_meal_time'
                : 'ALTER TABLE menu_dishes DROP INDEX IF EXISTS idx_menu_meal_time';
            $this->db->query($dropQuery);
            $this->db->query('CREATE INDEX idx_menu_meal_time ON menu_dishes (menu_id, meal_time_id)');
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
                $this->db->query('ALTER TABLE menu_schedules DROP INDEX IF EXISTS day_of_month');
            }
        } catch (\Exception $e) {}

        // Add standard index
        try {
            $dropQuery = $isSQLite
                ? 'DROP INDEX IF EXISTS idx_day_of_month'
                : 'ALTER TABLE menu_schedules DROP INDEX IF EXISTS idx_day_of_month';
            $this->db->query($dropQuery);
            $this->db->query('CREATE INDEX idx_day_of_month ON menu_schedules (day_of_month)');
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
            $this->db->query('ALTER TABLE menu_dishes DROP FOREIGN KEY IF EXISTS menu_dishes_menu_id_foreign');
            $this->db->query('ALTER TABLE menu_dishes ADD CONSTRAINT menu_dishes_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE ON UPDATE CASCADE');
            
            $this->db->query('ALTER TABLE menu_schedules DROP FOREIGN KEY IF EXISTS menu_schedules_menu_id_foreign');
            $this->db->query('ALTER TABLE menu_schedules ADD CONSTRAINT menu_schedules_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE ON UPDATE CASCADE');
        }
    }

    public function down()
    {
        $isSQLite = $this->db->getPlatform() === 'SQLite3';

        // 1. Remove patient_count column
        $this->forge->dropColumn('menu_schedules', 'patient_count');

        // 2. Revert foreign keys to RESTRICT
        if (! $isSQLite) {
            $this->db->query('ALTER TABLE menu_dishes DROP FOREIGN KEY IF EXISTS menu_dishes_menu_id_foreign');
            $this->db->query('ALTER TABLE menu_dishes ADD CONSTRAINT menu_dishes_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE RESTRICT ON UPDATE CASCADE');

            $this->db->query('ALTER TABLE menu_schedules DROP FOREIGN KEY IF EXISTS menu_schedules_menu_id_foreign');
            $this->db->query('ALTER TABLE menu_schedules ADD CONSTRAINT menu_schedules_menu_id_foreign FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE RESTRICT ON UPDATE CASCADE');
        }

        // 3. Drop non-unique indexes
        try {
            $query = $isSQLite
                ? 'DROP INDEX IF EXISTS idx_menu_meal_time'
                : 'ALTER TABLE menu_dishes DROP INDEX IF EXISTS idx_menu_meal_time';
            $this->db->query($query);
        } catch (\Exception $e) {}

        try {
            $query = $isSQLite
                ? 'DROP INDEX IF EXISTS idx_day_of_month'
                : 'ALTER TABLE menu_schedules DROP INDEX IF EXISTS idx_day_of_month';
            $this->db->query($query);
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
