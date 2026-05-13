<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\Shield\Entities\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        $roleModel = new \App\Models\RoleModel();
        
        $adminRole = $roleModel->findByName('admin');
        $spkRole = $roleModel->findByName('dapur');
        $gudangRole = $roleModel->findByName('gudang');

        $users = [
            [
                'role_id'   => $adminRole['id'],
                'name'      => 'Admin User',
                'username'  => 'admin',
                'email'     => 'admin@example.com',
                'is_active' => true,
                'active'    => true,
            ],
            [
                'role_id'   => $adminRole['id'],
                'name'      => 'Inactive Admin User',
                'username'  => 'inactiveadmin',
                'email'     => 'inactiveadmin@example.com',
                'is_active' => false,
                'active'    => false,
            ],
            [
                'role_id'   => $spkRole['id'],
                'name'      => 'SPK Gizi User',
                'username'  => 'spkgizi',
                'email'     => 'spkgizi@example.com',
                'is_active' => true,
                'active'    => true,
            ],
            [
                'role_id'   => $gudangRole['id'],
                'name'      => 'Gudang User',
                'username'  => 'gudang',
                'email'     => 'gudang@example.com',
                'is_active' => true,
                'active'    => true,
            ],
        ];

        $userProvider = new \App\Models\AppUserProvider();

        foreach ($users as $userData) {
            $user = new User($userData);
            $user->fill(['password' => 'password123']);
            $userProvider->insert($user, true);
        }
    }
}
