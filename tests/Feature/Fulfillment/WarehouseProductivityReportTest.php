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
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * @return array{user: WarehouseUser, warehouse: Warehouse, channel: SupplyChannel, refs: array<string, mixed>}
 */
function whProductivitySurface(): array
{
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $warehouse = Warehouse::query()->create([
        'channel_id' => $channel->id,
        'name' => 'مستودع التقارير',
        'status' => WarehouseStatus::Active,
    ]);
    $user = WarehouseUser::factory()->create();
    $user->assignRole('warehouse_keeper');
    WarehouseDevice::query()->create([
        'device_token_hash' => hash('sha256', 'wh-productivity-token'),
        'pin_hash' => Hash::make('1234'),
        'warehouse_id' => $warehouse->id,
        'channel_id' => $channel->id,
        'warehouse_user_id' => $user->id,
        'label' => 'productivity',
    ]);

    return compact('user', 'warehouse', 'channel', 'refs');
}

it('returns productivity kpis with pick accuracy in basis points', function () {
    $world = whProductivitySurface();
    $shop = AppSurface::retailer($world['refs']);
    $rep = AppSurface::rep($world['channel'], $world['refs']);
    [$productId] = AppSurface::productWithBrand($this, $world['channel'], $world['refs'], 'WH-PROD-1');
    $subOrderId = AppSurface::subOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        'confirmed',
        $rep->id,
        $productId,
    );

    Tenant::as($world['channel']->id, function () use ($world, $subOrderId, $productId) {
        $list = PickingList::query()->create([
            'sub_order_id' => $subOrderId,
            'warehouse_id' => $world['warehouse']->id,
            'channel_id' => $world['channel']->id,
            'status' => PickingStatus::Packed,
            'due_at' => now()->addHours(2),
        ]);
        PickingLine::query()->create([
            'picking_list_id' => $list->id,
            'product_id' => $productId,
            'variant_id' => null,
            'qty_required' => 10,
            'qty_picked' => 9,
            'location_id' => null,
            'barcode' => '6291000PROD01',
            'manual' => false,
            'shortage_reason' => 'shelf empty',
        ]);
        $list->forceFill(['updated_at' => now()])->save();
    });

    Sanctum::actingAs($world['user'], ['*'], 'warehouse');

    $today = now('Asia/Damascus')->toDateString();
    $response = $this->getJson("/api/v1/warehouse/reports/productivity?date_from={$today}&date_to={$today}");
    CatalogAssert::ok($response);

    expect($response->json('data.kpis.lists_completed'))->toBe(1)
        ->and($response->json('data.kpis.packs_completed'))->toBe(1)
        ->and($response->json('data.kpis.qty_required'))->toBe(10)
        ->and($response->json('data.kpis.qty_picked'))->toBe(9)
        ->and($response->json('data.kpis.pick_accuracy_bps'))->toBe(9000)
        ->and($response->json('data.kpis.shortage_lines'))->toBe(1)
        ->and($response->json('data.daily'))->toHaveCount(1)
        ->and($response->json('data.daily.0.date'))->toBe($today);
})->group('fulfillment');

it('returns zero kpis when the range has no activity', function () {
    $world = whProductivitySurface();
    Sanctum::actingAs($world['user'], ['*'], 'warehouse');

    $response = $this->getJson('/api/v1/warehouse/reports/productivity?date_from=2020-01-01&date_to=2020-01-07');
    CatalogAssert::ok($response);

    expect($response->json('data.kpis.lists_completed'))->toBe(0)
        ->and($response->json('data.kpis.pick_accuracy_bps'))->toBe(0)
        ->and($response->json('data.daily'))->toBe([]);
})->group('fulfillment');
