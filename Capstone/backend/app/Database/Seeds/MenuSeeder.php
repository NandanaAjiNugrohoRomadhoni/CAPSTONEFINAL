<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $builder = $this->db->table('menus');

        for ($id = 1; $id <= 12; $id++) {
            $builder->replace([
                'id'   => $id,
                'name' => ($id === 12) ? 'Suplemen Extra' : ('Paket ' . $id),
            ]);
        }
    }
}
