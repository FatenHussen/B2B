<?php

declare(strict_types=1);

/**
 * BF-WH-041 — EP-WH-041A–C warehouse offline pick/pack sync.
 */

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Support\Tenant;
use Modules\Fulfillment\Domain\Enums\PickingStatus;
use Modules\Fulfillment\Domain\Models\PickingLine;
use Modules\Fulfillment\Domain\Models\PickingList;
use Modules\Fulfillment\Domain\Models\WarehouseSyncOperation;
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
function whOfflineSurface(): array
{
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $warehouse = Warehouse::query()->create([
        'channel_id' => $channel->id,
        'name' => 'مستودع المزامنة',
        'status' => WarehouseStatus::Active,
    ]);
    $user = WarehouseUser::factory()->create();
    $user->assignRole('warehouse_keeper');
    WarehouseDevice::query()->create([
        'device_token_hash' => hash('sha256', 'wh-sync-token'),
        'pin_hash' => Hash::make('1234'),
        'warehouse_id' => $warehouse->id,
        'channel_id' => $channel->id,
        'warehouse_user_id' => $user->id,
        'label' => 'sync-device',
    ]);

    return compact('user', 'warehouse', 'channel', 'refs');
}

it('applies offline pick.scan, replays duplicate, and conflicts on second complete', function () {
    $world = whOfflineSurface();
    $shop = AppSurface::retailer($world['refs']);
    [$productId] = AppSurface::productWithBrand($this, $world['channel'], $world['refs'], 'WH-SYNC-1');
    $subOrderId = AppSurface::subOrder(
        $world['channel'],
        $world['refs'],
        AppSurface::retailerId($shop),
        'confirmed',
        null,
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
            'barcode' => '6291000SYNC01',
        ]);

        return $list;
    });

    Sanctum::actingAs($world['user'], ['*'], 'warehouse');
    $headers = ['X-Device-Id' => 'wh-offline-device-1'];

    $scan = $this->postJson('/api/v1/warehouse/sync/push', [
        'operations' => [[
            'client_op_id' => 'op_scan_1',
            'type' => 'pick.scan',
            'payload' => [
                'picking_list_id' => $list->id,
                'barcode' => '6291000SYNC01',
                'qty' => 2,
            ],
            'created_at' => '2026-03-01T09:05:00+03:00',
        ]],
    ], $headers);
    CatalogAssert::ok($scan);
    expect($scan->json('data.results.0.status'))->toBe('applied')
        ->and($scan->json('data.results.0.server_id'))->toBe($list->id)
        ->and($scan->json('data.results.0.error'))->toBeNull();

    $dup = $this->postJson('/api/v1/warehouse/sync/push', [
        'operations' => [[
            'client_op_id' => 'op_scan_1',
            'type' => 'pick.scan',
            'payload' => [
                'picking_list_id' => $list->id,
                'barcode' => '6291000SYNC01',
                'qty' => 2,
            ],
        ]],
    ], $headers);
    CatalogAssert::ok($dup);
    expect($dup->json('data.results.0.status'))->toBe('duplicate')
        ->and($dup->json('data.results.0.client_op_id'))->toBe('op_scan_1');

    $complete = $this->postJson('/api/v1/warehouse/sync/push', [
        'operations' => [[
            'client_op_id' => 'op_complete_1',
            'type' => 'pick.complete',
            'payload' => ['picking_list_id' => $list->id],
        ]],
    ], $headers);
    CatalogAssert::ok($complete);
    expect($complete->json('data.results.0.status'))->toBe('applied');

    $again = $this->postJson('/api/v1/warehouse/sync/push', [
        'operations' => [[
            'client_op_id' => 'op_complete_2',
            'type' => 'pick.complete',
            'payload' => ['picking_list_id' => $list->id],
        ]],
    ], $headers);
    CatalogAssert::ok($again);
    expect($again->json('data.results.0.status'))->toBeIn(['conflict', 'failed']);

    $status = $this->getJson('/api/v1/warehouse/sync/status', $headers);
    CatalogAssert::ok($status, ['pending_server_side', 'last_push_at', 'conflicts']);
    expect($status->json('data.last_push_at'))->not->toBeNull();

    if ($again->json('data.results.0.status') === 'conflict') {
        expect($status->json('data.pending_server_side'))->toBeGreaterThanOrEqual(1)
            ->and($status->json('data.conflicts.0.client_op_id'))->toBe('op_complete_2');

        $conflictId = $status->json('data.conflicts.0.conflict_id');
        $resolved = $this->postJson('/api/v1/warehouse/sync/resolve-conflict', [
            'conflict_id' => $conflictId,
            'resolution' => 'server_wins',
        ], $headers);
        CatalogAssert::ok($resolved);
        expect($resolved->json('data.success'))->toBeTrue();
        $statusAfter = Tenant::as($world['channel']->id, fn () => WarehouseSyncOperation::query()->find($conflictId)?->status);
        expect($statusAfter)->toBe('discarded');
    }
});
