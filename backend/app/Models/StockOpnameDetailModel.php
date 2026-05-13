<?php

namespace App\Models;

use CodeIgniter\Model;

class StockOpnameDetailModel extends Model
{
    protected $table         = 'stock_opname_details';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'stock_opname_id',
        'item_id',
        'system_qty',
        'counted_qty',
        'variance_qty',
    ];
    protected $useTimestamps = false;
    protected $returnType    = 'array';

    public function getDetailsByStockOpnameId(int $stockOpnameId): array
    {
        return $this->where('stock_opname_id', $stockOpnameId)->findAll();
    }
}
