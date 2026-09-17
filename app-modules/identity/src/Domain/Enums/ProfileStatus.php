<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Enums;

enum ProfileStatus: string
{
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Rejected = 'rejected';
    case Disabled = 'disabled';
}
