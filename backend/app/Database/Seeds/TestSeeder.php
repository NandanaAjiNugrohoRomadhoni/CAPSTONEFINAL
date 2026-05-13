<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TestSeeder extends Seeder
{
    /**
     * Root seeder orchestrator for deterministic baseline seeding.
     *
     * Dependency order enforces:
     * 1. Lookup tables (roles, categories, types, statuses, units, meal times) - no FK dependencies
     * 2. User-facing entities (users, items) - depend on lookups
     * 3. Domain entities (dishes, menus) - depend on lookups
     * 4. Composed entities (dish compositions, menu dishes) - depend on items/dishes
     * 5. Operational baseline (schedules, patients, transactions, opnames, SPK) - depend on all above
     *
     * This order ensures:
     * - All FK constraints are satisfied before dependent seeders run
     * - Fail-fast on missing lookups (no silent fallbacks)
     * - Deterministic, repeatable seeding on fresh databases
     * - Clear dependency intent for future maintenance
     */
    public function run()
    {
        // === PHASE 1: Lookup Tables (no dependencies) ===
        // These are foundational lookup/master data tables.
        $this->call('RoleSeeder');                    // Roles: admin, dapur, gudang
        $this->call('ItemCategorySeeder');            // Item categories: BASAH, KERING, PENGEMAS
        $this->call('TransactionTypeSeeder');         // Transaction types: IN, OUT, RETURN_IN, OPNAME_ADJUSTMENT
        $this->call('ApprovalStatusSeeder');          // Approval statuses: APPROVED, PENDING, REJECTED
        $this->call('MealTimeSeeder');                // Meal times: PAGI, SIANG, SORE
        $this->call('MenuSeeder');                    // Menus: Paket 1..11
        $this->call('ItemUnitSeeder');                // Item units: gram, kg, ml, liter, butir, pack

        // === PHASE 2: User-Facing Entities (depend on Phase 1) ===
        // Users depend on roles; items depend on categories and units.
        $this->call('UserSeeder');                    // Users: admin, spkgizi, gudang (with roles)
        $this->call('ItemSeeder');                    // Items: Beras, Ayam, Minyak Goreng, Telur (with categories/units)

        // === PHASE 3: Domain Entities (depend on Phase 1) ===
        // Dishes are independent; menu dishes depend on menus and meal times.
        $this->call('DishSeeder');                    // Dishes: 33 named dishes

        // === PHASE 4: Composed Entities (depend on Phase 2 & 3) ===
        // Compositions and menu assignments depend on items, dishes, and menus.
        $this->call('DishCompositionSeeder');         // Dish compositions: item assignments per dish
        $this->call('MenuDishSeeder');                // Menu dishes: meal-time slots per menu

        // === PHASE 5: Operational Baseline (depend on all above) ===
        // Schedules, patients, transactions, opnames, and SPK depend on the full baseline.
        $this->call('MenuScheduleSeeder');            // Menu schedules: calendar baseline
        $this->call('DailyPatientSeeder');            // Daily patients: patient counts per schedule
        $this->call('StockTransactionSeeder');        // Stock transactions: IN/OUT/RETURN_IN lifecycle samples
        $this->call('StockOpnameSeeder');             // Stock opnames: opname lifecycle samples (DRAFT..POSTED)
        $this->call('SpkPersistenceSeeder');          // SPK calculations/recommendations: versioned baseline
    }
}
