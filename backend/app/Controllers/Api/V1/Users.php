<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\UserManagementService;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Annotations as OA;

/**
 * Users
 *
 * Admin-only user management surface.
 */
class Users extends BaseController
{
    protected UserManagementService $userService;

    public function __construct()
    {
        $this->userService = new UserManagementService();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/users",
     *     operationId="listUsers",
     *     tags={"Users"},
     *     summary="List users",
     *     description="Returns active users in the standard collection envelope. Admin only. Runtime supports page, perPage, q, search, sortBy, sortDir, role_id, is_active, created_at_from, created_at_to, updated_at_from, and updated_at_to. Unlike lookup resources, users do not support paginate=false.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *     @OA\Parameter(name="q", in="query", description="Primary text search term across name, username, and email. If q and search are both present, q wins.", @OA\Schema(type="string", example="warehouse")),
     *     @OA\Parameter(name="search", in="query", description="Fallback text search term when q is absent.", @OA\Schema(type="string", example="admin")),
     *     @OA\Parameter(name="role_id", in="query", description="Filter by assigned active role id.", @OA\Schema(type="integer", minimum=1, example=3)),
     *     @OA\Parameter(name="is_active", in="query", description="Filter by activation state. Runtime accepts 0, 1, true, or false as strings.", @OA\Schema(type="string", enum={"0","1","true","false"}, example="0")),
     *     @OA\Parameter(name="sortBy", in="query", @OA\Schema(type="string", enum={"id","name","username","email","created_at","updated_at"}, example="email")),
     *     @OA\Parameter(name="sortDir", in="query", @OA\Schema(type="string", enum={"ASC","DESC"}, example="DESC")),
     *     @OA\Parameter(name="created_at_from", in="query", @OA\Schema(type="string", example="2026-04-10")),
     *     @OA\Parameter(name="created_at_to", in="query", @OA\Schema(type="string", example="2026-04-20")),
     *     @OA\Parameter(name="updated_at_from", in="query", @OA\Schema(type="string", example="2026-04-15 00:00:00")),
     *     @OA\Parameter(name="updated_at_to", in="query", @OA\Schema(type="string", example="2026-04-15 23:59:59")),
     *     @OA\Response(response=200, description="Active user collection.", @OA\JsonContent(ref="#/components/schemas/UserCollectionResponse")),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/v1/users/{id}",
     *     operationId="showUser",
     *     tags={"Users"},
     *     summary="Show one user",
     *     description="Returns one active user resource. Admin only. Soft-deleted users are treated as not found.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=1)),
     *     @OA\Response(response=200, description="Active user resource.", @OA\JsonContent(ref="#/components/schemas/UserResource")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="User not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/users",
     *     operationId="createUser",
     *     tags={"Users"},
     *     summary="Create user",
     *     description="Creates a new user. Admin only. The client must send exactly one of role_id or role_name. role_name resolution is trimmed and case-insensitive. Sending both fields returns HTTP 400. Usernames remain globally reserved across soft deletes: if a deleted user already owns the username, runtime returns HTTP 400 with errors.restore_id and requires the explicit restore flow instead of recreating the username.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     required={"role_id","name","username","password"},
     *                     @OA\Property(property="role_id", type="integer", minimum=1, example=3),
     *                     @OA\Property(property="name", type="string", maxLength=255, example="Warehouse User"),
      *                     @OA\Property(property="username", type="string", maxLength=100, example="example-warehouse-user"),
      *                     @OA\Property(property="email", type="string", format="email", nullable=true, example="warehouse.user@example.test"),
 *                     @OA\Property(property="password", type="string", format="password", minLength=8, example="example-user-password"),
     *                     @OA\Property(property="is_active", type="boolean", example=false)
     *                 ),
     *                 @OA\Schema(
     *                     required={"role_name","name","username","password"},
     *                     @OA\Property(property="role_name", type="string", maxLength=50, example="  gudang  "),
     *                     @OA\Property(property="name", type="string", maxLength=255, example="Warehouse User"),
      *                     @OA\Property(property="username", type="string", maxLength=100, example="example-warehouse-user"),
      *                     @OA\Property(property="email", type="string", format="email", nullable=true, example="warehouse.user@example.test"),
 *                     @OA\Property(property="password", type="string", format="password", minLength=8, example="example-user-password"),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=201, description="User created successfully.", @OA\JsonContent(ref="#/components/schemas/UserMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for missing required fields, conflicting role_id/role_name, invalid role lookup, duplicate username, or deleted-username restore guidance.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while creating the user.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/api/v1/users/{id}",
     *     operationId="updateUser",
     *     tags={"Users"},
     *     summary="Update user",
     *     description="Updates an existing active user. Admin only. This route behaves like a partial update even though it uses PUT. The client may change the assigned role using either role_id or role_name, but never both. role_name lookup is trimmed and case-insensitive. Reusing the username of another deleted user is blocked and returns HTTP 400 with restore guidance.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=2)),
      *     @OA\RequestBody(required=true, @OA\JsonContent(type="object", @OA\Property(property="role_id", type="integer", minimum=1, example=3), @OA\Property(property="role_name", type="string", maxLength=50, example="gudang"), @OA\Property(property="name", type="string", maxLength=255, example="Updated Example User"), @OA\Property(property="username", type="string", maxLength=100, example="updated-example-user"), @OA\Property(property="email", type="string", format="email", nullable=true, example="updated.user@example.test"), @OA\Property(property="is_active", type="boolean", example=false))),
     *     @OA\Response(response=200, description="User updated successfully.", @OA\JsonContent(ref="#/components/schemas/UserMutationResponse")),
     *     @OA\Response(response=400, description="Validation failed for conflicting role_id/role_name, invalid role lookup, duplicate username, or deleted-username restore guidance.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="User not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=422, description="Persistence failed while updating the user.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/users/{id}/activate",
     *     operationId="activateUser",
     *     tags={"Users"},
     *     summary="Activate user",
     *     description="Marks a user active by setting both is_active and active to true. Admin only. Soft-deleted users are treated as not found.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=2)),
     *     @OA\Response(response=200, description="User activated successfully.", @OA\JsonContent(ref="#/components/schemas/UserMutationResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="User not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/users/{id}/deactivate",
     *     operationId="deactivateUser",
     *     tags={"Users"},
     *     summary="Deactivate user",
     *     description="Marks a user inactive by setting both is_active and active to false. Admin only. Deactivated accounts can no longer log in. Soft-deleted users are treated as not found.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=2)),
     *     @OA\Response(response=200, description="User deactivated successfully.", @OA\JsonContent(ref="#/components/schemas/UserMutationResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="User not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/users/{id}/password",
     *     operationId="changeUserPassword",
     *     tags={"Users"},
     *     summary="Change user password",
     *     description="Changes another user's password and revokes all of that user's access tokens. Admin only. Soft-deleted users are treated as not found.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=2)),
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"password"}, @OA\Property(property="password", type="string", format="password", minLength=8, example="example-reset-password"))),
     *     @OA\Response(response=200, description="Password changed successfully and all access tokens were revoked.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=400, ref="#/components/responses/ValidationErrorResponse"),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="User not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/v1/users/{id}",
     *     operationId="deleteUser",
     *     tags={"Users"},
     *     summary="Delete user",
     *     description="Soft-deletes a user and revokes that user's access tokens. Admin only. The username remains globally reserved after deletion and must be reclaimed through the restore route, not by creating a replacement user with the same username.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=2)),
     *     @OA\Response(response=200, description="User deleted successfully.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="User not found.", @OA\JsonContent(ref="#/components/schemas/MessageResponse"))
     * )
     */
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

    /**
     * @OA\Patch(
     *     path="/api/v1/users/{id}/restore",
     *     operationId="restoreUser",
     *     tags={"Users"},
     *     summary="Restore user",
     *     description="Restores a soft-deleted user. Admin only. The operation is idempotent for already-active users and returns HTTP 200 with the current resource. Restore is conflict-checked: it fails with HTTP 400 if the assigned role has been soft-deleted or if an active user already uses the same username. Deleted-username collisions are therefore resolved through this route, not by auto-restoring on create or update.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", minimum=1, example=2)),
     *     @OA\Response(response=200, description="User restored successfully, or the current active user was returned unchanged.", @OA\JsonContent(ref="#/components/schemas/UserMutationResponse")),
     *     @OA\Response(response=400, description="Restore validation failed because the assigned role is no longer active or an active user already uses the same username.", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/UnauthorizedMessageResponse"),
     *     @OA\Response(response=403, description="Authenticated user lacks the admin role required by the route group.", @OA\JsonContent(ref="#/components/schemas/MessageResponse")),
     *     @OA\Response(response=404, description="User not found, including among soft-deleted rows.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse")),
     *     @OA\Response(response=422, description="Persistence failed while restoring the user.", @OA\JsonContent(ref="#/components/schemas/MessageWithOptionalErrorsResponse"))
     * )
     */
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
