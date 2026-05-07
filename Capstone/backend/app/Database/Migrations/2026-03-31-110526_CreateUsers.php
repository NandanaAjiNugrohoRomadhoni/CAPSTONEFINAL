<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUser extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "BIGINT",
                "auto_increment" => true,
            ],
            "role_id" => [
                "type" => "BIGINT",
                "null" => false,
            ],
            "name" => [
                "type" => "VARCHAR",
                "constraint" => 255,
                "null" => false,
            ],
            "username" => [
                "type" => "VARCHAR",
                "constraint" => 100,
                "null" => false,
            ],
            "password" => [
                "type" => "VARCHAR",
                "constraint" => 255,
                "null" => true,
            ],
            "email" => [
                "type" => "VARCHAR",
                "constraint" => 255,
                "null" => true,
            ],
            "is_active" => [
                "type" => "BOOLEAN",
                "default" => true,
            ],
            "last_active" => [
                "type" => "DATETIME",
                "null" => true,
            ],
            "status" => [
                "type" => "VARCHAR",
                "constraint" => 255,
                "null" => true,
            ],
            "status_message" => [
                "type" => "VARCHAR",
                "constraint" => 255,
                "null" => true,
            ],
            "active" => [
                "type" => "BOOLEAN",
                "default" => false,
                "null" => false,
            ],
            "force_pass_reset" => [
                "type" => "BOOLEAN",
                "default" => false,
                "null" => false,
            ],
            "created_at" => [
                "type" => "DATETIME",
                "null" => true,
            ],
            "updated_at" => [
                "type" => "DATETIME",
                "null" => true,
            ],
            "deleted_at" => [
                "type" => "DATETIME",
                "null" => true,
            ],
        ]);

        $this->forge->addForeignKey(
            "role_id",
            "roles",
            "id",
            "CASCADE",
            "CASCADE",
        );
        // Primary key
        $this->forge->addKey("id", true);

        // Unique constraint
        $this->forge->addUniqueKey("username");

        // Create table
        $this->forge->createTable("users");
    }

    public function down()
    {
        $this->forge->dropTable("users", true);
    }
}
