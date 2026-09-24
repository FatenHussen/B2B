<?php

declare(strict_types=1);

namespace Modules\Promotion\Infrastructure;

use Illuminate\Support\Collection;
use Modules\Core\Contracts\OfferApplicator;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Enums\OfferType;
use Modules\Promotion\Domain\Enums\TargetingScope;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\OfferRetailerRedemption;

final class EloquentOfferApplicator implements OfferApplicator
{
    public function apply(array $quote, array $context): array
    {
        $zoneId = (int) ($context['zone_id'] ?? 0);
        $retailerId = isset($context['retailer_id']) ? (int) $context['retailer_id'] : null;
        $activityTypeId = isset($context['activity_type_id']) ? (int) $context['activity_type_id'] : null;
        /** @var list<int> $groupIds */
        $groupIds = array_map('intval', $context['group_ids'] ?? []);
        $now = now('Asia/Damascus');
        $productIds = array_map(fn (array $l) => (int) $l['product_id'], $quote['lines']);
        $itemCount = array_sum(array_map(fn (array $l) => (int) $l['qty'], $quote['lines']));
        $subtotalBefore = (int) $quote['subtotal'];

        // Lifted, per rule 10: applied for app callers with no tenant. The offers are
        // narrowed to the quoted products' channel via components/rewards below, and those
        // products are already the caller's channels.
        $offers = Offer::withoutGlobalScope('channel')
            ->where('status', OfferStatus::Active)
            ->where(function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) use ($productIds): void {
                // Invoice-wide offers have no components; product offers must touch the cart.
                $q->whereDoesntHave('components')
                    ->orWhereHas('components', fn ($c) => $c->whereIn('product_id', $productIds));
            })
            ->with(['components', 'rewards', 'zones', 'groups', 'retailers', 'activityTypes', 'redemption'])
            ->orderByDesc('priority')
            ->get()
            ->filter(fn (Offer $o) => $this->matchesTargeting($o, $zoneId, $retailerId, $activityTypeId, $groupIds))
            ->filter(fn (Offer $o) => $this->passesConstraints($o, $subtotalBefore, $itemCount, $retailerId))
            ->values();

        $chosen = $this->pickOffers($offers);

        foreach ($chosen as $offer) {
            $quote = $this->applyOffer($quote, $offer);
        }

        $quote['subtotal'] = array_sum(array_map(fn (array $l) => (int) $l['line_total'], $quote['lines']));

