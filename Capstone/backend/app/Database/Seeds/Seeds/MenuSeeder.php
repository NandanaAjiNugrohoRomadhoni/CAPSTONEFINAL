<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $builder = $this->db->table('menus');

        for ($id = 1; $id <= 11; $id++) {
            $builder->replace([
                'id'   => $id,
                'name' => 'Paket ' . $id,
            ]);
        }
    }
}
