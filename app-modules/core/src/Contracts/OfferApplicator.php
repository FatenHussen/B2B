<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface OfferApplicator
{
    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @param  array{zone_id: int, retailer_id?: int|null, channel_id?: int|null}  $context
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    public function apply(array $quote, array $context): array;
}
