<?php

declare(strict_types=1);

namespace Modules\Promotion\Infrastructure;

use Modules\Core\Contracts\OfferApplicator;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Enums\OfferType;
use Modules\Promotion\Domain\Models\Offer;

final class EloquentOfferApplicator implements OfferApplicator
{
    public function apply(array $quote, array $context): array
    {
        $zoneId = (int) ($context['zone_id'] ?? 0);
        $now = now('Asia/Damascus');
        $productIds = array_map(fn (array $l) => (int) $l['product_id'], $quote['lines']);

        $offers = Offer::withoutGlobalScope('channel')
            ->where('status', OfferStatus::Active)
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) use ($zoneId): void {
                $q->whereDoesntHave('zones')->orWhereHas('zones', fn ($z) => $z->where('zone_id', $zoneId));
            })
            ->whereHas('components', fn ($c) => $c->whereIn('product_id', $productIds))
            ->with(['components', 'rewards'])
            ->orderByDesc('priority')
            ->get();

        $chosen = $offers->filter(fn (Offer $o) => $o->stackable)->values();
        $topExclusive = $offers->first(fn (Offer $o) => ! $o->stackable);
        if ($topExclusive !== null) {
            $chosen = collect([$topExclusive])->concat($chosen->where('stackable', true));
        }
        if ($chosen->isEmpty() && $offers->isNotEmpty()) {
            $chosen = collect([$offers->first()]);
        }

        foreach ($chosen as $offer) {
            $quote = $this->applyOffer($quote, $offer);
        }

        return $quote;
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    private function applyOffer(array $quote, Offer $offer): array
    {
        $buyQty = (int) ($offer->rules['buy_qty'] ?? 0);
        $getQty = (int) ($offer->rules['get_qty'] ?? 0);

        foreach ($quote['lines'] as $i => $line) {
            $productId = (int) $line['product_id'];
            $component = $offer->components->firstWhere('product_id', $productId);
            if ($component === null) {
                continue;
            }

            if ($offer->type === OfferType::BuyXGetY && $buyQty > 0 && (int) $line['qty'] >= $buyQty) {
                $sets = intdiv((int) $line['qty'], $buyQty);
                $free = $sets * max(1, $getQty);
                $discount = $free * (int) $line['unit_price'];
                $quote['lines'][$i]['discount'] = ($line['discount'] ?? 0) + $discount;
                $quote['lines'][$i]['line_total'] = ((int) $line['unit_price'] * (int) $line['qty']) - $quote['lines'][$i]['discount'];
            }

            if ($offer->type === OfferType::ProductDiscount) {
                $reward = $offer->rewards->first();
                $discount = 0;
                if ($reward?->discount_amount) {
                    $discount = (int) $reward->discount_amount;
                } elseif ($reward?->discount_percent) {
                    $discount = intdiv(((int) $line['unit_price'] * (int) $line['qty']) * (int) $reward->discount_percent, 100);
                }
                $quote['lines'][$i]['discount'] = ($line['discount'] ?? 0) + $discount;
                $quote['lines'][$i]['line_total'] = ((int) $line['unit_price'] * (int) $line['qty']) - $quote['lines'][$i]['discount'];
            }
        }

        $quote['subtotal'] = array_sum(array_map(fn (array $l) => (int) $l['line_total'], $quote['lines']));

        return $quote;
    }
}
