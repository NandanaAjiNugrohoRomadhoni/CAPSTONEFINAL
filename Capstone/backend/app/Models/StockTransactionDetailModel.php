<?php

namespace App\Models;

use CodeIgniter\Model;

class StockTransactionDetailModel extends Model
{
    protected $table         = 'stock_transaction_details';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'transaction_id',
        'item_id',
        'qty',
        'input_qty',
        'input_unit',
    ];
    protected $useTimestamps = false;
    protected $returnType    = 'array';

    public function getDetailsByTransactionId(int $transactionId): array
    {
        return $this->builder()
            ->select(
                'stock_transaction_details.*, ' .
                'items.name AS item_name, ' .
                'items.item_category_id AS item_category_id, ' .
                'item_categories.name AS item_category_name, ' .
                'items.unit_base AS satuan'
            )
            ->join('items', 'items.id = stock_transaction_details.item_id', 'left')
            ->join('item_categories', 'item_categories.id = items.item_category_id', 'left')
            ->where('stock_transaction_details.transaction_id', $transactionId)
            ->orderBy('stock_transaction_details.id', 'ASC')
            ->get()
            ->getResultArray();
    }
}
