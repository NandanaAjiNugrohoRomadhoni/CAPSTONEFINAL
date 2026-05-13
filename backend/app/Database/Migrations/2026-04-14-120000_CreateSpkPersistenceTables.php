<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSpkPersistenceTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'auto_increment' => true,
            ],
            'spk_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'comment'    => 'basah|kering_pengemas',
            ],
            'calculation_scope' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'comment'    => 'combined_window|monthly',
            ],
            'scope_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'comment'    => 'Deterministic key for version grouping.',
            ],
            'version' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'default'    => 1,
                'null'       => false,
            ],
            'is_latest' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
            'calculation_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'target_date_start' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'target_date_end' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'target_month' => [
                'type'       => 'VARCHAR',
                'constraint' => 7,
                'null'       => true,
                'comment'    => 'YYYY-MM for monthly kering/pengemas SPK.',
            ],
            'daily_patient_id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'null' => true,
            ],
            'user_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'category_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'estimated_patients' => [
                'type' => 'INT',
                'null' => false,
            ],
            'is_finish' => [
                'type'    => 'BOOLEAN',
                'default' => false,
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
        $this->forge->addUniqueKey(['scope_key', 'version'], 'uniq_spk_scope_version');
        $this->forge->addKey(['scope_key', 'is_latest'], false, false, 'idx_spk_scope_latest');
        $this->forge->addKey(['spk_type', 'calculation_date'], false, false, 'idx_spk_type_calc_date');
        $this->forge->addKey(['category_id', 'target_month'], false, false, 'idx_spk_category_target_month');

        $this->forge->addForeignKey('daily_patient_id', 'daily_patients', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('category_id', 'item_categories', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('spk_calculations');

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'auto_increment' => true,
            ],
            'spk_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'item_id' => [
                'type' => 'BIGINT',
                'null' => false,
            ],
            'target_date' => [
                'type' => 'DATE',
                'null' => true,
                'comment' => 'Per-target-date detail for combined basah window.',
            ],
            'current_stock_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
                'default'    => 0,
            ],
            'required_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
                'default'    => 0,
            ],
            'system_recommended_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
                'default'    => 0,
            ],
            'recommended_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'null'       => false,
                'default'    => 0,
            ],
            'is_overridden' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'override_reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'overridden_by' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'overridden_at' => [
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['spk_id', 'item_id', 'target_date'], 'uniq_spk_item_target_date');
        $this->forge->addKey(['spk_id'], false, false, 'idx_spk_recommendations_spk');
        $this->forge->addKey(['item_id'], false, false, 'idx_spk_recommendations_item');
        $this->forge->addKey(['target_date'], false, false, 'idx_spk_recommendations_target_date');

        $this->forge->addForeignKey('spk_id', 'spk_calculations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('overridden_by', 'users', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('spk_recommendations');
    }

    public function down()
    {
        $this->forge->dropTable('spk_recommendations', true);
        $this->forge->dropTable('spk_calculations', true);
    }
}
