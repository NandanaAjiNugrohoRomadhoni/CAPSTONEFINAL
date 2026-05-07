<?php

namespace App\Models;

use CodeIgniter\Model;

class SpkRecommendationModel extends Model
{
    protected $table         = 'spk_recommendations';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'spk_id',
        'item_id',
        'target_date',
        'current_stock_qty',
        'required_qty',
        'system_recommended_qty',
        'recommended_qty',
        'is_overridden',
        'override_reason',
        'overridden_by',
        'overridden_at',
    ];

    public function getBySpkId(int $spkId): array
    {
        return $this->where('spk_id', $spkId)
            ->orderBy('target_date', 'ASC')
            ->orderBy('item_id', 'ASC')
            ->findAll();
    }
}
