<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface ProductPricingReader
{
    /**
     * Editor shape matching the catalog `$productBody['pricing']`, or null when unset.
     *
     * @return array{
     *     type: string,
     *     base_price: int,
     *     currency_id: int,
     *     cost_price: int|null,
     *     tiers: list<array{from: int, to: int|null, price: int}>
     * }|null
     */
    public function show(int $productId): ?array;

    /**
     * Unit cost in minor currency units for offer net_margin. Null when unset.
     */
    public function costPrice(int $productId): ?int;
}
