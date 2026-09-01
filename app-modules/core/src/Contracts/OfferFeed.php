<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface OfferFeed
{
    /**
     * Home / brand sliders. Empty until active targeted offers exist.
     *
     * @return list<array<string, mixed>>
     */
    public function sliderFor(int $zoneId, int $activityTypeId, array $channelIds): array;

    public function productHasOffer(int $productId, int $zoneId, int $activityTypeId): bool;
}
