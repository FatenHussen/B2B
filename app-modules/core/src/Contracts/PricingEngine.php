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
     *     channel_id?: int|null,
     *     activity_type_id?: int|null,
     *     group_ids?: list<int>
     * }  $request
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    public function quote(array $request): array;

    /**
     * Unit price for qty (default 1) after base → tier → lists. Offers applied if OfferApplicator is bound.
     * When `$variantId` is set and the variant has a price override, that value replaces the product
     * base before qty tiers and price lists (lists remain product-scoped).
     *
     * @param  list<int>  $groupIds
     * @return array{
     *     unit_price: int,
     *     type: string,
     *     label: string,
     *     from: int|null,
     *     to: int|null,
     *     applied_rule: array{type: string, id: int|null, label: string}
     * }
     */
    public function quoteLine(
        int $productId,
        int $qty,
        int $zoneId,
        ?int $retailerId = null,
        ?int $channelId = null,
        array $groupIds = [],
        ?int $variantId = null,
    ): array;
}
