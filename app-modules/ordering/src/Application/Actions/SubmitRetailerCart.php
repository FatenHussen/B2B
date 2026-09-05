<?php

declare(strict_types=1);

namespace Modules\Ordering\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\CreditGuard;
use Modules\Core\Contracts\RetailerShoppingContext;
use Modules\Core\Domain\Events\CartSubmitted;
use Modules\Core\Domain\Exceptions\DomainException;
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

final class SubmitRetailerCart
{
    public function __construct(
        private readonly CartAssembler $carts,
        private readonly RetailerShoppingContext $shopping,
        private readonly CreditGuard $credit,
    ) {}

    /**
     * @param  array{sections?: list<array{ref: string, note?: string|null, scheduled_at?: string|null}>, client_created_at?: string|null, offline_created?: bool}  $data
     * @return array<string, mixed>
     */
    public function __invoke(object $user, array $data): array
    {
        $ctx = $this->shopping->for($user);
        $cart = $this->carts->activeFor($user);
        $cart->load('sections.lines');
        if ($cart->sections->every(fn ($s) => $s->lines->isEmpty())) {
            throw new DomainException(__('ordering.cart_empty'), 'validation_failed', 422);
        }

        $beforeTotal = 0;
        foreach ($cart->sections as $section) {
            foreach ($section->lines as $line) {
                $beforeTotal += (int) $line->line_total;
            }
        }

        $offline = (bool) ($data['offline_created'] ?? false);
        try {
            $this->carts->reprice($cart, $ctx['zone_id'], $ctx['retailer_id']);
        } catch (DomainException $e) {
            if (! $offline) {
                throw new DomainException(__('ordering.offer_no_longer_valid'), 'offer_no_longer_valid', 409);
            }
        }

        $cart->refresh()->load('sections.lines');
        $afterTotal = 0;
        foreach ($cart->sections as $section) {
            foreach ($section->lines as $line) {
                $afterTotal += (int) $line->line_total;
            }
        }

        foreach ($cart->sections as $section) {
            $this->credit->assertWithinLimit($ctx['retailer_id'], (int) $section->channel_id, $this->sectionTotal($section));
        }

        if (isset($data['sections'])) {
            foreach ($data['sections'] as $patch) {
                CartSection::query()
                    ->where('cart_id', $cart->id)
                    ->where('opaque_ref', $patch['ref'])
                    ->update([
                        'note' => $patch['note'] ?? null,
                        'scheduled_at' => $patch['scheduled_at'] ?? null,
                    ]);
            }
            $cart->refresh()->load('sections.lines');
        }

        $order = DB::transaction(function () use ($cart, $ctx, $data, $offline, $user): Order {
            $order = Order::query()->create([
                'retailer_id' => $ctx['retailer_id'],
                'source' => OrderSource::RetailerApp,
                'order_no' => 'ORD-tmp',
                'status' => SubOrderStatus::Pending->value,
                'currency' => 'SYP',
                'client_created_at' => $data['client_created_at'] ?? now(),
                'offline_created' => $offline,
            ]);
            $order->forceFill(['order_no' => 'ORD-'.$order->id])->save();

            foreach ($cart->sections as $section) {
                if ($section->lines->isEmpty()) {
                    continue;
                }
                OrderSection::query()->create([
                    'order_id' => $order->id,
                    'channel_id' => $section->channel_id,
                    'opaque_ref' => $section->opaque_ref,
                    'note' => $section->note,
                    'scheduled_at' => $section->scheduled_at,
                ]);

                $subtotal = 0;
                $discount = 0;
                foreach ($section->lines as $line) {
                    $subtotal += (int) $line->line_total;
                    $discount += (int) $line->discount;
                }

                $sub = SubOrder::query()->create([
                    'order_id' => $order->id,
                    'channel_id' => $section->channel_id,
                    'retailer_id' => $ctx['retailer_id'],
                    'zone_id' => $ctx['zone_id'],
                    'source' => OrderSource::RetailerApp,
                    'sub_order_no' => 'SO-tmp',
                    'status' => SubOrderStatus::Pending,
                    'subtotal' => $subtotal + $discount,
                    'discount' => $discount,
                    'total' => $subtotal,
                    'scheduled_at' => $section->scheduled_at,
                ]);
                $sub->forceFill(['sub_order_no' => 'SO-'.$sub->id])->save();

                foreach ($section->lines as $line) {
                    SubOrderLine::query()->create([
                        'sub_order_id' => $sub->id,
                        'product_id' => $line->product_id,
                        'variant_id' => $line->variant_id,
                        'qty' => $line->qty,
                        'unit_price' => $line->unit_price,
                        'discount' => $line->discount,
                        'line_total' => $line->line_total,
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
            }

            $cart->forceFill(['status' => CartStatus::Submitted])->save();

            return $order;
        });

        event(new CartSubmitted((int) $order->id, $ctx['retailer_id'], OrderSource::RetailerApp->value));

        $order->load('subOrders');
        $diff = $afterTotal !== $beforeTotal ? ($afterTotal - $beforeTotal) : null;
        $order->forceFill(['repricing_diff' => $diff])->save();

        return [
            'order' => [
                'id' => (int) $order->id,
                'order_no' => $order->order_no,
                'sub_orders' => $order->subOrders->map(fn (SubOrder $s) => [
                    'id' => (int) $s->id,
                    'sub_order_no' => $s->sub_order_no,
                    'status' => $s->status->value,
                    'total' => (int) $s->total,
                ])->all(),
            ],
            'repricing_diff' => $diff,
        ];
    }

    private function sectionTotal(CartSection $section): int
    {
        $total = 0;
        foreach ($section->lines as $line) {
            $total += (int) $line->line_total;
        }

        return $total;
    }
}
