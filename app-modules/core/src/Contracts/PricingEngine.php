<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface PricingEngine
{
    /**
     * @param  array{
     *     lines: list<array{product_id: int, variant_id?: int|null, qty: int}>,
     *     zone_id: int,
     *     retailer_id?: int|null,
     *     channel_id?: int|null
     * }  $request
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    public function quote(array $request): array;

    /**
     * Unit price for qty (default 1) after base → tier → lists. Offers applied if OfferApplicator is bound.
     *
     * @return array{
     *     unit_price: int,
     *     type: string,
     *     label: string,
     *     from: int|null,
     *     to: int|null,
     *     applied_rule: array{type: string, id: int|null, label: string}
     * }
     */
    public function quoteLine(int $productId, int $qty, int $zoneId, ?int $retailerId = null, ?int $channelId = null): array;
}
