<?php

namespace App\Enums;

enum AuditActionType: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Approval = 'approval';
    case Rejection = 'rejection';
    case Submit = 'submit';
    case Post = 'post';
    case Override = 'override';
    case Activate = 'activate';
    case Deactivate = 'deactivate';
    case PasswordChange = 'password_change';
    case Restore = 'restore';
    case Login = 'login';
    case Logout = 'logout';
}
