<?php

declare(strict_types=1);

namespace Modules\Ordering\Infrastructure;

use Modules\Core\Contracts\OfferLineStats;
use Modules\Core\Contracts\ProductPricingReader;
use Modules\Core\Support\Tenant;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderLine;

final class EloquentOfferLineStats implements OfferLineStats
{
    public function __construct(private readonly ProductPricingReader $pricing) {}

    public function forOffer(int $offerId, int $channelId): array
    {
        return Tenant::as($channelId, function () use ($offerId): array {
            $lines = SubOrderLine::query()
                ->where('offer_id', $offerId)
                ->with('subOrder')
                ->get();

            $linkedSales = 0;
            $discountGiven = 0;
            $cogs = 0;
            $costUnknown = false;
            $retailers = [];
            $zones = [];

            foreach ($lines as $line) {
                $linkedSales += (int) $line->line_total;
                $discountGiven += (int) $line->discount;
                $cost = $this->pricing->costPrice((int) $line->product_id);
                if ($cost === null) {
                    // EP-SC-042: never invent margin when cost_price is missing.
                    $costUnknown = true;
                } else {
                    $cogs += $cost * (int) $line->qty;
                }
                $sub = $line->subOrder;
                if (! $sub instanceof SubOrder) {
                    continue;
                }
                $retailers[(int) $sub->retailer_id] = true;
                $zoneId = $sub->zone_id !== null ? (int) $sub->zone_id : 0;
                $zones[$zoneId] = ($zones[$zoneId] ?? 0) + 1;
            }

            $byZone = [];
            foreach ($zones as $zoneId => $count) {
                if ($zoneId === 0) {
                    continue;
                }
                $byZone[] = ['zone_id' => (int) $zoneId, 'applied_count' => (int) $count];
            }

            return [
                'linked_sales' => $linkedSales,
                'discount_given' => $discountGiven,
                'net_margin' => $costUnknown ? null : ($linkedSales - $cogs),
                'retailers_count' => count($retailers),
                'by_zone' => $byZone,
                'line_count' => $lines->count(),
            ];
        });
    }
}
