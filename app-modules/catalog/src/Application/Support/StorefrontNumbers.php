<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Support;

/**
 * Stable stand-in numbers so a retailer screen matches the design before a channel
 * fills `products.storefront`. The channel dashboard overrides any key it sets.
 */
final class StorefrontNumbers
{
    public static function rating(int $id): float
    {
        return round(4.2 + ($id % 8) / 10, 1);
    }

    public static function ratingCount(int $id): int
    {
        return 180 + ($id % 2400);
    }

    public static function soldCount(int $id): int
    {
        return 35 + ($id % 500);
    }
}
