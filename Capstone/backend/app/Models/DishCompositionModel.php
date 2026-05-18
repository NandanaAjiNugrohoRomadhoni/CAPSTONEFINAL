<?php

namespace App\Models;

use CodeIgniter\Model;

class DishCompositionModel extends Model
{
    protected $table         = 'dish_compositions';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['dish_id', 'item_id', 'qty_per_patient'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public const SORTABLE_COLUMNS = ['id', 'dish_id', 'item_id', 'qty_per_patient', 'created_at', 'updated_at'];

    public function getAllCompositions(
        int $page,
        int $perPage,
        bool $paginate,
        ?int $dishId,
        ?int $itemId,
        string $search,
        string $sortBy = 'id',
        string $sortDir = 'ASC',
        ?string $createdAtFrom = null,
        ?string $createdAtTo = null,
        ?string $updatedAtFrom = null,
        ?string $updatedAtTo = null,
    ): array {
        $builder = $this->builder();
        $builder->select(
            'dish_compositions.*, ' .
            'dishes.name AS dish_name, ' .
            'items.name AS item_name, ' .
            'items.is_active AS item_is_active, ' .
            'items.unit_base AS item_unit_base'
        );
        $builder->join('dishes', 'dishes.id = dish_compositions.dish_id');
        $builder->join('items', 'items.id = dish_compositions.item_id');
        $builder->where('items.deleted_at', null);

        if ($dishId !== null) {
            $builder->where('dish_compositions.dish_id', $dishId);
        }

        if ($itemId !== null) {
            $builder->where('dish_compositions.item_id', $itemId);
        }

        if ($search !== '') {
            $builder->groupStart()
                ->like('dishes.name', $search)
                ->orLike('items.name', $search)
                ->groupEnd();
        }

        if ($createdAtFrom !== null && $createdAtFrom !== '') {
            $builder->where('dish_compositions.created_at >=', $createdAtFrom);
        }

        if ($createdAtTo !== null && $createdAtTo !== '') {
            $builder->where('dish_compositions.created_at <=', $createdAtTo);
        }

        if ($updatedAtFrom !== null && $updatedAtFrom !== '') {
            $builder->where('dish_compositions.updated_at >=', $updatedAtFrom);
        }

        if ($updatedAtTo !== null && $updatedAtTo !== '') {
            $builder->where('dish_compositions.updated_at <=', $updatedAtTo);
        }

        $validSort = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'id';
        $validDir  = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $builder->orderBy('dish_compositions.' . $validSort, $validDir);
        if ($validSort !== 'id') {
            $builder->orderBy('dish_compositions.id', 'ASC');
        }

        $countBuilder = clone $builder;
        $countBuilder->select('dish_compositions.id');
        $total = $countBuilder->countAllResults();

        if ($paginate) {
            $rows = $builder
                ->limit($perPage, ($page - 1) * $perPage)
                ->get()
                ->getResultArray();
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        } else {
            $rows = $builder->get()->getResultArray();
            $page = 1;
            $perPage = max(1, count($rows));
            $total = count($rows);
            $totalPages = $total > 0 ? 1 : 0;
        }

        return [
            'compositions' => $rows,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => $totalPages,
        ];
    }

    public function findById(int $id): ?array
    {
        $builder = $this->builder();
        $builder->select(
            'dish_compositions.*, ' .
            'dishes.name AS dish_name, ' .
            'items.name AS item_name, ' .
            'items.is_active AS item_is_active, ' .
            'items.unit_base AS item_unit_base'
        );
        $builder->join('dishes', 'dishes.id = dish_compositions.dish_id');
        $builder->join('items', 'items.id = dish_compositions.item_id');
        $builder->where('items.deleted_at', null);
        $builder->where('dish_compositions.id', $id);

        $row = $builder->get()->getRowArray();

        return $row ?: null;
    }

    public function existsByDishAndItem(int $dishId, int $itemId, ?int $exceptId = null): bool
    {
        $builder = $this->where('dish_id', $dishId)
            ->where('item_id', $itemId);

        if ($exceptId !== null) {
            $builder = $builder->where('id !=', $exceptId);
        }

        return $builder->first() !== null;
    }

    public function countByDishId(int $dishId): int
    {
        return $this->builder()
            ->where('dish_id', $dishId)
            ->countAllResults();
    }
}
