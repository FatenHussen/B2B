<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Fulfillment\Domain\Enums\HandoverStatus;
use Modules\Fulfillment\Domain\Models\Handover;
use Modules\Fulfillment\Domain\Models\HandoverItem;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('lists warehouse receipts with the sub-order id', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $id = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'accepted', $rep->id);

    $handover = Handover::query()->create([
        'warehouse_id' => 1,
        'rep_id' => $rep->id,
        'status' => HandoverStatus::AwaitingRepConfirm,
        'temp_code' => '7391',
        'opened_at' => now(),
    ]);
    HandoverItem::query()->create([
        'handover_id' => $handover->id,
        'sub_order_id' => $id,
    ]);
    Sanctum::actingAs($rep, ['*'], 'app');

    $response = $this->getJson('/api/v1/app/rep/warehouse-receipts');
    CatalogAssert::ok($response);
    expect($response->json('data.count'))->toBe(1)
        ->and($response->json('data.orders.0.sub_order_id'))->toBe($id)
        ->and($response->json('data.orders.0.handover_id'))->toBe($handover->id);
});
