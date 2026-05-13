<?php

namespace App\Models;

use CodeIgniter\Model;

class DishModel extends Model
{
    protected $table         = 'dishes';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public const SORTABLE_COLUMNS = ['id', 'name', 'created_at', 'updated_at'];

    public function getAllDishes(
        int $page,
        int $perPage,
        bool $paginate,
        string $search,
        string $sortBy = 'name',
        string $sortDir = 'ASC',
        ?string $createdAtFrom = null,
        ?string $createdAtTo = null,
        ?string $updatedAtFrom = null,
        ?string $updatedAtTo = null,
    ): array {
        $builder = $this->builder();

        if ($search !== '') {
            $builder->like('dishes.name', $search);
        }

        if ($createdAtFrom !== null && $createdAtFrom !== '') {
            $builder->where('dishes.created_at >=', $createdAtFrom);
        }

        if ($createdAtTo !== null && $createdAtTo !== '') {
            $builder->where('dishes.created_at <=', $createdAtTo);
        }

        if ($updatedAtFrom !== null && $updatedAtFrom !== '') {
            $builder->where('dishes.updated_at >=', $updatedAtFrom);
        }

        if ($updatedAtTo !== null && $updatedAtTo !== '') {
            $builder->where('dishes.updated_at <=', $updatedAtTo);
        }

        $validSort = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $validDir  = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $builder->orderBy('dishes.' . $validSort, $validDir);
        if ($validSort !== 'id') {
            $builder->orderBy('dishes.id', 'ASC');
        }

        $countBuilder = clone $builder;
        $countBuilder->select('dishes.id');
        $total        = $countBuilder->countAllResults();

        if ($paginate) {
            $dishes = $builder
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        } else {
            $dishes     = $builder->get()->getResultArray();
            $page       = 1;
            $perPage    = max(1, count($dishes));
            $total      = count($dishes);
            $totalPages = $total > 0 ? 1 : 0;
        }

        return [
            'dishes'     => $dishes,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    public function findById(int $id): ?array
    {
        $dish = $this->find($id);

        return $dish !== null ? $dish : null;
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $builder = $this->where('LOWER(name)', strtolower(trim($name)));

        if ($exceptId !== null) {
            $builder = $builder->where('id !=', $exceptId);
        }

        return $builder->first() !== null;
    }
}
