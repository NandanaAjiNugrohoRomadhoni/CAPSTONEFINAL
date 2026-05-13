<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockTransactionDetails extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'auto_increment' => true,
            ],
            'transaction_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'item_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['transaction_id', 'item_id'], false, true);

        $this->forge->addForeignKey(
            'transaction_id',
            'stock_transactions',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'item_id',
            'items',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->createTable('stock_transaction_details');
    }

    public function down()
    {
        $this->forge->dropTable('stock_transaction_details', true);
    }
}
