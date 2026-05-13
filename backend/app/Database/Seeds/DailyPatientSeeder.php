<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DailyPatientSeeder extends Seeder
{
    /**
     * Deterministic baseline date for all seeded operational data.
     * All date-bearing seeders (MenuScheduleSeeder, DailyPatientSeeder, SpkPersistenceSeeder)
     * use this as the anchor point to ensure reproducible, stable seeding across fresh runs.
     */
    private const BASELINE_DATE = '2026-04-15';

    public function run(): void
    {
        $builder = $this->db->table('daily_patients');

        $rows = [
            [
                'service_date'   => self::BASELINE_DATE,
                'total_patients' => 128,
                'notes'          => 'Baseline pasien harian untuk simulasi SPK basah.',
            ],
            [
                'service_date'   => date('Y-m-d', strtotime(self::BASELINE_DATE . ' +1 day')),
                'total_patients' => 122,
                'notes'          => 'Data lanjutan untuk rentang target basah +1 hari.',
            ],
        ];

        $builder->insertBatch($rows);
    }
}
