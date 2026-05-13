<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInputFieldsToStockTransactionDetails extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('stock_transaction_details', [
            'input_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => true,
                'after'      => 'qty',
            ],
            'input_unit' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'after'      => 'input_qty',
            ],
        ]);

        $this->db->table('stock_transaction_details')
            ->set('input_qty', 'qty', false)
            ->set('input_unit', 'base')
            ->update();
    }

    public function down(): void
    {
        $this->forge->dropColumn('stock_transaction_details', ['input_qty', 'input_unit']);
    }
}
