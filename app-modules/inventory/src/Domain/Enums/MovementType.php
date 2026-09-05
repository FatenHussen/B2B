<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Enums;

enum MovementType: string
{
    case Adjust = 'adjust';
    case Reserve = 'reserve';
    case Release = 'release';
    case PickDeduct = 'pick_deduct';
    case Receive = 'receive';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case ReturnIn = 'return_in';
    case Stocktake = 'stocktake';
}
