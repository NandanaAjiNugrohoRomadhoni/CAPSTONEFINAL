<?php

namespace App\Filters;

use App\Models\AppUserProvider;
use App\Models\RoleModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth()->user();

        if (!$user) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Authentication required.',
                ]);
        }

        $userProvider = new AppUserProvider();
        $userData = $userProvider->getActiveUserWithRole((int) $user->id);

        if (!$userData) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'message' => 'Account is inactive or has been deleted.',
                ]);
        }

        if (empty($arguments)) {
            return null;
        }

        $allowedRoleNames = is_array($arguments) ? $arguments : [$arguments];
        if (! isset($userData['role_name'])) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'message' => 'User role not found.',
                ]);
        }

        if (!in_array($userData['role_name'], $allowedRoleNames, true)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'message' => 'Insufficient permissions.',
                ]);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
