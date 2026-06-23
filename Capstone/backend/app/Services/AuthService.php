<?php

namespace App\Services;

use App\Enums\AuditActionType;
use App\Models\AppUserProvider;
use App\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use App\Services\StockSnapshotService;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

class AuthService
{
    protected AppUserProvider $userProvider;
    protected UserModel $userModel;
    protected AuditService $auditService;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->userProvider = new AppUserProvider();
        $this->userModel = new UserModel();
        $this->auditService = new AuditService();
        $this->db = Database::connect();
    }

    public function attemptLogin(string $username, string $password, ?string $ipAddress = null): array
    {
        $user = $this->userProvider->findByUsername($username);

        if (!$user) {
            return [
                "success" => false,
                "message" => "Invalid credentials.",
            ];
        }

        if (!$this->isUserAllowedToLogin($user)) {
            return [
                "success" => false,
                "message" => "Account is inactive or has been deleted.",
            ];
        }

        $authenticator = auth("session")->getAuthenticator();
        $credentials = [
            "username" => $username,
            "password" => $password,
        ];

        $result = $authenticator->check($credentials);

        if (!$result->isOK()) {
            return [
                "success" => false,
                "message" => "Invalid credentials.",
            ];
        }

        $loggedUser = $result->extraInfo();
        $token = $loggedUser->generateAccessToken("api-access");
        $this->auditService->log(
            (int) $loggedUser->id,
            AuditActionType::Login,
            'users',
            (int) $loggedUser->id,
            'User logged in.',
            null,
            ['username' => $username],
            $ipAddress
        );

        // Opportunistic snapshot trigger for read-only users (idempotent, failure-safe)
        (new StockSnapshotService())->ensureOpeningSnapshot(date('Y-m'));

        return [
            "success" => true,
            "token" => $token->raw_token,
            "user" => $this->formatUserResponse($loggedUser),
        ];
    }

    public function logout(User $user, ?string $ipAddress = null): bool
    {
        $token = $user->currentAccessToken();

        if ($token === null) {
            return false;
        }

        $this->auditService->log(
            (int) $user->id,
            AuditActionType::Logout,
            'users',
            (int) $user->id,
            'User logged out.',
            null,
            null,
            $ipAddress
        );

        $user->revokeAccessTokenBySecret($token->secret);

        return true;
    }

    public function getCurrentUser(User $user): ?array
    {
        if (!$this->isUserAllowedToLogin($user)) {
            return null;
        }

        return $this->formatUserResponse($user);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): array
    {
        $authenticator = auth("session")->getAuthenticator();
        $credentials = [
            "username" => $user->username,
            "password" => $currentPassword,
        ];

        $result = $authenticator->check($credentials);

        if (!$result->isOK()) {
            return [
                "success" => false,
                "message" => "Current password is incorrect.",
            ];
        }

        $user->fill(['password' => $newPassword]);

        $this->db->transStart();
        $updated = $this->userProvider->save($user);

        if (!$updated) {
            $this->db->transRollback();
            return [
                "success" => false,
                "message" => "Failed to update password.",
            ];
        }

        $this->userProvider->revokeAllUserTokens((int) $user->id);

        $auditLogged = $this->auditService->log(
            (int) $user->id,
            AuditActionType::PasswordChange,
            'users',
            (int) $user->id,
            'Password changed via self-service.',
            null,
            ['password_updated' => true],
            null
        );

        if (!$auditLogged) {
            $this->db->transRollback();
            return [
                "success" => false,
                "message" => "Failed to log password change.",
            ];
        }

        $this->db->transComplete();
        if (!$this->db->transStatus()) {
            return [
                "success" => false,
                "message" => "Failed to change password due to a system error.",
            ];
        }

        return [
            "success" => true,
            "message" => "Password changed successfully. All access tokens have been revoked.",
        ];
    }

    protected function isUserAllowedToLogin(User $user): bool
    {
        $userData = $this->userProvider
            ->asArray()
            ->where("id", $user->id)
            ->where("is_active", true)
            ->where("deleted_at", null)
            ->first();

        return $userData !== null;
    }

    protected function formatUserResponse(User $user): array
    {
        $userData = $this->userProvider->getActiveUserWithRole((int) $user->id);

        if (!$userData) {
            return [];
        }

        unset($userData["password"]);

        $response = [
            "id" => $userData["id"],
            "role_id" => $userData["role_id"],
            "name" => $userData["name"],
            "username" => $userData["username"],
            "email" => $userData["email"] ?? null,
            "is_active" => (bool) $userData["is_active"],
            "created_at" => $userData["created_at"],
            "updated_at" => $userData["updated_at"],
        ];

        $response["role"] = [
            "id" => $userData["role_id"],
            "name" => $userData["role_name"],
        ];

        return $response;
    }
}
