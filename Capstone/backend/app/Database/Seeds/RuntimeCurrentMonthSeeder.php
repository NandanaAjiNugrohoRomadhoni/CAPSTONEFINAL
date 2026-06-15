<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RuntimeCurrentMonthSeeder extends Seeder
{
    /**
     * Root seeder orchestrator for runtime-relative current-month SPK generation.
     *
     * This seeder intentionally keeps the deterministic TestSeeder path unchanged.
     * Developers can opt into this runtime-relative scenario separately when they
     * want current-month SPK data without posting stock.
     */
    public function run(): void
    {
        // === PHASE 1: Lookup Tables (no dependencies) ===
        $this->call('RoleSeeder');
        $this->call('ItemCategorySeeder');
        $this->call('TransactionTypeSeeder');
        $this->call('ApprovalStatusSeeder');
        $this->call('MealTimeSeeder');
        $this->call('MenuSeeder');
        $this->call('ItemUnitSeeder');

        // === PHASE 2: User-Facing Entities ===
        $this->call('UserSeeder');
        $this->call('ItemSeeder');

        // === PHASE 3: Domain + Composed Entities ===
        $this->call('DishSeeder');
        $this->call('CsvDishSeeder');
        $this->call('DishCompositionSeeder');
        $this->call('CsvMenuDishSeeder');
        $this->call('MenuScheduleSeeder');

        // === PHASE 4: Runtime-relative operational scenario ===
        $this->call('RuntimeCurrentMonthSpkScenarioSeeder');
    }
}
