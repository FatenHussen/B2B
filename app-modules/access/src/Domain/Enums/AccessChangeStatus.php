<?php

declare(strict_types=1);

namespace Modules\Access\Domain\Enums;

enum AccessChangeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
