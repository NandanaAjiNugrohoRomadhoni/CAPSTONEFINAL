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

        if ($dishCount < $menuCount * count($mealTimeIds)) {
            throw new RuntimeException('MenuDishSeeder requires at least 33 seeded dishes to cover all menu slots.');
        }

        $rows = [];

        for ($menuId = 1; $menuId <= $menuCount; $menuId++) {
            foreach ($mealTimeIds as $slotIndex => $mealTimeId) {
                $dishIndex = $slotIndex * $menuCount + ($menuId - 1);
                $dishId    = $dishes[$dishIndex % $dishCount]['id'];

                $rows[] = [
                    'menu_id'      => $menuId,
                    'meal_time_id' => $mealTimeId,
                    'dish_id'      => $dishId,
                ];
            }
        }

        $this->db->table('menu_dishes')->insertBatch($rows);
    }
}
