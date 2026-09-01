<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Enums;

enum AccessChangeType: string
{
    case RoleCreate = 'role_create';
    case RolePermissions = 'role_permissions';
    case TempGrant = 'temp_grant';
    case ChannelDelete = 'channel_delete';
}
