<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class DishCompositionSeeder extends Seeder
{
    public function run(): void
    {
        $itemIds = $this->resolveRequiredItemIds(['Beras', 'Daging Ayam', 'Minyak Goreng', 'Telur']);

        $dishes = $this->db->table('dishes')
            ->select('id, name')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($dishes === []) {
            throw new RuntimeException('DishCompositionSeeder requires dishes to be seeded first.');
        }

        foreach ($dishes as $dish) {
            if (! array_key_exists('id', $dish) || $dish['id'] === null) {
                $dishName = (string) ($dish['name'] ?? '[unknown dish]');
                throw new RuntimeException("DishCompositionSeeder prerequisite invalid: dish '{$dishName}' is missing an id.");
            }
        }

        $keringItems = [$itemIds['Beras'], $itemIds['Minyak Goreng']];
        $basahItems  = [$itemIds['Daging Ayam'], $itemIds['Telur']];
        $rows        = [];

        foreach ($dishes as $index => $dish) {
            // Assign one KERING item
            $rows[] = [
                'dish_id'         => $dish['id'],
                'item_id'         => $keringItems[$index % 2],
                'qty_per_patient' => '50.00',
            ];
            // Assign one BASAH item
            $rows[] = [
                'dish_id'         => $dish['id'],
                'item_id'         => $basahItems[$index % 2],
                'qty_per_patient' => '50.00',
            ];
        }

        $this->db->table('dish_compositions')->insertBatch($rows);
    }

    /**
     * @param list<string> $requiredNames
     *
     * @return array<string, int>
     */
    private function resolveRequiredItemIds(array $requiredNames): array
    {
        $rows = $this->db->table('items')
            ->select('id, name')
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $itemLookup = [];

        foreach ($rows as $row) {
            if (! array_key_exists('name', $row) || ! array_key_exists('id', $row)) {
                continue;
            }

            $normalizedName             = strtolower(trim((string) $row['name']));
            $itemLookup[$normalizedName] = (int) $row['id'];
        }

        $resolved = [];

        foreach ($requiredNames as $name) {
            $key = strtolower(trim($name));

            if (! array_key_exists($key, $itemLookup)) {
                throw new RuntimeException("DishCompositionSeeder prerequisite missing: items.name '{$name}'. Seed ItemSeeder before DishCompositionSeeder.");
            }

            $resolved[$name] = $itemLookup[$key];
        }

        return $resolved;
    }
}
