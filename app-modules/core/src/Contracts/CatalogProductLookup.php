<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface CatalogProductLookup
{
    public function exists(int $productId): bool;

    public function isActive(int $productId): bool;

    public function channelId(int $productId): ?int;

    public function name(int $productId): ?string;

    public function saleUnitName(int $productId): ?string;

    /**
     * Visible to a retailer shopping snapshot (zone / activity / covering channels).
     *
     * @param  array{zone_id: int, activity_type_id: int, channel_ids: list<int>, category_ids?: list<int>}  $shopping
     */
    public function isVisibleToRetailer(int $productId, array $shopping): bool;
}
