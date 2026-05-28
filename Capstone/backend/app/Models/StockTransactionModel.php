<?php

namespace App\Models;

use CodeIgniter\Model;

class StockTransactionModel extends Model
{
    protected $table          = 'stock_transactions';
    protected $primaryKey     = 'id';
    protected $allowedFields  = [
        'type_id',
        'transaction_date',
        'is_revision',
        'parent_transaction_id',
        'approval_status_id',
        'approved_by',
        'user_id',
        'spk_id',
        'reason',
        'rejection_reason',
        'legacy_source_table',
        'legacy_source_id',
        'legacy_source_detail_id',
    ];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
    protected $returnType     = 'array';

    /**
     * Fields that can be sorted on in list operations
     * Used by StockTransactionListService for allowlisting sortBy parameter
     */
    public const SORTABLE_COLUMNS = [
        'id',
        'transaction_date',
        'type_id',
        'approval_status_id',
        'created_at',
        'updated_at',
    ];

    public function getAllPaginated(int $page, int $perPage): array
    {
        $builder = $this->builder();
        $builder->where('deleted_at', null);
        $builder->orderBy('transaction_date', 'DESC');
        $builder->orderBy('id', 'DESC');

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $transactions = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'transactions' => $transactions,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    public function getAllPaginatedFiltered(
        int $page,
        int $perPage,
        string $search,
        string $sortBy,
        string $sortDir,
        ?int $typeId,
        ?int $statusId,
        ?string $transactionDateFrom,
        ?string $transactionDateTo,
        ?string $createdAtFrom,
        ?string $createdAtTo,
        ?string $updatedAtFrom,
        ?string $updatedAtTo,
    ): array {
        $validSort = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'transaction_date';
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $builder = $this->builder();
        $builder->where('stock_transactions.deleted_at', null);

        if ($search !== '') {
            $builder->like('stock_transactions.spk_id', $search);
        }

        if ($typeId !== null) {
            $builder->where('stock_transactions.type_id', $typeId);
        }

        if ($statusId !== null) {
            $builder->where('stock_transactions.approval_status_id', $statusId);
        }

        if ($transactionDateFrom !== null) {
            $builder->where('stock_transactions.transaction_date >=', $transactionDateFrom);
        }

        if ($transactionDateTo !== null) {
            $builder->where('stock_transactions.transaction_date <=', $transactionDateTo);
        }

        if ($createdAtFrom !== null) {
            $builder->where('stock_transactions.created_at >=', $createdAtFrom);
        }

        if ($createdAtTo !== null) {
            $builder->where('stock_transactions.created_at <=', $createdAtTo);
        }

        if ($updatedAtFrom !== null) {
            $builder->where('stock_transactions.updated_at >=', $updatedAtFrom);
        }

        if ($updatedAtTo !== null) {
            $builder->where('stock_transactions.updated_at <=', $updatedAtTo);
        }

        $builder->orderBy('stock_transactions.' . $validSort, $direction);
        if ($validSort !== 'id') {
            $builder->orderBy('stock_transactions.id', 'DESC');
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $transactions = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'transactions' => $transactions,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    public function findById(int $id): ?array
    {
        $builder = $this->builder();
        $builder->where('stock_transactions.id', $id);
        $builder->where('stock_transactions.deleted_at', null);

        $transaction = $builder->get()->getRowArray();

        return $transaction ?: null;
    }

    public function findRevisionById(int $id): ?array
    {
        $builder = $this->builder();
        $builder->where('id', $id);
        $builder->where('is_revision', true);
        $builder->where('deleted_at', null);

        $transaction = $builder->get()->getRowArray();

        return $transaction ?: null;
    }

    public function findApprovedRevisionByParentId(int $parentId, int $approvedStatusId, ?int $excludeId = null): ?array
    {
        return $this->findLatestApprovedRevisionByParentId($parentId, $approvedStatusId, $excludeId);
    }

    public function findLatestApprovedRevisionByParentId(int $parentId, int $approvedStatusId, ?int $excludeId = null): ?array
    {
        $builder = $this->builder();
        $builder->where('parent_transaction_id', $parentId);
        $builder->where('is_revision', true);
        $builder->where('approval_status_id', $approvedStatusId);
        $builder->where('deleted_at', null);

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        $builder->orderBy('id', 'DESC');

        $transaction = $builder->get()->getRowArray();

        return $transaction ?: null;
    }

    public function findPendingRevisionByParentId(int $parentId, int $pendingStatusId): ?array
    {
        $builder = $this->builder();
        $builder->where('parent_transaction_id', $parentId);
        $builder->where('is_revision', true);
        $builder->where('approval_status_id', $pendingStatusId);
        $builder->where('deleted_at', null);
        $builder->orderBy('id', 'DESC');

        $transaction = $builder->get()->getRowArray();

        return $transaction ?: null;
    }

    public function hasPendingRevision(int $parentId, int $pendingStatusId, ?int $excludeId = null): bool
    {
        $builder = $this->builder();
        $builder->select('id');
        $builder->where('parent_transaction_id', $parentId);
        $builder->where('is_revision', true);
        $builder->where('approval_status_id', $pendingStatusId);
        $builder->where('deleted_at', null);

        if ($excludeId !== null) {
            $builder->where('id !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }
}
