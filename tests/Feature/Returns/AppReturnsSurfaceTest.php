<?php

declare(strict_types=1);

/**
 * BE-C12 — return requests from the retailer and rep apps: the list answers, in one
 * query filtered by the retailer; creating one is refused on an order that is not the
 * caller's.
 *
 * `GET /app/retailer/return-requests` threw 500 channel_scope_required — ReturnRequest is
 * strict and /app/* sets no tenant — and, when it did not throw, isolated in PHP after
 * reading every channel's rows. `POST` on both apps never checked the order belonged to
 * the caller at all: any sub-order id opened a return.
 */

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Tests\Support\AppSurface;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function returnBody(int $subOrderId): array
{
    $lineId = (int) DB::table('sub_order_lines')->where('sub_order_id', $subOrderId)->value('id');

    return ['sub_order_id' => $subOrderId, 'type' => 'return', 'lines' => [['line_id' => $lineId, 'qty' => 1, 'reason' => 'تالف']]];
}

// ─── retailer ───────────────────────────────────────────────────────────────────────

it('lets a retailer open a return on their own delivered order and lists it', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $delivered = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($a), 'delivered');
    Sanctum::actingAs($a, ['*'], 'app');

    $created = $this->postJson('/api/v1/app/retailer/return-requests', returnBody($delivered))
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $list = $this->getJson('/api/v1/app/retailer/return-requests')->assertOk();

    expect(collect($list->json('data'))->pluck('request_no')->all())->toBe([$created->json('data.request_no')]);
});

it('never lists another retailer return request', function () {
    // A rep may raise a return on the retailer's behalf, so the list is "returns on my
    // orders", not "returns I filed" — and it is one query with my orders in its where.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $b = AppSurface::retailer($refs);
    $bOrder = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($b), 'delivered');
    Sanctum::actingAs($b, ['*'], 'app');
    $this->postJson('/api/v1/app/retailer/return-requests', returnBody($bOrder))->assertOk();

    app('auth')->forgetGuards();
    Sanctum::actingAs($a, ['*'], 'app');

    $this->getJson('/api/v1/app/retailer/return-requests')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('refuses a retailer opening a return on another retailer order', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $a = AppSurface::retailer($refs);
    $b = AppSurface::retailer($refs);
    $bOrder = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($b), 'delivered');
    Sanctum::actingAs($a, ['*'], 'app');

    $this->postJson('/api/v1/app/retailer/return-requests', returnBody($bOrder))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    expect(DB::table('return_requests')->where('sub_order_id', $bOrder)->count())->toBe(0);
});

// ─── rep ────────────────────────────────────────────────────────────────────────────

it('lets a rep open a return on a delivery that is theirs', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $rep = AppSurface::rep($channel, $refs);
    $mine = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'delivered', $rep->id);
    Sanctum::actingAs($rep, ['*'], 'app');

    $this->postJson('/api/v1/app/rep/return-requests', returnBody($mine))
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

it('refuses a rep opening a return on another rep delivery', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $repA = AppSurface::rep($channel, $refs);
    $repB = AppSurface::rep($channel, $refs);
    $theirs = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'delivered', $repB->id);
    Sanctum::actingAs($repA, ['*'], 'app');

    $this->postJson('/api/v1/app/rep/return-requests', returnBody($theirs))
        ->assertNotFound();

    expect(DB::table('return_requests')->where('sub_order_id', $theirs)->count())->toBe(0);
});
