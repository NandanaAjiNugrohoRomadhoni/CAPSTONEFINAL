<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $allowedFields    = ['role_id', 'name', 'username', 'password', 'email', 'is_active', 'last_active', 'status', 'status_message', 'active', 'force_pass_reset'];
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $deletedField     = 'deleted_at';
    protected $returnType       = 'array';
    protected $validationRules  = [
        'role_id'  => 'required|is_natural_no_zero',
        'name'     => 'required|max_length[255]',
        'username' => 'required|max_length[100]|is_unique[users.username,id,{id}]',
        'password' => 'required|min_length[8]',
    ];

    public function getAll(): array
    {
        return $this->orderBy('name', 'ASC')->findAll();
    }

    public function getByUsername(string $username): ?array
    {
        return $this->where('username', $username)->first();
    }

    public function findActiveByUsername(string $username): ?array
    {
        return $this->where('username', $username)
                    ->where('is_active', true)
                    ->where('deleted_at', null)
                    ->first();
    }

    public function findActiveById(int $id): ?array
    {
        return $this->where('id', $id)
                    ->where('is_active', true)
                    ->where('deleted_at', null)
                    ->first();
    }

    public function getWithRole(int $id): ?array
    {
        $user = $this->find($id);
        
        if (!$user) {
            return null;
        }

        $roleModel = new RoleModel();
        $role = $roleModel->find($user['role_id']);
        
        if ($role) {
            $user['role'] = $role;
        }

        return $user;
    }

    public function getAllWithRoles(): array
    {
        $users = $this->findAll();
        $roleModel = new RoleModel();
        
        foreach ($users as &$user) {
            $role = $roleModel->find($user['role_id']);
            if ($role) {
                $user['role'] = $role;
            }
        }
        
        return $users;
    }

    public function getRole(int $userId): ?array
    {
        $user = $this->find($userId);

        if ($user === null) {
            return null;
        }

        $roleModel = new RoleModel();
        $role      = $roleModel->find($user['role_id']);

        if ($role === null) {
            return null;
        }

        return [
            'id'   => $role['id'],
            'name' => $role['name'],
        ];
    }

    public function deactivate(int $id): bool
    {
        return $this->update($id, ['is_active' => false]);
    }

    public function activate(int $id): bool
    {
        return $this->update($id, ['is_active' => true]);
    }
}
