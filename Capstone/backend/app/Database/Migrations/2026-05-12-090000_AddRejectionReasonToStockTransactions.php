<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRejectionReasonToStockTransactions extends Migration
{
    public function up()
    {
        $this->forge->addColumn('stock_transactions', [
            'rejection_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'reason',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('stock_transactions', 'rejection_reason');
    }
}
