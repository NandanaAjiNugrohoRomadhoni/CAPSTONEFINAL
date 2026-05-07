<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuDishModel extends Model
{
    protected $table            = 'menu_dishes';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['menu_id', 'meal_time_id', 'dish_id'];
    protected $useTimestamps    = true;
    protected $returnType       = 'array';

    public function getAllWithRelations(): array
    {
        return $this->builder()
            ->select(
                'menu_dishes.*, menus.name AS menu_name, meal_times.name AS meal_time_name, dishes.name AS dish_name'
            )
            ->join('menus', 'menus.id = menu_dishes.menu_id')
            ->join('meal_times', 'meal_times.id = menu_dishes.meal_time_id')
            ->join('dishes', 'dishes.id = menu_dishes.dish_id')
            ->orderBy('menu_dishes.menu_id', 'ASC')
            ->orderBy('menu_dishes.meal_time_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getByIdWithRelations(int $id): ?array
    {
        $row = $this->builder()
            ->select(
                'menu_dishes.*, menus.name AS menu_name, meal_times.name AS meal_time_name, dishes.name AS dish_name'
            )
            ->join('menus', 'menus.id = menu_dishes.menu_id')
            ->join('meal_times', 'meal_times.id = menu_dishes.meal_time_id')
            ->join('dishes', 'dishes.id = menu_dishes.dish_id')
            ->where('menu_dishes.id', $id)
            ->get()
            ->getFirstRow('array');

        return $row ?: null;
    }

    public function findBySlot(int $menuId, int $mealTimeId): ?array
    {
        $row = $this->where('menu_id', $menuId)
            ->where('meal_time_id', $mealTimeId)
            ->first();

        return $row ?: null;
    }

    public function countByMenuId(int $menuId): int
    {
        return $this->builder()
            ->where('menu_id', $menuId)
            ->countAllResults();
    }

    public function countByDishId(int $dishId): int
    {
        return $this->builder()
            ->where('dish_id', $dishId)
            ->countAllResults();
    }
}
