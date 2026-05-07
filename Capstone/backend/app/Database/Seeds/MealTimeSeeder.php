<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MealTimeSeeder extends Seeder
{
    public function run(): void
    {
        $builder = $this->db->table('meal_times');

        $builder->replace(['id' => 1, 'name' => 'Pagi']);
        $builder->replace(['id' => 2, 'name' => 'Siang']);
        $builder->replace(['id' => 3, 'name' => 'Sore']);
    }
}
