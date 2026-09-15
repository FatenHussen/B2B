<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function inboxManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

function inboxSubOrder(int $channelId, string $no, string $status = 'pending'): int
{
    $orderId = (int) DB::table('orders')->insertGetId([
        'retailer_id' => 1,
        'source' => 'app',
        'order_no' => 'ORD-'.$no,
        'status' => $status,
        'currency' => 'SYP',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) DB::table('sub_orders')->insertGetId([
        'order_id' => $orderId,
        'channel_id' => $channelId,
        'retailer_id' => 1,
        'zone_id' => 12,
        'source' => 'retailer_app',
        'sub_order_no' => $no,
        'status' => $status,
        'subtotal' => 48_000,
        'discount' => 0,
        'total' => 48_000,
        'currency_code' => 'SYP',
        'fx_rate' => Money::FX_UNIT,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('lists the channel inbox without a 500 and accepts the catalog filters', function () {
    $channel = SupplyChannel::factory()->create();
    $ownId = inboxSubOrder($channel->id, 'SO-OWN');
    Sanctum::actingAs(inboxManager($channel), ['*'], 'channel');

    $response = $this->getJson('/api/v1/channel/sub-orders?filter[status]=pending&filter[zone_id]=12');
    CatalogAssert::ok($response);
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($response->json('meta'))->toHaveKeys(['page', 'per_page', 'total'])
        ->and($ids)->toContain($ownId)
        ->and($response->json('data.0'))->toHaveKeys(['id', 'sub_order_no', 'status', 'zone_id', 'total']);
});

it('hides another channel sub-order from the inbox and 404s it by id', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $ownId = inboxSubOrder($own->id, 'SO-MINE');
    $foreignId = inboxSubOrder($foreign->id, 'SO-THEIRS');

    Sanctum::actingAs(inboxManager($own), ['*'], 'channel');

    $list = $this->getJson('/api/v1/channel/sub-orders')->assertOk();
    $ids = collect($list->json('data'))->pluck('id')->all();

    expect($ids)->toContain($ownId)->not->toContain($foreignId);

    CatalogAssert::error(
        $this->getJson("/api/v1/channel/sub-orders/{$foreignId}"),
        404,
        'not_found',
    );
})->group('tenancy');
