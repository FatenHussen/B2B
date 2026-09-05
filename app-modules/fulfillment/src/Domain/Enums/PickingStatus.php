<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Domain\Enums;

enum PickingStatus: string
{
    case ToPick = 'to_pick';
    case Picking = 'picking';
    case ToPack = 'to_pack';
    case Packed = 'packed';
    case Cancelled = 'cancelled';
}
