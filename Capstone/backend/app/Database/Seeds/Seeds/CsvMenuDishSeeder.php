<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class CsvMenuDishSeeder extends Seeder
{
    private const SOURCE_FILE = __DIR__ . '/data/menu_standar_porsi.csv';

    /**
     * CSV menu blocks use roman numeral menu markers in the first column.
     *
     * @var array<string, int>
     */
    private const MENU_MAP = [
        'I'    => 1,
        'II'   => 2,
        'III'  => 3,
        'IV'   => 4,
        'V'    => 5,
        'VI'   => 6,
        'VII'  => 7,
        'VIII' => 8,
        'IX'   => 9,
        'X'    => 10,
        'XI'   => 11,
    ];

    /**
     * CSV session columns and their matching meal_time ids.
     *
     * @var array<int, int>
     */
    private const SESSION_TO_MEAL_TIME = [
        1 => 2, // SIANG
        5 => 3, // SORE
        9 => 1, // PAGI
    ];

    /**
     * @var list<string>
     */
    private const RESERVED_LABELS = [
        'STANDAR PORSI PER MENU',
        'SIANG',
        'SORE',
        'PAGI',
        'MAKANAN BIASA',
        'LUNAK',
        'HALUS',
        'MINYAK,BUMBU,GULA,GARAM DAN BAHAN PENGEMAS',
    ];

    public function run(): void
    {
        if (! is_file(self::SOURCE_FILE)) {
            throw new RuntimeException('CsvMenuDishSeeder source file not found: ' . self::SOURCE_FILE);
        }

        $menuIds = $this->db->table('menus')
            ->select('id')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($menuIds === []) {
            throw new RuntimeException('CsvMenuDishSeeder requires menus to be seeded first.');
        }

        $dishLookup = $this->buildDishLookup();
        if ($dishLookup === []) {
            throw new RuntimeException('CsvMenuDishSeeder requires dishes to be seeded first.');
        }

        $rows = $this->extractAssignments(self::SOURCE_FILE, $dishLookup);

        $builder = $this->db->table('menu_dishes');
        $builder->emptyTable();

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            $builder->insertBatch($chunk);
        }
    }

    /**
     * @return array<string, int>
     */
    private function buildDishLookup(): array
    {
        $rows = $this->db->table('dishes')
            ->select('id, name')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $lookup = [];

        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            $id   = (int) ($row['id'] ?? 0);

            if ($name === '' || $id < 1) {
                continue;
            }

            $lookup[$this->normalizeKey($name)] = $id;
        }

        return $lookup;
    }

    /**
     * @param array<string, int> $dishLookup
     *
     * @return list<array{menu_id:int, meal_time_id:int, dish_id:int}>
     */
    private function extractAssignments(string $path, array $dishLookup): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV source: ' . $path);
        }

        $rows = [];
        $currentMenuId = null;
        $seen = [];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $menuMarker = $this->normalizeMenuMarker($row[0] ?? '');
                if ($menuMarker !== null) {
                    $currentMenuId = self::MENU_MAP[$menuMarker] ?? null;
                    continue;
                }

                if ($currentMenuId === null) {
                    continue;
                }

                foreach (self::SESSION_TO_MEAL_TIME as $columnIndex => $mealTimeId) {
                    $label = $this->normalizeDishLabel($row[$columnIndex] ?? '');
                    if ($label === null) {
                        continue;
                    }

                    $lookupKey = $this->normalizeKey($label);
                    $dishId = $dishLookup[$lookupKey] ?? null;
                    if ($dishId === null) {
                        continue;
                    }

                    $compositeKey = $currentMenuId . '|' . $mealTimeId . '|' . $dishId;
                    if (isset($seen[$compositeKey])) {
                        continue;
                    }

                    $seen[$compositeKey] = true;
                    $rows[] = [
                        'menu_id'      => $currentMenuId,
                        'meal_time_id' => $mealTimeId,
                        'dish_id'      => $dishId,
                    ];
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    private function normalizeMenuMarker(string $value): ?string
    {
        $marker = str_replace("\xC2\xA0", ' ', $value);
        $marker = trim($marker);
        $marker = preg_replace('/\s+/u', ' ', $marker) ?? $marker;

        if ($marker === '') {
            return null;
        }

        $marker = strtoupper($marker);
        return array_key_exists($marker, self::MENU_MAP) ? $marker : null;
    }

    private function normalizeDishLabel(string $value): ?string
    {
        $label = str_replace("\xC2\xA0", ' ', $value);
        $label = trim($label);
        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;
        $label = preg_replace('/^[\-\–\—\•\.\s]+/u', '', $label) ?? $label;
        $label = trim($label);

        if ($label === '' || $label === '-' || $label === '—') {
            return null;
        }

        $upper = strtoupper($label);
        if (in_array($upper, self::RESERVED_LABELS, true)) {
            return null;
        }

        return $label;
    }

    private function normalizeKey(string $value): string
    {
        $value = str_replace("\xC2\xA0", ' ', $value);
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return strtolower($value);
    }
}
