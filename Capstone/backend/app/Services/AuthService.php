<?php

namespace App\Services;

use App\Models\AppUserProvider;
use App\Models\UserModel;
use CodeIgniter\Shield\Entities\User;

class AuthService
{
    protected AppUserProvider $userProvider;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userProvider = new AppUserProvider();
        $this->userModel = new UserModel();
    }

    public function attemptLogin(string $username, string $password): array
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

        return [
            "success" => true,
            "token" => $token->raw_token,
            "user" => $this->formatUserResponse($loggedUser),
        ];
    }

    public function logout(User $user): bool
    {
        $token = $user->currentAccessToken();

        if ($token === null) {
            return false;
        }

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
        $updated = $this->userProvider->save($user);

        if (!$updated) {
            return [
                "success" => false,
                "message" => "Failed to update password.",
            ];
        }

        $this->userProvider->revokeAllUserTokens((int) $user->id);

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
