<?php

declare(strict_types=1);

namespace Modules\Promotion\Domain\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Stopped = 'stopped';
    case Expired = 'expired';
}
