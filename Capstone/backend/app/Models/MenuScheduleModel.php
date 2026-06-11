<?php

namespace App\Models;

use CodeIgniter\Model;

class MenuScheduleModel extends Model
{
    protected $table         = 'menu_schedules';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['day_of_month', 'menu_id', 'patient_count'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function getAllWithMenu(): array
    {
        return $this->builder()
            ->select('menu_schedules.*, menus.name AS menu_name')
            ->join('menus', 'menus.id = menu_schedules.menu_id')
            ->orderBy('menu_schedules.day_of_month', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function findWithMenuById(int $id): ?array
    {
        $row = $this->builder()
            ->select('menu_schedules.*, menus.name AS menu_name')
            ->join('menus', 'menus.id = menu_schedules.menu_id')
            ->where('menu_schedules.id', $id)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function findAssignmentsByDay(int $dayOfMonth, ?int $excludeId = null): array
    {
        $builder = $this->where('day_of_month', $dayOfMonth);
        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }
        return $builder->findAll();
    }

    public function getDayToMenuMap(): array
    {
        $rows = $this->builder()
            ->select('day_of_month, menu_id')
            ->orderBy('day_of_month', 'ASC')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['day_of_month']][] = (int) $row['menu_id'];
        }

        return $map;
    }

    public function countByMenuId(int $menuId): int
    {
        return $this->builder()
            ->where('menu_id', $menuId)
            ->countAllResults();
    }
}
