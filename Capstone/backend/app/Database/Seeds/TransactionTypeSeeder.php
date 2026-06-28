<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    public function run()
    {
        if ($this->db->table('transaction_types')->countAll() > 0) {
            return;
        }

        $this->db
            ->table("transaction_types")
            ->insertBatch([
                ["name" => "IN"],
                ["name" => "OUT"],
                ["name" => "RETURN_IN"],
                ["name" => "OPNAME_ADJUSTMENT"],
            ]);
    }
}
