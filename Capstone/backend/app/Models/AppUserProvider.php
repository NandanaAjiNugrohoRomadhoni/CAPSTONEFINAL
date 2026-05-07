<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;
use CodeIgniter\Shield\Entities\User;

class AppUserProvider extends ShieldUserModel
{
    protected function initialize(): void
    {
        parent::initialize();

        $this->allowedFields = [
            ...$this->allowedFields,
            'role_id',
            'name',
            'email',
            'is_active',
        ];

        $this->useSoftDeletes = true;
        $this->deletedField   = 'deleted_at';
    }

    public function findByUsername(string $username): ?User
    {
        $user = $this->where('username', $username)
                     ->where('deleted_at', null)
                     ->first();

        return $user;
    }

    public function findByUsernameIncludingDeleted(string $username): ?User
    {
        $user = $this->withDeleted()
                     ->where('username', $username)
                     ->first();

        return $user instanceof User ? $user : null;
    }

    public function usernameExists(string $username, ?int $exceptId = null, bool $includeDeleted = true): bool
    {
        $builder = $includeDeleted ? $this->withDeleted() : $this;
        $builder = $builder->where('username', $username);

        if ($exceptId !== null) {
            $builder = $builder->where('id !=', $exceptId);
        }

        return $builder->first() !== null;
    }

    public function restore(int $id): bool
    {
        return $this->builder()
            ->where('id', $id)
            ->update([
                'deleted_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public function findById($id): ?User
    {
        $user = $this->find($id);

        return $user instanceof User ? $user : null;
    }

    public function findByIdIncludingDeleted($id): ?User
    {
        $user = $this->withDeleted()->find($id);

        return $user instanceof User ? $user : null;
    }

    public function findActiveById(int $id): ?User
    {
        $user = $this->where('id', $id)
                     ->where('is_active', true)
                     ->where('deleted_at', null)
                     ->first();

        return $user instanceof User ? $user : null;
    }

    public function isActiveUser(User $user): bool
    {
        $userData = $this->where('id', $user->id)
                         ->where('is_active', true)
                         ->where('deleted_at', null)
                         ->first();

        return $userData !== null;
    }

    public function getUserWithRole(int $userId): ?array
    {
        return $this->select('users.*, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.id', $userId)
            ->asArray()
            ->first();
    }

    public function getActiveUserWithRole(int $userId): ?array
    {
        return $this->select('users.*, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.id', $userId)
            ->where('users.is_active', true)
            ->where('users.deleted_at', null)
            ->asArray()
            ->first();
    }

    public function getAllWithRoles(): array
    {
        return $this->select('users.*, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id')
            ->where('users.deleted_at', null)
            ->asArray()
            ->findAll();
    }

    public function getAllWithRolesPaginated(
        int $page,
        int $perPage,
        string $search,
        string $sortBy,
        string $sortDir,
        ?int $roleId,
        ?bool $isActive,
        ?string $createdAtFrom,
        ?string $createdAtTo,
        ?string $updatedAtFrom,
        ?string $updatedAtTo,
    ): array {
        $validSortColumns = ['id', 'name', 'username', 'email', 'created_at', 'updated_at'];
        $sortColumn       = in_array($sortBy, $validSortColumns, true) ? $sortBy : 'name';
        $direction        = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        $builder = $this->db->table('users')
            ->select('users.*, roles.name as role_name')
            ->join('roles', 'roles.id = users.role_id', 'left')
            ->where('users.deleted_at', null);

        if ($search !== '') {
            $builder->groupStart()
                ->like('users.name', $search)
                ->orLike('users.username', $search)
                ->orLike('users.email', $search)
                ->groupEnd();
        }

        if ($roleId !== null) {
            $builder->where('users.role_id', $roleId);
        }

        if ($isActive !== null) {
            $builder->where('users.is_active', (int) $isActive);
        }

        if ($createdAtFrom !== null && $createdAtFrom !== '') {
            $builder->where('users.created_at >=', $createdAtFrom);
        }

        if ($createdAtTo !== null && $createdAtTo !== '') {
            $builder->where('users.created_at <=', $createdAtTo);
        }

        if ($updatedAtFrom !== null && $updatedAtFrom !== '') {
            $builder->where('users.updated_at >=', $updatedAtFrom);
        }

        if ($updatedAtTo !== null && $updatedAtTo !== '') {
            $builder->where('users.updated_at <=', $updatedAtTo);
        }

        $builder->orderBy('users.' . $sortColumn, $direction);
        if ($sortColumn !== 'id') {
            $builder->orderBy('users.id', 'ASC');
        }

        $countBuilder = clone $builder;
        $total        = $countBuilder->countAllResults();

        $users = $builder
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        return [
            'users'      => $users,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ];
    }

    public function revokeAllUserTokens(int $userId): void
    {
        $user = $this->findByIdIncludingDeleted($userId);
        
        if ($user instanceof User) {
            $user->revokeAllAccessTokens();
        }
    }

    public function delete($id = null, bool $purge = false): bool
    {
        if (is_int($id) || is_string($id)) {
            $user = $this->findByIdIncludingDeleted($id);

            if ($user instanceof User) {
                $user->revokeAllAccessTokens();
            }
        }

        return parent::delete($id, $purge);
    }
}
