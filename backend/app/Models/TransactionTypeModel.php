<?php

namespace App\Models;

use CodeIgniter\Model;

class TransactionTypeModel extends Model
{
    public const NAME_IN = 'IN';
    public const NAME_OUT = 'OUT';
    public const NAME_RETURN_IN = 'RETURN_IN';
    public const NAME_OPNAME_ADJUSTMENT = 'OPNAME_ADJUSTMENT';

    protected $table         = 'transaction_types';
    protected $primaryKey    = 'id';
    protected $allowedFields  = ['name'];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
    protected $returnType     = 'array';

    /**
     * Get transaction type ID by name with case-insensitive and trimmed matching.
     *
     * @param string $name Transaction type name to search for
     * @return int|null Transaction type ID if found, null otherwise
     */
    public function getIdByName(string $name): ?int
    {
        $trimmedName = trim($name);
        $result = $this->where('LOWER(name)', strtolower($trimmedName))->first();

        return $result !== null ? (int) $result['id'] : null;
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
