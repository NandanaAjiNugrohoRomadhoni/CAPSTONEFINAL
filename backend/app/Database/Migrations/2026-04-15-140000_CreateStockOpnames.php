<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockOpnames extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'auto_increment' => true,
            ],
            'opname_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'state' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'DRAFT',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'submitted_by' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'submitted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'approved_by' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'approved_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'rejected_by' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'rejected_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'rejection_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'posted_by' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'posted_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('state');

        $this->forge->addForeignKey(
            'created_by',
            'users',
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'submitted_by',
            'users',
            'id',
            'SET NULL',
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
            'rejected_by',
            'users',
            'id',
            'SET NULL',
            'CASCADE'
        );
        $this->forge->addForeignKey(
            'posted_by',
            'users',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->forge->createTable('stock_opnames');

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'auto_increment' => true,
            ],
            'stock_opname_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'item_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'system_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
            ],
            'counted_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
            ],
            'variance_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['stock_opname_id', 'item_id']);
        $this->forge->addForeignKey(
            'stock_opname_id',
            'stock_opnames',
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

        $this->forge->createTable('stock_opname_details');
    }

    public function down()
    {
        $this->forge->dropTable('stock_opname_details', true);
        $this->forge->dropTable('stock_opnames', true);
    }
}
