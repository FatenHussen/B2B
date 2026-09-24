<?php

declare(strict_types=1);

/**
 * Launch fixes: banner CTR, warehouse queues alerts, retailer 360, rep live, assign auto.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Content\Domain\Models\Banner;
use Modules\Core\Support\Tenant;
use Modules\Delivery\Domain\Models\RepLocationPing;
use Modules\Fulfillment\Domain\Enums\PickingStatus;
use Modules\Fulfillment\Domain\Models\GoodsReceipt;
use Modules\Fulfillment\Domain\Models\PickingList;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RepDutyState;
use Modules\Identity\Domain\Models\WarehouseDevice;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Ordering\Domain\Enums\SubOrderStatus;
use Modules\Ordering\Domain\Models\SubOrder;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\Warehouse;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('increments banner impressions on home-blocks and clicks via app endpoint', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $rep = AppSurface::rep($channel, $refs);

    $bannerId = Tenant::as($channel->id, function () use ($channel) {
        return (int) Banner::query()->create([
            'supply_channel_id' => $channel->id,
            'media_type' => 'image',
            'media_id' => 'media_banner_ctr',
            'link' => ['type' => 'offer', 'target' => 1],
            'placements' => ['home'],
            'targeting' => [],
            'order' => 1,
            'weight' => 1,
            'impressions' => 0,
            'clicks' => 0,
        ])->id;
    });

    Sanctum::actingAs($rep, ['*'], 'app');
    $blocks = $this->getJson('/api/v1/app/content/home-blocks');
    CatalogAssert::ok($blocks, ['banners', 'sliders']);
    expect($blocks->json('data.banners.0.id'))->toBe($bannerId);

    Tenant::as($channel->id, function () use ($bannerId) {
        expect((int) Banner::query()->whereKey($bannerId)->value('impressions'))->toBe(1);
    });

    $click = $this->postJson("/api/v1/app/content/banners/{$bannerId}/click", []);
    CatalogAssert::ok($click);
    expect($click->json('data.clicks'))->toBe(1);
});

it('reports inbound_returns and overdue_picks on warehouse queues', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $warehouse = Warehouse::query()->create([
        'channel_id' => $channel->id,
        'name' => 'مستودع CTR',
        'status' => WarehouseStatus::Active,
    ]);
    $user = WarehouseUser::factory()->create();
    $user->assignRole('warehouse_keeper');
    WarehouseDevice::query()->create([
        'device_token_hash' => hash('sha256', 'wh-queues-token'),
        'pin_hash' => Hash::make('1234'),
        'warehouse_id' => $warehouse->id,
        'channel_id' => $channel->id,
        'warehouse_user_id' => $user->id,
        'label' => 'queues',
    ]);

    Tenant::as($channel->id, function () use ($warehouse, $channel) {
        GoodsReceipt::query()->create([
            'warehouse_id' => $warehouse->id,
            'source' => 'field_return',
            'status' => 'pending_qc',
        ]);
        PickingList::query()->create([
            'sub_order_id' => 1,
            'warehouse_id' => $warehouse->id,
            'channel_id' => $channel->id,
            'status' => PickingStatus::ToPick,
            'due_at' => now()->subHour(),
        ]);
    });

    Sanctum::actingAs($user, ['*'], 'warehouse');
    $queues = $this->getJson('/api/v1/warehouse/queues');
    CatalogAssert::ok($queues);
    expect($queues->json('data.queues.inbound_returns'))->toBe(1)
        ->and($queues->json('data.alerts.0.type'))->toBe('overdue_picks')
        ->and($queues->json('data.alerts.0.count'))->toBeGreaterThanOrEqual(1);
});

it('shows retailer 360 for a shop in coverage', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $retailerId = AppSurface::retailerId($shop);
    $rep = AppSurface::rep($channel, $refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs, 'R360-1');
    AppSurface::subOrder($channel, $refs, $retailerId, 'delivered', null, $productId);

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $groupId = (int) $this->postJson('/api/v1/channel/retailer-groups', [
        'name' => 'VIP R360',
        'retailer_ids' => [$retailerId],
    ], ['X-Idempotency-Key' => 'r360-group-'.uniqid()])->json('data.id');

    $show = $this->getJson("/api/v1/channel/retailers/{$retailerId}");
    CatalogAssert::ok($show, [
        'id', 'shop_name', 'credit', 'outstanding', 'groups', 'assigned_rep_ids',
        'last_order_at', 'recent_orders', 'top_products',
    ]);
    expect($show->json('data.id'))->toBe($retailerId)
        ->and($show->json('data.recent_orders'))->not->toBeEmpty()
        ->and($show->json('data.outstanding'))->toBe(0)
        ->and($show->json('data.groups'))->toBe([['id' => $groupId, 'name' => 'VIP R360']])
        ->and($show->json('data.assigned_rep_ids'))->toContain($rep->id)
        ->and($show->json('data.last_order_at'))->not->toBeNull();
});

it('returns rep live location with on_duty', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $rep = AppSurface::rep($channel, $refs);

    RepDutyState::query()->updateOrCreate(
        ['rep_user_id' => $rep->id],
        ['on_duty' => true, 'tracking_enabled' => true],
    );
    RepLocationPing::query()->create([
        'rep_id' => $rep->id,
        'lat' => 33.51,
        'lng' => 36.29,
        'at' => now(),
        'accuracy' => 10,
    ]);

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $live = $this->getJson("/api/v1/channel/reps/{$rep->id}/live");
    CatalogAssert::ok($live, ['lat', 'lng', 'at', 'on_duty']);
    expect($live->json('data.on_duty'))->toBeTrue()
        ->and($live->json('data.lat'))->toBe(33.51);
});

it('auto-assigns a confirmed sub-order to an on-duty covering rep', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs, 'AUTO-1');
    $subOrderId = AppSurface::subOrder(
        $channel,
        $refs,
        AppSurface::retailerId($shop),
        'confirmed',
        null,
        $productId,
    );

    RepDutyState::query()->updateOrCreate(
        ['rep_user_id' => $rep->id],
        ['on_duty' => true, 'tracking_enabled' => true],
    );

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $assign = $this->postJson('/api/v1/channel/sub-orders/assign', [
        'sub_order_ids' => [$subOrderId],
        'mode' => 'auto',
    ]);
    CatalogAssert::ok($assign);
    expect($assign->json('data.assigned'))->toContain($subOrderId);

    Tenant::as($channel->id, function () use ($subOrderId, $rep) {
        $sub = SubOrder::query()->find($subOrderId);
        expect($sub)->not->toBeNull()
            ->and($sub->status)->toBe(SubOrderStatus::Assigned)
            ->and((int) $sub->rep_id)->toBe((int) $rep->id);
    });
});

it('lists return requests with sla_due_at and overdue flag', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    Tenant::as($channel->id, function () use ($channel) {
        DB::table('return_requests')->insert([
            'channel_id' => $channel->id,
            'sub_order_id' => 1,
            'zone_id' => 12,
            'rep_id' => 70,
            'requester_type' => ChannelUser::class,
            'requester_id' => 1,
            'type' => 'return',
            'status' => 'pending',
            'request_no' => 'RR-SLA',
            'sla_due_at' => now()->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    Sanctum::actingAs($manager, ['*'], 'channel');
    $list = $this->getJson('/api/v1/channel/return-requests');
    CatalogAssert::ok($list);
    $row = collect($list->json('data'))->firstWhere('request_no', 'RR-SLA');
    expect($row)->not->toBeNull()
        ->and($row['overdue'])->toBeTrue()
        ->and($row['sla_due_at'])->not->toBeNull();
});
