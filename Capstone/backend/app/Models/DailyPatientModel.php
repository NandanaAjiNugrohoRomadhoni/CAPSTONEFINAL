<?php

namespace App\Models;

use CodeIgniter\Model;

class DailyPatientModel extends Model
{
    protected $table         = 'daily_patients';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['service_date', 'total_patients', 'notes'];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public function findByServiceDate(string $serviceDate, ?int $exceptId = null): ?array
    {
        $builder = $this->where('service_date', $serviceDate);

        if ($exceptId !== null) {
            $builder = $builder->where('id !=', $exceptId);
        }

        $row = $builder->first();

        return $row ?: null;
    }
}
