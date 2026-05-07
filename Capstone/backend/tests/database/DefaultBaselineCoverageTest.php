<?php

namespace Tests\Database;

use App\Database\Seeds\TestSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class DefaultBaselineCoverageTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = 'App';
    protected $seed        = TestSeeder::class;

    public function testDishesTableIsPopulated(): void
    {
        $count = $this->db->table('dishes')->countAllResults();

        $this->assertSame(33, $count, 'dishes table should contain the full 33-row default baseline');
    }

    public function testDishCompositionsTableIsPopulated(): void
    {
        $count = $this->db->table('dish_compositions')->countAllResults();

        $this->assertSame(33, $count, 'dish_compositions table should contain one composition per seeded dish');
    }

    public function testMenuDishesTableIsPopulated(): void
    {
        $count = $this->db->table('menu_dishes')->countAllResults();

        $this->assertSame(33, $count, 'menu_dishes table should contain all 11 menus × 3 meal-time slots');
    }

    public function testTransactionTypesMatchDocumentedBaseline(): void
    {
        $transactionTypes = $this->db->table('transaction_types')
            ->select('name')
            ->get()
            ->getResultArray();

        $typeNames = array_map(static fn (array $row): string => (string) $row['name'], $transactionTypes);
        sort($typeNames, SORT_NATURAL);

        $this->assertSame(
            ['IN', 'OPNAME_ADJUSTMENT', 'OUT', 'RETURN_IN'],
            $typeNames,
            'transaction_types table should contain the documented baseline lookup names'
        );
    }

    public function testContractCriticalLookupsAreSeeded(): void
    {
        $this->assertLookupNames('roles', ['admin', 'dapur', 'gudang'], 'roles table should contain the supported role baseline');
        $this->assertLookupNames('item_categories', ['BASAH', 'KERING', 'PENGEMAS'], 'item_categories table should contain the supported baseline categories');
        $this->assertLookupNames('transaction_types', ['IN', 'OUT', 'RETURN_IN', 'OPNAME_ADJUSTMENT'], 'transaction_types table should contain the contract-critical transaction lookup baseline');
        $this->assertLookupNames('approval_statuses', ['APPROVED', 'PENDING', 'REJECTED'], 'approval_statuses table should contain the contract-critical approval lookup baseline');
        $this->assertLookupNames('meal_times', ['Pagi', 'Siang', 'Sore'], 'meal_times table should contain the deterministic baseline meal-time rows');
        $this->assertLookupNames('item_units', ['gram', 'kg', 'ml', 'liter', 'butir', 'pack'], 'item_units table should contain the active baseline unit set');
    }

    public function testMenusAndMealTimesMatchDocumentedBaseline(): void
    {
        $menus = $this->db->table('menus')
            ->select('name')
            ->get()
            ->getResultArray();

        $menuNames = array_map(static fn (array $row): string => (string) $row['name'], $menus);
        sort($menuNames, SORT_NATURAL);

        $this->assertSame(
            array_map(static fn (int $id): string => 'Paket ' . $id, range(1, 11)),
            $menuNames,
            'menus table should contain Paket 1 through Paket 11'
        );

        $mealTimes = $this->db->table('meal_times')
            ->select('name')
            ->get()
            ->getResultArray();

        $mealTimeNames = array_map(static fn (array $row): string => (string) $row['name'], $mealTimes);
        sort($mealTimeNames, SORT_NATURAL);

        $this->assertSame(
            ['Pagi', 'Siang', 'Sore'],
            $mealTimeNames,
            'meal_times table should contain the exact baseline meal-time names'
        );
    }

    public function testEachSeededDishHasAtLeastOneComposition(): void
    {
        $dishes = $this->db->table('dishes')->get()->getResultArray();

        foreach ($dishes as $dish) {
            $compositionCount = $this->db->table('dish_compositions')
                ->where('dish_id', $dish['id'])
                ->countAllResults();

            $this->assertGreaterThan(
                0,
                $compositionCount,
                "Dish '{$dish['name']}' (id={$dish['id']}) has no composition"
            );
        }
    }

    public function testEachMenuOneToElevenHasAllThreeMealTimeSlots(): void
    {
        $mealTimes = $this->db->table('meal_times')
            ->select('id, name')
            ->get()
            ->getResultArray();

        $menuRows = $this->db->table('menus')
            ->select('id, name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $mealTimeNames = array_map(static fn (array $row): string => (string) $row['name'], $mealTimes);
        sort($mealTimeNames, SORT_NATURAL);

        $this->assertSame(['Pagi', 'Siang', 'Sore'], $mealTimeNames);
        $this->assertCount(11, $menuRows, 'menus table should contain Paket 1 through Paket 11');

        $mealTimeIdsByName = [];
        foreach ($mealTimes as $mealTime) {
            $mealTimeIdsByName[(string) $mealTime['name']] = (int) $mealTime['id'];
        }

        $expectedMealTimeIds = array_values($mealTimeIdsByName);
        sort($expectedMealTimeIds, SORT_NUMERIC);

        foreach ($menuRows as $menu) {
            $slotCount = $this->db->table('menu_dishes')
                ->where('menu_id', $menu['id'])
                ->countAllResults();

            $this->assertSame(
                3,
                $slotCount,
                "Menu '{$menu['name']}' should have exactly 3 meal-time slots, got {$slotCount}"
            );

            $assignedMealTimeIds = array_map(
                static fn (array $row): int => (int) $row['meal_time_id'],
                $this->db->table('menu_dishes')
                    ->select('meal_time_id')
                    ->where('menu_id', $menu['id'])
                    ->orderBy('meal_time_id', 'ASC')
                    ->get()
                    ->getResultArray()
            );

            sort($assignedMealTimeIds, SORT_NUMERIC);

            $this->assertSame(
                $expectedMealTimeIds,
                $assignedMealTimeIds,
                "Menu '{$menu['name']}' should be linked to Pagi, Siang, and Sore"
            );
        }
    }

    public function testMenuSchedulesIsPopulated(): void
    {
        $count = $this->db->table('menu_schedules')->countAllResults();

        $this->assertGreaterThan(0, $count, 'menu_schedules should contain deterministic baseline overrides');
    }

    public function testDailyPatientsIsPopulated(): void
    {
        $count = $this->db->table('daily_patients')->countAllResults();

        $this->assertGreaterThan(0, $count, 'daily_patients should contain deterministic baseline rows');
    }

    public function testStockTransactionsIsPopulated(): void
    {
        $count = $this->db->table('stock_transactions')->countAllResults();

        $this->assertGreaterThan(0, $count, 'stock_transactions should contain lifecycle baseline rows');
    }

    public function testStockTransactionDetailsIsPopulated(): void
    {
        $count = $this->db->table('stock_transaction_details')->countAllResults();

        $this->assertGreaterThan(0, $count, 'stock_transaction_details should contain lifecycle baseline rows');
    }

    public function testSpkCalculationsIsPopulated(): void
    {
        $count = $this->db->table('spk_calculations')->countAllResults();

        $this->assertGreaterThan(0, $count, 'spk_calculations should contain versioned baseline rows');
    }

    public function testSpkRecommendationsIsPopulated(): void
    {
        $count = $this->db->table('spk_recommendations')->countAllResults();

        $this->assertGreaterThan(0, $count, 'spk_recommendations should contain baseline rows');
    }

    public function testStockOpnameLifecycleStatesAreSeeded(): void
    {
        $states = $this->db->table('stock_opnames')
            ->select('state')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $stateNames = array_values(array_unique(array_map(static fn (array $row): string => (string) $row['state'], $states)));

        $this->assertContains('DRAFT', $stateNames);
        $this->assertContains('SUBMITTED', $stateNames);
        $this->assertContains('APPROVED', $stateNames);
        $this->assertContains('REJECTED', $stateNames);
        $this->assertContains('POSTED', $stateNames);
    }

    public function testStockTransactionLifecycleRowsAreSeeded(): void
    {
        $rows = $this->db->table('stock_transactions')
            ->select('is_revision, reason, approval_status_id')
            ->get()
            ->getResultArray();

        $this->assertNotEmpty($rows);

        $hasRevision = false;
        $hasDirectCorrection = false;

        foreach ($rows as $row) {
            if ((int) $row['is_revision'] === 1) {
                $hasRevision = true;
            }

            if (is_string($row['reason']) && str_contains($row['reason'], 'direct correction')) {
                $hasDirectCorrection = true;
            }
        }

        $this->assertTrue($hasRevision, 'stock_transactions should include revision lifecycle samples');
        $this->assertTrue($hasDirectCorrection, 'stock_transactions should include direct correction baseline sample');
    }

    public function testSeederBaselineForeignKeysAreSatisfiable(): void
    {
        $this->assertAllRowsReferenceExisting('users', 'role_id', 'roles', 'users.role_id should resolve to a seeded role');
        $this->assertAllRowsReferenceExisting('items', 'item_category_id', 'item_categories', 'items.item_category_id should resolve to a seeded category');
        $this->assertAllRowsReferenceExisting('items', 'item_unit_base_id', 'item_units', 'items.item_unit_base_id should resolve to a seeded unit');
        $this->assertAllRowsReferenceExisting('items', 'item_unit_convert_id', 'item_units', 'items.item_unit_convert_id should resolve to a seeded unit');
        $this->assertAllRowsReferenceExisting('menu_schedules', 'menu_id', 'menus', 'menu_schedules.menu_id should resolve to a seeded menu');
        $this->assertAllRowsReferenceExisting('menu_dishes', 'menu_id', 'menus', 'menu_dishes.menu_id should resolve to a seeded menu');
        $this->assertAllRowsReferenceExisting('menu_dishes', 'meal_time_id', 'meal_times', 'menu_dishes.meal_time_id should resolve to a seeded meal time');
        $this->assertAllRowsReferenceExisting('menu_dishes', 'dish_id', 'dishes', 'menu_dishes.dish_id should resolve to a seeded dish');
        $this->assertAllRowsReferenceExisting('dish_compositions', 'dish_id', 'dishes', 'dish_compositions.dish_id should resolve to a seeded dish');
        $this->assertAllRowsReferenceExisting('dish_compositions', 'item_id', 'items', 'dish_compositions.item_id should resolve to a seeded item');
        $this->assertAllRowsReferenceExisting('stock_transactions', 'type_id', 'transaction_types', 'stock_transactions.type_id should resolve to a seeded transaction type');
        $this->assertAllRowsReferenceExisting('stock_transactions', 'approval_status_id', 'approval_statuses', 'stock_transactions.approval_status_id should resolve to a seeded approval status');
        $this->assertAllRowsReferenceExisting('stock_transactions', 'user_id', 'users', 'stock_transactions.user_id should resolve to a seeded user');
        $this->assertNullableRowsReferenceExisting('stock_transactions', 'approved_by', 'users', 'stock_transactions.approved_by should resolve when present');
        $this->assertNullableRowsReferenceExisting('stock_transactions', 'parent_transaction_id', 'stock_transactions', 'stock_transactions.parent_transaction_id should resolve when present');
        $this->assertAllRowsReferenceExisting('stock_transaction_details', 'transaction_id', 'stock_transactions', 'stock_transaction_details.transaction_id should resolve to a seeded transaction');
        $this->assertAllRowsReferenceExisting('stock_transaction_details', 'item_id', 'items', 'stock_transaction_details.item_id should resolve to a seeded item');
        $this->assertAllRowsReferenceExisting('spk_calculations', 'user_id', 'users', 'spk_calculations.user_id should resolve to a seeded user');
        $this->assertAllRowsReferenceExisting('spk_calculations', 'category_id', 'item_categories', 'spk_calculations.category_id should resolve to a seeded category');
        $this->assertNullableRowsReferenceExisting('spk_calculations', 'daily_patient_id', 'daily_patients', 'spk_calculations.daily_patient_id should resolve when present');
        $this->assertAllRowsReferenceExisting('spk_recommendations', 'spk_id', 'spk_calculations', 'spk_recommendations.spk_id should resolve to a seeded SPK calculation');
        $this->assertAllRowsReferenceExisting('spk_recommendations', 'item_id', 'items', 'spk_recommendations.item_id should resolve to a seeded item');
        $this->assertNullableRowsReferenceExisting('spk_recommendations', 'overridden_by', 'users', 'spk_recommendations.overridden_by should resolve when present');
    }

    public function testAuditLogsIsEmpty(): void
    {
        $count = $this->db->table('audit_logs')->countAllResults();

        $this->assertSame(0, $count, 'audit_logs must stay empty in the default baseline');
    }

    private function assertLookupNames(string $table, array $expectedNames, string $message): void
    {
        $rows = $this->db->table($table)
            ->select('name')
            ->get()
            ->getResultArray();

        $names = array_map(static fn (array $row): string => (string) $row['name'], $rows);
        sort($expectedNames, SORT_NATURAL);
        sort($names, SORT_NATURAL);

        $this->assertSame($expectedNames, $names, $message);
    }

    private function assertAllRowsReferenceExisting(string $table, string $foreignKey, string $referencedTable, string $message): void
    {
        $rows = $this->db->table($table)
            ->select($foreignKey)
            ->get()
            ->getResultArray();

        $this->assertNotEmpty($rows, $table . ' should contain seeded rows before FK validation');

        foreach ($rows as $row) {
            $foreignKeyValue = $row[$foreignKey];

            $this->assertNotNull($foreignKeyValue, $message);

            $referencedCount = $this->db->table($referencedTable)
                ->where('id', $foreignKeyValue)
                ->countAllResults();

            $this->assertSame(1, $referencedCount, $message . ' (value=' . (string) $foreignKeyValue . ')');
        }
    }

    private function assertNullableRowsReferenceExisting(string $table, string $foreignKey, string $referencedTable, string $message): void
    {
        $rows = $this->db->table($table)
            ->select($foreignKey)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $foreignKeyValue = $row[$foreignKey];

            if ($foreignKeyValue === null) {
                continue;
            }

            $referencedCount = $this->db->table($referencedTable)
                ->where('id', $foreignKeyValue)
                ->countAllResults();

            $this->assertSame(1, $referencedCount, $message . ' (value=' . (string) $foreignKeyValue . ')');
        }
    }
}
