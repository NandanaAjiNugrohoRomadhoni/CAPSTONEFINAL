<?php

namespace App\Models;

use CodeIgniter\Model;

class ApprovalStatusModel extends Model
{
    public const NAME_APPROVED = 'APPROVED';
    public const NAME_PENDING = 'PENDING';
    public const NAME_REJECTED = 'REJECTED';

    protected $table         = 'approval_statuses';
    protected $primaryKey    = 'id';
    protected $allowedFields  = ['name'];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
    protected $returnType     = 'array';

    public function findByName(string $name): ?array
    {
        $status = $this->where('name', $name)->first();

        return $status ?: null;
    }

    public function getIdByName(string $name): ?int
    {
        $status = $this->findByName($name);

        return $status !== null ? (int) $status['id'] : null;
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
}
