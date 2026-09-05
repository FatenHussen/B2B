<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Infrastructure;

use Modules\Core\Contracts\HandoverGuard;
use Modules\Fulfillment\Domain\Enums\HandoverStatus;
use Modules\Fulfillment\Domain\Models\HandoverItem;

final class EloquentHandoverGuard implements HandoverGuard
{
    public function isConfirmedForSubOrder(int $subOrderId): bool
    {
        return HandoverItem::query()
            ->where('sub_order_id', $subOrderId)
            ->whereHas('handover', fn ($q) => $q->where('status', HandoverStatus::Confirmed))
            ->exists();
    }
}
