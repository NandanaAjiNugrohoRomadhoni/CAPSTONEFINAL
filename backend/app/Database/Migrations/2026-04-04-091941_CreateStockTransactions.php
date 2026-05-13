<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockTransactions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'auto_increment' => true,
            ],
            'type_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'transaction_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'is_revision' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'parent_transaction_id' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'approval_status_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'approved_by' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'user_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'spk_id' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);

        $this->forge->addForeignKey(
            'type_id',
            'transaction_types',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'approval_status_id',
            'approval_statuses',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'user_id',
            'users',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'approved_by',
            'users',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'parent_transaction_id',
            'stock_transactions',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->forge->createTable('stock_transactions');
    }

    public function down()
    {
        $this->forge->dropTable('stock_transactions', true);
    }
}
