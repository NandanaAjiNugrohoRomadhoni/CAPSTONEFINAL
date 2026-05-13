<?php

namespace App\Services;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Shield\Entities\User;

class UserManagementService
{
    protected AppUserProvider $userProvider;
    protected RoleModel $roleModel;

    public function __construct()
    {
        $this->userProvider = new AppUserProvider();
        $this->roleModel    = new RoleModel();
    }

    private const ALLOWED_QUERY_PARAMS = [
        'page',
        'perPage',
        'q',
        'search',
        'sortBy',
        'sortDir',
        'role_id',
        'is_active',
        'created_at_from',
        'created_at_to',
        'updated_at_from',
        'updated_at_to',
    ];

    public function getAllUsers(array $queryParams = []): array
    {
        $unknownParams = array_diff(array_keys($queryParams), self::ALLOWED_QUERY_PARAMS);

        if ($unknownParams !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'query' => 'Unsupported query parameter(s): ' . implode(', ', $unknownParams),
                ],
            ];
        }

        $queryErrors = $this->validateListQueryValues($queryParams);
        if ($queryErrors !== []) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $queryErrors,
            ];
        }

        $page          = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage       = max(1, min(100, (int) ($queryParams['perPage'] ?? 10)));
        $search        = trim((string) ($queryParams['q'] ?? $queryParams['search'] ?? ''));
        $sortBy        = (string) ($queryParams['sortBy'] ?? 'name');
        $sortDir       = (string) ($queryParams['sortDir'] ?? 'ASC');
        $roleId        = isset($queryParams['role_id']) ? (int) $queryParams['role_id'] : null;
        $isActive      = isset($queryParams['is_active'])
            ? filter_var($queryParams['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $createdAtFrom = $queryParams['created_at_from'] ?? null;
        $createdAtTo   = $queryParams['created_at_to'] ?? null;
        $updatedAtFrom = $queryParams['updated_at_from'] ?? null;
        $updatedAtTo   = $queryParams['updated_at_to'] ?? null;

        $result = $this->userProvider->getAllWithRolesPaginated(
            $page,
            $perPage,
            $search,
            $sortBy,
            $sortDir,
            $roleId,
            $isActive,
            $createdAtFrom,
            $createdAtTo,
            $updatedAtFrom,
            $updatedAtTo,
        );

        return [
            'success' => true,
            'data'    => array_map(fn (array $user): array => $this->formatUserResponse($user), $result['users']),
            'meta'    => [
                'page'       => $result['page'],
                'perPage'    => $result['perPage'],
                'total'      => $result['total'],
                'totalPages' => $result['totalPages'],
            ],
        ];
    }

    public function getUserById(int $id): ?array
    {
        $user = $this->userProvider->getUserWithRole($id);
        
        if (!$user) {
            return null;
        }

        return $this->formatUserResponse($user);
    }

    public function createUser(array $data): array
    {
        // Resolve role_name to role_id if provided
        if (isset($data['role_name']) && !isset($data['role_id'])) {
            $roleId = $this->roleModel->getIdByName($data['role_name']);
            if ($roleId === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['role_name' => 'The selected role is invalid.'],
                ];
            }
            $data['role_id'] = $roleId;
        }

        $role = $this->roleModel->find($data['role_id']);
        $roleName = is_array($role) && isset($role['name']) && is_string($role['name']) ? $role['name'] : null;

        if ($roleName === null || !$this->isAllowedRole($roleName)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['role_id' => 'The selected role is invalid.'],
            ];
        }

        // Check for active-username duplicate
        if ($this->userProvider->usernameExists($data['username'], null, false)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['username' => 'The username has already been taken.'],
            ];
        }

        // Check for deleted-username collision
        $deletedMatch = $this->userProvider->findByUsernameIncludingDeleted($data['username']);
        if ($deletedMatch !== null && $deletedMatch->deleted_at !== null) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => [
                    'username'   => 'The username belongs to a deleted user. Restore it instead.',
                    'restore_id' => (string) $deletedMatch->id,
                ],
            ];
        }

        $userData = [
            'role_id'   => $data['role_id'],
            'name'      => $data['name'],
            'username'  => $data['username'],
            'is_active' => $data['is_active'] ?? true,
            'active'    => $data['is_active'] ?? true,
        ];

        if (isset($data['email'])) {
            $userData['email'] = $data['email'];
        }

        $user = new User($userData);

        $user->fill(['password' => $data['password']]);

        $inserted = $this->userProvider->insert($user, true);

        if (!$inserted) {
            return [
                'success' => false,
                'message' => 'Failed to create user.',
                'errors'  => $this->userProvider->errors(),
            ];
        }

        $userId = $this->userProvider->getInsertID();
        $createdUser = $this->getUserById((int) $userId);

        return [
            'success' => true,
            'user'    => $createdUser,
        ];
    }

    public function updateUser(int $id, array $data): array
    {
        $user = $this->userProvider->findById($id);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        // Resolve role_name to role_id if provided
        if (isset($data['role_name']) && !isset($data['role_id'])) {
            $roleId = $this->roleModel->getIdByName($data['role_name']);
            if ($roleId === null) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['role_name' => 'The selected role is invalid.'],
                ];
            }
            $data['role_id'] = $roleId;
        }

        if (isset($data['role_id'])) {
            $role = $this->roleModel->find($data['role_id']);
            $roleName = is_array($role) && isset($role['name']) && is_string($role['name']) ? $role['name'] : null;

            if ($roleName === null || !$this->isAllowedRole($roleName)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['role_id' => 'The selected role is invalid.'],
                ];
            }
        }

        $updateData = [];

        if (isset($data['role_id'])) {
            $updateData['role_id'] = $data['role_id'];
        }
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['username'])) {
            $newUsername = $data['username'];

            // Check for active-username duplicate (excluding self)
            if ($this->userProvider->usernameExists($newUsername, $id, false)) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['username' => 'The username has already been taken.'],
                ];
            }

            // Check for deleted-username collision (excluding self)
            $deletedMatch = $this->userProvider->findByUsernameIncludingDeleted($newUsername);
            if ($deletedMatch !== null && $deletedMatch->deleted_at !== null && (int) $deletedMatch->id !== $id) {
                return [
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'username'   => 'The username belongs to a deleted user. Restore it instead.',
                        'restore_id' => (string) $deletedMatch->id,
                    ],
                ];
            }

            $updateData['username'] = $newUsername;
        }
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool) $data['is_active'];
            $updateData['active'] = (bool) $data['is_active'];
        }

        if ($updateData !== []) {
            $updated = $this->userProvider->update($id, $updateData);

            if (!$updated) {
                return [
                    'success' => false,
                    'message' => 'Failed to update user.',
                    'errors'  => $this->userProvider->errors(),
                ];
            }
        }

        if (isset($data['email'])) {
            $identityUser = $this->userProvider->findById($id);

            if (!$identityUser) {
                return [
                    'success' => false,
                    'message' => 'User not found.',
                ];
            }

            $identityUser->email = $data['email'];

            $identitySynced = $this->userProvider->save($identityUser);

            if (!$identitySynced) {
                return [
                    'success' => false,
                    'message' => 'Failed to update user.',
                    'errors'  => $this->userProvider->errors(),
                ];
            }
        }

        $updatedUser = $this->getUserById($id);

        return [
            'success' => true,
            'user'    => $updatedUser,
        ];
    }

    public function activateUser(int $id): array
    {
        $user = $this->userProvider->findById($id);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        $updated = $this->userProvider->update($id, [
            'is_active' => true,
            'active'    => true,
        ]);

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Failed to activate user.',
            ];
        }

        $updatedUser = $this->getUserById($id);

        return [
            'success' => true,
            'user'    => $updatedUser,
        ];
    }

    public function deactivateUser(int $id): array
    {
        $user = $this->userProvider->findById($id);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        $updated = $this->userProvider->update($id, [
            'is_active' => false,
            'active'    => false,
        ]);

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Failed to deactivate user.',
            ];
        }

        $updatedUser = $this->getUserById($id);

        return [
            'success' => true,
            'user'    => $updatedUser,
        ];
    }

    public function changePassword(int $id, string $newPassword): array
    {
        $user = $this->userProvider->findById($id);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        $user->fill(['password' => $newPassword]);
        $updated = $this->userProvider->save($user);

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Failed to update password.',
            ];
        }

        $this->userProvider->revokeAllUserTokens($id);

        return [
            'success' => true,
            'message' => 'Password changed successfully. All access tokens have been revoked.',
        ];
    }

    public function deleteUser(int $id): array
    {
        $user = $this->userProvider->findById($id);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        $deleted = $this->userProvider->delete($id);

        if (!$deleted) {
            return [
                'success' => false,
                'message' => 'Failed to delete user.',
            ];
        }

        return [
            'success' => true,
            'message' => 'User deleted successfully.',
        ];
    }

    public function restoreUser(int $id): array
    {
        $user = $this->userProvider->findByIdIncludingDeleted($id);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.',
            ];
        }

        // Already active — idempotent: return current data
        if ($user->deleted_at === null) {
            $current = $this->getUserById($id);
            return [
                'success' => true,
                'user'    => $current,
            ];
        }

        $role = $this->roleModel->find((int) $user->role_id);
        if (! is_array($role)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['role_id' => 'Cannot restore: the assigned role is no longer active.'],
            ];
        }

        // Active duplicate username exists → block restore
        if ($this->userProvider->usernameExists((string) $user->username, $id, false)) {
            return [
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['username' => 'Cannot restore: an active user with this username already exists.'],
            ];
        }

        if (!$this->userProvider->restore($id)) {
            return [
                'success' => false,
                'message' => 'Failed to restore user.',
            ];
        }

        $restoredUser = $this->getUserById($id);

        return [
            'success' => true,
            'user'    => $restoredUser,
        ];
    }

    protected function formatUserResponse(array $userData): array
    {
        unset($userData['password']);

        $response = [
            'id'         => $userData['id'],
            'role_id'    => $userData['role_id'],
            'name'       => $userData['name'],
            'username'   => $userData['username'],
            'email'      => $userData['email'] ?? null,
            'is_active'  => (bool) $userData['is_active'],
            'created_at' => $userData['created_at'],
            'updated_at' => $userData['updated_at'],
        ];

        if (isset($userData['role_name'])) {
            $response['role'] = [
                'id'   => $userData['role_id'],
                'name' => $userData['role_name'],
            ];
        }

        return $response;
    }

    protected function isAllowedRole(string $roleName): bool
    {
        return in_array($roleName, ['admin', 'dapur', 'gudang'], true);
    }

    private function validateListQueryValues(array $queryParams): array
    {
        $errors = [];

        if (isset($queryParams['page']) && (! ctype_digit((string) $queryParams['page']) || (int) $queryParams['page'] < 1)) {
            $errors['page'] = 'The page field must be a positive integer.';
        }

        if (isset($queryParams['perPage']) && (! ctype_digit((string) $queryParams['perPage']) || (int) $queryParams['perPage'] < 1 || (int) $queryParams['perPage'] > 100)) {
            $errors['perPage'] = 'The perPage field must be an integer between 1 and 100.';
        }

        if (isset($queryParams['role_id']) && (! ctype_digit((string) $queryParams['role_id']) || (int) $queryParams['role_id'] < 1)) {
            $errors['role_id'] = 'The role_id field must be a positive integer.';
        }

        if (isset($queryParams['is_active']) && ! in_array((string) $queryParams['is_active'], ['0', '1', 'true', 'false'], true)) {
            $errors['is_active'] = 'The is_active field must be a boolean value.';
        }

        $validSortColumns = ['id', 'name', 'username', 'email', 'created_at', 'updated_at'];
        if (isset($queryParams['sortBy']) && ! in_array($queryParams['sortBy'], $validSortColumns, true)) {
            $errors['sortBy'] = 'The sortBy field must be one of: ' . implode(', ', $validSortColumns) . '.';
        }

        if (isset($queryParams['sortDir']) && ! in_array(strtoupper((string) $queryParams['sortDir']), ['ASC', 'DESC'], true)) {
            $errors['sortDir'] = 'The sortDir field must be ASC or DESC.';
        }

        foreach (['created_at_from', 'created_at_to', 'updated_at_from', 'updated_at_to'] as $dateParam) {
            if (isset($queryParams[$dateParam]) && strtotime((string) $queryParams[$dateParam]) === false) {
                $errors[$dateParam] = sprintf('The %s field must be a valid date/datetime string.', $dateParam);
            }
        }

        return $errors;
    }
}
