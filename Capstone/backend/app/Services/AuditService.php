<?php

namespace App\Services;

use App\Enums\AuditActionType;

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
        AuditActionType $actionType,
        string $tableName,
        int $recordId,
        ?string $message = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null
    ): bool {
        $data = [
            'user_id'     => $userId,
            'action_type' => $actionType->value,
            'table_name'  => $tableName,
            'record_id'   => $recordId,
            'message'     => $message,
            'old_values'  => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values'  => $newValues !== null ? json_encode($newValues) : null,
            'ip_address'  => $ipAddress,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        $recentDuplicate = $this->auditLogModel
            ->where('user_id', $data['user_id'])
            ->where('action_type', $data['action_type'])
            ->where('table_name', $data['table_name'])
            ->where('record_id', $data['record_id'])
            ->where('message', $data['message'])
            ->where('old_values', $data['old_values'])
            ->where('new_values', $data['new_values'])
            ->where('ip_address', $data['ip_address'])
            ->where('created_at >=', date('Y-m-d H:i:s', time() - 2))
            ->first();

        if ($recentDuplicate !== null) {
            return true;
        }

        return $this->auditLogModel->insert($data) !== false;
    }
}
