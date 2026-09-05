<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Enums;

enum HandoverStatus: string
{
    case AwaitingRepConfirm = 'awaiting_rep_confirm';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
