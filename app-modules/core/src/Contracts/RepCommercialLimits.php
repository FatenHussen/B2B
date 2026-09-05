<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * The commercial ceiling a channel sets on one of its reps.
 *
 * Pricing owns `rep_commercial_limits`. Ordering enforces the discount cap when a rep
 * submits a cart section, which is a read of a Pricing table — so it asks here. Kept to
 * the one question Ordering actually asks, in the shape of {@see ChannelLimits}.
 */
interface RepCommercialLimits
{
    /**
     * Largest discount this rep may grant on this channel, as a whole percent.
     * Zero when no limit row exists, which denies any discount.
     */
    public function maxDiscountPercent(int $channelId, int $repId): int;
}
