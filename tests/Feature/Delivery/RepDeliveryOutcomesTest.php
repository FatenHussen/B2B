<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Delivery\Domain\Models\Delivery;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('persists postpone date and lists the scheduled order with its id', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $id = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'on_the_way', $rep->id);
    Delivery::query()->create(['sub_order_id' => $id, 'rep_id' => $rep->id, 'status' => 'on_the_way']);
    Sanctum::actingAs($rep, ['*'], 'app');

    $when = '2026-03-02T10:00:00+03:00';
    $response = $this->postJson("/api/v1/app/rep/deliveries/{$id}/postpone", [
        'scheduled_at' => $when,
        'reason' => 'المحل مغلق',
    ]);
    CatalogAssert::ok($response);
    expect($response->json('data.status'))->toBe('postponed');

    $row = DB::table('sub_orders')->where('id', $id)->first();
    expect($row->status)->toBe('postponed')->and($row->scheduled_at)->not->toBeNull();
    expect(DB::table('deliveries')->where('sub_order_id', $id)->value('reason'))->toBe('المحل مغلق');

    $listed = $this->getJson('/api/v1/app/rep/scheduled-orders?date=2026-03-02');
    CatalogAssert::ok($listed);
    expect($listed->json('data'))->toHaveCount(1)
        ->and($listed->json('data.0.id'))->toBe($id)
        ->and($listed->json('data.0.status'))->toBe('postponed');
});

it('persists the undelivered reason', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $id = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'on_the_way', $rep->id);
    Delivery::query()->create(['sub_order_id' => $id, 'rep_id' => $rep->id, 'status' => 'on_the_way']);
    Sanctum::actingAs($rep, ['*'], 'app');

    $response = $this->postJson("/api/v1/app/rep/deliveries/{$id}/fail", [
        'reason' => 'رفض الاستلام',
    ]);
    CatalogAssert::ok($response);
    expect($response->json('data.status'))->toBe('undelivered')
        ->and($response->json('data.border_color'))->toBe('red')
        ->and(DB::table('deliveries')->where('sub_order_id', $id)->value('reason'))->toBe('رفض الاستلام')
        ->and(DB::table('sub_order_events')->where('sub_order_id', $id)->orderByDesc('id')->value('reason'))->toBe('رفض الاستلام');
});

it('404s postpone and fail for another rep order', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $repA = AppSurface::rep($channel, $refs);
    $repB = AppSurface::rep($channel, $refs);
    $id = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'on_the_way', $repA->id);
    Sanctum::actingAs($repB, ['*'], 'app');

    CatalogAssert::error(
        $this->postJson("/api/v1/app/rep/deliveries/{$id}/postpone", [
            'scheduled_at' => '2026-03-02T10:00:00+03:00',
            'reason' => 'ليس لي',
        ]),
        404,
        'not_found',
    );
    CatalogAssert::error(
        $this->postJson("/api/v1/app/rep/deliveries/{$id}/fail", ['reason' => 'ليس لي']),
        404,
        'not_found',
    );
})->group('tenancy');

it('lists today deliveries with ordered_at and a real delivered count', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $open = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'accepted', $rep->id);
    $done = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'delivered', $rep->id);
    Sanctum::actingAs($rep, ['*'], 'app');

    $list = $this->getJson('/api/v1/app/rep/deliveries');
    CatalogAssert::ok($list);
    expect($list->json('data.zones.0.total'))->toBe(2)
        ->and($list->json('data.zones.0.delivered'))->toBe(1)
        ->and($list->json('data.zones.0.cards.0.ordered_at'))->toBeString();
    $cardIds = [];
    foreach ($list->json('data.zones.0.cards') as $card) {
        $cardIds[] = $card['id'];
    }
    expect($cardIds)->toContain($open, $done);

    $detail = $this->getJson("/api/v1/app/rep/deliveries/{$open}");
    CatalogAssert::ok($detail);
    expect($detail->json('data.lines.0.qty'))->toBe(1)
        ->and($detail->json('data.lines.0.qty_delivered'))->toBe(0);
});
