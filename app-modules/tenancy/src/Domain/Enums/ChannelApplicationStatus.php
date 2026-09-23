<?php

declare(strict_types=1);

namespace Modules\Tenancy\Domain\Enums;

enum ChannelApplicationStatus: string
{
    case UnderReview = 'under_review';
    case Provisioning = 'provisioning';
    case Rejected = 'rejected';
}
