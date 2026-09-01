<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum PriceListStatus: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case Disabled = 'disabled';
}
