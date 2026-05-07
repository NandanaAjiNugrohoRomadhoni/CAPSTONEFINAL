<?php

namespace App\Database\Seeds;

use App\Models\ItemCategoryModel;
use App\Models\ItemUnitModel;
use CodeIgniter\Database\Seeder;
use RuntimeException;

class ItemSeeder extends Seeder
{
    public function run()
    {
        $categoryModel = new ItemCategoryModel();
        $itemUnitModel = new ItemUnitModel();

        $categoryIds = $this->resolveRequiredCategoryIds($categoryModel, ['BASAH', 'KERING', 'PENGEMAS']);

        $gramId  = $this->resolveRequiredUnitId($itemUnitModel, 'gram');
        $kgId    = $this->resolveRequiredUnitId($itemUnitModel, 'kg');
        $mlId    = $this->resolveRequiredUnitId($itemUnitModel, 'ml');
        $literId = $this->resolveRequiredUnitId($itemUnitModel, 'liter');
        $butirId = $this->resolveRequiredUnitId($itemUnitModel, 'butir');
        $packId  = $this->resolveRequiredUnitId($itemUnitModel, 'pack');

        $this->db->table('items')->insertBatch([
            [
                'item_category_id'     => $categoryIds['KERING'],
                'name'                 => 'Beras',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 0,
            ],
            [
                'item_category_id'     => $categoryIds['BASAH'],
                'name'                 => 'Ayam',
                'unit_base'            => 'gram',
                'unit_convert'         => 'kg',
                'item_unit_base_id'    => $gramId,
                'item_unit_convert_id' => $kgId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 0,
            ],
            [
                'item_category_id'     => $categoryIds['BASAH'],
                'name'                 => 'Minyak Goreng',
                'unit_base'            => 'ml',
                'unit_convert'         => 'liter',
                'item_unit_base_id'    => $mlId,
                'item_unit_convert_id' => $literId,
                'conversion_base'      => 1000,
                'is_active'            => true,
                'qty'                  => 0,
            ],
            [
                'item_category_id'     => $categoryIds['PENGEMAS'],
                'name'                 => 'Telur',
                'unit_base'            => 'butir',
                'unit_convert'         => 'pack',
                'item_unit_base_id'    => $butirId,
                'item_unit_convert_id' => $packId,
                'conversion_base'      => 10,
                'is_active'            => true,
                'qty'                  => 0,
            ],
        ]);
    }

    /**
     * @param list<string> $requiredNames
     *
     * @return array<string, int>
     */
    private function resolveRequiredCategoryIds(ItemCategoryModel $categoryModel, array $requiredNames): array
    {
        $rows = $categoryModel->select('id, name')->findAll();

        $categoryLookup = [];

        foreach ($rows as $row) {
            $categoryLookup[strtolower(trim((string) $row['name']))] = (int) $row['id'];
        }

        $resolved = [];

        foreach ($requiredNames as $name) {
            $key = strtolower(trim($name));

            if (! array_key_exists($key, $categoryLookup)) {
                throw new RuntimeException("ItemSeeder prerequisite missing: item_categories.name '{$name}'. Seed ItemCategorySeeder before ItemSeeder.");
            }

            $resolved[$name] = $categoryLookup[$key];
        }

        return $resolved;
    }

    private function resolveRequiredUnitId(ItemUnitModel $itemUnitModel, string $unitName): int
    {
        $unitId = $itemUnitModel->getIdByName($unitName);

        if ($unitId === null) {
            throw new RuntimeException("ItemSeeder prerequisite missing: item_units.name '{$unitName}'. Seed ItemUnitSeeder before ItemSeeder.");
        }

        return (int) $unitId;
    }
}
