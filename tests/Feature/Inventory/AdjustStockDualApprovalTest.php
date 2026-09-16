<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * @return array{warehouse_id: int, product_id: int}
 */
function adjustFixture(int $channelId, int $onHand = 40): array
{
    $warehouseId = (int) DB::table('warehouses')->insertGetId([
        'channel_id' => $channelId,
        'name' => 'مستودع تسوية',
        'status' => WarehouseStatus::Active->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $productId = (int) DB::table('products')->insertGetId([
        'supply_channel_id' => $channelId,
        'name_ar' => 'زيت تسوية',
        'sku' => 'ADJ-1',
        'status' => ProductStatus::Active->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_balances')->insert([
        'supply_channel_id' => $channelId,
        'warehouse_id' => $warehouseId,
        'product_id' => $productId,
        'variant_id' => 0,
        'on_hand' => $onHand,
        'reserved' => 0,
        'in_transit' => 0,
        'damaged' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['warehouse_id' => $warehouseId, 'product_id' => $productId];
}

function adjustManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('does not execute a stock adjust on the first request', function () {
    $channel = SupplyChannel::factory()->create();
    $ids = adjustFixture($channel->id);
    Sanctum::actingAs(adjustManager($channel), ['*'], 'channel');

    $response = $this->postJson('/api/v1/channel/inventory/adjust', [
        'product_id' => $ids['product_id'],
        'warehouse_id' => $ids['warehouse_id'],
        'qty_delta' => -3,
        'reason' => 'جرد دوري',
    ]);
    CatalogAssert::ok($response);

    expect($response->json('data.approval_request_id'))->toBeInt()
        ->and($response->json('meta.requires_dual_approval'))->toBeTrue()
        ->and(DB::table('stock_movements')->count())->toBe(0);
});

it('rejects a creator approving their own adjust', function () {
    $channel = SupplyChannel::factory()->create();
    $ids = adjustFixture($channel->id);
    Sanctum::actingAs(adjustManager($channel), ['*'], 'channel');

    $first = $this->postJson('/api/v1/channel/inventory/adjust', [
        'product_id' => $ids['product_id'],
        'warehouse_id' => $ids['warehouse_id'],
        'qty_delta' => -3,
        'reason' => 'جرد دوري',
    ]);
    CatalogAssert::ok($first);

    CatalogAssert::error(
        $this->postJson('/api/v1/channel/inventory/adjust', [
            'product_id' => $ids['product_id'],
            'warehouse_id' => $ids['warehouse_id'],
            'qty_delta' => -3,
            'reason' => 'جرد دوري',
            'approval_request_id' => $first->json('data.approval_request_id'),
            'approval_reason' => 'اعتماد ذاتي',
        ]),
        403,
        'sod_violation',
    );

    expect(DB::table('stock_movements')->count())->toBe(0);
});

it('executes the adjust after a different user approves', function () {
    $channel = SupplyChannel::factory()->create();
    $ids = adjustFixture($channel->id);
    $requester = adjustManager($channel);
    $approver = adjustManager($channel);

    Sanctum::actingAs($requester, ['*'], 'channel');
    $first = $this->postJson('/api/v1/channel/inventory/adjust', [
        'product_id' => $ids['product_id'],
        'warehouse_id' => $ids['warehouse_id'],
        'qty_delta' => -3,
        'reason' => 'جرد دوري',
    ]);
    CatalogAssert::ok($first);

    Sanctum::actingAs($approver, ['*'], 'channel');
    $second = $this->postJson('/api/v1/channel/inventory/adjust', [
        'product_id' => $ids['product_id'],
        'warehouse_id' => $ids['warehouse_id'],
        'qty_delta' => -3,
        'reason' => 'جرد دوري',
        'approval_request_id' => $first->json('data.approval_request_id'),
        'approval_reason' => 'مراجعة مكتملة',
    ]);
    CatalogAssert::ok($second);

    expect($second->json('data.movement_id'))->toBeInt()
        ->and($second->json('data.available'))->toBe(37)
        ->and(DB::table('stock_movements')->count())->toBe(1);
});
