<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\RepSellingContext;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Ordering\Application\Support\CartAssembler;
use Modules\Ordering\Domain\Enums\CartStatus;
use Modules\Ordering\Domain\Enums\OrderSource;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\CartSection;
use Modules\Ordering\Domain\Models\Order;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\Models\SubOrderLine;
use Modules\Pricing\Domain\Models\RepCommercialLimit;

final class SubmitRepCartSection
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RepSellingContext $selling,
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
        $cap = (int) RepCommercialLimit::query()
            ->where('channel_id', $channelId)
            ->where('rep_id', $user->getAuthIdentifier())
            ->value('max_discount_percent');
        if ($percent > 0 && $percent > $cap) {
            throw new DomainException(__('ordering.discount_cap_exceeded'), 'discount_cap_exceeded', 403);
        }

        $retailer = RetailerProfile::query()->find($retailerId);
        if ($retailer === null) {
            throw new DomainException(__('ordering.not_found'), 'not_found', 404);
        }

        $cart = $this->carts->activeFor($user);
        $section = CartSection::query()
            ->where('cart_id', $cart->id)
            ->where('retailer_id', $retailerId)
            ->first();
        if ($section === null || $section->lines()->count() === 0) {
            throw new DomainException(__('ordering.cart_empty'), 'validation_failed', 422);
        }

        $this->carts->reprice($cart, (int) $retailer->zone_id, $retailerId, (int) $section->channel_id);
        $section->refresh()->load('lines');

        $sub = DB::transaction(function () use ($cart, $section, $retailer, $percent, $user): SubOrder {
            $subtotal = 0;
            $discount = 0;
            foreach ($section->lines as $line) {
                $subtotal += (int) $line->line_total;
                $discount += (int) $line->discount;
            }
            $extra = $percent > 0 ? intdiv($subtotal * $percent, 100) : 0;
            $total = $subtotal - $extra;

            $order = Order::query()->create([
                'retailer_id' => $retailer->id,
                'source' => OrderSource::RepApp,
                'order_no' => 'ORD-tmp',
                'status' => SubOrderStatus::Pending->value,
                'currency' => 'SYP',
            ]);
            $order->forceFill(['order_no' => 'ORD-'.$order->id])->save();

            $sub = SubOrder::query()->create([
                'order_id' => $order->id,
                'channel_id' => $section->channel_id,
                'retailer_id' => $retailer->id,
                'zone_id' => $retailer->zone_id,
                'source' => OrderSource::RepApp,
                'sub_order_no' => 'SO-tmp',
                'status' => SubOrderStatus::Pending,
                'subtotal' => $subtotal + $discount,
                'discount' => $discount + $extra,
                'total' => $total,
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
