<?php

declare(strict_types=1);

/**
 * EP-RP-002 — GET /app/rep/home.
 *
 * Morning counts via contracts. Must not materialise delivery rows (GET /deliveries does).
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Delivery\Domain\Models\Delivery;
use Modules\Fulfillment\Domain\Enums\HandoverStatus;
use Modules\Fulfillment\Domain\Models\Handover;
use Modules\Fulfillment\Domain\Models\HandoverItem;
use Modules\Identity\Domain\Models\RepDutyState;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('returns zeroed morning counts for a fresh rep without a bearer-less guest token', function () {
    $refs = AppSurface::refs();
    $rep = AppSurface::rep(AppSurface::channel($refs), $refs);
    Sanctum::actingAs($rep, ['*'], 'app');

    $get = $this->getJson('/api/v1/app/rep/home');
    CatalogAssert::ok($get, ['greeting', 'tasks']);
    expect($get->json('data.greeting.name'))->toBe($rep->name)
        ->and($get->json('data.greeting.avatar'))->toBeNull()
        ->and($get->json('data.on_duty'))->toBeFalse()
        ->and($get->json('data.tasks'))->toBe([
            'orders_today' => 0,
            'deliveries_pending' => 0,
            'collected_today' => 0,
            'assignments' => 0,
            'scheduled' => 0,
            'warehouse_receipts' => 0,
        ])
        ->and($get->json('data.loyalty.points'))->toBe(0)
        ->and($get->json('data.loyalty.tier'))->toBe('bronze')
        ->and($get->json('data.unread_notifications'))->toBe(0);
});

it('rejects the home snapshot without a bearer — guest browse is local', function () {
    CatalogAssert::error($this->getJson('/api/v1/app/rep/home'), 401, 'unauthenticated');
});

it('counts assignments and pending deliveries without creating delivery rows', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'assigned', $rep->id);
    AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'accepted', $rep->id);
    AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'on_the_way', $rep->id);
    Sanctum::actingAs($rep, ['*'], 'app');

    $get = $this->getJson('/api/v1/app/rep/home');
    CatalogAssert::ok($get);
    expect($get->json('data.tasks.assignments'))->toBe(1)
        ->and($get->json('data.tasks.deliveries_pending'))->toBe(2)
        ->and(Delivery::query()->count())->toBe(0);
});

it('counts warehouse receipts waiting on the rep', function () {
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

    $get = $this->getJson('/api/v1/app/rep/home');
    expect($get->json('data.tasks.warehouse_receipts'))->toBe(1);
});

it('reflects on-duty after PATCH /status', function () {
    $refs = AppSurface::refs();
    $rep = AppSurface::rep(AppSurface::channel($refs), $refs);
    Sanctum::actingAs($rep, ['*'], 'app');

    $this->patchJson('/api/v1/app/rep/status', ['on_duty' => true])->assertOk();

    $home = $this->getJson('/api/v1/app/rep/home');
    $session = $this->getJson('/api/v1/app/session');
    expect($home->json('data.on_duty'))->toBeTrue()
        ->and($home->json('data.tracking_enabled'))->toBeTrue()
        ->and($session->json('data.duty.on_duty'))->toBeTrue()
        ->and($session->json('data.user.avatar'))->toBeNull()
        ->and(RepDutyState::query()->where('rep_user_id', $rep->id)->value('on_duty'))->toBeTrue();
});
