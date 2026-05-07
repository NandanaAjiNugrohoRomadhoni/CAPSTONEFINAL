<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemUnitModel extends Model
{
    protected $table          = 'item_units';
    protected $primaryKey     = 'id';
    protected $allowedFields  = ['name'];
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
    protected $returnType     = 'array';

    public const SORTABLE_COLUMNS = ['id', 'name', 'created_at', 'updated_at'];

    public function exists(int $id): bool
    {
        return $this->find($id) !== null;
    }

    public function findByIdIncludingDeleted(int $id): ?array
    {
        $itemUnit = $this->withDeleted()->find($id);

        return $itemUnit !== null ? $itemUnit : null;
    }

    public function getIdByName(string $name): ?int
    {
        $trimmedName = trim($name);
        $result      = $this->where('LOWER(name)', strtolower($trimmedName))->first();

        return $result !== null ? (int) $result['id'] : null;
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
}
