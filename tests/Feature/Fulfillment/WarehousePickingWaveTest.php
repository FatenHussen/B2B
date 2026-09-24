<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\WarehouseDevice;
use Modules\Identity\Domain\Models\WarehouseUser;
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

it('creates a picking wave and returns merged lines', function () {
    $world = whSurface();
    $shop = AppSurface::retailer($world['refs']);
    [$productId] = AppSurface::productWithBrand($this, $world['channel'], $world['refs'], 'WH-WAVE-1');

    $subA = AppSurface::subOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        'confirmed',
        null,
        $productId,
    );
    $subB = AppSurface::subOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        'confirmed',
        null,
        $productId,
    );

    Sanctum::actingAs($world['user'], ['*'], 'warehouse');

    $create = $this->postJson('/api/v1/warehouse/picking-waves', [
        'sub_order_ids' => [$subA, $subB],
        'assigned_to' => null,
    ]);
    CatalogAssert::ok($create);
    expect($create->json('data.status'))->toBe('open')
        ->and($create->json('data.picking_list_ids'))->toHaveCount(2)
        ->and($create->json('data.id'))->toBeInt();

    $waveId = (int) $create->json('data.id');
    $listIds = $create->json('data.picking_list_ids');

    $show = $this->getJson("/api/v1/warehouse/picking-waves/{$waveId}");
    CatalogAssert::ok($show);
    expect($show->json('data.id'))->toBe($waveId)
        ->and($show->json('data.status'))->toBe('open')
        ->and($show->json('data.assigned_to'))->toBeNull()
        ->and($show->json('data.picking_list_ids'))->toEqualCanonicalizing($listIds)
        ->and($show->json('data.lines'))->toHaveCount(1)
        ->and($show->json('data.lines.0.product_id'))->toBe($productId)
        ->and($show->json('data.lines.0.qty_required'))->toBe(2)
        ->and($show->json('data.lines.0.sub_order_ids'))->toEqualCanonicalizing([$subA, $subB])
        ->and($show->json('data.lines.0'))->toHaveKeys([
            'product_id', 'variant_id', 'barcode', 'qty_required', 'qty_picked', 'location_id', 'sub_order_ids',
        ]);
});
