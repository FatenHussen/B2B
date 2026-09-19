<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Ordering\Application\Support\OpaqueChannelRef;
use Modules\Ordering\Domain\Enums\AssignmentStatus;
use Modules\Ordering\Domain\Enums\OrderSource;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\Order;
use Modules\Ordering\Domain\Models\OrderSection;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderAssignment;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\Models\SubOrderLine;
use Modules\Pricing\Domain\Models\ProductBasePrice;
use Modules\Pricing\Domain\Models\ProductQtyTier;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelEvent;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * Ninety days of sub-orders for whichever channel is the tenant, so every series the
 * platform reads — `GET /platform/channels/{id}/usage`, the channel dashboard, the
 * reports — has a curve instead of a spike.
 *
 * `DemoOrderingSeeder` writes a handful of hand-picked orders that exercise every queue
 * tab; this one writes volume. It draws on what the earlier seeders left in the channel
 * (active products with a base price, active retailers inside its coverage, its reps) and
 * invents nothing else: a channel with no priced products or no retailers gets no history.
 *
 * The shape is deterministic — the generator is seeded from the channel id — so two runs
 * on two machines draw the same chart. Volume ramps up over the window, Fridays are near
 * silent and Saturdays are light, and today's orders are still in flight while older ones
 * are mostly delivered. A channel that is suspended or archived stops on the day it left
 * `active`, which is what its usage series should show.
 *
 * Numbers carry `H` and the channel id (`SO-H3-00042`) so they never collide with the
 * app's `SO-{id}` or the demo seeder's `SO-D0001`. Runs once per channel: a channel that
 * already has a history row is left alone.
 */
final class DemoOrderHistorySeeder extends DemoSeeder
{
    public const DAYS = 90;

    /**
     * Status mix by age in days: `[status => weight]`, weights summing to 100.
     *
     * @var array<string, array<string, int>>
     */
    private const MIX = [
        'settled' => ['delivered' => 86, 'undelivered' => 4, 'cancelled' => 6, 'rejected' => 4],
        'recent' => ['delivered' => 45, 'on_the_way' => 20, 'processing' => 15, 'confirmed' => 10, 'cancelled' => 5, 'rejected' => 5],
        'today' => ['pending' => 35, 'confirmed' => 30, 'assigned' => 15, 'processing' => 10, 'on_the_way' => 10],
    ];

    /** Statuses that only exist once a rep holds the order. */
    private const NEEDS_REP = ['assigned', 'accepted', 'processing', 'on_the_way', 'delivered', 'undelivered'];

