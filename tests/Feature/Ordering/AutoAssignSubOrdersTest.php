<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('auto-assigns a confirmed sub-order to the on-duty rep covering its zone', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $rep = AppSurface::rep($channel, $refs);
    app(RepDutyLookup::class)->setDuty($rep->id, true);

    $subId = (int) DB::table('sub_orders')->insertGetId([
        'order_id' => DB::table('orders')->insertGetId([
            'retailer_id' => 1,
            'source' => 'app',
            'order_no' => 'ORD-AUTO-1',
            'status' => 'confirmed',
            'currency' => 'SYP',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'channel_id' => $channel->id,
        'retailer_id' => 1,
        'zone_id' => $refs['zone']->id,
        'source' => 'retailer_app',
        'sub_order_no' => 'SO-AUTO-1',
        'status' => SubOrderStatus::Confirmed->value,
        'subtotal' => 10_000,
        'discount' => 0,
        'total' => 10_000,
        'currency_code' => 'SYP',
        'fx_rate' => Money::FX_UNIT,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $response = $this->postJson('/api/v1/channel/sub-orders/assign', [
        'sub_order_ids' => [$subId],
        'mode' => 'auto',
    ]);

    CatalogAssert::ok($response);
    expect($response->json('data.assigned'))->toContain($subId)
        ->and((int) DB::table('sub_orders')->where('id', $subId)->value('rep_id'))->toBe($rep->id)
        ->and((string) DB::table('sub_orders')->where('id', $subId)->value('status'))->toBe(SubOrderStatus::Assigned->value);
});

it('422s auto-assign when no on-duty rep covers the zone', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    AppSurface::rep($channel, $refs); // off duty

    $subId = (int) DB::table('sub_orders')->insertGetId([
        'order_id' => DB::table('orders')->insertGetId([
            'retailer_id' => 1,
            'source' => 'app',
            'order_no' => 'ORD-AUTO-2',
            'status' => 'confirmed',
            'currency' => 'SYP',
            'created_at' => now(),
            'updated_at' => now(),
        ]),
        'channel_id' => $channel->id,
        'retailer_id' => 1,
        'zone_id' => $refs['zone']->id,
        'source' => 'retailer_app',
        'sub_order_no' => 'SO-AUTO-2',
        'status' => SubOrderStatus::Confirmed->value,
        'subtotal' => 10_000,
        'discount' => 0,
        'total' => 10_000,
        'currency_code' => 'SYP',
        'fx_rate' => Money::FX_UNIT,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    CatalogAssert::error(
        $this->postJson('/api/v1/channel/sub-orders/assign', [
            'sub_order_ids' => [$subId],
            'mode' => 'auto',
        ]),
        422,
        'validation_failed',
    );
});
