<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemModel extends Model
{
    protected $table          = 'items';
    protected $primaryKey     = 'id';
    protected $allowedFields  = [
        'item_category_id',
        'name',
        'unit_base',
        'unit_convert',
        'item_unit_base_id',
        'item_unit_convert_id',
        'conversion_base',
        'is_active',
        'min_stock',
    ];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
    protected $returnType     = 'array';

    /**
     * Fields that can be sorted on in list operations
     * Used by ItemListService for allowlisting sortBy parameter
     */
    public const SORTABLE_COLUMNS = [
        'id',
        'name',
        'item_category_id',
        'created_at',
        'updated_at',
    ];

    /**
     * Fields that support single-value filtering in list operations
     * Used by ItemListService for allowlisting query filters
     */
    public function getAllWithCategories(
        int $page,
        int $perPage,
        ?int $categoryId,
        ?bool $isActive,
        string $search,
        string $sortBy = 'name',
        string $sortDir = 'ASC',
        ?string $createdAtFrom = null,
        ?string $createdAtTo = null,
        ?string $updatedAtFrom = null,
        ?string $updatedAtTo = null,
    ): array {
        $builder = $this->builder();
        $builder->select(
            'items.*, ' .
            'item_categories.name AS category_name'
        );
        $builder->join('item_categories', 'item_categories.id = items.item_category_id');
        $builder->where('items.deleted_at', null);

        if ($categoryId !== null) {
            $builder->where('items.item_category_id', $categoryId);
        }

        if ($isActive !== null) {
            $builder->where('items.is_active', $isActive);
        }

        if ($search !== '') {
            $builder->like('items.name', $search);
        }

        if ($createdAtFrom !== null && $createdAtFrom !== '') {
            $builder->where('items.created_at >=', $createdAtFrom);
        }

        if ($createdAtTo !== null && $createdAtTo !== '') {
            $builder->where('items.created_at <=', $createdAtTo);
        }

        if ($updatedAtFrom !== null && $updatedAtFrom !== '') {
            $builder->where('items.updated_at >=', $updatedAtFrom);
        }

        if ($updatedAtTo !== null && $updatedAtTo !== '') {
            $builder->where('items.updated_at <=', $updatedAtTo);
        }

        $validSort = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'name';
        $validDir  = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $builder->orderBy('items.' . $validSort, $validDir);
        if ($validSort !== 'id') {
            $builder->orderBy('items.id', 'ASC');
        }

        $countBuilder = clone $builder;
        $countBuilder->select('items.id');
        $total        = $countBuilder->countAllResults();

        $items = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    public function findWithCategory(int $id): ?array
    {
        $builder = $this->builder();
        $builder->select(
            'items.*, ' .
            'item_categories.name AS category_name'
        );
        $builder->join('item_categories', 'item_categories.id = items.item_category_id');
        $builder->where('items.id', $id);
        $builder->where('items.deleted_at', null);

        $item = $builder->get()->getRowArray();

        return $item ?: null;
    }

    public function findByIdIncludingDeleted(int $id): ?array
    {
        $item = $this->withDeleted()->find($id);

        return $item !== null ? $item : null;
    }

    public function findByNameIncludingDeleted(string $name): ?array
    {
        $trimmedName = trim($name);
        $result      = $this->withDeleted()->where('LOWER(name)', strtolower($trimmedName))->first();

        return $result !== null ? $result : null;
    }

    public function nameExists(string $name, ?int $exceptId = null, bool $includeDeleted = true): bool
    {
        $builder = $includeDeleted ? $this->withDeleted() : $this;
        $builder = $builder->where('LOWER(name)', strtolower(trim($name)));

        if ($exceptId !== null) {
            $builder = $builder->where('id !=', $exceptId);
        }

        return $builder->first() !== null;
    }

    public function restore(int $id): bool
    {
        return $this->builder()
            ->where('id', $id)
            ->update([
                'deleted_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function countActiveItemsByCategoryId(int $categoryId): int
    {
        return $this->builder()
            ->where('item_category_id', $categoryId)
            ->where('deleted_at', null)
            ->countAllResults();
    }

    public function countActiveItemsByItemUnitId(int $itemUnitId): int
    {
        return $this->builder()
            ->groupStart()
            ->where('item_unit_base_id', $itemUnitId)
            ->orWhere('item_unit_convert_id', $itemUnitId)
            ->groupEnd()
            ->where('deleted_at', null)
            ->countAllResults();
    }
}
