<?php

namespace App\Models;

use CodeIgniter\Model;

class StockOpnameModel extends Model
{
    public const STATE_DRAFT = 'DRAFT';
    public const STATE_SUBMITTED = 'SUBMITTED';
    public const STATE_APPROVED = 'APPROVED';
    public const STATE_REJECTED = 'REJECTED';
    public const STATE_POSTED = 'POSTED';

    /** Columns allowed for sortBy in list operations. */
    public const SORTABLE_COLUMNS = [
        'id', 'opname_date', 'state', 'created_at', 'updated_at',
    ];

    protected $table          = 'stock_opnames';
    protected $primaryKey     = 'id';
    protected $allowedFields  = [
        'opname_date',
        'state',
        'notes',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'posted_by',
        'posted_at',
    ];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
    protected $returnType     = 'array';

    public function findById(int $id): ?array
    {
        $builder = $this->builder();
        $builder->select('stock_opnames.*, '
            . 'creator.name as created_by_name, '
            . 'submitter.name as submitted_by_name, '
            . 'approver.name as approved_by_name, '
            . 'rejector.name as rejected_by_name, '
            . 'poster.name as posted_by_name');
        $builder->join('users creator', 'creator.id = stock_opnames.created_by AND creator.deleted_at IS NULL', 'left');
        $builder->join('users submitter', 'submitter.id = stock_opnames.submitted_by AND submitter.deleted_at IS NULL', 'left');
        $builder->join('users approver', 'approver.id = stock_opnames.approved_by AND approver.deleted_at IS NULL', 'left');
        $builder->join('users rejector', 'rejector.id = stock_opnames.rejected_by AND rejector.deleted_at IS NULL', 'left');
        $builder->join('users poster', 'poster.id = stock_opnames.posted_by AND poster.deleted_at IS NULL', 'left');
        $builder->where('stock_opnames.id', $id);
        $builder->where('stock_opnames.deleted_at', null);

        $row = $builder->get()->getRowArray();

        return $row ?: null;
    }

    public function getAllPaginatedFiltered(
        int $page,
        int $perPage,
        string $search,
        string $sortBy,
        string $sortDir,
        ?string $state,
        ?string $opnameDateFrom,
        ?string $opnameDateTo,
        ?int $createdBy,
        ?string $createdAtFrom,
        ?string $createdAtTo,
        ?string $updatedAtFrom,
        ?string $updatedAtTo,
    ): array {
        $validSort = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'opname_date';
        $direction = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $builder = $this->builder();
        $builder->select('stock_opnames.*, '
            . 'creator.name as created_by_name, '
            . 'submitter.name as submitted_by_name, '
            . 'approver.name as approved_by_name, '
            . 'rejector.name as rejected_by_name, '
            . 'poster.name as posted_by_name');
        $builder->join('users creator', 'creator.id = stock_opnames.created_by AND creator.deleted_at IS NULL', 'left');
        $builder->join('users submitter', 'submitter.id = stock_opnames.submitted_by AND submitter.deleted_at IS NULL', 'left');
        $builder->join('users approver', 'approver.id = stock_opnames.approved_by AND approver.deleted_at IS NULL', 'left');
        $builder->join('users rejector', 'rejector.id = stock_opnames.rejected_by AND rejector.deleted_at IS NULL', 'left');
        $builder->join('users poster', 'poster.id = stock_opnames.posted_by AND poster.deleted_at IS NULL', 'left');
        $builder->where('stock_opnames.deleted_at', null);

        if ($search !== '') {
            $builder->groupStart();
            $builder->like('stock_opnames.id', $search);
            $builder->orLike('stock_opnames.notes', $search);
            $builder->orLike('stock_opnames.rejection_reason', $search);
            $builder->orLike('creator.name', $search);
            $builder->groupEnd();
        }

        if ($state !== null) {
            $builder->where('stock_opnames.state', $state);
        }

        if ($createdBy !== null) {
            $builder->where('stock_opnames.created_by', $createdBy);
        }

        if ($opnameDateFrom !== null) {
            $builder->where('stock_opnames.opname_date >=', $opnameDateFrom);
        }

        if ($opnameDateTo !== null) {
            $builder->where('stock_opnames.opname_date <=', $opnameDateTo);
        }

        if ($createdAtFrom !== null) {
            $builder->where('stock_opnames.created_at >=', $createdAtFrom);
        }

        if ($createdAtTo !== null) {
            $builder->where('stock_opnames.created_at <=', $createdAtTo);
        }

        if ($updatedAtFrom !== null) {
            $builder->where('stock_opnames.updated_at >=', $updatedAtFrom);
        }

        if ($updatedAtTo !== null) {
            $builder->where('stock_opnames.updated_at <=', $updatedAtTo);
        }

        $builder->orderBy('stock_opnames.' . $validSort, $direction);
        if ($validSort !== 'id') {
            $builder->orderBy('stock_opnames.id', 'DESC');
        }

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();

        $rows = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'stockOpnames' => $rows,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => $perPage,
            'totalPages'   => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }
}
