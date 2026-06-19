<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class MenuDishSeeder extends Seeder
{
    public function run(): void
    {
        $mealTimes = $this->db->table('meal_times')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $dishes = $this->db->table('dishes')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $mealTimeIds = array_column($mealTimes, 'id');
        $dishCount   = count($dishes);
        $menuCount   = 11;

        if (count($mealTimeIds) !== 3) {
            throw new RuntimeException('MenuDishSeeder requires exactly 3 seeded meal times before assigning menu slots.');
        }

        if ($dishCount < 33) {
            throw new RuntimeException('MenuDishSeeder requires at least 33 seeded dishes.');
        }

        $rows = [];

        // 1. Standard 1:1 mapping for Paket 1-11
        for ($menuId = 1; $menuId <= 11; $menuId++) {
            foreach ($mealTimeIds as $slotIndex => $mealTimeId) {
                $dishIndex = $slotIndex * 11 + ($menuId - 1);
                $dishId    = $dishes[$dishIndex % $dishCount]['id'];

                $rows[] = [
                    'menu_id'      => $menuId,
                    'meal_time_id' => $mealTimeId,
                    'dish_id'      => $dishId,
                ];
            }
        }

        // 2. Add an EXTRA dish to Paket 1 Siang (Test Multiple Dishes per Slot)
        // Assume mealTimeIds[1] is Siang
        $rows[] = [
            'menu_id'      => 1,
            'meal_time_id' => $mealTimeIds[1],
            'dish_id'      => $dishes[30]['id'], // Use another dish
        ];

        $this->db->table('menu_dishes')->insertBatch($rows);
    }
}
