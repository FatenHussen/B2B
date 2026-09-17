<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Enums;

enum RepSourcedShopStatus: string
{
    case PendingSync = 'pending_sync';
    case Linked = 'linked';
    case Rejected = 'rejected';
}
