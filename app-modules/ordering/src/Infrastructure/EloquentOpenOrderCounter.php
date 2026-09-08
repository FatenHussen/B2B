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
     * Deliberately unscoped by channel.
     *
     * A zone is platform-owned reference data and EP-AD-034 is a platform endpoint: the
     * admin disabling a zone needs every open order in it, across every channel. A
     * per-tenant count would understate the impact of an irreversible decision, which is
     * the one thing this number exists to prevent — and the caller has no tenant at all,
     * so the scope would not narrow the count but throw.
     *
     * `acrossChannels()` is the explicit opt-out, added when `SubOrder` gained
     * `BelongsToChannel`. Before that the model was simply unprotected and this method
     * happened to work; the escape hatch is now visible at the one call site that needs
     * it, which is the point of making the default safe.
     */
    public function countOpenInZone(int $zoneId): int
    {
        return SubOrder::query()
            ->acrossChannels()
            ->where('zone_id', $zoneId)
            ->whereNotIn('status', array_map(fn (SubOrderStatus $s) => $s->value, self::TERMINAL))
            ->count();
    }
}
