<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReasonToStockTransactions extends Migration
{
    public function up()
    {
        $this->forge->addColumn('stock_transactions', [
            'reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'spk_id',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('stock_transactions', 'reason');
    }
}
