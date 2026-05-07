<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ItemUnitSeeder extends Seeder
{
    public function run()
    {
        $this->db->table('item_units')->insertBatch([
            ['name' => 'gram'],
            ['name' => 'kg'],
            ['name' => 'ml'],
            ['name' => 'liter'],
            ['name' => 'butir'],
            ['name' => 'pack'],
        ]);
    }
}
