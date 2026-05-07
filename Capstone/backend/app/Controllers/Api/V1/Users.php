<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\UserManagementService;
use CodeIgniter\HTTP\ResponseInterface;

class Users extends BaseController
{
    protected UserManagementService $userService;

    public function __construct()
    {
        $this->userService = new UserManagementService();
    }

    public function index(): ResponseInterface
    {
        $result = $this->userService->getAllUsers($this->request->getGet());

        if (! $result['success']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data'  => $result['data'],
                'meta'  => $result['meta'],
                'links' => $this->buildPaginationLinks($result['meta']),
            ]);
    }

    public function show(int $id): ResponseInterface
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => 'User not found.',
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $user,
            ]);
    }

    public function create(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        // Check for conflicting role_id and role_name
        if (isset($data['role_id']) && isset($data['role_name'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'role_id' => 'Cannot specify both role_id and role_name.',
                        'role_name' => 'Cannot specify both role_id and role_name.',
                    ],
                ]);
        }

        $rules = [
            'name'     => 'required|max_length[255]',
            'username' => 'required|max_length[100]',
            'password' => 'required|min_length[8]',
            'email'    => 'permit_empty|valid_email|max_length[255]',
        ];

        if (isset($data['role_id'])) {
            $rules['role_id'] = 'required|is_natural_no_zero';
        } elseif (isset($data['role_name'])) {
            $rules['role_name'] = 'required|max_length[50]';
        } else {
            // Neither provided - add role_id rule so validation will report it as missing
            $rules['role_id'] = 'required|is_natural_no_zero';
        }

        if (!$this->validateData($data ?? [], $rules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $result = $this->userService->createUser($data);

        if (!$result['success']) {
            $statusCode = isset($result['errors']) ? 400 : 422;
            $response = ['message' => $result['message']];
            
            if (isset($result['errors'])) {
                $response['errors'] = $result['errors'];
            }
            
            return $this->response
                ->setStatusCode($statusCode)
                ->setJSON($response);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'message' => 'User created successfully.',
                'data'    => $result['user'],
            ]);
    }

    public function update(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true) ?? [];

        // Check for conflicting role_id and role_name
        if (isset($data['role_id']) && isset($data['role_name'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => [
                        'role_id' => 'Cannot specify both role_id and role_name.',
                        'role_name' => 'Cannot specify both role_id and role_name.',
                    ],
                ]);
        }

        $validationData = [
            ...$data,
            'id' => $id,
        ];

        $rules = [
            'id'       => 'required|is_natural_no_zero',
            'name'     => 'permit_empty|max_length[255]',
            'email'    => 'permit_empty|valid_email|max_length[255]',
        ];

        if (isset($data['role_id'])) {
            $rules['role_id'] = 'permit_empty|is_natural_no_zero';
        }

        if (isset($data['role_name'])) {
            $rules['role_name'] = 'permit_empty|max_length[50]';
        }

        if (array_key_exists('username', $data)) {
            $rules['username'] = 'required|max_length[100]';
        }

        if (!$this->validateData($validationData, $rules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $result = $this->userService->updateUser($id, $data);

        if (!$result['success']) {
            // Determine status code
            if ($result['message'] === 'User not found.') {
                $statusCode = 404;
            } elseif (isset($result['errors'])) {
                $statusCode = 400; // Validation errors
            } else {
                $statusCode = 422; // Other processing errors
            }
            
            $response = ['message' => $result['message']];
            
            if (isset($result['errors'])) {
                $response['errors'] = $result['errors'];
            }
            
            return $this->response
                ->setStatusCode($statusCode)
                ->setJSON($response);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'User updated successfully.',
                'data'    => $result['user'],
            ]);
    }

    public function activate(int $id): ResponseInterface
    {
        $result = $this->userService->activateUser($id);

        if (!$result['success']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => $result['message'],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'User activated successfully.',
                'data'    => $result['user'],
            ]);
    }

    public function deactivate(int $id): ResponseInterface
    {
        $result = $this->userService->deactivateUser($id);

        if (!$result['success']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => $result['message'],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'User deactivated successfully.',
                'data'    => $result['user'],
            ]);
    }

    public function changePassword(int $id): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $rules = [
            'password' => 'required|min_length[8]',
        ];

        if (!$this->validateData($data ?? [], $rules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $result = $this->userService->changePassword($id, $data['password']);

        if (!$result['success']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => $result['message'],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => $result['message'],
            ]);
    }

    public function delete(int $id): ResponseInterface
    {
        $result = $this->userService->deleteUser($id);

        if (!$result['success']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON([
                    'message' => $result['message'],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => $result['message'],
            ]);
    }

    public function restore(int $id): ResponseInterface
    {
        $result = $this->userService->restoreUser($id);

        if (!$result['success']) {
            $statusCode = match ($result['message']) {
                'User not found.' => 404,
                'Failed to restore user.' => 422,
                default => 400,
            };

            return $this->response
                ->setStatusCode($statusCode)
                ->setJSON([
                    'message' => $result['message'],
                    'errors'  => $result['errors'] ?? [],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'User restored successfully.',
                'data'    => $result['user'],
            ]);
    }

    private function buildPaginationLinks(array $meta): array
    {
        $queryParams = $this->request->getGet();
        $path        = current_url();

        $buildLink = function (int $page) use ($path, $queryParams, $meta): string {
            return $path . '?' . http_build_query([
                ...$queryParams,
                'page'    => $page,
                'perPage' => $meta['perPage'],
            ]);
        };

        return [
            'self'     => $buildLink($meta['page']),
            'first'    => $buildLink(1),
            'last'     => $buildLink(max(1, $meta['totalPages'])),
            'next'     => $meta['page'] < $meta['totalPages'] ? $buildLink($meta['page'] + 1) : null,
            'previous' => $meta['page'] > 1 ? $buildLink($meta['page'] - 1) : null,
        ];
    }
}
