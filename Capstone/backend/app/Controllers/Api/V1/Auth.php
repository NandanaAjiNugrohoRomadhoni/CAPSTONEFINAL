<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

class Auth extends BaseController
{
    protected AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $rules = [
            'username' => 'required',
            'password' => 'required',
        ];

        if (!$this->validateData($data ?? [], $rules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $result = $this->authService->attemptLogin(
            $data['username'],
            $data['password']
        );

        if (!$result['success']) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => $result['message'],
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message'      => 'Login successful.',
                'access_token' => $result['token'],
                'token_type'   => 'Bearer',
                'user'         => $result['user'],
            ]);
    }

    public function me(): ResponseInterface
    {
        $user = auth()->user();

        if (!$user) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthenticated.',
                ]);
        }

        $userData = $this->authService->getCurrentUser($user);

        if (!$userData) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'message' => 'Account is inactive or has been deleted.',
                ]);
        }

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'data' => $userData,
            ]);
    }

    public function logout(): ResponseInterface
    {
        $user = auth()->user();

        if (!$user) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthenticated.',
                ]);
        }

        $this->authService->logout($user);

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'message' => 'Logout successful.',
            ]);
    }

    public function changePassword(): ResponseInterface
    {
        $user = auth()->user();

        if (!$user) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON([
                    'message' => 'Unauthenticated.',
                ]);
        }

        $data = $this->request->getJSON(true);

        $rules = [
            'current_password' => 'required',
            'password'         => 'required|min_length[8]',
        ];

        if (!$this->validateData($data ?? [], $rules)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'message' => 'Validation failed.',
                    'errors'  => $this->validator->getErrors(),
                ]);
        }

        $result = $this->authService->changePassword($user, $data['current_password'], $data['password']);

        if (!$result['success']) {
            return $this->response
                ->setStatusCode(401)
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
}
