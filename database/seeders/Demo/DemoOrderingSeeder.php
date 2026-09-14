<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
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
use Modules\Promotion\Domain\Models\Offer;
use Modules\Returns\Domain\Models\ReturnDecision;
use Modules\Returns\Domain\Models\ReturnLine;
use Modules\Returns\Domain\Models\ReturnRequest;

/**
 * Sub-orders in every state the queue tabs filter by, plus return requests on the
 * delivered ones.
 *
 * Written straight to the tables rather than through `SubmitRetailerCart` and the
 * lifecycle service: those need a live cart, stock to reserve and a signed-in actor, and
 * they stamp `now()` on everything — a queue where every order is a minute old shows
 * nothing about waiting times. The timeline events are written in the order the state
 * machine would have produced them, so the detail page reads as a real history.
 *
 * Numbers carry a `D` so they can never collide with the `SO-{id}` the app generates.
 */
final class DemoOrderingSeeder extends DemoSeeder
{
    /**
     * Sub-orders by number. `age` is hours ago; `lines` map SKU → qty, with an optional
     * variant SKU written as `SKU@VARIANT`.
     *
     * @var array<string, array{retailer: string, source: string, status: string, age: int, rep?: string, scheduled?: int, offline?: bool, lines: array<string, int>}>
     */
    private const SUB_ORDERS = [
        'D0001' => ['retailer' => '+963931000001', 'source' => 'retailer_app', 'status' => 'pending', 'age' => 0, 'lines' => ['SUG-1KG' => 50, 'OIL-1L' => 24, 'RICE-5KG' => 4]],
        'D0002' => ['retailer' => '+963931000002', 'source' => 'retailer_app', 'status' => 'pending', 'age' => 3, 'lines' => ['COLA-330' => 48, 'CHIPS-50' => 48, 'BIS-100' => 24]],
        'D0003' => ['retailer' => '+963931000005', 'source' => 'rep_app', 'rep' => '+963932000002', 'status' => 'pending', 'age' => 26, 'offline' => true, 'lines' => ['DET-3KG' => 8, 'FLC-1L' => 12, 'TP-100' => 12]],
        'D0015' => ['retailer' => '+963931000007', 'source' => 'retailer_app', 'status' => 'pending', 'age' => 1, 'lines' => ['SUG-1KG' => 30, 'RICE-5KG' => 6, 'TEA-500' => 12]],
        'D0004' => ['retailer' => '+963931000003', 'source' => 'retailer_app', 'status' => 'confirmed', 'age' => 48, 'lines' => ['MILK-1L' => 24, 'NESC-200' => 12, 'CHOC-40' => 48]],
        'D0005' => ['retailer' => '+963931000004', 'source' => 'retailer_app', 'status' => 'confirmed', 'age' => 50, 'lines' => ['OIL-4L' => 6, 'TOM-400' => 24, 'PASTA-500' => 36, 'TUNA-160' => 24]],
        'D0006' => ['retailer' => '+963931000001', 'source' => 'retailer_app', 'status' => 'assigned', 'rep' => '+963932000001', 'age' => 72, 'lines' => ['WATER-500' => 10, 'PEPSI-1500' => 12, 'JUICE-ORG-1L' => 12]],
        'D0007' => ['retailer' => '+963931000003', 'source' => 'retailer_app', 'status' => 'accepted', 'rep' => '+963932000001', 'age' => 74, 'lines' => ['CHZ-500' => 12, 'LAB-500' => 12, 'YOG-1KG' => 12]],
        'D0008' => ['retailer' => '+963931000002', 'source' => 'rep_app', 'rep' => '+963932000002', 'status' => 'processing', 'age' => 96, 'lines' => ['SUG-1KG' => 20, 'TEA-500' => 6, 'BIS-100' => 48]],
        'D0009' => ['retailer' => '+963931000006', 'source' => 'rep_app', 'rep' => '+963932000002', 'status' => 'postponed', 'age' => 120, 'scheduled' => 48, 'lines' => ['RICE-5KG' => 2, 'OIL-1L' => 12]],
        'D0010' => ['retailer' => '+963931000005', 'source' => 'retailer_app', 'status' => 'on_the_way', 'rep' => '+963932000002', 'age' => 30, 'lines' => ['DET-3KG' => 4, 'SHP@SHP-400مل' => 6, 'TP-100' => 24]],
        'D0011' => ['retailer' => '+963931000001', 'source' => 'retailer_app', 'status' => 'delivered', 'rep' => '+963932000001', 'age' => 192, 'lines' => ['SUG-1KG' => 100, 'OIL-1L' => 60, 'MILK-1L' => 48, 'COLA-330' => 72]],
        'D0012' => ['retailer' => '+963931000004', 'source' => 'retailer_app', 'status' => 'delivered', 'rep' => '+963932000002', 'age' => 288, 'lines' => ['TOM-400' => 24, 'TUNA-160' => 36, 'PASTA-500' => 24]],
        'D0016' => ['retailer' => '+963931000005', 'source' => 'retailer_app', 'status' => 'undelivered', 'rep' => '+963932000002', 'age' => 240, 'lines' => ['WATER-500' => 5, 'JUICE-ORG-1L' => 12]],
        'D0013' => ['retailer' => '+963931000002', 'source' => 'retailer_app', 'status' => 'rejected', 'age' => 144, 'lines' => ['OLD-COLA-250' => 24]],
        'D0014' => ['retailer' => '+963931000003', 'source' => 'retailer_app', 'status' => 'cancelled', 'age' => 216, 'lines' => ['NESC-200' => 6]],
    ];

