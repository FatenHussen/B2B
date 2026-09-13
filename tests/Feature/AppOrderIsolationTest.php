<?php

declare(strict_types=1);

/**
 * The six /app/* sub-order routes that run `acrossChannels()`: they answer, and they
 * answer only to their owner.
 *
 * These six broke silently when `SubOrder` gained `BelongsToChannel` — the `/app/retailer`
 * and `/app/rep` groups set no tenant, so every read began throwing
 * `MissingChannelScopeException`, and no test noticed because none touched them. The fix
 * was `acrossChannels()` at each site, which removes channel isolation on purpose: a
 * retailer buys from several channels and reads their own orders across all of them.
 *
 * That leaves exactly one line of defence on each route — `where('retailer_id', ...)` or
 * `where('rep_id', ...)` — and this file exists to prove that line holds. The "own" tests
 * show the route works; the "other" tests are the ones that matter. If any of them turned
 * green through a leak, a loud exception would have been traded for a silent one.
 *
 * Every read goes through the HTTP route. The model would only show whether a query
 * filtered; the route shows what a user is actually handed.
 *
 * One request per test, per the guard-caching hazard recorded in CrossGuardTest.
 */

use Illuminate\Support\Facades\DB;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * Two retailers, two reps, and one sub-order per retailer — each assigned to a different
 * rep. Returns everything a test needs to be either owner, or the wrong one.
 *
 * @return array<string, mixed>
 */
