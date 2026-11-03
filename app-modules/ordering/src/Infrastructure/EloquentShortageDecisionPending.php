<?php

declare(strict_types=1);

namespace Modules\Ordering\Infrastructure;

use Modules\Core\Contracts\ShortageDecisionPending;
use Modules\Ordering\Domain\Models\ShortageAllocationLine;

final class EloquentShortageDecisionPending implements ShortageDecisionPending
{
    public function blocksSubOrder(int $subOrderId): bool
    {
        return ShortageAllocationLine::query()
            ->where('sub_order_id', $subOrderId)
            ->where('status', 'awaiting_retailer')
            ->exists();
    }
}
