<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Support;

use Modules\Core\Contracts\CatalogProductLookup;
use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Ordering\Domain\Enums\CartLineSource;
use Modules\Ordering\Domain\Enums\CartStatus;
use Modules\Ordering\Domain\Models\Cart;
use Modules\Ordering\Domain\Models\CartLine;

final class CartAssembler
{
    public function __construct(
        private readonly PricingEngine $pricing,
        private readonly CatalogProductLookup $products,
        private readonly RetailerDirectory $retailers,
        private readonly ChannelDirectory $channels,
    ) {}

    public function activeFor(object $owner): Cart
    {
        $cart = Cart::query()
            ->where('owner_type', $owner::class)
            ->where('owner_id', $owner->getAuthIdentifier())
            ->where('status', CartStatus::Active)
            ->first();

        if ($cart !== null) {
            return $cart;
        }

        return Cart::query()->create([
            'owner_type' => $owner::class,
            'owner_id' => $owner->getAuthIdentifier(),
            'status' => CartStatus::Active,
        ]);
    }

    /**
     * @return list<string>
     */
    public function reprice(Cart $cart, int $zoneId, ?int $retailerId, ?int $channelId = null): array
    {
        $beforeOffers = $this->offerIds($cart);
        $cart->load('sections.lines');
        $activityTypeId = $retailerId !== null ? $this->retailers->activityTypeId($retailerId) : null;
        $groupIds = $retailerId !== null ? $this->retailers->groupIds($retailerId) : [];

        foreach ($cart->sections as $section) {
            $paidLines = $section->lines
                ->filter(fn (CartLine $line) => $line->source !== CartLineSource::Offer)
                ->values();

            $lines = [];
            foreach ($paidLines as $line) {
                $lines[] = [
                    'product_id' => (int) $line->product_id,
                    'variant_id' => $line->variant_id ? (int) $line->variant_id : null,
                    'qty' => (int) $line->qty,
                ];
            }
            if ($lines === []) {
                continue;
            }

            $quote = $this->pricing->quote([
                'lines' => $lines,
                'zone_id' => $zoneId,
                'retailer_id' => $retailerId,
                'channel_id' => $channelId ?? (int) $section->channel_id,
                'activity_type_id' => $activityTypeId,
                'group_ids' => $groupIds,
            ]);

            foreach ($paidLines as $i => $line) {
                $quoted = $quote['lines'][$i] ?? null;
                if ($quoted === null || (($quoted['gift'] ?? false) === true)) {
                    continue;
                }
                $line->forceFill([
                    'unit_price' => (int) $quoted['unit_price'],
                    'discount' => (int) ($quoted['discount'] ?? 0),
                    'line_total' => (int) $quoted['line_total'],
                    'applied_rule' => $quoted['applied_rule'] ?? null,
                    'offer_id' => $quoted['offer_id'] ?? null,
                ])->save();
            }

            // Drop previous gift rows then recreate from quote extras (source = offer).
            foreach ($section->lines->filter(fn (CartLine $l) => $l->source === CartLineSource::Offer) as $gift) {
                $gift->delete();
            }
            foreach (array_slice($quote['lines'], $paidLines->count()) as $giftQuote) {
                if (($giftQuote['gift'] ?? false) !== true) {
                    continue;
                }
                CartLine::query()->create([
                    'section_id' => $section->id,
                    'product_id' => (int) $giftQuote['product_id'],
                    'variant_id' => $giftQuote['variant_id'] ?? null,
                    'qty' => (int) $giftQuote['qty'],
                    'source' => CartLineSource::Offer,
                    'unit_price' => 0,
                    'discount' => 0,
                    'line_total' => 0,
                    'applied_rule' => $giftQuote['applied_rule'] ?? null,
                    'offer_id' => $giftQuote['offer_id'] ?? null,
                ]);
            }
        }

        $afterOffers = $this->offerIds($cart->fresh(['sections.lines']));

        return array_values(array_diff($beforeOffers, $afterOffers));
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRetailer(Cart $cart): array
    {
        $cart->load('sections.lines');
        $sections = [];
        $grand = 0;
        $discount = 0;
        $offers = [];

        foreach ($cart->sections as $section) {
            $lines = [];
            $subtotal = 0;
            $secDiscount = 0;
            foreach ($section->lines as $line) {
                $snap = $this->products->snapshot((int) $line->product_id, $line->variant_id ? (int) $line->variant_id : null);
                $lines[] = [
                    'id' => (int) $line->id,
                    'product_id' => (int) $line->product_id,
                    'name' => $snap['name'] ?? '',
                    'qty' => (int) $line->qty,
                    'unit_price' => (int) $line->unit_price,
                    'line_total' => (int) $line->line_total,
                ];
                $subtotal += (int) $line->line_total;
                $secDiscount += (int) $line->discount;
                if ($line->offer_id) {
                    $offers[(int) $line->offer_id] = (int) $line->offer_id;
                }
            }
            $grand += $subtotal;
            $discount += $secDiscount;
            $sections[] = [
                'supply_channel_ref' => $section->opaque_ref,
                'temp_order_no' => 'T-'.$cart->id.'-'.$section->id,
                'note' => $section->note,
                'lines' => $lines,
                'subtotal' => $subtotal + $secDiscount,
                'discount' => $secDiscount,
                'total' => $subtotal,
            ];
        }

        return [
            'sections' => $sections,
            'summary' => [
                'grand_total' => $grand + $discount,
                'total_discount' => $discount,
                'final_total' => $grand,
                'estimated_delivery' => null,
            ],
            'applied_offers' => array_values($offers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentRep(Cart $cart): array
    {
        $cart->load('sections.lines');
        $byRetailer = [];
        foreach ($cart->sections as $section) {
            $rid = (int) ($section->retailer_id ?? 0);
            if (! isset($byRetailer[$rid])) {
                $byRetailer[$rid] = [
                    'retailer_id' => $rid,
                    'channel_id' => (int) $section->channel_id,
                    'created_at' => $section->created_at?->timezone('Asia/Damascus')->toIso8601String(),
                    'lines' => [],
                    'total' => 0,
                    'discount' => 0,
                ];
            }
            foreach ($section->lines as $line) {
                $snap = $this->products->snapshot((int) $line->product_id, $line->variant_id ? (int) $line->variant_id : null);
                $byRetailer[$rid]['lines'][] = [
                    'id' => (int) $line->id,
                    'product_id' => (int) $line->product_id,
                    'name' => $snap['name'] ?? '',
                    'qty' => (int) $line->qty,
                    'unit_price' => (int) $line->unit_price,
                    'line_total' => (int) $line->line_total,
                ];
                $byRetailer[$rid]['total'] += (int) $line->line_total;
                $byRetailer[$rid]['discount'] += (int) $line->discount;
            }
        }

        $sections = [];
        foreach ($byRetailer as $row) {
            $shop = $this->retailers->find((int) $row['retailer_id']);
            $channelId = (int) $row['channel_id'];
            $sections[] = [
                'retailer' => [
                    'id' => $row['retailer_id'],
                    'shop_name' => $shop['shop_name'] ?? '',
                    'zone_id' => $this->retailers->zoneId((int) $row['retailer_id']),
                ],
                'channel' => [
                    'id' => $channelId,
                    'name' => $this->channels->name($channelId),
                ],
                'created_at' => $row['created_at'],
                'lines' => $row['lines'],
                'total' => $row['total'],
                'discount' => $row['discount'],
            ];
        }

        return ['sections' => $sections];
    }

    /**
     * @return list<int>
     */
    private function offerIds(Cart $cart): array
    {
        $ids = [];
        $cart->loadMissing('sections.lines');
        foreach ($cart->sections as $section) {
            foreach ($section->lines as $line) {
                if ($line->offer_id) {
                    $ids[] = (int) $line->offer_id;
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
