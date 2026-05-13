<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMonthlyStockSnapshots extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'auto_increment' => true,
            ],
            'period_month' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'item_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'opening_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['period_month', 'item_id'], false, true);

        $this->forge->addForeignKey(
            'item_id',
            'items',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->createTable('monthly_stock_snapshots');
    }

    public function down()
    {
        $this->forge->dropTable('monthly_stock_snapshots', true);
    }
}
