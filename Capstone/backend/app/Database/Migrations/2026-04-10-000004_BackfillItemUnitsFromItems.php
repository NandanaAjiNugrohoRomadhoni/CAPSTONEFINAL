<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillItemUnitsFromItems extends Migration
{
    public function up(): void
    {
        if (! $this->hasTable('items') || ! $this->hasTable('item_units')) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $rows = $this->db->query(
            "SELECT DISTINCT LOWER(TRIM(unit_base)) AS unit_name FROM items WHERE unit_base IS NOT NULL AND unit_base != ''
             UNION
             SELECT DISTINCT LOWER(TRIM(unit_convert)) AS unit_name FROM items WHERE unit_convert IS NOT NULL AND unit_convert != ''"
        )->getResultArray();

        foreach ($rows as $row) {
            $name = $row['unit_name'];
            if ($name === null || $name === '') {
                continue;
            }

            $exists = $this->db->table('item_units')
                ->where('LOWER(name)', $name)
                ->countAllResults();

            if ($exists === 0) {
                $this->db->table('item_units')->insert([
                    'name'       => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $itemUnits = $this->db->table('item_units')
            ->select('id, name')
            ->get()
            ->getResultArray();

        $itemUnitMap = [];
        foreach ($itemUnits as $itemUnit) {
            $normalizedName = strtolower(trim((string) $itemUnit['name']));
            if ($normalizedName === '') {
                continue;
            }

            $itemUnitMap[$normalizedName] = (int) $itemUnit['id'];
        }

        $items = $this->db->table('items')
            ->select('id, unit_base, unit_convert')
            ->get()
            ->getResultArray();

        foreach ($items as $item) {
            $updateData = [];

            $unitBase = strtolower(trim((string) ($item['unit_base'] ?? '')));
            if ($unitBase !== '' && isset($itemUnitMap[$unitBase])) {
                $updateData['item_unit_base_id'] = $itemUnitMap[$unitBase];
            }

            $unitConvert = strtolower(trim((string) ($item['unit_convert'] ?? '')));
            if ($unitConvert !== '' && isset($itemUnitMap[$unitConvert])) {
                $updateData['item_unit_convert_id'] = $itemUnitMap[$unitConvert];
            }

            if ($updateData !== []) {
                $this->db->table('items')
                    ->where('id', $item['id'])
                    ->update($updateData);
            }
        }
    }

    public function down(): void
    {
        if ($this->hasTable('items')) {
            $this->db->query('UPDATE items SET item_unit_base_id = NULL, item_unit_convert_id = NULL');
        }

        if ($this->hasTable('item_units')) {
            $this->db->table('item_units')->where('id >', 0)->delete();
        }
    }

    private function hasTable(string $table): bool
    {
        $platform = $this->db->getPlatform();

        if ($platform === 'SQLite3') {
            $result = $this->db->query(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table],
            )->getRowArray();

            return $result !== null;
        }

        if ($platform === 'MySQLi') {
            $result = $this->db->query(
                'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            )->getRowArray();

            return $result !== null;
        }

        try {
            $this->db->query('SELECT 1 FROM ' . $table . ' LIMIT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
