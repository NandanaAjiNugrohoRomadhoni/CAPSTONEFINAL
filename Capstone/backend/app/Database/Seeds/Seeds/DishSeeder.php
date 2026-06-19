<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DishSeeder extends Seeder
{
    public function run(): void
    {
        $this->db->table('dishes')->insertBatch([
            ['name' => 'Bubur Ayam'],
            ['name' => 'Nasi Tim'],
            ['name' => 'Sup Sayur'],
            ['name' => 'Bubur Kacang Hijau'],
            ['name' => 'Nasi Putih Pagi'],
            ['name' => 'Roti Telur'],
            ['name' => 'Bubur Sumsum'],
            ['name' => 'Nasi Gurih'],
            ['name' => 'Sup Ayam'],
            ['name' => 'Bubur Beras Merah'],
            ['name' => 'Nasi Tim Ayam'],
            ['name' => 'Nasi Ayam Kuah'],
            ['name' => 'Nasi Telur Bumbu'],
            ['name' => 'Nasi Sayur Bening'],
            ['name' => 'Nasi Goreng Telur'],
            ['name' => 'Nasi Ayam Goreng'],
            ['name' => 'Nasi Semur Ayam'],
            ['name' => 'Nasi Ayam Kecap'],
            ['name' => 'Nasi Telur Dadar'],
            ['name' => 'Nasi Sayur Oseng'],
            ['name' => 'Nasi Ayam Opor'],
            ['name' => 'Nasi Tim Siang'],
            ['name' => 'Sup Ayam Sore'],
            ['name' => 'Nasi Putih Sore'],
            ['name' => 'Nasi Ayam Bakar'],
            ['name' => 'Nasi Tim Sore'],
            ['name' => 'Sup Telur Sayur'],
            ['name' => 'Nasi Sayur Lodeh'],
            ['name' => 'Nasi Ayam Rebus'],
            ['name' => 'Nasi Telur Rebus'],
            ['name' => 'Nasi Ayam Panggang'],
            ['name' => 'Sup Bening Sore'],
            ['name' => 'Nasi Gurih Sore'],
        ]);
    }
}