    /**
     * The stages a sub-order passed through to reach each status, oldest first.
     *
     * @var array<string, list<string>>
     */
    private const JOURNEYS = [
        'pending' => ['pending'],
        'confirmed' => ['pending', 'confirmed'],
        'assigned' => ['pending', 'confirmed', 'assigned'],
        'accepted' => ['pending', 'confirmed', 'assigned', 'accepted'],
        'processing' => ['pending', 'confirmed', 'assigned', 'accepted', 'processing'],
        'on_the_way' => ['pending', 'confirmed', 'assigned', 'accepted', 'processing', 'awaiting_handover', 'on_the_way'],
        'delivered' => ['pending', 'confirmed', 'assigned', 'accepted', 'processing', 'awaiting_handover', 'on_the_way', 'delivered'],
        'undelivered' => ['pending', 'confirmed', 'assigned', 'accepted', 'processing', 'awaiting_handover', 'on_the_way', 'undelivered'],
        'postponed' => ['pending', 'confirmed', 'postponed'],
        'rejected' => ['pending', 'rejected'],
        'cancelled' => ['pending', 'cancelled'],
    ];

    /**
     * Returns on delivered sub-orders: `[request no, sub-order no, type, status, sku, qty, reason, decision reason|null]`.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int, 6: string, 7: string|null}>
     */
    private const RETURNS = [
        ['RR-D0001', 'D0011', 'return', 'pending', 'MILK-1L', 6, 'عبوات تالفة عند الاستلام', null],
        ['RR-D0002', 'D0012', 'exchange', 'approved', 'TUNA-160', 12, 'قريبة من انتهاء الصلاحية', 'تمت الموافقة على الاستبدال'],
        ['RR-D0003', 'D0012', 'return', 'rejected', 'PASTA-500', 24, 'طلب خاطئ', 'مضى أكثر من 7 أيام على التسليم'],
    ];

    /** The offer whose discount is written on matching lines, by name. */
    private const OIL_OFFER = 'خصم 10% على زيت الدرة 1 ل';

    public function run(): void
    {
        $this->seedSubOrders();
        $this->seedReturns();
    }

