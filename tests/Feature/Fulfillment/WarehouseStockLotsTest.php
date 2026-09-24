<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\StockLedger;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\WarehouseDevice;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Inventory\Domain\Models\StockLot;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * @return array{user: WarehouseUser, warehouse: Warehouse, channel: SupplyChannel, refs: array<string, mixed>}
 */
if (! function_exists('whSurface')) {
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
}

it('lists and adjusts stock lots', function () {
    $world = whSurface();
    [$productId] = AppSurface::productWithBrand($this, $world['channel'], $world['refs'], 'WH-LOT-1');

    $lotId = Tenant::as($world['channel']->id, function () use ($world, $productId): int {
        /** @var StockLedger $ledger */
        $ledger = app(StockLedger::class);
        $ledger->receiveLot(
            (int) $world['warehouse']->id,
            $productId,
            null,
            50,
            $world['user'],
            'goods_receipt',
            1,
            'L-TEST-1',
            '2027-06-01',
        );

        $lot = StockLot::query()
            ->where('warehouse_id', $world['warehouse']->id)
            ->where('product_id', $productId)
            ->where('lot_no', 'L-TEST-1')
            ->firstOrFail();

        StockLot::query()->create([
            'warehouse_id' => $world['warehouse']->id,
            'product_id' => $productId,
            'variant_id' => null,
            'lot_no' => 'L-TEST-2',
            'expiry_date' => '2028-01-15',
            'qty' => 20,
        ]);

        return (int) $lot->id;
    });

    Sanctum::actingAs($world['user'], ['*'], 'warehouse');

    $list = $this->getJson('/api/v1/warehouse/stock-lots?product_id='.$productId);
    CatalogAssert::ok($list);
    expect($list->json('data'))->toHaveCount(2)
        ->and($list->json('meta.total'))->toBe(2)
        ->and($list->json('data.0'))->toHaveKeys([
            'id', 'warehouse_id', 'product_id', 'variant_id', 'lot_no', 'expiry_date', 'qty',
        ])
        ->and($list->json('data.0.lot_no'))->toBe('L-TEST-1')
        ->and($list->json('data.0.qty'))->toBe(50);

    $filtered = $this->getJson('/api/v1/warehouse/stock-lots?product_id='.$productId.'&expiry_before=2027-12-31');
    CatalogAssert::ok($filtered);
    expect($filtered->json('data'))->toHaveCount(1)
        ->and($filtered->json('data.0.lot_no'))->toBe('L-TEST-1');

    $up = $this->postJson("/api/v1/warehouse/stock-lots/{$lotId}/adjust", [
        'qty_delta' => 5,
        'reason' => 'count correction',
    ]);
    CatalogAssert::ok($up);
    expect($up->json('data.id'))->toBe($lotId)
        ->and($up->json('data.qty'))->toBe(55)
        ->and($up->json('data.movement_id'))->toBeInt()
        ->and($up->json('data.available'))->toBeInt();

    $down = $this->postJson("/api/v1/warehouse/stock-lots/{$lotId}/adjust", [
        'qty_delta' => -10,
        'reason' => 'count correction down',
    ]);
    CatalogAssert::ok($down);
    expect($down->json('data.qty'))->toBe(45);

    $gone = $this->postJson('/api/v1/warehouse/stock-lots/999999/adjust', [
        'qty_delta' => 1,
        'reason' => 'missing',
    ]);
    expect($gone->status())->toBe(404)
        ->and($gone->json('error.code'))->toBe('not_found');

    $over = $this->postJson("/api/v1/warehouse/stock-lots/{$lotId}/adjust", [
        'qty_delta' => -999,
        'reason' => 'too much',
    ]);
    expect($over->status())->toBe(409)
        ->and($over->json('error.code'))->toBe('insufficient_stock');
});
