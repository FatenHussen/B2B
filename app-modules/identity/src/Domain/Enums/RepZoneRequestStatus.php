<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Enums;

enum RepZoneRequestStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
