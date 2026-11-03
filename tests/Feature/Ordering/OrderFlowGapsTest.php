<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Core\Support\Tenant;
use Modules\Fulfillment\Domain\Enums\PickingStatus;
use Modules\Fulfillment\Domain\Models\PickingLine;
use Modules\Fulfillment\Domain\Models\PickingList;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\WarehouseDevice;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Ordering\Domain\Enums\OrderSource;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;
use Tests\TestCase;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * @return array{channel: SupplyChannel, warehouse: Warehouse, whUser: WarehouseUser, refs: array<string, mixed>, productId: int}
 */
function flowWorld(TestCase $t): array
{
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $warehouse = Warehouse::query()->create([
        'channel_id' => $channel->id,
        'name' => 'مستودع الفجوات',
        'status' => WarehouseStatus::Active,
    ]);
    $whUser = WarehouseUser::factory()->create();
    $whUser->assignRole('warehouse_keeper');
    WarehouseDevice::query()->create([
        'device_token_hash' => hash('sha256', 'flow-gaps-token-'.uniqid()),
        'pin_hash' => Hash::make('1234'),
        'warehouse_id' => $warehouse->id,
        'channel_id' => $channel->id,
        'warehouse_user_id' => $whUser->id,
        'label' => 'flow',
    ]);
    [$productId] = AppSurface::productWithBrand($t, $channel, $refs, 'FLOW-GAP-'.uniqid());
    DB::table('stock_balances')->insert([
        'supply_channel_id' => $channel->id,
        'warehouse_id' => $warehouse->id,
        'product_id' => $productId,
        'variant_id' => 0,
        'on_hand' => 100,
        'reserved' => 0,
        'in_transit' => 0,
        'damaged' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('channel', 'warehouse', 'whUser', 'refs', 'productId');
}

function flowManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

function flowSubOrder(
    SupplyChannel $channel,
    array $refs,
    int $retailerId,
    int $productId,
    string $source,
    int $qty,
    ?int $repId = null,
    ?DateTimeInterface $createdAt = null,
): int {
    $at = $createdAt ?? now();
    $orderId = (int) DB::table('orders')->insertGetId([
        'retailer_id' => $retailerId,
        'source' => $source,
        'order_no' => 'ORD-FLOW-'.uniqid(),
        'status' => 'pending',
        'currency' => 'SYP',
        'created_at' => $at,
        'updated_at' => $at,
    ]);
    $id = (int) DB::table('sub_orders')->insertGetId([
        'order_id' => $orderId,
        'channel_id' => $channel->id,
        'retailer_id' => $retailerId,
        'zone_id' => $refs['zone']->id,
        'source' => $source,
        'sub_order_no' => 'SO-FLOW-'.uniqid(),
        'status' => SubOrderStatus::Pending->value,
        'subtotal' => 12000 * $qty,
        'discount' => 0,
        'total' => 12000 * $qty,
        'rep_id' => $repId,
        'currency_code' => 'SYP',
        'fx_rate' => Money::FX_UNIT,
        'created_at' => $at,
        'updated_at' => $at,
    ]);
    DB::table('sub_order_lines')->insert([
        'sub_order_id' => $id,
        'product_id' => $productId,
        'qty' => $qty,
        'unit_price' => 12000,
        'discount' => 0,
        'line_total' => 12000 * $qty,
        'created_at' => $at,
        'updated_at' => $at,
    ]);

    return $id;
}

it('lets the channel confirm when order_approver is channel and rejects warehouse confirm', function () {
    $world = flowWorld($this);
    $shop = AppSurface::retailer($world['refs']);
    $subId = flowSubOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        $world['productId'],
        OrderSource::RetailerApp->value,
        2,
    );

    Sanctum::actingAs($world['whUser'], ['*'], 'warehouse');
    CatalogAssert::error(
        $this->postJson("/api/v1/warehouse/sub-orders/{$subId}/confirm", []),
        409,
        'illegal_transition',
    );

    Sanctum::actingAs(flowManager($world['channel']), ['*'], 'channel');
    $ok = $this->postJson("/api/v1/channel/sub-orders/{$subId}/confirm", []);
    CatalogAssert::ok($ok);
    expect($ok->json('data.status'))->toBe(SubOrderStatus::Confirmed->value)
        ->and((string) DB::table('sub_orders')->where('id', $subId)->value('status'))->toBe(SubOrderStatus::Confirmed->value);
});

it('lets the warehouse confirm when order_approver is warehouse and auto-assigns rep-sourced orders', function () {
    $world = flowWorld($this);
    $world['channel']->forceFill([
        'settings' => array_merge($world['channel']->settings ?? [], ['order_approver' => 'warehouse']),
    ])->save();
    $shop = AppSurface::retailer($world['refs']);
    $rep = AppSurface::rep($world['channel'], $world['refs']);
    $subId = flowSubOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        $world['productId'],
        OrderSource::RepApp->value,
        2,
        $rep->id,
    );

    Sanctum::actingAs(flowManager($world['channel']), ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson("/api/v1/channel/sub-orders/{$subId}/confirm", []),
        409,
        'illegal_transition',
    );

    Sanctum::actingAs($world['whUser'], ['*'], 'warehouse');
    $list = $this->getJson('/api/v1/warehouse/sub-orders?filter[status]=pending');
    CatalogAssert::ok($list);
    expect(collect($list->json('data'))->pluck('id')->all())->toContain($subId);

    $ok = $this->postJson("/api/v1/warehouse/sub-orders/{$subId}/confirm", []);
    CatalogAssert::ok($ok);
    expect($ok->json('data.status'))->toBe(SubOrderStatus::Assigned->value)
        ->and((int) DB::table('sub_orders')->where('id', $subId)->value('rep_id'))->toBe($rep->id)
        ->and(DB::table('sub_order_assignments')->where('sub_order_id', $subId)->where('rep_id', $rep->id)->exists())->toBeTrue();
});