    /**
     * @param  int  $perDay  the average weekday volume at the end of the window
     */
    public function run(int $perDay = 5): void
    {
        $channelId = $this->channelId();
        $prefix = 'SO-H'.$channelId.'-';

        if (SubOrder::query()->where('sub_order_no', 'like', $prefix.'%')->exists()) {
            return;
        }

        $products = $this->pricedProducts();
        $retailers = $this->retailers();
        $reps = $this->repUserIds();
        $managerId = $this->managerId();
        [$from, $to, $closed] = $this->window();

        if ($products === [] || $retailers === [] || $from->gt($to)) {
            return;
        }

        mt_srand(crc32('demo-history-'.$channelId));
        $seq = 0;

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            if ($this->isClosed($day, $closed)) {
                continue;
            }

            $count = $this->ordersOn($day, $perDay, $to);

            DB::transaction(function () use ($day, $count, &$seq, $prefix, $products, $retailers, $reps, $managerId): void {
                for ($i = 0; $i < $count; $i++) {
                    $seq++;
                    $this->writeOrder($day, $seq, $prefix, $products, $retailers, $reps, $managerId);
                }
            });
        }
    }

    /**
     * Base plus tiers per active product, loaded once: one query per line would make
     * two thousand orders a slow seed.
     *
     * @return list<array{id: int, min: int, multiple: int, base: int, tiers: list<array{0: int, 1: int|null, 2: int}>}>
     */
    private function pricedProducts(): array
    {
        $products = Product::query()->where('status', ProductStatus::Active)->orderBy('id')->get();
        $ids = $products->pluck('id')->all();

        $bases = ProductBasePrice::query()->whereIn('product_id', $ids)->get()->keyBy('product_id');
        $tiers = ProductQtyTier::query()->whereIn('product_id', $ids)->orderBy('from_qty')->get()->groupBy('product_id');

        $out = [];
        foreach ($products as $product) {
            $base = $bases->get($product->id);
            if ($base === null) {
                continue;
            }

            $out[] = [
                'id' => (int) $product->id,
                'min' => max(1, (int) $product->min_order_qty),
                'multiple' => max(1, (int) $product->order_multiple),
                'base' => (int) $base->base_price,
                'tiers' => $tiers->get($product->id, collect())
                    ->map(fn ($tier) => [(int) $tier->from_qty, $tier->to_qty !== null ? (int) $tier->to_qty : null, (int) $tier->price])
                    ->values()
                    ->all(),
            ];
        }

        return $out;
    }

    /**
     * Active retailers whose zone the channel covers. Retailer profiles are platform-wide,
     * so coverage is the only thing that ties a shop to this channel — the same rule the
     * cart uses to route a section.
     *
     * @return list<array{profile: int, user: int, zone: int}>
     */
    private function retailers(): array
    {
        return RetailerProfile::query()
            ->whereIn('zone_id', $this->coveredZoneIds())
            ->where('status', ProfileStatus::Active)
            ->orderBy('id')
            ->get()
            ->map(fn (RetailerProfile $profile): array => [
                'profile' => (int) $profile->id,
                'user' => (int) $profile->app_user_id,
                'zone' => (int) $profile->zone_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function repUserIds(): array
    {
        return RepProfile::query()
            ->where('channel_id', $this->channelId())
            ->where('status', ProfileStatus::Active)
            ->orderBy('id')
            ->pluck('app_user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** The channel's first dashboard user, as the actor on channel-side stages. */
    private function managerId(): ?int
    {
        $id = ChannelUserChannel::query()
            ->where('channel_id', $this->channelId())
            ->orderBy('id')
            ->value('channel_user_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * The days this channel could have taken orders: the window, starting no earlier
     * than the day it was provisioned, minus every stretch it spent suspended or archived
     * — read from `channel_events`, so a channel suspended for a week shows the gap and
     * one suspended today stops today.
     *
     * @return array{0: Carbon, 1: Carbon, 2: list<array{0: Carbon, 1: Carbon|null}>}
     */
    private function window(): array
    {
        $today = Carbon::today('Asia/Damascus');
        $from = $today->copy()->subDays(self::DAYS - 1);

        $channel = SupplyChannel::query()->findOrFail($this->channelId());

        if ($channel->provisioned_at !== null) {
            $from = $from->max($channel->provisioned_at->copy()->setTimezone('Asia/Damascus')->startOfDay());
        }

        $closed = [];
        $open = null;
        $events = ChannelEvent::query()->where('channel_id', $channel->id)->orderBy('at')->orderBy('id')->get();

        foreach ($events as $event) {
            $at = $event->at->copy()->setTimezone('Asia/Damascus')->startOfDay();

            if ($event->to_status === ChannelStatus::Active) {
                if ($open !== null) {
                    $closed[] = [$open, $at];
                    $open = null;
                }
            } elseif ($open === null) {
                $open = $at;
            }
        }

        if ($open !== null) {
            $closed[] = [$open, null];
        }

        return [$from, $today, $closed];
    }

    /**
     * @param  list<array{0: Carbon, 1: Carbon|null}>  $closed
     */
    private function isClosed(Carbon $day, array $closed): bool
    {
        foreach ($closed as [$start, $end]) {
            if ($day->gte($start) && ($end === null || $day->lt($end))) {
                return true;
            }
        }

        return false;
    }

    /**
     * How many orders `$day` gets: the weekday volume, dampened on the weekend, ramping
     * from three quarters at the start of the window to full at the end, with noise.
     */
    private function ordersOn(Carbon $day, int $perDay, Carbon $to): int
    {
        $weekday = match ($day->dayOfWeek) {
            Carbon::FRIDAY => 0.15,
            Carbon::SATURDAY => 0.6,
            default => 1.0,
        };
        $ramp = 0.75 + 0.25 * (1 - min(1, $day->diffInDays($to) / self::DAYS));
        $noise = 0.7 + mt_rand(0, 60) / 100;

        return (int) round($perDay * $weekday * $ramp * $noise);
    }

    /**
     * @param  list<array{id: int, min: int, multiple: int, base: int, tiers: list<array{0: int, 1: int|null, 2: int}>}>  $products
     * @param  list<array{profile: int, user: int, zone: int}>  $retailers
     * @param  list<int>  $reps
     */
    private function writeOrder(Carbon $day, int $seq, string $prefix, array $products, array $retailers, array $reps, ?int $managerId): void
    {
        $createdAt = $day->copy()->setTime(mt_rand(8, 20), mt_rand(0, 59), mt_rand(0, 59));
        if ($createdAt->isFuture()) {
            $createdAt = Carbon::now('Asia/Damascus')->subMinutes(mt_rand(5, 90));
        }

        $retailer = $retailers[mt_rand(0, count($retailers) - 1)];
        $source = $reps !== [] && mt_rand(1, 100) <= 30 ? OrderSource::RepApp : OrderSource::RetailerApp;
        $status = $this->pickStatus($createdAt);

        $repId = null;
        if (in_array($status, self::NEEDS_REP, true)) {
            if ($reps === []) {
                $status = 'confirmed';
            } else {
                $repId = $reps[mt_rand(0, count($reps) - 1)];
            }
        } elseif ($source === OrderSource::RepApp) {
            $repId = $reps[mt_rand(0, count($reps) - 1)];
        }

        $number = $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);

        $order = Order::query()->create([
            'retailer_id' => $retailer['profile'],
            'source' => $source,
            'order_no' => 'ORD-'.substr($number, 3),
            'status' => 'pending',
            'currency' => 'SYP',
            'client_created_at' => $createdAt,
            'offline_created' => $source === OrderSource::RepApp && mt_rand(1, 100) <= 20,
        ]);
        $this->stamp($order, $createdAt);

        $section = OrderSection::query()->create([
            'order_id' => $order->id,
            'opaque_ref' => OpaqueChannelRef::make($this->channelId()),
        ]);
        $this->stamp($section, $createdAt);

        $sub = SubOrder::query()->create([
            'order_id' => $order->id,
            'retailer_id' => $retailer['profile'],
            'zone_id' => $retailer['zone'],
            'source' => $source,
            'sub_order_no' => $number,
            'status' => SubOrderStatus::from($status),
            'rep_id' => $repId,
            'currency_code' => 'SYP',
        ]);

        $subtotal = 0;
        $lines = [];
        foreach ($this->pickProducts($products) as $product) {
            $qty = $product['min'] + $product['multiple'] * mt_rand(0, 3);
            $unitPrice = $this->unitPrice($product, $qty);
            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $lines[] = [
                'sub_order_id' => $sub->id,
                'product_id' => $product['id'],
                'variant_id' => null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'line_total' => $lineTotal,
                'applied_rule' => null,
                'offer_id' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }
        SubOrderLine::query()->insert($lines);

        $sub->forceFill([
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $this->writeTimeline((int) $sub->id, $status, $createdAt, $retailer['user'], $repId, $managerId);

        if ($repId !== null && in_array($status, self::NEEDS_REP, true)) {
            $assignedAt = $createdAt->copy()->addMinutes(40);
            SubOrderAssignment::query()->insert([
                'sub_order_id' => $sub->id,
                'rep_id' => $repId,
                'status' => $status === 'assigned' ? AssignmentStatus::Pending->value : AssignmentStatus::Accepted->value,
                'reason' => null,
                'created_at' => $assignedAt,
                'updated_at' => $assignedAt,
            ]);
        }
    }

    private function pickStatus(Carbon $createdAt): string
    {
        $age = $createdAt->copy()->startOfDay()->diffInDays(Carbon::today('Asia/Damascus'));
        $mix = self::MIX[$age >= 3 ? 'settled' : ($age >= 1 ? 'recent' : 'today')];

        $roll = mt_rand(1, 100);
        $cumulative = 0;
        foreach ($mix as $status => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $status;
            }
        }

        return array_key_first($mix);
    }

    /**
     * Two to five distinct products, earlier (higher-priority) ones a little more often.
     *
     * @param  list<array{id: int, min: int, multiple: int, base: int, tiers: list<array{0: int, 1: int|null, 2: int}>}>  $products
     * @return list<array{id: int, min: int, multiple: int, base: int, tiers: list<array{0: int, 1: int|null, 2: int}>}>
     */
    private function pickProducts(array $products): array
    {
        $count = min(count($products), mt_rand(2, 5));
        $picked = [];

        while (count($picked) < $count) {
            $index = min(mt_rand(0, count($products) - 1), mt_rand(0, count($products) - 1));
            $picked[$index] = $products[$index];
        }

        return array_values($picked);
    }

    /**
     * @param  array{id: int, min: int, multiple: int, base: int, tiers: list<array{0: int, 1: int|null, 2: int}>}  $product
     */
    private function unitPrice(array $product, int $qty): int
    {
        $price = $product['base'];
        foreach ($product['tiers'] as [$from, $to, $tierPrice]) {
            if ($qty >= $from && ($to === null || $qty <= $to)) {
                $price = $tierPrice;
            }
        }

        return $price;
    }

    /**
     * One event per stage, the same journeys and actors `DemoOrderingSeeder` writes.
     */
    private function writeTimeline(int $subOrderId, string $status, Carbon $createdAt, int $retailerUserId, ?int $repId, ?int $managerId): void
    {
        $at = $createdAt->copy();
        $events = [];

        foreach (DemoOrderingSeeder::JOURNEYS[$status] as $i => $stage) {
            if ($i > 0) {
                $at = $at->copy()->addMinutes(20 + $i * 35 + mt_rand(0, 25));
            }

            [$actorType, $actorId] = match ($stage) {
                'pending', 'cancelled' => [AppUser::class, $retailerUserId],
                'accepted', 'on_the_way', 'delivered', 'undelivered' => [AppUser::class, $repId],
                default => [$managerId !== null ? ChannelUser::class : null, $managerId],
            };

            $events[] = [
                'sub_order_id' => $subOrderId,
                'stage' => $stage,
                'at' => $at,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ];
        }

        SubOrderEvent::query()->insert($events);
    }

    /**
     * Backdates a row. `updated_at` is set explicitly so `save()` keeps it instead of
     * stamping `now()`.
     */
    private function stamp(object $model, Carbon $at): void
    {
        $model->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }
}
