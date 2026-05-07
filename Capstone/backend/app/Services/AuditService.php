<?php

namespace App\Services;

use App\Models\AuditLogModel;

class AuditService
{
    protected AuditLogModel $auditLogModel;

    public function __construct()
    {
        $this->auditLogModel = new AuditLogModel();
    }

    public function log(
        ?int $userId,
        string $actionType,
        string $tableName,
        int $recordId,
        ?string $message = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null
    ): bool {
        $data = [
            'user_id'     => $userId,
            'action_type' => $actionType,
            'table_name'  => $tableName,
            'record_id'   => $recordId,
            'message'     => $message,
            'old_values'  => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values'  => $newValues !== null ? json_encode($newValues) : null,
            'ip_address'  => $ipAddress,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        return $this->auditLogModel->insert($data) !== false;
    }
}
