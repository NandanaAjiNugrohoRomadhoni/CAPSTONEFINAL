<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class MigrateBasahCombinedScopeKeyToCalculationDate extends Migration
{
    public function up()
    {
        $groupsWithVersionCollision = $this->db->table('spk_calculations')
            ->select('calculation_date, category_id, version, COUNT(*) AS total_rows')
            ->where('spk_type', 'basah')
            ->where('calculation_scope', 'combined_window')
            ->groupBy('calculation_date, category_id, version')
            ->having('COUNT(*) >', 1)
            ->get()
            ->getResultArray();

        if ($groupsWithVersionCollision !== []) {
            $samples = array_slice($groupsWithVersionCollision, 0, 5);
            $sampleText = implode('; ', array_map(static function (array $row): string {
                return sprintf(
                    'calculation_date=%s category_id=%d version=%d count=%d',
                    (string) $row['calculation_date'],
                    (int) $row['category_id'],
                    (int) $row['version'],
                    (int) $row['total_rows']
                );
            }, $samples));

            throw new RuntimeException(
                'Cannot migrate basah combined scope keys because duplicate versions exist for the new grouping. Resolve conflicts first. Samples: '
                . $sampleText
            );
        }

        $rows = $this->db->table('spk_calculations')
            ->select('id, calculation_date, category_id')
            ->where('spk_type', 'basah')
            ->where('calculation_scope', 'combined_window')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $newScopeKey = sprintf(
                'basah|combined_window|%s|%d',
                (string) $row['calculation_date'],
                (int) $row['category_id']
            );

            $this->db->table('spk_calculations')
                ->where('id', (int) $row['id'])
                ->update(['scope_key' => $newScopeKey]);
        }

        $groups = $this->db->table('spk_calculations')
            ->select('calculation_date, category_id')
            ->where('spk_type', 'basah')
            ->where('calculation_scope', 'combined_window')
            ->groupBy('calculation_date, category_id')
            ->get()
            ->getResultArray();

        foreach ($groups as $group) {
            $scopeKey = sprintf(
                'basah|combined_window|%s|%d',
                (string) $group['calculation_date'],
                (int) $group['category_id']
            );

            $latest = $this->db->table('spk_calculations')
                ->select('id')
                ->where('scope_key', $scopeKey)
                ->orderBy('version', 'DESC')
                ->orderBy('id', 'DESC')
                ->get(1)
                ->getRowArray();

            if ($latest === null) {
                continue;
            }

            $this->db->table('spk_calculations')
                ->where('scope_key', $scopeKey)
                ->update(['is_latest' => false]);

            $this->db->table('spk_calculations')
                ->where('id', (int) $latest['id'])
                ->update(['is_latest' => true]);
        }
    }

    public function down()
    {
        $rows = $this->db->table('spk_calculations')
            ->select('id, target_date_start, target_date_end, category_id')
            ->where('spk_type', 'basah')
            ->where('calculation_scope', 'combined_window')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $legacyScopeKey = sprintf(
                'basah|combined_window|%s|%s|%d',
                (string) $row['target_date_start'],
                (string) $row['target_date_end'],
                (int) $row['category_id']
            );

            $this->db->table('spk_calculations')
                ->where('id', (int) $row['id'])
                ->update(['scope_key' => $legacyScopeKey]);
        }
    }
}
