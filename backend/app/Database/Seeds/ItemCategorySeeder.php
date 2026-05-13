<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    public function run()
    {
        $this->db->table('item_categories')->insertBatch([
            ['name' => 'BASAH'],
            ['name' => 'KERING'],
            ['name' => 'PENGEMAS'],
        ]);
    }
}
