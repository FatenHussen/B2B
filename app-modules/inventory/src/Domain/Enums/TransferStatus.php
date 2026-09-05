<?php

declare(strict_types=1);

namespace Modules\Inventory\Domain\Enums;

enum TransferStatus: string
{
    case Sent = 'sent';
    case Received = 'received';
}
