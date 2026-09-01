<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure;

use Modules\Core\Contracts\OfferFeed;

final class EmptyOfferFeed implements OfferFeed
{
    public function sliderFor(int $zoneId, int $activityTypeId, array $channelIds): array
    {
        return [];
    }

    public function productHasOffer(int $productId, int $zoneId, int $activityTypeId): bool
    {
        return false;
    }
}