    private function seedSubOrders(): void
    {
        $oilOffer = Offer::query()->where('name', self::OIL_OFFER)->first();
        $oilProductId = $this->productId('OIL-1L');

        foreach (self::SUB_ORDERS as $number => $row) {
            if (SubOrder::query()->where('sub_order_no', 'SO-'.$number)->exists()) {
                continue;
            }

            $createdAt = Carbon::now('Asia/Damascus')->subHours($row['age'])->subMinutes($row['age'] % 7 * 5);
            $retailerUser = AppUser::query()->where('phone', $row['retailer'])->firstOrFail();
            $profile = RetailerProfile::query()->where('app_user_id', $retailerUser->id)->firstOrFail();
            $repId = isset($row['rep']) ? $this->repUserId($row['rep']) : null;

            $order = Order::query()->create([
                'retailer_id' => $profile->id,
                'source' => OrderSource::from($row['source']),
                'order_no' => 'ORD-'.$number,
                'status' => 'pending',
                'currency' => 'SYP',
                'client_created_at' => $createdAt,
                'offline_created' => $row['offline'] ?? false,
            ]);
            $this->stamp($order, $createdAt);

            $section = OrderSection::query()->create([
                'order_id' => $order->id,
                'opaque_ref' => OpaqueChannelRef::make($this->channelId()),
                'note' => $number === 'D0003' ? 'التسليم من الباب الخلفي بعد الساعة 4' : null,
                'scheduled_at' => isset($row['scheduled']) ? $createdAt->copy()->addHours($row['scheduled']) : null,
            ]);
            $this->stamp($section, $createdAt);

            $sub = SubOrder::query()->create([
                'order_id' => $order->id,
                'retailer_id' => $profile->id,
                'zone_id' => $profile->zone_id,
                'source' => OrderSource::from($row['source']),
                'sub_order_no' => 'SO-'.$number,
                'status' => SubOrderStatus::from($row['status']),
                'rep_id' => $repId,
                'scheduled_at' => $section->scheduled_at,
                'currency_code' => 'SYP',
            ]);

            $subtotal = 0;
            $discount = 0;

            foreach ($row['lines'] as $key => $qty) {
                [$sku, $variantSku] = array_pad(explode('@', $key, 2), 2, null);
                $productId = $this->productId($sku);
                $unitPrice = $this->unitPrice($productId, $qty);
                $lineTotal = $unitPrice * $qty;
                $lineDiscount = 0;
                $rule = null;
                $offerId = null;

                if ($oilOffer !== null && $productId === $oilProductId) {
                    $lineDiscount = intdiv($lineTotal * 10, 100);
                    $offerId = (int) $oilOffer->id;
                    $rule = ['type' => 'offer', 'id' => $offerId, 'label' => $oilOffer->name];
                }

                $line = SubOrderLine::query()->create([
                    'sub_order_id' => $sub->id,
                    'product_id' => $productId,
                    'variant_id' => $variantSku !== null
                        ? (int) ProductVariant::query()->where('sku', $variantSku)->firstOrFail()->id
                        : null,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $lineDiscount,
                    'line_total' => $lineTotal - $lineDiscount,
                    'applied_rule' => $rule,
                    'offer_id' => $offerId,
                ]);
                $this->stamp($line, $createdAt);

                $subtotal += $lineTotal;
                $discount += $lineDiscount;
            }

            $sub->forceFill([
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $subtotal - $discount,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();

            $this->writeTimeline($sub, $row['status'], $createdAt, $retailerUser, $repId);

            if ($repId !== null && in_array($row['status'], ['assigned', 'accepted', 'processing', 'on_the_way', 'delivered', 'undelivered'], true)) {
                $assignment = SubOrderAssignment::query()->create([
                    'sub_order_id' => $sub->id,
                    'rep_id' => $repId,
                    'status' => $row['status'] === 'assigned' ? AssignmentStatus::Pending : AssignmentStatus::Accepted,
                ]);
                $this->stamp($assignment, $createdAt->copy()->addMinutes(40));
            }
        }
    }

    /**
     * One event per stage, spaced out so the detail page's timeline has real gaps.
     * Channel-side stages are stamped with the channel manager, field stages with the rep,
     * and the first (and any cancellation) with the retailer.
     */
    private function writeTimeline(SubOrder $sub, string $status, Carbon $createdAt, AppUser $retailer, ?int $repId): void
    {
        $managerId = $this->managerId();
        $at = $createdAt->copy();

        foreach (self::JOURNEYS[$status] as $i => $stage) {
            if ($i > 0) {
                $at = $at->copy()->addMinutes(20 + $i * 35);
            }

            [$actorType, $actorId] = match ($stage) {
                'pending', 'cancelled' => [AppUser::class, (int) $retailer->id],
                'accepted', 'on_the_way', 'delivered', 'undelivered' => [AppUser::class, $repId],
                default => [$managerId !== null ? ChannelUser::class : null, $managerId],
            };

            SubOrderEvent::query()->create([
                'sub_order_id' => $sub->id,
                'stage' => $stage,
                'at' => $at,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);
        }
    }

    private function seedReturns(): void
    {
        $managerId = $this->managerId();

        foreach (self::RETURNS as [$requestNo, $subNumber, $type, $status, $sku, $qty, $reason, $decisionReason]) {
            if (ReturnRequest::query()->where('request_no', $requestNo)->exists()) {
                continue;
            }

            $sub = SubOrder::query()->where('sub_order_no', 'SO-'.$subNumber)->firstOrFail();
            $line = SubOrderLine::query()
                ->where('sub_order_id', $sub->id)
                ->where('product_id', $this->productId($sku))
                ->firstOrFail();
            $retailerUserId = (int) RetailerProfile::query()->findOrFail($sub->retailer_id)->app_user_id;
            $requestedAt = $sub->created_at->copy()->addDays(1)->addHours(3);

            $request = ReturnRequest::query()->create([
                'sub_order_id' => $sub->id,
                'requester_type' => AppUser::class,
                'requester_id' => $retailerUserId,
                'type' => $type,
                'status' => $status,
                'request_no' => $requestNo,
            ]);
            $this->stamp($request, $requestedAt);

            ReturnLine::query()->create([
                'return_request_id' => $request->id,
                'line_id' => $line->id,
                'qty' => $qty,
                'reason' => $reason,
                'photos' => [],
            ]);

            if ($status !== 'pending') {
                $decision = ReturnDecision::query()->create([
                    'return_request_id' => $request->id,
                    'decision' => $status === 'approved' ? 'approve' : 'reject',
                    'reason' => $decisionReason,
                    'actor_type' => $managerId !== null ? ChannelUser::class : null,
                    'actor_id' => $managerId,
                ]);
                $this->stamp($decision, $requestedAt->copy()->addHours(5));
            }
        }
    }

    /**
     * The price a line would have been quoted at: the tier for its quantity when the
     * product is tiered, the base price otherwise. Mirrors `EloquentPricingEngine::quoteLine`
     * without the zone and retailer lists.
     */
    private function unitPrice(int $productId, int $qty): int
    {
        $base = ProductBasePrice::query()->where('product_id', $productId)->first();
        if ($base === null) {
            return 0;
        }

        $tier = ProductQtyTier::query()
            ->where('product_id', $productId)
            ->where('from_qty', '<=', $qty)
            ->where(fn ($q) => $q->whereNull('to_qty')->orWhere('to_qty', '>=', $qty))
            ->orderByDesc('from_qty')
            ->first();

        return $tier !== null ? (int) $tier->price : (int) $base->base_price;
    }

    /** The channel manager `DatabaseSeeder` creates, as the actor on channel-side stages. */
    private function managerId(): ?int
    {
        $id = ChannelUser::query()->where('phone', '+963900000001')->value('id');

        return $id !== null ? (int) $id : null;
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
