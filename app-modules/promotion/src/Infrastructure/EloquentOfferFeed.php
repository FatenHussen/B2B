<?php

declare(strict_types=1);

namespace Modules\Promotion\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\OfferFeed;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Support\MediaUrl;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Models\Offer;

final class EloquentOfferFeed implements OfferFeed
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly CatalogProductLookup $products,
    ) {}

    public function sliderFor(int $zoneId, int $activityTypeId, array $channelIds): array
    {
        return $this->matching($zoneId, $activityTypeId, $channelIds)
            ->limit(10)
            ->get()
            ->map(fn (Offer $offer) => $this->card($offer, $zoneId))
            ->all();
    }

    public function productHasOffer(int $productId, int $zoneId, int $activityTypeId): bool
    {
        $channelId = $this->products->channelId($productId);
        if ($channelId === null) {
            return false;
        }

        return $this->matching($zoneId, $activityTypeId, [$channelId])
            ->whereHas('components', fn ($q) => $q->where('product_id', $productId))
            ->exists();
    }

    /**
     * @param  list<int>  $channelIds
     */
    public function matching(int $zoneId, int $activityTypeId, array $channelIds): Builder
    {
        $now = now('Asia/Damascus');

        return Offer::withoutGlobalScope('channel')
            ->whereIn('supply_channel_id', $channelIds === [] ? [0] : $channelIds)
            ->where('status', OfferStatus::Active)
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) use ($activityTypeId): void {
                $q->whereDoesntHave('activityTypes')
                    ->orWhereHas('activityTypes', fn ($a) => $a->where('activity_type_id', $activityTypeId));
            })
            ->where(function ($q) use ($zoneId): void {
                $q->whereDoesntHave('zones')
                    ->orWhereHas('zones', fn ($z) => $z->where('zone_id', $zoneId));
            })
            ->orderByDesc('priority');
    }

    /**
     * @return array<string, mixed>
     */
    public function card(Offer $offer, int $zoneId): array
    {
        $offer->loadMissing(['components', 'rewards', 'media', 'redemption']);
        $before = 0;
        foreach ($offer->components as $component) {
            $quoted = $this->pricing->quoteLine(
                (int) $component->product_id,
                (int) $component->qty,
                $zoneId,
                null,
                (int) $offer->supply_channel_id,
            );
            $before += $quoted['unit_price'] * (int) $component->qty;
        }

        $discount = 0;
        foreach ($offer->rewards as $reward) {
            if ($reward->discount_amount) {
                $discount += (int) $reward->discount_amount;
            } elseif ($reward->product_id) {
                $quoted = $this->pricing->quoteLine(
                    (int) $reward->product_id,
                    (int) $reward->qty,
                    $zoneId,
                    null,
                    (int) $offer->supply_channel_id,
                );
                $discount += $quoted['unit_price'] * (int) $reward->qty;
            } elseif ($reward->discount_percent) {
                $discount += intdiv($before * (int) $reward->discount_percent, 100);
            }
        }

        $consumed = (int) ($offer->redemption?->qty_consumed ?? 0);
        $ends = $offer->ends_at?->timezone('Asia/Damascus');
        $daysLeft = $ends ? max(0, (int) now('Asia/Damascus')->startOfDay()->diffInDays($ends->startOfDay(), false)) : 0;

        return [
            'id' => (int) $offer->id,
            'image' => MediaUrl::of($offer->media->first()?->media_id),
            'name' => $offer->name,
            'company' => null,
            'rating' => 0,
            'components' => $offer->components->map(fn ($c) => [
                'product_id' => (int) $c->product_id,
                'qty' => (int) $c->qty,
            ])->all(),
            'price_before' => $before,
            'discount' => $discount,
            'price_after' => max(0, $before - $discount),
            'ends_at' => $ends?->toIso8601String(),
            'days_left' => $daysLeft,
            'remaining_qty' => $offer->total_qty !== null ? max(0, (int) $offer->total_qty - $consumed) : null,
            'sold_count' => 0,
        ];
    }
}
