<?php

namespace App\Models;

use CodeIgniter\Model;

class DishModel extends Model
{
    protected $table         = 'dishes';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['name', 'is_active'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';
    protected $afterFind     = ['castIsActiveAfterFind'];

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
        ?bool $isActive = null,
    ): array {
        $builder = $this->builder();

        if ($isActive !== null) {
            $builder->where('dishes.is_active', $isActive);
        }

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

        $dishes = array_map(fn (array $dish): array => $this->normalizeDishRow($dish), $dishes);

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

    protected function castIsActiveAfterFind(array $data): array
    {
        if (! array_key_exists('data', $data) || $data['data'] === null) {
            return $data;
        }

        if (array_is_list($data['data'])) {
            $data['data'] = array_map(
                fn (mixed $dish): mixed => is_array($dish) ? $this->normalizeDishRow($dish) : $dish,
                $data['data']
            );

            return $data;
        }

        if (is_array($data['data'])) {
            $data['data'] = $this->normalizeDishRow($data['data']);
        }

        return $data;
    }

    private function normalizeDishRow(array $dish): array
    {
        if (array_key_exists('is_active', $dish)) {
            $dish['is_active'] = (bool) $dish['is_active'];
        }

        return $dish;
    }
}
