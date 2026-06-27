<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUser extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('users')) {
            // Shield migration already created users table; add app-specific columns
            // Check and add each column individually to avoid "Duplicate column" errors
            $existingColumns = $this->db->getFieldNames('users');
            $addColumns = [];
            
            if (!in_array('role_id', $existingColumns)) {
                $addColumns['role_id'] = ['type' => 'BIGINT'];
            }
            if (!in_array('name', $existingColumns)) {
                $addColumns['name'] = ['type' => 'VARCHAR', 'constraint' => '255'];
            }
            if (!in_array('password', $existingColumns)) {
                $addColumns['password'] = ['type' => 'VARCHAR', 'constraint' => '255', 'null' => true];
            }
            if (!in_array('email', $existingColumns)) {
                $addColumns['email'] = ['type' => 'VARCHAR', 'constraint' => '255', 'null' => true];
            }
            if (!in_array('is_active', $existingColumns)) {
                $addColumns['is_active'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0];
            }
            if (!in_array('force_pass_reset', $existingColumns)) {
                $addColumns['force_pass_reset'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0];
            }
            
            if (!empty($addColumns)) {
                $this->forge->addColumn('users', $addColumns);
            }
            
            // Add foreign key if role_id was just added
            if (isset($addColumns['role_id'])) {
                try {
                    $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
                } catch (\Throwable $e) {
                    // Foreign key may already exist
                    echo "  Note: role_id FK exists, skipping.\n";
                }
            }
        } else {
            $this->forge->addField([
                "id" => ["type" => "BIGINT", "auto_increment" => true],
                "role_id" => ["type" => "BIGINT"],
                "name" => ["type" => "VARCHAR", "constraint" => "255"],
                "username" => ["type" => "VARCHAR", "constraint" => "30"],
                "password" => ["type" => "VARCHAR", "constraint" => "255", "null" => true],
                "email" => ["type" => "VARCHAR", "constraint" => "255", "null" => true],
                "is_active" => ["type" => "TINYINT", "constraint" => 1, "default" => 0],
                "last_active" => ["type" => "DATETIME", "null" => true],
                "status" => ["type" => "VARCHAR", "constraint" => "255", "null" => true],
                "status_message" => ["type" => "VARCHAR", "constraint" => "255", "null" => true],
                "active" => ["type" => "TINYINT", "constraint" => 1, "default" => 0],
                "force_pass_reset" => ["type" => "TINYINT", "constraint" => 1, "default" => 0],
                "created_at" => ["type" => "DATETIME", "null" => true],
                "updated_at" => ["type" => "DATETIME", "null" => true],
                "deleted_at" => ["type" => "DATETIME", "null" => true],
            ]);
            $this->forge->addForeignKey("role_id", "roles", "id", "CASCADE", "CASCADE");
            $this->forge->addKey("id", true);
            $this->forge->addUniqueKey("username");
            $this->forge->createTable("users");
        }
    }

    public function down()
    {
        $this->forge->dropTable("users", true);
    }
}