function twoOwners(): array
{
    $channel = SupplyChannel::factory()->create();
    $governorate = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $governorate->id]);
    $activityTypeId = (int) DB::table('activity_types')->insertGetId([
        'name' => 'بقالة', 'order' => 0, 'status' => RefStatus::Active->value,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $retailers = [];
    foreach (['A', 'B'] as $tag) {
        $user = AppUser::factory()->retailer()->create();
        $profileId = (int) DB::table('retailer_profiles')->insertGetId([
            'app_user_id' => $user->id,
            'shop_name' => "Shop $tag",
            'activity_type_id' => $activityTypeId,
            'governorate_id' => $governorate->id,
            'zone_id' => $zone->id,
            'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $retailers[$tag] = ['user' => $user, 'profile_id' => $profileId];
    }

    $reps = [];
    foreach (['A', 'B'] as $tag) {
        $user = AppUser::factory()->rep()->create();
        DB::table('rep_profiles')->insert([
            'app_user_id' => $user->id,
            'channel_id' => $channel->id,
            'activity_type_id' => $activityTypeId,
            'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $reps[$tag] = $user;
    }

    $subOrders = [];
    foreach (['A', 'B'] as $tag) {
        $orderId = (int) DB::table('orders')->insertGetId([
            'retailer_id' => $retailers[$tag]['profile_id'], 'source' => 'app',
            'order_no' => "ORD-$tag", 'status' => 'assigned', 'currency' => 'SYP',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $subOrderId = (int) DB::table('sub_orders')->insertGetId([
            'order_id' => $orderId, 'channel_id' => $channel->id,
            'retailer_id' => $retailers[$tag]['profile_id'], 'zone_id' => $zone->id,
            'source' => 'app', 'sub_order_no' => "SO-$tag", 'status' => 'assigned',
            'subtotal' => 100_000, 'discount' => 0, 'total' => 100_000,
            'rep_id' => $reps[$tag]->id,
            'currency_code' => 'SYP', 'fx_rate' => Money::FX_UNIT,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sub_order_lines')->insert([
            'sub_order_id' => $subOrderId, 'product_id' => 1, 'qty' => 1,
            'unit_price' => 100_000, 'discount' => 0, 'line_total' => 100_000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sub_order_assignments')->insert([
            'sub_order_id' => $subOrderId, 'rep_id' => $reps[$tag]->id,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $subOrders[$tag] = $subOrderId;
    }

    return compact('retailers', 'reps', 'subOrders');
}

function appBearer(AppUser $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('isolation', ['*'])->plainTextToken];
}

// ─── 1. GET /app/retailer/orders/{id}  (ShowRetailerOrder) ─────────────────────────

it('shows a retailer their own order', function () {
    $f = twoOwners();

    $this->getJson("/api/v1/app/retailer/orders/{$f['subOrders']['A']}", appBearer($f['retailers']['A']['user']))
        ->assertOk()
        ->assertJsonPath('data.lines.0.price', 100_000);
})->group('tenancy');

it('hides another retailer order behind 404, not 403', function () {
    // Rule 11: existence is never disclosed. Retailer A asks for B's order and learns
    // nothing — not even that there is a B.
    $f = twoOwners();

    $this->getJson("/api/v1/app/retailer/orders/{$f['subOrders']['B']}", appBearer($f['retailers']['A']['user']))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
})->group('tenancy');

// ─── 2. GET /app/retailer/orders/{id}/tracking  (TrackRetailerOrder) ──────────────

it('tracks a retailer own order', function () {
    $f = twoOwners();

    $this->getJson("/api/v1/app/retailer/orders/{$f['subOrders']['A']}/tracking", appBearer($f['retailers']['A']['user']))
        ->assertOk();
})->group('tenancy');

it('does not track another retailer order', function () {
    $f = twoOwners();

    $this->getJson("/api/v1/app/retailer/orders/{$f['subOrders']['B']}/tracking", appBearer($f['retailers']['A']['user']))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
})->group('tenancy');

// ─── 3. GET /app/retailer/orders  (ListRetailerOrders) ─────────────────────────────

it('lists only the retailer own orders', function () {
    // The list is the route most likely to leak, because it has no id to miss: an
    // unfiltered query would simply return both rows and look like a working list.
    $f = twoOwners();

    $response = $this->getJson('/api/v1/app/retailer/orders', appBearer($f['retailers']['A']['user']))
        ->assertOk();

    $numbers = collect($response->json('data'))->pluck('sub_order_no')->all();

    expect($numbers)->toBe(['SO-A'])
        ->and($numbers)->not->toContain('SO-B');
})->group('tenancy');

// ─── 4. POST /app/retailer/orders/{id}/reorder  (ReorderSubOrder) ──────────────────

it('refuses to reorder another retailer order', function () {
    // The write side of the same boundary. Only the negative is asserted here: a
    // successful reorder depends on live catalog products, which is Ordering's own
    // test to write — this file proves the owner filter, not the cart.
    $f = twoOwners();

    $this->postJson("/api/v1/app/retailer/orders/{$f['subOrders']['B']}/reorder", [], appBearer($f['retailers']['A']['user']))
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
})->group('tenancy');

// ─── 5. GET /app/rep/assignments  (ListRepAssignments) ─────────────────────────────

it('lists only the rep own assignments', function () {
    $f = twoOwners();

    $response = $this->getJson('/api/v1/app/rep/assignments', appBearer($f['reps']['A']))
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->map(fn ($v) => (int) $v)->all();

    expect($ids)->toBe([$f['subOrders']['A']])
        ->and($ids)->not->toContain($f['subOrders']['B']);
})->group('tenancy');

// ─── 6. POST /app/rep/assignments/{id}/accept · /reject  (Accept/RejectAssignment) ─

it('lets a rep accept their own assignment', function () {
    $f = twoOwners();

    $this->postJson("/api/v1/app/rep/assignments/{$f['subOrders']['A']}/accept", [], appBearer($f['reps']['A']))
        ->assertOk();

    expect(DB::table('sub_orders')->where('id', $f['subOrders']['A'])->value('status'))->toBe('accepted');
})->group('tenancy');

it('refuses a rep accepting another rep assignment', function () {
    // The most consequential leak of the six: accepting takes ownership of a delivery.
    $f = twoOwners();

    $this->postJson("/api/v1/app/rep/assignments/{$f['subOrders']['B']}/accept", [], appBearer($f['reps']['A']))
        ->assertNotFound();

    // Untouched: still assigned, still B's.
    $row = DB::table('sub_orders')->where('id', $f['subOrders']['B'])->first();
    expect($row->status)->toBe('assigned')
        ->and((int) $row->rep_id)->toBe($f['reps']['B']->id);
})->group('tenancy');

it('refuses a rep rejecting another rep assignment', function () {
    $f = twoOwners();

    $this->postJson("/api/v1/app/rep/assignments/{$f['subOrders']['B']}/reject", ['reason' => 'ليس لي'], appBearer($f['reps']['A']))
        ->assertNotFound();

    $row = DB::table('sub_orders')->where('id', $f['subOrders']['B'])->first();
    expect($row->status)->toBe('assigned')
        ->and((int) $row->rep_id)->toBe($f['reps']['B']->id);
})->group('tenancy');
