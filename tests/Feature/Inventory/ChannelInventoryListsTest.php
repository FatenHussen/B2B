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

function inventoryManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

/**
 * @return array{warehouse_id: int, product_id: int}
 */
function inventoryBalance(int $channelId, string $sku, int $onHand = 40): array
{
    $warehouseId = (int) DB::table('warehouses')->insertGetId([
        'channel_id' => $channelId,
        'name' => 'مستودع '.$sku,
        'status' => WarehouseStatus::Active->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $productId = (int) DB::table('products')->insertGetId([
        'supply_channel_id' => $channelId,
        'name_ar' => 'زيت '.$sku,
        'sku' => $sku,
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
        'reserved' => 5,
        'in_transit' => 0,
        'damaged' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_movements')->insert([
        'supply_channel_id' => $channelId,
        'warehouse_id' => $warehouseId,
        'product_id' => $productId,
        'variant_id' => 0,
        'type' => 'adjust',
        'qty_delta' => $onHand,
        'qty_before' => 0,
        'qty_after' => $onHand,
        'at' => now(),
    ]);

    return ['warehouse_id' => $warehouseId, 'product_id' => $productId];
}

it('lists stock levels without a 500', function () {
    $channel = SupplyChannel::factory()->create();
    $row = inventoryBalance($channel->id, 'LVL-1');
    Sanctum::actingAs(inventoryManager($channel), ['*'], 'channel');

    $response = $this->getJson(
        '/api/v1/channel/inventory/levels?filter[warehouse_id]='.$row['warehouse_id'].'&filter[product_id]='.$row['product_id']
    );
    CatalogAssert::ok($response);

    expect($response->json('data.0.product.id'))->toBe($row['product_id'])
        ->and($response->json('data.0.warehouse.id'))->toBe($row['warehouse_id'])
        ->and($response->json('data.0.available'))->toBe(35)
        ->and($response->json('data.0.reserved'))->toBe(5)
        ->and($response->json('data.0'))->toHaveKeys(['variant', 'in_transit', 'damaged']);
});

it('lists stock movements without a 500', function () {
    $channel = SupplyChannel::factory()->create();
    $row = inventoryBalance($channel->id, 'MOV-1', 12);
    Sanctum::actingAs(inventoryManager($channel), ['*'], 'channel');

    $response = $this->getJson('/api/v1/channel/inventory/movements?filter[product_id]='.$row['product_id']);
    CatalogAssert::ok($response);

    expect($response->json('data.0'))->toHaveKeys(['id', 'at', 'type', 'qty_before', 'qty_after'])
        ->and($response->json('data.0.type'))->toBe('adjust')
        ->and($response->json('data.0.qty_after'))->toBe(12);
});

it('hides another channel stock rows from levels and movements', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $mine = inventoryBalance($own->id, 'OWN-1');
    $theirs = inventoryBalance($foreign->id, 'FOR-1');

    Sanctum::actingAs(inventoryManager($own), ['*'], 'channel');

    $levels = $this->getJson('/api/v1/channel/inventory/levels')->assertOk();
    $levelProducts = collect($levels->json('data'))->pluck('product.id')->all();

    $movements = $this->getJson('/api/v1/channel/inventory/movements')->assertOk();
    $movementCount = $movements->json('meta.total');

    expect($levelProducts)->toContain($mine['product_id'])->not->toContain($theirs['product_id'])
        ->and($movementCount)->toBe(1);
})->group('tenancy');
