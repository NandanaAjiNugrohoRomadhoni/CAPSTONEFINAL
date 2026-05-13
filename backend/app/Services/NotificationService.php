<?php

namespace App\Services;

use App\Models\NotificationModel;
use App\Models\RoleModel;
use App\Models\AppUserProvider;

class NotificationService
{
    private NotificationModel $notificationModel;
    private RoleModel $roleModel;
    private AppUserProvider $userProvider;

    public function __construct()
    {
        $this->notificationModel = new NotificationModel();
        $this->roleModel = new RoleModel();
        $this->userProvider = new AppUserProvider();
    }

    /**
     * Send a single notification to a user.
     *
     * @return int|null Inserted notification ID or null on failure.
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $message,
        string $type,
        ?int $relatedId = null,
    ): ?int {
        $data = [
            "user_id" => $userId,
            "title" => $title,
            "message" => $message,
            "type" => $type,
            "related_id" => $relatedId,
            "is_read" => false,
        ];

        $insertId = $this->notificationModel->insert($data, true);
        return is_numeric($insertId) ? (int) $insertId : null;
    }

    /**
     * Send the same notification to all users that have a given role.
     *
     * @return bool Returns true on success, false on DB failure or if role/users not found.
     */
    public function sendToRole(
        string $roleName,
        string $title,
        string $message,
        string $type,
        ?int $relatedId = null,
    ): bool {
        $roleId = $this->roleModel->getIdByName($roleName);
        if (!$roleId) {
            return false;
        }

        $users = $this->userProvider->where("role_id", $roleId)->findAll();
        if (empty($users)) {
            return false;
        }

        $batchData = [];
        foreach ($users as $user) {
            $batchData[] = [
                "user_id" => (int) $user->id,
                "title" => $title,
                "message" => $message,
                "type" => $type,
                "related_id" => $relatedId,
                "is_read" => false,
            ];
        }

        $result = $this->notificationModel->insertBatch($batchData);
        return $result !== false ? true : false;
    }

    /**
     * Mark a specific notification as read for the given user.
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = $this->notificationModel
            ->where("id", $notificationId)
            ->where("user_id", $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        return (bool) $this->notificationModel->update($notificationId, [
            "is_read" => true,
        ]);
    }

    /**
     * Mark all notifications as read for the given user.
     */
    public function markAllAsRead(int $userId): bool
    {
        return (bool) $this->notificationModel
            ->where("user_id", $userId)
            ->set(["is_read" => true])
            ->update();
    }

    /**
     * Delete a single notification if it belongs to the user.
     */
    public function deleteNotification(int $notificationId, int $userId): bool
    {
        $notification = $this->notificationModel
            ->where("id", $notificationId)
            ->where("user_id", $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        // Use model delete so any potential callbacks are honored.
        return (bool) $this->notificationModel->delete($notificationId);
    }

    /**
     * Delete all notifications for a user.
     */
    public function deleteAllNotifications(int $userId): bool
    {
        return (bool) $this->notificationModel
            ->where("user_id", $userId)
            ->delete();
    }

    /**
     * Retrieve notifications for a user with filtering, sorting and full pagination options.
     *
     * Supported filters:
     *  - 'is_read' => bool|int
     *  - 'type' => string
     *  - 'q' => string (search over title and message)
     *
     * Supported sorting keys: id, created_at, updated_at, is_read, type
     *
     * @param int $userId
     * @param array $filters optional filters: ['is_read'=>0|1|false|true, 'type'=>'MIN_STOCK', 'q'=>'search']
     * @param int $page
     * @param int $perPage
     * @param bool $paginate
     * @param string|null $sortBy
     * @param string|null $sortDir 'ASC'|'DESC'
     *
     * @return array ['data' => array, 'total' => int]
     */
    public function getUserNotifications(
        int $userId,
        array $filters = [],
        int $page = 1,
        int $perPage = 10,
        bool $paginate = true,
        ?string $sortBy = null,
        ?string $sortDir = null,
    ): array {
        $builder = $this->notificationModel->where("user_id", $userId);

        // Filtering
        if (isset($filters["is_read"])) {
            $isRead = $filters["is_read"];
            // Normalize boolean-ish values
            if (is_string($isRead)) {
                $isRead = in_array(
                    strtolower($isRead),
                    ["1", "true", "t", "yes"],
                    true,
                )
                    ? 1
                    : 0;
            }
            $builder->where("is_read", (int) $isRead);
        }

        if (!empty($filters["type"])) {
            $builder->where("type", $filters["type"]);
        }

        if (!empty($filters["q"])) {
            $q = "%" . trim($filters["q"]) . "%";
            // Search title or message
            $builder
                ->groupStart()
                ->like("title", $q)
                ->orLike("message", $q)
                ->groupEnd();
        }

        // Sorting
        $allowedSort = ["id", "created_at", "updated_at", "is_read", "type"];
        $sortBy = $sortBy ?? ($filters["sortBy"] ?? "created_at");
        $sortDir = strtoupper($sortDir ?? ($filters["sortDir"] ?? "DESC"));
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = "created_at";
        }
        if (!in_array($sortDir, ["ASC", "DESC"], true)) {
            $sortDir = "DESC";
        }

        $builder->orderBy($sortBy, $sortDir);
        // Secondary stable sort
        if ($sortBy !== "id") {
            $builder->orderBy("id", "DESC");
        }

        // Count total without applying limit
        $total = $builder->countAllResults(false);

        if ($paginate) {
            $data = $builder->findAll($perPage, max(0, ($page - 1) * $perPage));
        } else {
            $data = $builder->findAll();
        }

        return [
            "data" => $data,
            "total" => $total,
        ];
    }
}
