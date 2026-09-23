<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\OfferConsumption;
use Modules\Core\Contracts\RepCommercialLimits;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Domain\Enums\CartStatus;
use Modules\Ordering\Domain\Enums\OrderSource;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\CartSection;
use Modules\Ordering\Domain\Models\Order;
use Modules\Ordering\Domain\Models\OrderSection;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\Models\SubOrderLine;

final class SubmitRepCartSection
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RepSellingContext $selling,
        private readonly RepCommercialLimits $limits,
        private readonly RetailerDirectory $retailers,
        private readonly OfferConsumption $offers,
    ) {}

    /**
     * @param  array{note?: string|null, discount_percent?: int}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $user, int $retailerId, array $data): array
    {
        $selling = $this->selling->for($user);
        $percent = (int) ($data['discount_percent'] ?? 0);
        $channelId = $selling['channel_ids'][0] ?? 0;
        $cap = $this->limits->maxDiscountPercent($channelId, (int) $user->getAuthIdentifier());
        if ($percent > 0 && $percent > $cap) {
            throw new DomainException(__('ordering.discount_cap_exceeded'), 'discount_cap_exceeded', 403);
        }

        if (! $this->retailers->exists($retailerId)) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }
        $retailerZoneId = (int) $this->retailers->zoneId($retailerId);

        $cart = $this->carts->activeFor($user);
        // acrossChannels(), per rule 10 (BE-C12): the cart is the rep's own, `cart_id` is
        // the isolation and `retailer_id` picks the customer's section within it.
        $section = CartSection::query()
            ->acrossChannels()
            ->where('cart_id', $cart->id)
            ->where('retailer_id', $retailerId)
            ->first();
        if ($section === null || $section->lines()->count() === 0) {
            throw new DomainException(__('ordering.cart_empty'), 'validation_failed', 422);
        }

        $section->forceFill(['note' => $data['note'] ?? $section->note])->save();

        $this->carts->reprice($cart, $retailerZoneId, $retailerId, (int) $section->channel_id);
        $section->refresh()->load('lines');

        $sub = DB::transaction(function () use ($cart, $section, $retailerId, $retailerZoneId, $percent, $user): SubOrder {
            $subtotal = 0;
            $discount = 0;
            foreach ($section->lines as $line) {
                $subtotal += (int) $line->line_total;
                $discount += (int) $line->discount;
            }
            $extra = $percent > 0 ? intdiv($subtotal * $percent, 100) : 0;
            $total = $subtotal - $extra;

            $order = Order::query()->create([
                'retailer_id' => $retailerId,
                'source' => OrderSource::RepApp,
                'order_no' => 'ORD-tmp',
                'status' => SubOrderStatus::Pending->value,
                'currency' => 'SYP',
            ]);
            $order->forceFill(['order_no' => 'ORD-'.$order->id])->save();

            OrderSection::query()->create([
                'order_id' => $order->id,
                'channel_id' => $section->channel_id,
                'opaque_ref' => $section->opaque_ref,
                'note' => $section->note,
                'scheduled_at' => $section->scheduled_at,
            ]);

            $sub = SubOrder::query()->create([
                'order_id' => $order->id,
                'channel_id' => $section->channel_id,
                'retailer_id' => $retailerId,
                'zone_id' => $retailerZoneId,
                'rep_id' => (int) $user->getAuthIdentifier(),
                'source' => OrderSource::RepApp,
                'sub_order_no' => 'SO-tmp',
                'status' => SubOrderStatus::Pending,
                'subtotal' => $subtotal + $discount,
                'discount' => $discount + $extra,
                'total' => $total,
                // Frozen here and never recomputed — BR-AD-19. See SubmitRetailerCart for
                // why the identity rate is the right value today.
                'currency_code' => $order->currency,
                'fx_rate' => Money::FX_UNIT,
            ]);
            $sub->forceFill(['sub_order_no' => 'SO-'.$sub->id])->save();

            foreach ($section->lines as $line) {
                $lineTotal = (int) $line->line_total;
                if ($percent > 0) {
                    $lineTotal -= intdiv($lineTotal * $percent, 100);
                }
                SubOrderLine::query()->create([
                    'sub_order_id' => $sub->id,
                    'product_id' => $line->product_id,
                    'variant_id' => $line->variant_id,
                    'qty' => $line->qty,
                    'unit_price' => $line->unit_price,
                    'discount' => $line->discount,
                    'line_total' => $lineTotal,
                    'applied_rule' => $line->applied_rule,
                    'offer_id' => $line->offer_id,
                ]);
            }

            $appliedOfferIds = [];
            foreach ($section->lines as $line) {
                if ($line->offer_id) {
                    $appliedOfferIds[(int) $line->offer_id] = true;
                }
            }
            foreach (array_keys($appliedOfferIds) as $offerId) {
                $this->offers->recordApplied((int) $offerId, $retailerId, 1);
            }

            SubOrderEvent::query()->create([
                'sub_order_id' => $sub->id,
                'stage' => SubOrderStatus::Pending->value,
                'at' => now(),
                'actor_type' => $user::class,
                'actor_id' => $user->getAuthIdentifier(),
            ]);

            $section->lines()->delete();
            $section->delete();
            if ($cart->sections()->count() === 0) {
                $cart->forceFill(['status' => CartStatus::Submitted])->save();
            }

            return $sub;
        });

        return [
            'sub_order' => [
                'id' => (int) $sub->id,
                'sub_order_no' => $sub->sub_order_no,
                'status' => $sub->status->value,
                'total' => (int) $sub->total,
            ],
        ];
    }
}
