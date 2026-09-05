<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Enums;

enum AssignmentStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
