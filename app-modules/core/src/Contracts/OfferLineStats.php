<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface OfferLineStats
{
    /**
     * Sales figures taken from order lines that carry this offer.
     * net_margin is null when any linked line lacks cost_price — never a fabricated 0.
     *
     * @return array{
     *     linked_sales: int,
     *     discount_given: int,
     *     net_margin: int|null,
     *     retailers_count: int,
     *     by_zone: list<array{zone_id: int, applied_count: int}>,
     *     line_count: int
     * }
     */
    public function forOffer(int $offerId, int $channelId): array;
}
