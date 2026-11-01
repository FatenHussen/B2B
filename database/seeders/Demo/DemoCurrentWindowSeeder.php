<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use Illuminate\Support\Carbon;
use Laravel\Telescope\Telescope;
use Modules\Catalog\Domain\Models\Product;
use Modules\Catalog\Domain\Models\ProductUnitFactor;
use Modules\Content\Domain\Models\Slider;
use Modules\Core\Support\Tenant;
use Modules\Fulfillment\Domain\Enums\HandoverStatus;
use Modules\Fulfillment\Domain\Models\Handover;
use Modules\Fulfillment\Domain\Models\HandoverItem;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Loyalty\Domain\Models\LoyaltyAccount;
use Modules\Notification\Domain\Models\AppNotification;
use Modules\Ordering\Domain\Enums\OrderSource;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\Order;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Ordering\Domain\Models\SubOrderEvent;
use Modules\Ordering\Domain\Models\SubOrderLine;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Tenancy\Domain\Models\Warehouse;

/**
 * Puts the generated history on the current calendar, then fills the screens that
 * stay empty even after orders exist: home sliders, loyalty, carton factors,
 * the rep inbox, one postponed order, and one warehouse receipt waiting on the rep.
 *
 * Safe to run twice. Live `SO-{id}` rows from the app are not moved.
 */
final class DemoCurrentWindowSeeder extends DemoSeeder
{
    public function run(): void
    {
        if (class_exists(Telescope::class)) {
            Telescope::stopRecording();
        }

        foreach ($this->catalogChannelIds() as $channelId) {
            Tenant::as($channelId, function (): void {
                app(DemoOrderHistorySeeder::class)->alignWindow();
                $this->seedSliders();
                $this->seedUnitFactors();
                $this->seedRepSurface();
            });
        }
    }

    private function seedSliders(): void
    {
        Slider::query()->firstOrCreate(
            ['name' => 'الأكثر طلباً'],
            [
                'source' => 'algorithm',
                'algorithm' => 'best_selling',
                'placements' => ['home'],
                'items_count' => 8,
                'show_all_button' => true,
            ],
        );
        Slider::query()->firstOrCreate(
            ['name' => 'العروض'],
            [
                'source' => 'offers',
                'placements' => ['home'],
                'items_count' => 8,
                'show_all_button' => true,
            ],
        );
    }

    private function seedUnitFactors(): void
    {
        $piece = SaleUnit::query()->where('name', 'قطعة')->value('id');
        $carton = SaleUnit::query()->where('name', 'كرتونة')->value('id');
        if ($piece === null || $carton === null) {
            return;
        }

        Product::query()
            ->where('sale_unit_id', (int) $piece)
            ->orderBy('id')
            ->each(function (Product $product) use ($carton, $piece): void {
                $multiple = (int) $product->order_multiple;
                ProductUnitFactor::query()->firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'from_unit_id' => (int) $carton,
                        'to_unit_id' => (int) $piece,
                    ],
                    ['factor' => $multiple > 1 ? $multiple : 12],
                );
            });
    }

    private function seedRepSurface(): void
    {
        $warehouseId = (int) Warehouse::query()->orderBy('id')->value('id');
        $retailer = RetailerProfile::query()
            ->whereIn('zone_id', $this->coveredZoneIds())
            ->where('status', ProfileStatus::Active)
            ->orderBy('id')
            ->first();
        $product = Product::query()->where('status', 'active')->orderBy('id')->first();

        foreach (RepProfile::query()->where('channel_id', $this->channelId())->orderBy('id')->get() as $profile) {
            $repUserId = (int) $profile->app_user_id;

            LoyaltyAccount::query()->firstOrCreate(
                ['owner_kind' => 'rep', 'owner_id' => $repUserId],
                ['balance' => 350, 'tier' => 'bronze'],
            );

            AppNotification::query()->firstOrCreate(
                [
                    'recipient_kind' => 'rep',
                    'recipient_id' => $repUserId,
                    'title' => 'طلبات بانتظار التسليم',
                ],
                [
                    'body' => 'في طلبات على الطريق. افتح تسليم الطلبات بعد تأكيد العهدة من المستودع.',
                    'icon' => 'orders',
                    'sent_at' => now(),
                ],
            );

            if ($retailer !== null && $product !== null) {
                $this->seedPostponed($repUserId, $retailer, $product);
            }
            if ($warehouseId > 0) {
                $this->seedReceipt($repUserId, $warehouseId);
            }
        }
    }

    private function seedPostponed(int $repUserId, RetailerProfile $retailer, Product $product): void
    {
        $number = 'SO-SCH-'.$repUserId;
        if (SubOrder::query()->where('sub_order_no', $number)->exists()) {
            return;
        }

        $when = Carbon::now('Asia/Damascus')->addDay()->setTime(10, 0);
        $qty = max(1, (int) $product->min_order_qty);
        $unit = 12000;
        $total = $unit * $qty;

        $order = Order::query()->create([
            'retailer_id' => $retailer->id,
            'source' => OrderSource::RepApp,
            'order_no' => 'ORD-SCH-'.$repUserId,
            'status' => 'pending',
            'currency' => 'SYP',
            'client_created_at' => $when,
            'offline_created' => false,
        ]);

        $sub = SubOrder::query()->create([
            'order_id' => $order->id,
            'retailer_id' => $retailer->id,
            'zone_id' => $retailer->zone_id,
            'source' => OrderSource::RepApp,
            'sub_order_no' => $number,
            'status' => SubOrderStatus::Postponed,
            'rep_id' => $repUserId,
            'scheduled_at' => $when,
            'subtotal' => $total,
            'discount' => 0,
            'total' => $total,
            'currency_code' => 'SYP',
        ]);

        SubOrderLine::query()->create([
            'sub_order_id' => $sub->id,
            'product_id' => $product->id,
            'qty' => $qty,
            'unit_price' => $unit,
            'discount' => 0,
            'line_total' => $total,
        ]);

        SubOrderEvent::query()->insert([
            [
                'sub_order_id' => $sub->id,
                'stage' => SubOrderStatus::Pending->value,
                'at' => $when->copy()->subHour(),
                'actor_type' => AppUser::class,
                'actor_id' => $repUserId,
            ],
            [
                'sub_order_id' => $sub->id,
                'stage' => SubOrderStatus::Postponed->value,
                'at' => $when->copy()->subMinutes(30),
                'actor_type' => AppUser::class,
                'actor_id' => $repUserId,
            ],
        ]);
    }

    private function seedReceipt(int $repUserId, int $warehouseId): void
    {
        $assigned = SubOrder::query()
            ->where('rep_id', $repUserId)
            ->where('status', SubOrderStatus::Assigned)
            ->orderByDesc('id')
            ->first();

        if ($assigned === null || HandoverItem::query()->where('sub_order_id', $assigned->id)->exists()) {
            return;
        }

        $handover = Handover::query()->create([
            'warehouse_id' => $warehouseId,
            'rep_id' => $repUserId,
            'status' => HandoverStatus::AwaitingRepConfirm,
            'temp_code' => '4821',
            'opened_at' => now(),
        ]);
        HandoverItem::query()->create([
            'handover_id' => $handover->id,
            'sub_order_id' => $assigned->id,
        ]);
    }
}
