<?php

declare(strict_types=1);

namespace Modules\Ordering\Infrastructure;

use Modules\Core\Contracts\OpenOrderCounter;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;

final class EloquentOpenOrderCounter implements OpenOrderCounter
{
    /**
     * The three terminal states from DOC-01 §4.6.3. Everything else is open.
     *
     * `SubOrderStatus` has twelve cases, so the positive list would be nine — and would
     * need editing every time the lifecycle gains a state, silently under-reporting until
     * someone noticed. Listing the three that end a sub-order means a new case counts as
     * open by default, and only a deliberate edit here can make it terminal.
     *
     * @var list<SubOrderStatus>
     */
    private const TERMINAL = [
        SubOrderStatus::Delivered,
        SubOrderStatus::Cancelled,
        SubOrderStatus::Rejected,
    ];

    /**
     * Deliberately unscoped by channel, and `SubOrder` carries no `ChannelScope` to
     * remove. A zone is platform-owned reference data and EP-AD-034 is a platform
     * endpoint: the admin disabling a zone needs every open order in it, across every
     * channel. A per-tenant count here would understate the impact of an irreversible
     * decision, which is the one thing this number exists to prevent.
     */
    public function countOpenInZone(int $zoneId): int
    {
        return SubOrder::query()
            ->where('zone_id', $zoneId)
            ->whereNotIn('status', array_map(fn (SubOrderStatus $s) => $s->value, self::TERMINAL))
            ->count();
    }
}
