<?php

namespace App\Models;

use CodeIgniter\Model;

class MonthlyStockSnapshotModel extends Model
{
    protected $table          = 'monthly_stock_snapshots';
    protected $primaryKey     = 'id';
    protected $allowedFields  = ['period_month', 'item_id', 'opening_qty'];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = false;
    protected $returnType     = 'array';

    protected $validationRules = [
        'period_month' => 'required|valid_date[Y-m-d]',
        'item_id'      => 'required|integer',
        'opening_qty'  => 'required|decimal',
    ];

    /**
     * Retrieve paginated and filtered stock snapshots with item and category details.
     *
     * Follows the clone-for-count pattern used by StockTransactionModel::getAllPaginatedFiltered().
     *
     * @param int         $page         Current page number (1-indexed)
     * @param int         $perPage      Items per page
     * @param string|null $periodMonth  Filter by period_month (YYYY-MM-DD format)
     * @param int|null    $itemId       Filter by item_id
     * @param int|null    $categoryId   Filter by item_category_id
     * @return array{snapshots: array, total: int, page: int, perPage: int, totalPages: int}
     */
    public function getAllPaginatedFiltered(
        int $page,
        int $perPage,
        ?string $periodMonth = null,
        ?int $itemId = null,
        ?int $categoryId = null,
    ): array {
        $builder = $this->builder();
        $builder->select('monthly_stock_snapshots.*, items.name as item_name, item_categories.name as category_name');
        $builder->join('items', 'items.id = monthly_stock_snapshots.item_id');
        $builder->join('item_categories', 'item_categories.id = items.item_category_id');

        if ($periodMonth !== null) {
            $builder->where('monthly_stock_snapshots.period_month', $periodMonth);
        }

        if ($itemId !== null) {
            $builder->where('monthly_stock_snapshots.item_id', $itemId);
        }

        if ($categoryId !== null) {
            $builder->where('items.item_category_id', $categoryId);
        }

        $builder->orderBy('monthly_stock_snapshots.period_month', 'DESC');
        $builder->orderBy('monthly_stock_snapshots.id', 'DESC');

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $snapshots = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'snapshots'  => $snapshots,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }
}
