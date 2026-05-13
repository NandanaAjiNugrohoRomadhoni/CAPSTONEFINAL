<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Services\NotificationService;
use CodeIgniter\HTTP\ResponseInterface;

class Notifications extends BaseController
{
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    public function index(): ResponseInterface
    {
        $userId = auth()->id();

        $queryParams = $this->request->getGet();
        $page = max(1, (int) ($queryParams["page"] ?? 1));
        $perPage = max(1, min(100, (int) ($queryParams["perPage"] ?? 10)));
        $paginate = !in_array(
            strtolower((string) ($queryParams["paginate"] ?? "")),
            ["false", "0"],
            true,
        );

        // Build filters (supports is_read, type, q) and sorting
        $filters = [];
        if (isset($queryParams["is_read"])) {
            $filters["is_read"] = $queryParams["is_read"];
        }
        if (!empty($queryParams["type"])) {
            $filters["type"] = $queryParams["type"];
        }
        if (!empty($queryParams["q"])) {
            $filters["q"] = $queryParams["q"];
        }

        $sortBy = $queryParams["sortBy"] ?? null;
        $sortDir = $queryParams["sortDir"] ?? null;

        $result = $this->notificationService->getUserNotifications(
            $userId,
            $filters,
            $page,
            $perPage,
            $paginate,
            $sortBy,
            $sortDir,
        );

        $data = $result["data"];
        $total = $result["total"];

        if ($paginate) {
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        } else {
            $page = 1;
            $perPage = max(1, count($data));
            $totalPages = $total > 0 ? 1 : 0;
        }

        $meta = [
            "page" => $page,
            "perPage" => $perPage,
            "total" => $total,
            "totalPages" => $totalPages,
            "paginated" => $paginate,
        ];

        return $this->response->setStatusCode(200)->setJSON([
            "data" => $data,
            "meta" => $meta,
            "links" => $this->buildPaginationLinks($meta),
        ]);
    }

    private function buildPaginationLinks(array $meta): array
    {
        $queryParams = $this->request->getGet();
        $path = current_url();

        $buildLink = function (int $page) use (
            $path,
            $queryParams,
            $meta,
        ): string {
            return $path .
                "?" .
                http_build_query([
                    ...$queryParams,
                    "page" => $page,
                    "perPage" => $meta["perPage"],
                ]);
        };

        return [
            "self" => $buildLink($meta["page"]),
            "first" => $buildLink(1),
            "last" => $buildLink(max(1, $meta["totalPages"])),
            "next" =>
                $meta["page"] < $meta["totalPages"]
                    ? $buildLink($meta["page"] + 1)
                    : null,
            "previous" =>
                $meta["page"] > 1 ? $buildLink($meta["page"] - 1) : null,
        ];
    }

    public function markAsRead(int $id): ResponseInterface
    {
        if ($id <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                "message" => "ID must be a positive integer.",
                "errors" => [],
            ]);
        }

        $userId = auth()->id();

        $success = $this->notificationService->markAsRead($id, $userId);

        if (!$success) {
            return $this->response->setStatusCode(404)->setJSON([
                "message" => "Notification not found or access denied.",
                "errors" => [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            "message" => "Notification marked as read.",
        ]);
    }

    public function markAllAsRead(): ResponseInterface
    {
        $userId = auth()->id();

        $success = $this->notificationService->markAllAsRead($userId);

        if (!$success) {
            return $this->response->setStatusCode(500)->setJSON([
                "message" => "Failed to mark notifications as read.",
                "errors" => [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            "message" => "All notifications marked as read.",
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        if ($id <= 0) {
            return $this->response->setStatusCode(400)->setJSON([
                "message" => "ID must be a positive integer.",
                "errors" => [],
            ]);
        }

        $userId = auth()->id();

        $success = $this->notificationService->deleteNotification($id, $userId);

        if (!$success) {
            return $this->response->setStatusCode(404)->setJSON([
                "message" => "Notification not found or access denied.",
                "errors" => [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            "message" => "Notification deleted.",
        ]);
    }

    public function deleteAll(): ResponseInterface
    {
        $userId = auth()->id();

        $success = $this->notificationService->deleteAllNotifications($userId);

        if (!$success) {
            return $this->response->setStatusCode(500)->setJSON([
                "message" => "Failed to delete notifications.",
                "errors" => [],
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            "message" => "All notifications deleted.",
        ]);
    }
}