        return $quote;
    }

    /**
     * @param  Collection<int, Offer>  $offers
     * @return Collection<int, Offer>
     */
    private function pickOffers(Collection $offers): Collection
    {
        $chosen = $offers->filter(fn (Offer $o) => $o->stackable)->values();
        $topExclusive = $offers->first(fn (Offer $o) => ! $o->stackable);
        if ($topExclusive !== null) {
            $chosen = collect([$topExclusive])->concat($chosen->where('stackable', true));
        }
        if ($chosen->isEmpty() && $offers->isNotEmpty()) {
            $chosen = collect([$offers->first()]);
        }

        return $chosen;
    }

    /**
     * @param  list<int>  $groupIds
     */
    private function matchesTargeting(
        Offer $offer,
        int $zoneId,
        ?int $retailerId,
        ?int $activityTypeId,
        array $groupIds,
    ): bool {
        if ($offer->activityTypes->isNotEmpty()) {
            if ($activityTypeId === null || ! $offer->activityTypes->contains('activity_type_id', $activityTypeId)) {
                return false;
            }
        }

        if ($offer->zones->isNotEmpty() && ! $offer->zones->contains('zone_id', $zoneId)) {
            return false;
        }

        return match ($offer->targeting_scope) {
            TargetingScope::All => true,
            TargetingScope::Zones => $offer->zones->isEmpty() || $offer->zones->contains('zone_id', $zoneId),
            TargetingScope::Groups => $groupIds !== [] && $offer->groups->contains(
                fn ($g) => in_array((int) $g->group_id, $groupIds, true),
            ),
            TargetingScope::Retailers => $retailerId !== null && $offer->retailers->contains('retailer_id', $retailerId),
        };
    }

    private function passesConstraints(Offer $offer, int $subtotal, int $itemCount, ?int $retailerId): bool
    {
        if ((int) $offer->min_invoice_value > 0 && $subtotal < (int) $offer->min_invoice_value) {
            return false;
        }
        if ($offer->min_items !== null && $itemCount < (int) $offer->min_items) {
            return false;
        }

        $redemption = $offer->redemption;
        if ($offer->total_qty !== null && $redemption !== null
            && (int) $redemption->qty_consumed >= (int) $offer->total_qty) {
            return false;
        }

        // per_retailer_max — count applications for this retailer.
        if ($offer->per_retailer_max !== null && $retailerId !== null) {
            $used = (int) OfferRetailerRedemption::query()
                ->where('offer_id', $offer->id)
                ->where('retailer_id', $retailerId)
                ->value('applied_count');
            if ($used >= (int) $offer->per_retailer_max) {
                return false;
            }
        }

        // per_order_max of 0 means the offer cannot apply on any order.
        if ($offer->per_order_max !== null && (int) $offer->per_order_max <= 0) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    private function applyOffer(array $quote, Offer $offer): array
    {
        return match ($offer->type) {
            OfferType::ProductDiscount => $this->applyProductDiscount($quote, $offer),
            OfferType::BuyXGetY => $this->applyBuyXGetY($quote, $offer),
            OfferType::InvoiceDiscount => $this->applyInvoiceDiscount($quote, $offer),
            OfferType::Bundle => $this->applyBundle($quote, $offer),
            OfferType::TieredDiscount => $this->applyTieredDiscount($quote, $offer),
            OfferType::Gift => $this->applyGift($quote, $offer),
        };
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    private function applyProductDiscount(array $quote, Offer $offer): array
    {
        $reward = $offer->rewards->first();

        foreach ($quote['lines'] as $i => $line) {
            if ($this->isGiftLine($line)) {
                continue;
            }
            $component = $offer->components->firstWhere('product_id', (int) $line['product_id']);
            if ($component === null) {
                continue;
            }

            $lineGross = (int) $line['unit_price'] * (int) $line['qty'];
            $discount = $this->rewardDiscount($reward, $lineGross);
            $quote['lines'][$i] = $this->withDiscount($line, $discount, (int) $offer->id);
        }

        return $quote;
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    private function applyBuyXGetY(array $quote, Offer $offer): array
    {
        $buyQty = (int) ($offer->rules['buy_qty'] ?? 0);
        $getQty = (int) ($offer->rules['get_qty'] ?? 0);
        if ($buyQty <= 0) {
            return $quote;
        }

        foreach ($quote['lines'] as $i => $line) {
            if ($this->isGiftLine($line)) {
                continue;
            }
            $component = $offer->components->firstWhere('product_id', (int) $line['product_id']);
            if ($component === null || (int) $line['qty'] < $buyQty) {
                continue;
            }

            $sets = intdiv((int) $line['qty'], $buyQty);
            if ($offer->per_order_max !== null) {
                $sets = min($sets, (int) $offer->per_order_max);
            }
            if ($sets < 1) {
                continue;
            }
            $free = $sets * max(1, $getQty);
            $discount = $free * (int) $line['unit_price'];
            $quote['lines'][$i] = $this->withDiscount($line, $discount, (int) $offer->id);
        }

        return $quote;
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    private function applyInvoiceDiscount(array $quote, Offer $offer): array
    {
        $reward = $offer->rewards->first();
        $gross = array_sum(array_map(
            fn (array $l) => $this->isGiftLine($l) ? 0 : ((int) $l['unit_price'] * (int) $l['qty']),
            $quote['lines'],
        ));
        $totalDiscount = $this->rewardDiscount($reward, $gross);
        if ($totalDiscount <= 0 || $gross <= 0) {
            return $quote;
        }

        $allocated = 0;
        $payableIndexes = [];
        foreach ($quote['lines'] as $i => $line) {
            if (! $this->isGiftLine($line)) {
                $payableIndexes[] = $i;
            }
        }

        foreach ($payableIndexes as $n => $i) {
            $line = $quote['lines'][$i];
            $lineGross = (int) $line['unit_price'] * (int) $line['qty'];
            $share = $n === array_key_last($payableIndexes)
                ? $totalDiscount - $allocated
                : intdiv($totalDiscount * $lineGross, $gross);
            $allocated += $share;
            $quote['lines'][$i] = $this->withDiscount($line, $share, (int) $offer->id);
        }

        return $quote;
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    private function applyBundle(array $quote, Offer $offer): array
    {
        if ($offer->components->isEmpty()) {
            return $quote;
        }

        $sets = PHP_INT_MAX;
        foreach ($offer->components as $component) {
            $need = max(1, (int) $component->qty);
            $have = 0;
            foreach ($quote['lines'] as $line) {
                if ($this->isGiftLine($line)) {
                    continue;
                }
                if ((int) $line['product_id'] === (int) $component->product_id) {
                    $have += (int) $line['qty'];
                }
            }
            $sets = min($sets, intdiv($have, $need));
        }
        if ($sets < 1 || $sets === PHP_INT_MAX) {
            return $quote;
        }

        if ($offer->per_order_max !== null) {
            $sets = min($sets, (int) $offer->per_order_max);
        }
        if ($sets < 1) {
            return $quote;
        }

        $bundlePrice = isset($offer->rules['bundle_price']) ? (int) $offer->rules['bundle_price'] : null;
        $reward = $offer->rewards->first();

        if ($bundlePrice !== null) {
            $componentGross = 0;
            $indexes = [];
            foreach ($quote['lines'] as $i => $line) {
                if ($this->isGiftLine($line)) {
                    continue;
                }
                if ($offer->components->firstWhere('product_id', (int) $line['product_id']) === null) {
                    continue;
                }
                $indexes[] = $i;
                $componentGross += (int) $line['unit_price'] * (int) $line['qty'];
            }
            $target = $bundlePrice * $sets;
            $discountPool = max(0, $componentGross - $target);
            $allocated = 0;
            foreach ($indexes as $n => $i) {
                $line = $quote['lines'][$i];
                $lineGross = (int) $line['unit_price'] * (int) $line['qty'];
                $share = $n === array_key_last($indexes)
                    ? $discountPool - $allocated
                    : ($componentGross > 0 ? intdiv($discountPool * $lineGross, $componentGross) : 0);
                $allocated += $share;
                $quote['lines'][$i] = $this->withDiscount($line, $share, (int) $offer->id);
            }

            return $quote;
        }

        foreach ($quote['lines'] as $i => $line) {
            if ($this->isGiftLine($line)) {
                continue;
            }
            if ($offer->components->firstWhere('product_id', (int) $line['product_id']) === null) {
                continue;
            }
            $lineGross = (int) $line['unit_price'] * (int) $line['qty'];
            $discount = $this->rewardDiscount($reward, $lineGross);
            $quote['lines'][$i] = $this->withDiscount($line, $discount, (int) $offer->id);
        }

        return $quote;
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    private function applyTieredDiscount(array $quote, Offer $offer): array
    {
        $tiers = $offer->rules['tiers'] ?? null;
        if (is_array($tiers) && $tiers !== []) {
            $gross = array_sum(array_map(
                fn (array $l) => $this->isGiftLine($l) ? 0 : ((int) $l['unit_price'] * (int) $l['qty']),
                $quote['lines'],
            ));
            $percent = 0;
            foreach ($tiers as $tier) {
                $from = (int) ($tier['from'] ?? $tier['min'] ?? 0);
                $to = isset($tier['to']) || isset($tier['max'])
                    ? (int) ($tier['to'] ?? $tier['max'])
                    : null;
                if ($gross >= $from && ($to === null || $gross <= $to)) {
                    $percent = (int) ($tier['percent'] ?? $tier['discount_percent'] ?? 0);
                }
            }
            if ($percent <= 0) {
                return $quote;
            }

            foreach ($quote['lines'] as $i => $line) {
                if ($this->isGiftLine($line)) {
                    continue;
                }
                $lineGross = (int) $line['unit_price'] * (int) $line['qty'];
                $discount = intdiv($lineGross * $percent, 100);
                $quote['lines'][$i] = $this->withDiscount($line, $discount, (int) $offer->id);
            }

            return $quote;
        }

        return $this->applyProductDiscount($quote, $offer);
    }

    /**
     * @param  array{lines: list<array<string, mixed>>, subtotal: int, currency: string}  $quote
     * @return array{lines: list<array<string, mixed>>, subtotal: int, currency: string}
     */
    private function applyGift(array $quote, Offer $offer): array
    {
        $buyQty = (int) ($offer->rules['buy_qty'] ?? 0);
        $reward = $offer->rewards->first();
        $getQty = max(1, (int) ($offer->rules['get_qty'] ?? ($reward !== null ? (int) $reward->qty : 1)));
        $giftProductId = $reward !== null && $reward->product_id !== null ? (int) $reward->product_id : null;
        if ($giftProductId === null) {
            return $quote;
        }

        $triggerQty = 0;
        foreach ($quote['lines'] as $i => $line) {
            if ($this->isGiftLine($line)) {
                continue;
            }
            $component = $offer->components->firstWhere('product_id', (int) $line['product_id']);
            if ($component === null) {
                continue;
            }
            $triggerQty += (int) $line['qty'];
            $quote['lines'][$i]['offer_id'] = (int) $offer->id;
        }

        if ($buyQty > 0) {
            $sets = intdiv($triggerQty, $buyQty);
        } else {
            $sets = $triggerQty > 0 ? 1 : 0;
        }
        $giftQty = $sets * $getQty;
        if ($giftQty < 1) {
            return $quote;
        }

        $quote['lines'][] = [
            'product_id' => $giftProductId,
            'variant_id' => null,
            'qty' => $giftQty,
            'unit_price' => 0,
            'applied_rule' => [
                'type' => 'gift',
                'id' => (int) $offer->id,
                'label' => (string) $offer->name,
            ],
            'tier' => null,
            'discount' => 0,
            'line_total' => 0,
            'offer_id' => (int) $offer->id,
            'gift' => true,
        ];

        return $quote;
    }

    private function rewardDiscount(mixed $reward, int $gross): int
    {
        if ($reward === null || $gross <= 0) {
            return 0;
        }
        if ($reward->discount_amount !== null) {
            return min($gross, (int) $reward->discount_amount);
        }
        if ($reward->discount_percent !== null) {
            return intdiv($gross * (int) $reward->discount_percent, 100);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function withDiscount(array $line, int $discount, int $offerId): array
    {
        $discount = max(0, $discount);
        $line['discount'] = ((int) ($line['discount'] ?? 0)) + $discount;
        $line['line_total'] = ((int) $line['unit_price'] * (int) $line['qty']) - (int) $line['discount'];
        $line['offer_id'] = $offerId;

        return $line;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function isGiftLine(array $line): bool
    {
        return ($line['gift'] ?? false) === true
            || (($line['applied_rule']['type'] ?? null) === 'gift');
    }
}
