<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table         = 'audit_logs';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'user_id',
        'action_type',
        'table_name',
        'record_id',
        'message',
        'old_values',
        'new_values',
        'ip_address',
        'created_at',
    ];
    protected $useTimestamps = false;
    protected $returnType    = 'array';
}
