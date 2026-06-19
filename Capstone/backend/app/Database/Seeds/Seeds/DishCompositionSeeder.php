<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class DishCompositionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const BASAH_ITEM_NAMES = [
        'Bakso Sapi',
        'Daging Ayam',
        'Daging Sapi',
        'Tahu',
        'Telur',
        'Tempe',
        'Tengiri Potong',
        'Tongkol',
        'Bayam',
        'Buncis',
        'Bunga Kol',
        'Gambas',
        'Kacang Panjang',
        'Kentang',
        'Ketimun',
        'Labu Siam',
        'Sawi Hijau',
        'Sawi Putih',
        'Tauge Pendek',
        'Wortel',
        'Kol',
        'Melon',
        'Pepaya',
        'Pisang Ambon',
        'Semangka',
        'Bawang Merah',
        'Bawang Putih',
        'Cabe Merah',
        'Bawang Prey',
        'Kluwek',
        'Jahe',
    ];

    public function run(): void
    {
        $itemLookup = $this->resolveItemIds(self::BASAH_ITEM_NAMES);
        $dishes = $this->db->table('dishes')
            ->select('id, name')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($dishes === []) {
            throw new RuntimeException('DishCompositionSeeder requires dishes to be seeded first.');
        }

        $rows = [];
        $basahItemNames = array_values(array_keys($itemLookup));

        foreach ($dishes as $index => $dish) {
            if (! array_key_exists('id', $dish) || $dish['id'] === null) {
                $dishName = (string) ($dish['name'] ?? '[unknown dish]');
                throw new RuntimeException("DishCompositionSeeder prerequisite invalid: dish '{$dishName}' is missing an id.");
            }

            $dishName = strtolower(trim((string) ($dish['name'] ?? '')));
            $itemName = $this->resolveItemNameForDish($dishName, $basahItemNames, $index);
            $itemId = $itemLookup[$itemName] ?? null;

            if ($itemId === null) {
                throw new RuntimeException("DishCompositionSeeder prerequisite missing: items.name '{$itemName}'. Seed ItemSeeder before DishCompositionSeeder.");
            }

            $rows[] = [
                'dish_id'         => (int) $dish['id'],
                'item_id'         => $itemId,
                'qty_per_patient' => $this->resolveQtyPerPatient($dishName),
            ];
        }

        $builder = $this->db->table('dish_compositions');
        $builder->emptyTable();

        foreach (array_chunk($rows, 500) as $chunk) {
            $builder->insertBatch($chunk);
        }
    }

    /**
     * @param list<string> $requiredNames
     *
     * @return array<string, int>
     */
    private function resolveItemIds(array $requiredNames): array
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

            $normalizedName = strtolower(trim((string) $row['name']));
            $itemLookup[$normalizedName] = (int) $row['id'];
        }

        $resolved = [];

        foreach ($requiredNames as $name) {
            $key = strtolower(trim($name));

            if (! array_key_exists($key, $itemLookup)) {
                throw new RuntimeException("DishCompositionSeeder prerequisite missing: items.name '{$name}'. Seed ItemSeeder before DishCompositionSeeder.");
            }

            $resolved[$key] = $itemLookup[$key];
        }

        return $resolved;
    }

    /**
     * @param list<string> $fallbackItems
     */
    private function resolveItemNameForDish(string $dishName, array $fallbackItems, int $index): string
    {
        $keywordMap = [
            'ayam' => 'daging ayam',
            'sapi' => 'daging sapi',
            'telur' => 'telur',
            'tahu' => 'tahu',
            'tempe' => 'tempe',
            'tengiri' => 'tengiri potong',
            'tongkol' => 'tongkol',
            'bayam' => 'bayam',
            'buncis' => 'buncis',
            'kol' => 'bunga kol',
            'gambas' => 'gambas',
            'kacang panjang' => 'kacang panjang',
            'kentang' => 'kentang',
            'ketimun' => 'ketimun',
            'labu siam' => 'labu siam',
            'sawi hijau' => 'sawi hijau',
            'sawi putih' => 'sawi putih',
            'tauge' => 'tauge pendek',
            'wortel' => 'wortel',
            'melon' => 'melon',
            'pepaya' => 'pepaya',
            'pisang' => 'pisang ambon',
            'semangka' => 'semangka',
            'bawang merah' => 'bawang merah',
            'bawang putih' => 'bawang putih',
            'cabe merah' => 'cabe merah',
            'bawang prey' => 'bawang prey',
            'kluwek' => 'kluwek',
            'jahe' => 'jahe',
        ];

        foreach ($keywordMap as $keyword => $itemName) {
            if (str_contains($dishName, $keyword)) {
                return $itemName;
            }
        }

        return $fallbackItems[$index % count($fallbackItems)];
    }

    private function resolveQtyPerPatient(string $dishName): string
    {
        if (str_contains($dishName, 'ayam') || str_contains($dishName, 'sapi') || str_contains($dishName, 'ikan') || str_contains($dishName, 'tengiri') || str_contains($dishName, 'tongkol')) {
            return '0.10';
        }

        if (str_contains($dishName, 'telur')) {
            return '0.08';
        }

        if (str_contains($dishName, 'sayur') || str_contains($dishName, 'sup') || str_contains($dishName, 'lodeh') || str_contains($dishName, 'oseng') || str_contains($dishName, 'bening')) {
            return '0.05';
        }

        return '0.10';
    }
}
