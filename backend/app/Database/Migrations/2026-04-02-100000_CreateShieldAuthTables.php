<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates Shield auth support tables that reference our existing users table.
 * Tables created: auth_identities, auth_logins, auth_token_logins, auth_remember_tokens,
 * auth_groups_users, auth_permissions_users.
 * Does NOT create auth_users table since we have our own users table.
 */
class CreateShieldAuthTables extends Migration
{
    private array $tables;
    private array $attributes;

    public function __construct($forge = null)
    {
        parent::__construct($forge);

        $authConfig = config('Auth');
        $this->tables = $authConfig->tables;
        $this->attributes = ($this->db->getPlatform() === 'MySQLi') ? ['ENGINE' => 'InnoDB'] : [];
    }

    public function up(): void
    {
        /*
         * Auth Identities Table
         * Used for storage of passwords, access tokens, social login identities, etc.
         */
        $this->forge->addField([
            'id'           => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'BIGINT'],
            'type'         => ['type' => 'varchar', 'constraint' => 255],
            'name'         => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'secret'       => ['type' => 'varchar', 'constraint' => 255],
            'secret2'      => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'expires'      => ['type' => 'datetime', 'null' => true],
            'extra'        => ['type' => 'text', 'null' => true],
            'force_reset'  => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'last_used_at' => ['type' => 'datetime', 'null' => true],
            'created_at'   => ['type' => 'datetime', 'null' => true],
            'updated_at'   => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['type', 'secret']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->createTable($this->tables['identities']);

        /**
         * Auth Login Attempts Table
         * Records login attempts. A login means users think it is a login.
         * To login, users do action(s) like posting a form.
         */
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ip_address' => ['type' => 'varchar', 'constraint' => 255],
            'user_agent' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'id_type'    => ['type' => 'varchar', 'constraint' => 255],
            'identifier' => ['type' => 'varchar', 'constraint' => 255],
            'user_id'    => ['type' => 'BIGINT', 'null' => true], // Only for successful logins
            'date'       => ['type' => 'datetime'],
            'success'    => ['type' => 'tinyint', 'constraint' => 1],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_type', 'identifier']);
        $this->forge->addKey('user_id');
        // NOTE: Do NOT delete the user_id or identifier when the user is deleted for security audits
        $this->createTable($this->tables['logins']);

        /*
         * Auth Token Login Attempts Table
         * Records Bearer Token type login attempts.
         */
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ip_address' => ['type' => 'varchar', 'constraint' => 255],
            'user_agent' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'id_type'    => ['type' => 'varchar', 'constraint' => 255],
            'identifier' => ['type' => 'varchar', 'constraint' => 255],
            'user_id'    => ['type' => 'BIGINT', 'null' => true], // Only for successful logins
            'date'       => ['type' => 'datetime'],
            'success'    => ['type' => 'tinyint', 'constraint' => 1],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_type', 'identifier']);
        $this->forge->addKey('user_id');
        // NOTE: Do NOT delete the user_id or identifier when the user is deleted for security audits
        $this->createTable($this->tables['token_logins']);

        /*
         * Auth Remember Tokens (remember-me) Table
         * Used by Shield session authenticator for remember-me functionality.
         */
        $this->forge->addField([
            'id'              => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'selector'        => ['type' => 'varchar', 'constraint' => 255],
            'hashedValidator' => ['type' => 'varchar', 'constraint' => 255],
            'user_id'         => ['type' => 'BIGINT'],
            'expires'         => ['type' => 'datetime'],
            'created_at'      => ['type' => 'datetime'],
            'updated_at'      => ['type' => 'datetime'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('selector');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->createTable($this->tables['remember_tokens']);

        /*
         * Auth Groups Users Table
         * Maps users to Shield authorization groups.
         * Note: This project uses custom roles table for business authorization.
         * This table exists for Shield internal compatibility only.
         */
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'BIGINT'],
            'group'      => ['type' => 'varchar', 'constraint' => 255],
            'created_at' => ['type' => 'datetime'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->createTable($this->tables['groups_users']);

        /*
         * Auth Permissions Users Table
         * Maps users to Shield authorization permissions.
         * Note: This project uses custom roles table for business authorization.
         * This table exists for Shield internal compatibility only.
         */
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'BIGINT'],
            'permission' => ['type' => 'varchar', 'constraint' => 255],
            'created_at' => ['type' => 'datetime'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE');
        $this->createTable($this->tables['permissions_users']);
    }

    public function down(): void
    {
        // Drop in reverse order of creation to respect dependencies
        $this->forge->dropTable($this->tables['permissions_users'], true);
        $this->forge->dropTable($this->tables['groups_users'], true);
        $this->forge->dropTable($this->tables['remember_tokens'], true);
        $this->forge->dropTable($this->tables['token_logins'], true);
        $this->forge->dropTable($this->tables['logins'], true);
        $this->forge->dropTable($this->tables['identities'], true);
    }

    private function createTable(string $tableName): void
    {
        $this->forge->createTable($tableName, false, $this->attributes);
    }
}
