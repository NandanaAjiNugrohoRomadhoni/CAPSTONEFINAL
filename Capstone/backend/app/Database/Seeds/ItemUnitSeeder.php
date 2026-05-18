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
            ['name' => 'btr'],
            ['name' => 'pack'],
            ['name' => 'pcs'],
            ['name' => 'roll'],
            ['name' => 'bks'],
            ['name' => 'ssr'],
            ['name' => 'ons'],
            ['name' => 'ikt'],
            ['name' => 'sachet'],
            ['name' => 'dus'],
            ['name' => 'kotak'],
            ['name' => 'kaleng'],
            ['name' => 'bungkus'],
            ['name' => 'jurigen'],
            ['name' => 'botol'],
            ['name' => 'pace'],
        ]);
    }
}
