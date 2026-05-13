<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $data = [
            ['name' => 'admin'],
            ['name' => 'dapur'],
            ['name' => 'gudang'],
        ];

        $this->db->table('roles')->insertBatch($data);
    }
}
