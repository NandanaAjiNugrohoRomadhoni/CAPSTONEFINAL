<?php

namespace App\Models;

use CodeIgniter\Model;

class SpkCalculationModel extends Model
{
    public const TYPE_BASAH = 'basah';
    public const TYPE_KERING_PENGEMAS = 'kering_pengemas';

    public const SCOPE_COMBINED_WINDOW = 'combined_window';
    public const SCOPE_MONTHLY = 'monthly';

    protected $table         = 'spk_calculations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'spk_type',
        'calculation_scope',
        'scope_key',
        'version',
        'is_latest',
        'calculation_date',
        'target_date_start',
        'target_date_end',
        'target_month',
        'daily_patient_id',
        'user_id',
        'category_id',
        'estimated_patients',
        'is_finish',
    ];

    public function getLatestByScopeKey(string $scopeKey): ?array
    {
        $row = $this->where('scope_key', $scopeKey)
            ->where('is_latest', true)
            ->orderBy('version', 'DESC')
            ->first();

        return $row ?: null;
    }

    public function getNextVersionForScope(string $scopeKey): int
    {
        $row = $this->selectMax('version')
            ->where('scope_key', $scopeKey)
            ->first();

        $maxVersion = (int) ($row['version'] ?? 0);

        return $maxVersion + 1;
    }
}
