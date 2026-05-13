<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ApprovalStatusSeeder extends Seeder
{
    public function run()
    {
        $this->db->table('approval_statuses')->insertBatch([
            ['name' => 'APPROVED'],
            ['name' => 'PENDING'],
            ['name' => 'REJECTED'],
        ]);
    }
}
