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
}