it('allocates a SKU shortage FIFO and lets the retailer accept or cancel', function () {
    $world = flowWorld($this);
    $shop = AppSurface::retailer($world['refs']);
    $retailerId = AppSurface::retailerId($shop);
    $first = flowSubOrder(
        $world['channel'],
        $world['refs'],
        $retailerId,
        $world['productId'],
        OrderSource::RetailerApp->value,
        10,
        null,
        now()->subHour(),
    );
    $second = flowSubOrder(
        $world['channel'],
        $world['refs'],
        $retailerId,
        $world['productId'],
        OrderSource::RetailerApp->value,
        10,
        null,
        now(),
    );

    Sanctum::actingAs(flowManager($world['channel']), ['*'], 'channel');
    CatalogAssert::ok($this->postJson("/api/v1/channel/sub-orders/{$first}/confirm", []));
    CatalogAssert::ok($this->postJson("/api/v1/channel/sub-orders/{$second}/confirm", []));

    Sanctum::actingAs($world['whUser'], ['*'], 'warehouse');
    $alloc = $this->postJson('/api/v1/warehouse/stock-shortages', [
        'product_id' => $world['productId'],
        'variant_id' => null,
        'qty_available' => 12,
        'reason' => 'out_of_stock',
    ]);
    CatalogAssert::ok($alloc);
    $lines = collect($alloc->json('data.lines'));
    expect($lines->firstWhere('sub_order_id', $first)['qty_allocated'])->toBe(10)
        ->and($lines->firstWhere('sub_order_id', $second)['qty_allocated'])->toBe(2)
        ->and($lines->firstWhere('sub_order_id', $second)['status'])->toBe('awaiting_retailer');

    $listId = (int) DB::table('picking_lists')->where('sub_order_id', $second)->value('id');
    expect($listId)->toBeGreaterThan(0);
    Tenant::as($world['channel']->id, function () use ($listId, $world): void {
        $list = PickingList::query()->findOrFail($listId);
        $list->forceFill(['status' => PickingStatus::ToPick->value])->save();
        if (! PickingLine::query()->where('picking_list_id', $listId)->exists()) {
            PickingLine::query()->create([
                'picking_list_id' => $listId,
                'product_id' => $world['productId'],
                'variant_id' => null,
                'qty_required' => 2,
                'qty_picked' => 2,
                'location_id' => null,
                'barcode' => 'x',
            ]);
        } else {
            PickingLine::query()->where('picking_list_id', $listId)->update(['qty_picked' => 2]);
        }
    });
    CatalogAssert::error(
        $this->postJson("/api/v1/warehouse/picking-lists/{$listId}/complete", []),
        409,
        'operation_in_progress',
    );

    Sanctum::actingAs($shop, ['*'], 'app');
    $show = $this->getJson("/api/v1/app/retailer/orders/{$second}/shortage");
    CatalogAssert::ok($show);
    expect($show->json('data.status'))->toBe('awaiting_decision');

    $accept = $this->postJson("/api/v1/app/retailer/orders/{$second}/shortage-decision", [
        'decision' => 'accept_partial',
    ]);
    CatalogAssert::ok($accept);
    expect($accept->json('data.decision'))->toBe('accept_partial')
        ->and((int) DB::table('sub_order_lines')->where('sub_order_id', $second)->value('qty'))->toBe(2);

    Sanctum::actingAs($world['whUser'], ['*'], 'warehouse');
    CatalogAssert::ok($this->postJson("/api/v1/warehouse/picking-lists/{$listId}/complete", []));
});

