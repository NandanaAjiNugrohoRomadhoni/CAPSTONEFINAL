<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class CsvDishSeeder extends Seeder
{
    private const SOURCE_FILE = __DIR__ . '/data/menu_standar_porsi.csv';

    /**
     * Session columns in the exported sheet.
     * The CSV stores one menu label per session in columns 2, 6, and 10
     * (0-based indexes 1, 5, and 9).
     *
     * @var list<int>
     */
    private const SESSION_COLUMNS = [1, 5, 9];

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
            throw new RuntimeException('CsvDishSeeder source file not found: ' . self::SOURCE_FILE);
        }

        $dishNames = $this->extractDishNames(self::SOURCE_FILE);
        if ($dishNames === []) {
            return;
        }

        $existing = $this->db->table('dishes')
            ->select('LOWER(name) AS name_lower', false)
            ->get()
            ->getResultArray();

        $lookup = [];
        foreach ($existing as $row) {
            $key = (string) ($row['name_lower'] ?? '');
            if ($key !== '') {
                $lookup[$key] = true;
            }
        }

        $rows = [];
        foreach ($dishNames as $name) {
            $normalized = strtolower($name);
            if (isset($lookup[$normalized])) {
                continue;
            }

            $rows[] = [
                'name'       => $name,
                'is_active'  => 1,
            ];
            $lookup[$normalized] = true;
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            $this->db->table('dishes')->insertBatch($chunk);
        }
    }

    /**
     * @return list<string>
     */
    private function extractDishNames(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV source: ' . $path);
        }

        $dishNames = [];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                foreach (self::SESSION_COLUMNS as $index) {
                    $rawLabel = $row[$index] ?? '';
                    $label    = $this->normalizeLabel($rawLabel);

                    if ($label === null) {
                        continue;
                    }

                    $dishNames[] = $label;
                }
            }
        } finally {
            fclose($handle);
        }

        $dishNames = array_values(array_unique($dishNames));

        return $dishNames;
    }

    private function normalizeLabel(string $value): ?string
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
}
