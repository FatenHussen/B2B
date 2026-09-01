<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Enums;

enum TempGrantStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
    case Revoked = 'revoked';
}
