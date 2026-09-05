<?php

declare(strict_types=1);

namespace Modules\Ordering\Domain\Enums;

enum SubOrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Processing = 'processing';
    case AwaitingHandover = 'awaiting_handover';
    case OnTheWay = 'on_the_way';
    case Delivered = 'delivered';
    case Undelivered = 'undelivered';
    case Postponed = 'postponed';
}
