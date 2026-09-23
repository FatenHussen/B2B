<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Offer consumption ledger — Promotion owns redemptions and views; Ordering records
 * applications on submit, Returns reverses on a full offer-line return (DOC §4.4.3).
 */
interface OfferConsumption
{
    /**
     * Record one application for this retailer (increments global + per-retailer counters).
     * Marks the offer exhausted when total_qty is fully consumed.
     */
    public function recordApplied(int $offerId, int $retailerId, int $qty = 1): void;

    /**
     * Reverse one application after a full return of offer-bearing lines (DOC §4.4.3).
     */
    public function reverseApplied(int $offerId, int $retailerId, int $qty = 1): void;

    /**
     * Unique retailer view for conversion_rate (scale 10^4). Idempotent per retailer.
     */
    public function recordView(int $offerId, int $retailerId): void;

    public function uniqueViewers(int $offerId): int;
}
