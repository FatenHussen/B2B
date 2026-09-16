<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Enums;

enum DualApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
}