it('cancels the whole sub-order when the retailer rejects the shortage allocation', function () {
    $world = flowWorld($this);
    $shop = AppSurface::retailer($world['refs']);
    $retailerId = AppSurface::retailerId($shop);
    $subId = flowSubOrder(
        $world['channel'],
        $world['refs'],
        $retailerId,
        $world['productId'],
        OrderSource::RetailerApp->value,
        5,
    );

    Sanctum::actingAs(flowManager($world['channel']), ['*'], 'channel');
    CatalogAssert::ok($this->postJson("/api/v1/channel/sub-orders/{$subId}/confirm", []));

    Sanctum::actingAs($world['whUser'], ['*'], 'warehouse');
    CatalogAssert::ok($this->postJson('/api/v1/warehouse/stock-shortages', [
        'product_id' => $world['productId'],
        'qty_available' => 2,
        'reason' => 'out_of_stock',
    ]));

    Sanctum::actingAs($shop, ['*'], 'app');
    $cancel = $this->postJson("/api/v1/app/retailer/orders/{$subId}/shortage-decision", [
        'decision' => 'cancel',
    ]);
    CatalogAssert::ok($cancel);
    expect($cancel->json('data.status'))->toBe(SubOrderStatus::Cancelled->value)
        ->and((string) DB::table('sub_orders')->where('id', $subId)->value('status'))->toBe(SubOrderStatus::Cancelled->value);
});

it('allows warehouse assign for a confirmed retailer order', function () {
    $world = flowWorld($this);
    $shop = AppSurface::retailer($world['refs']);
    $rep = AppSurface::rep($world['channel'], $world['refs']);
    app(RepDutyLookup::class)->setDuty($rep->id, true);
    $subId = flowSubOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        $world['productId'],
        OrderSource::RetailerApp->value,
        1,
    );

    Sanctum::actingAs(flowManager($world['channel']), ['*'], 'channel');
    CatalogAssert::ok($this->postJson("/api/v1/channel/sub-orders/{$subId}/confirm", []));

    Sanctum::actingAs($world['whUser'], ['*'], 'warehouse');
    $assign = $this->postJson('/api/v1/warehouse/sub-orders/assign', [
        'sub_order_ids' => [$subId],
        'rep_id' => $rep->id,
        'mode' => 'manual',
    ]);
    CatalogAssert::ok($assign);
    expect($assign->json('data.assigned'))->toContain($subId);
});
