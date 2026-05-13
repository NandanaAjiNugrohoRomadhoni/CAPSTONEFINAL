<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSoftDeleteToLookupTables extends Migration
{
    private array $tables = [
        'roles',
        'item_categories',
        'transaction_types',
        'approval_statuses',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            $this->forge->addColumn($table, [
                'deleted_at' => [
                    'type'    => 'DATETIME',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'updated_at',
                ],
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            $this->forge->dropColumn($table, 'deleted_at');
        }
    }
}
