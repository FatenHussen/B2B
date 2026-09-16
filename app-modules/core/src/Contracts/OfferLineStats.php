<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface OfferLineStats
{
    /**
     * Sales figures taken from order lines that carry this offer.
     * conversion_rate is a dimensionless integer at scale 10^4 (1.00 = 10000).
     *
     * @return array{
     *     linked_sales: int,
     *     discount_given: int,
     *     retailers_count: int,
     *     by_zone: list<array{zone_id: int, applied_count: int}>,
     *     line_count: int
     * }
     */
    public function forOffer(int $offerId, int $channelId): array;
}
