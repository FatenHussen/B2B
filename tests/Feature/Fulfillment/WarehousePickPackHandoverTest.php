<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Support\Tenant;
use Modules\Fulfillment\Domain\Enums\PickingStatus;
use Modules\Fulfillment\Domain\Models\PickingLine;
use Modules\Fulfillment\Domain\Models\PickingList;
use Modules\Identity\Domain\Models\WarehouseDevice;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Inventory\Domain\Models\WarehouseLocation;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * @return array{user: WarehouseUser, warehouse: Warehouse, channel: SupplyChannel, refs: array<string, mixed>}
 */
function whSurface(): array
{
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $warehouse = Warehouse::query()->create([
        'channel_id' => $channel->id,
        'name' => 'مستودع الاختبار',
        'status' => WarehouseStatus::Active,
    ]);
    $user = WarehouseUser::factory()->create();
    $user->assignRole('warehouse_keeper');
    WarehouseDevice::query()->create([
        'device_token_hash' => hash('sha256', 'wh-e2e-token'),
        'pin_hash' => Hash::make('1234'),
        'warehouse_id' => $warehouse->id,
        'channel_id' => $channel->id,
        'warehouse_user_id' => $user->id,
        'label' => 'e2e',
    ]);

    return compact('user', 'warehouse', 'channel', 'refs');
}

it('runs pick → pack → handover open and honest return-trip match', function () {
    $world = whSurface();
    $shop = AppSurface::retailer($world['refs']);
    $rep = AppSurface::rep($world['channel'], $world['refs']);
    [$productId] = AppSurface::productWithBrand($this, $world['channel'], $world['refs'], 'WH-E2E-1');
    $subOrderId = AppSurface::subOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        'confirmed',
        $rep->id,
        $productId,
    );

    $list = Tenant::as($world['channel']->id, function () use ($world, $subOrderId, $productId) {
        $list = PickingList::query()->create([
            'sub_order_id' => $subOrderId,
            'warehouse_id' => $world['warehouse']->id,
            'channel_id' => $world['channel']->id,
            'status' => PickingStatus::ToPick,
            'due_at' => now()->addHours(2),
        ]);
        PickingLine::query()->create([
            'picking_list_id' => $list->id,
            'product_id' => $productId,
            'variant_id' => null,
            'qty_required' => 2,
            'qty_picked' => 0,
            'location_id' => null,
            'barcode' => '6291000WH001',
        ]);

        return $list;
    });

    Sanctum::actingAs($world['user'], ['*'], 'warehouse');

    $queues = $this->getJson('/api/v1/warehouse/queues');
    CatalogAssert::ok($queues);
    expect($queues->json('data.queues.to_pick'))->toBeGreaterThanOrEqual(1);

    $scan = $this->postJson("/api/v1/warehouse/picking-lists/{$list->id}/scan", [
        'barcode' => '6291000WH001',
        'qty' => 2,
    ]);
    CatalogAssert::ok($scan);
    expect($scan->json('data.qty_picked'))->toBe(2);

    $completePick = $this->postJson("/api/v1/warehouse/picking-lists/{$list->id}/complete", []);
    CatalogAssert::ok($completePick);
    expect($completePick->json('data.status'))->toBe('to_pack');

    $verify = $this->postJson("/api/v1/warehouse/packing/{$list->id}/verify", [
        'scans' => [['barcode' => '6291000WH001', 'qty' => 2]],
    ]);
    CatalogAssert::ok($verify);
    expect($verify->json('data.mismatches'))->toBe([]);

    $pack = $this->postJson("/api/v1/warehouse/packing/{$list->id}/complete", [
        'packages_count' => 1,
        'total_weight' => 1.5,
        'flags' => [],
    ]);
    CatalogAssert::ok($pack);
    expect($pack->json('data.labels.0.package_no'))->toStartWith('PKG-');

    $handover = $this->postJson('/api/v1/warehouse/handovers', [
        'rep_id' => $rep->id,
        'sub_order_ids' => [$subOrderId],
        'rep_qr' => null,
    ]);
    CatalogAssert::ok($handover);
    $handoverId = (int) $handover->json('data.handover_id');
    expect($handover->json('data.status'))->toBe('awaiting_rep_confirm')
        ->and($handover->json('data.temp_code'))->toBeString();

    $return = $this->postJson("/api/v1/warehouse/handovers/{$handoverId}/return-trip", [
        'undelivered' => [['sub_order_id' => $subOrderId, 'reason' => 'closed_shop']],
    ]);
    CatalogAssert::ok($return);
    expect($return->json('data.restocked'))->toBe([$subOrderId])
        ->and($return->json('data.wallet_matched'))->toBeTrue();

    // Empty undelivered list: nothing restocked, still an honest match.
    $empty = $this->postJson("/api/v1/warehouse/handovers/{$handoverId}/return-trip", [
        'undelivered' => [],
    ]);
    CatalogAssert::ok($empty);
    expect($empty->json('data.wallet_matched'))->toBeTrue()
        ->and($empty->json('data.restocked'))->toBe([]);
});

it('orders picking lines by warehouse location aisle then shelf', function () {
    $world = whSurface();
    $shop = AppSurface::retailer($world['refs']);
    [$productA] = AppSurface::productWithBrand($this, $world['channel'], $world['refs'], 'WH-PATH-A');
    [$productB] = AppSurface::productWithBrand($this, $world['channel'], $world['refs'], 'WH-PATH-B');
    $subOrderId = AppSurface::subOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        'confirmed',
        null,
        $productA,
    );

    $locs = Tenant::as($world['channel']->id, function () use ($world) {
        $late = WarehouseLocation::query()->create([
            'supply_channel_id' => $world['channel']->id,
            'warehouse_id' => $world['warehouse']->id,
            'aisle' => 'B',
            'shelf' => '1',
            'code' => 'B-1',
        ]);
        $early = WarehouseLocation::query()->create([
            'supply_channel_id' => $world['channel']->id,
            'warehouse_id' => $world['warehouse']->id,
            'aisle' => 'A',
            'shelf' => '2',
            'code' => 'A-2',
        ]);

        return compact('late', 'early');
    });

    $list = Tenant::as($world['channel']->id, function () use ($world, $productA, $productB, $locs, $subOrderId) {
        $list = PickingList::query()->create([
            'sub_order_id' => $subOrderId,
            'warehouse_id' => $world['warehouse']->id,
            'channel_id' => $world['channel']->id,
            'status' => PickingStatus::ToPick,
            'due_at' => now()->addHours(2),
        ]);
        // Insert B-aisle first so id order would be wrong.
        PickingLine::query()->create([
            'picking_list_id' => $list->id,
            'product_id' => $productB,
            'variant_id' => null,
            'qty_required' => 1,
            'qty_picked' => 0,
            'location_id' => $locs['late']->id,
            'barcode' => 'PATH-B',
        ]);
        PickingLine::query()->create([
            'picking_list_id' => $list->id,
            'product_id' => $productA,
            'variant_id' => null,
            'qty_required' => 1,
            'qty_picked' => 0,
            'location_id' => $locs['early']->id,
            'barcode' => 'PATH-A',
        ]);

        return $list;
    });

    Sanctum::actingAs($world['user'], ['*'], 'warehouse');
    $show = $this->getJson("/api/v1/warehouse/picking-lists/{$list->id}");
    CatalogAssert::ok($show);
    expect($show->json('data.lines.0.barcode'))->toBe('PATH-A')
        ->and($show->json('data.lines.1.barcode'))->toBe('PATH-B');
});
