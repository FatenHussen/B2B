<?php

declare(strict_types=1);

namespace Modules\Loyalty\Domain\Enums;

enum LoyaltyRewardStatus: string
{
    case Active = 'active';
    case Stopped = 'stopped';
}
