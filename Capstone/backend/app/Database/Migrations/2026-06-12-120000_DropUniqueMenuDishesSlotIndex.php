<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropUniqueMenuDishesSlotIndex extends Migration
{
    public function up(): void
    {
        if ($this->db->getPlatform() !== 'SQLite3') {
            try {
                $this->db->query('ALTER TABLE menu_dishes DROP INDEX menu_id_meal_time_id');
            } catch (\Throwable $e) {
                // The unique index may already be gone on some environments.
            }
        }

        try {
            $this->db->query('CREATE INDEX idx_menu_meal_time ON menu_dishes (menu_id, meal_time_id)');
        } catch (\Throwable $e) {
            // The non-unique index may already exist.
        }
    }

    public function down(): void
    {
        if ($this->db->getPlatform() !== 'SQLite3') {
            try {
                $this->db->query('ALTER TABLE menu_dishes DROP INDEX idx_menu_meal_time');
            } catch (\Throwable $e) {
                // The non-unique index may already be absent.
            }

            try {
                $this->db->query('ALTER TABLE menu_dishes ADD UNIQUE KEY menu_id_meal_time_id (menu_id, meal_time_id)');
            } catch (\Throwable $e) {
                // Recreating the unique key can fail if the data already contains duplicates.
            }
        }
    }
}
