<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * CsvMenuPlanSeeder
 *
 * Imports the CSV menu planning recipes and packages defined in menu-csv-plan.json
 * into the dishes, dish_compositions, and menu_dishes database tables.
 */
class CsvMenuPlanSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = ROOTPATH . '../../gudang-app/src/data/menu-csv-plan.json';

        if (!file_exists($jsonPath)) {
            // Try fallback path if any
            $jsonPath = ROOTPATH . 'writable/menu-csv-plan.json';
        }

        if (!file_exists($jsonPath)) {
            throw new RuntimeException("CSV Menu Plan JSON file not found at: {$jsonPath}");
        }

        $jsonContent = file_get_contents($jsonPath);
        $plan = json_decode($jsonContent, true);

        if ($plan === null) {
            throw new RuntimeException("Invalid JSON format in menu-csv-plan.json");
        }

        // 2. Fetch all existing items from the items table to build a lookup map
        $items = $this->db->table('items')
            ->select('id, name')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $itemLookup = [];
        foreach ($items as $item) {
            $normalized = $this->normalizeName($item['name']);
            $itemLookup[$normalized] = (int) $item['id'];
        }

        // Load category lookups
        $categories = $this->db->table('item_categories')->get()->getResultArray();
        $categoryLookup = [];
        foreach ($categories as $cat) {
            $categoryLookup[strtoupper(trim($cat['name']))] = (int)$cat['id'];
        }

        // Load unit lookups
        $units = $this->db->table('item_units')->get()->getResultArray();
        $unitLookup = [];
        foreach ($units as $u) {
            $unitLookup[strtolower(trim($u['name']))] = (int)$u['id'];
        }

        // Apply name aliases for known variants between JSON ingredient names and DB item names.
        // This avoids renaming DB items (which would break transaction history) while matching
        // common naming differences: generic→specific, spelling variants, brand/pack suffixes.
        $nameAliases = [
            'ayam'          => 'daging ayam',
            'bakso'         => 'bakso sapi',
            'saus tomat'    => 'saus tomat delmonte',
            'tepung kanji'  => 'tepung kanji 500gr',
            'tengiri'       => 'tengiri potong',
            'taoge pendek'  => 'tauge pendek',
            'ayam giling'   => 'daging ayam',
        ];
        foreach ($nameAliases as $jsonName => $dbName) {
            $normalized = $this->normalizeName($jsonName);
            if (!isset($itemLookup[$normalized]) && isset($itemLookup[$this->normalizeName($dbName)])) {
                $itemLookup[$normalized] = $itemLookup[$this->normalizeName($dbName)];
            }
        }

        // 3. Clear existing compositions and menu dishes to avoid duplicates
        $this->db->disableForeignKeyChecks();
        $this->db->table('dish_compositions')->truncate();
        $this->db->table('menu_dishes')->truncate();
        $this->db->table('dishes')->truncate();
        $this->db->enableForeignKeyChecks();

        // 4. Seed unique recipes as dishes
        $dishIdsByName = [];
        $unmatchedItems = [];

        foreach ($plan['recipes'] as $recipe) {
            $dishName = trim($recipe['name']);
            
            $this->db->table('dishes')->insert([
                'name' => $dishName,
            ]);
            $dishId = $this->db->insertID();
            $dishIdsByName[$this->normalizeName($dishName)] = $dishId;

            // Seed composition for each recipe
            foreach ($recipe['ingredients'] as $ingredient) {
                $itemName = trim($ingredient['name']);
                $normalizedItemName = $this->normalizeName($itemName);

                // Try to resolve item_id
                $itemId = $itemLookup[$normalizedItemName] ?? null;

                if ($itemId === null) {
                    // Try to dynamically create the missing item in the items table
                    $itemId = $this->dynamicallyCreateItem($itemName, $ingredient['unit'] ?? 'gr', $categoryLookup, $unitLookup);
                    if ($itemId !== null) {
                        $itemLookup[$normalizedItemName] = $itemId;
                    } else {
                        $unmatchedItems[$itemName] = ($unmatchedItems[$itemName] ?? 0) + 1;
                        continue;
                    }
                }

                $this->db->table('dish_compositions')->insert([
                    'dish_id' => $dishId,
                    'item_id' => $itemId,
                    'qty_per_patient' => (float)$ingredient['qty'],
                ]);
            }
        }

        // 5. Seed packages and menu_dishes
        $mealTimeLookup = [
            'pagi' => 1,
            'siang' => 2,
            'sore' => 3
        ];

        foreach ($plan['packages'] as $package) {
            // Name: "Paket I", "Paket II", etc.
            $packageName = trim($package['label']);
            
            // Map "Paket I" to 1, "Paket II" to 2, etc.
            $menuId = $this->resolveMenuId($packageName);

            if ($menuId === null) {
                continue;
            }

            foreach ($package['sessions'] as $sessionKey => $sessionDishes) {
                $mealTimeId = $mealTimeLookup[$sessionKey] ?? null;
                if ($mealTimeId === null) continue;

                foreach ($sessionDishes as $sessionDish) {
                    $dishName = trim($sessionDish['name']);
                    $normalizedDishName = $this->normalizeName($dishName);
                    $dishId = $dishIdsByName[$normalizedDishName] ?? null;

                    if ($dishId === null) {
                        // Dish wasn't in recipes, insert it on the fly
                        $this->db->table('dishes')->insert([
                            'name' => $dishName,
                        ]);
                        $dishId = $this->db->insertID();
                        $dishIdsByName[$normalizedDishName] = $dishId;
                    }

                    $this->db->table('menu_dishes')->insert([
                        'menu_id' => $menuId,
                        'meal_time_id' => $mealTimeId,
                        'dish_id' => $dishId,
                    ]);
                }
            }
        }

        // Print warnings for unmatched items
        if (!empty($unmatchedItems)) {
            echo "\n--- WARNING: Missing Items in Database ---\n";
            echo "The following ingredients were found in the CSV plan but are missing in the 'items' table:\n";
            foreach ($unmatchedItems as $name => $count) {
                echo "- {$name} (used in {$count} recipes)\n";
            }
            echo "Please create these items in the database first to map them properly.\n";
            echo "----------------------------------------\n\n";
        }
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    private function resolveMenuId(string $packageName): ?int
    {
        $romans = [
            'I' => 1, 'II' => 2, 'III' => 3, 'IV' => 4, 'V' => 5,
            'VI' => 6, 'VII' => 7, 'VIII' => 8, 'IX' => 9, 'X' => 10,
            'XI' => 11
        ];

        $parts = explode(' ', $packageName);
        $roman = end($parts);

        return $romans[$roman] ?? null;
    }

    private function dynamicallyCreateItem(
        string $itemName,
        string $jsonUnit,
        array $categoryLookup,
        array $unitLookup
    ): ?int {
        $knownItemsInfo = [
            'ayam' => [
                'category' => 'BASAH',
                'name' => 'Ayam',
                'unit_convert' => 'kg',
                'unit_base' => 'gram',
                'conversion_base' => 1000,
            ],
            'bakso' => [
                'category' => 'BASAH',
                'name' => 'Bakso',
                'unit_convert' => 'bks',
                'unit_base' => 'bks',
                'conversion_base' => 1,
            ],
            'saus tomat' => [
                'category' => 'KERING',
                'name' => 'Saus Tomat',
                'unit_convert' => 'jurigen',
                'unit_base' => 'jurigen',
                'conversion_base' => 1,
            ],
            'tepung kanji' => [
                'category' => 'KERING',
                'name' => 'Tepung Kanji',
                'unit_convert' => 'kg',
                'unit_base' => 'gram',
                'conversion_base' => 1000,
            ],
            'taoge pendek' => [
                'category' => 'BASAH',
                'name' => 'Taoge Pendek',
                'unit_convert' => 'kg',
                'unit_base' => 'gram',
                'conversion_base' => 1000,
            ],
            'tengiri' => [
                'category' => 'BASAH',
                'name' => 'Tengiri',
                'unit_convert' => 'kg',
                'unit_base' => 'gram',
                'conversion_base' => 1000,
            ],
        ];

        $normalizedName = $this->normalizeName($itemName);
        $info = $knownItemsInfo[$normalizedName] ?? null;

        if ($info === null) {
            $isBasah = preg_match('/(daging|ayam|ikan|sayur|tahu|tempe|telur|bawang|cabe|tomat|buah|pisang|melon|pepaya|semangka)/i', $itemName);
            $category = $isBasah ? 'BASAH' : 'KERING';

            $jsonUnitLower = strtolower(trim($jsonUnit));
            $unitConvert = 'pcs';
            if ($jsonUnitLower === 'gr' || $jsonUnitLower === 'gram') {
                $unitConvert = 'kg';
            } elseif ($jsonUnitLower === 'kg') {
                $unitConvert = 'kg';
            } elseif ($jsonUnitLower === 'ml' || $jsonUnitLower === 'liter') {
                $unitConvert = 'liter';
            } elseif ($jsonUnitLower === 'bks' || $jsonUnitLower === 'bungkus') {
                $unitConvert = 'bks';
            }

            $unitBase = $unitConvert;
            $convBase = 1;
            if ($unitConvert === 'kg') {
                $unitBase = 'gram';
                $convBase = 1000;
            } elseif ($unitConvert === 'liter') {
                $unitBase = 'ml';
                $convBase = 1000;
            }

            $info = [
                'category' => $category,
                'name' => $itemName,
                'unit_convert' => $unitConvert,
                'unit_base' => $unitBase,
                'conversion_base' => $convBase,
            ];
        }

        $catId = $categoryLookup[strtoupper($info['category'])] ?? null;
        if ($catId === null) {
            return null;
        }

        $unitBaseName = strtolower($info['unit_base']);
        $unitConvertName = strtolower($info['unit_convert']);

        $unitBaseId = $unitLookup[$unitBaseName] ?? ($unitLookup['gram'] ?? null);
        $unitConvertId = $unitLookup[$unitConvertName] ?? ($unitLookup['pcs'] ?? null);

        if ($unitBaseId === null || $unitConvertId === null) {
            return null;
        }

        $inserted = $this->db->table('items')->insert([
            'item_category_id'     => $catId,
            'name'                 => $info['name'],
            'unit_base'            => $unitBaseName,
            'unit_convert'         => $unitConvertName,
            'item_unit_base_id'    => $unitBaseId,
            'item_unit_convert_id' => $unitConvertId,
            'conversion_base'      => $info['conversion_base'],
            'is_active'            => true,
            'qty'                  => 0.0,
            'min_stock'            => 0,
        ]);

        if ($inserted) {
            return (int) $this->db->insertID();
        }

        return null;
    }
}
