<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Auth
 *
 * Documents the implemented authentication endpoints exposed under `/api/v1/auth`.
 */
class Auth extends BaseController
{
    protected AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     tags={"Auth"},
     *     operationId="authLogin",
     *     summary="Authenticate a user and issue a bearer token.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", example="example-user"),
 *             @OA\Property(property="password", type="string", format="password", example="example-password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful.",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(
     *         response=401,
     *         description="Credentials are invalid or the account is inactive or deleted.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     tags={"Auth"},
     *     operationId="authMe",
     *     summary="Return the authenticated user's current profile.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Authenticated user profile.",
     *         @OA\JsonContent(ref="#/components/schemas/UserResource")
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(
     *         response=403,
     *         description="Account is inactive or has been deleted.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     tags={"Auth"},
     *     operationId="authLogout",
     *     summary="Revoke the current bearer token.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, ref="#/components/responses/MessageOnlyResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse")
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/auth/password",
     *     tags={"Auth"},
     *     operationId="authChangePassword",
     *     summary="Change the authenticated user's password.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","password"},
 *             @OA\Property(property="current_password", type="string", format="password", example="example-current-password"),
 *             @OA\Property(property="password", type="string", format="password", minLength=8, example="example-new-password")
 *         )
 *     ),
     *     @OA\Response(response=200, ref="#/components/responses/MessageOnlyResponse"),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(
     *         response=401,
     *         description="Authentication is required or the current password is incorrect.",
     *         @OA\JsonContent(ref="#/components/schemas/MessageResponse")
     *     )
     * )
     */
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
