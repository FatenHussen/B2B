<?php

declare(strict_types=1);

/**
 * The six `/app/*` sub-order routes that opted out of the channel scope in e89279f.
 *
 * `/app/retailer/*` and `/app/rep/*` set no tenant, so every one of these sites runs
 * `SubOrder::query()->acrossChannels()`. That removes channel isolation on purpose — a
 * retailer buys from several channels and reads their own orders across all of them. What
 * is left is the *owner* column, `retailer_id` or `rep_id`, and nothing enforces it except
 * the one `where` in each query. These tests exist to prove that `where` is there, and to
 * fail the day it is not.
 *
 * Every test reads through the route with a real bearer token, never through the model,
 * and makes exactly one HTTP request. A guard memoises its user for the life of the
 * application, so a second request in the same test would answer as the first caller
 * whatever token it sent. BR-AD-19 (FxRateTest) already covers GET /orders/{id} as a
 * "works" case; its isolation case lives here with the others.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);

    $this->channel = SupplyChannel::factory()->create();
    $governorate = Governorate::factory()->create();
    $this->zone = Zone::factory()->create(['governorate_id' => $governorate->id]);
    $this->activityTypeId = (int) DB::table('activity_types')->insertGetId([
        'name' => 'بقالة', 'order' => 0, 'status' => RefStatus::Active->value,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->governorateId = (int) $governorate->id;
});

/**
 * A retailer with an active profile and a device token.
 *
 * @return array{user: AppUser, retailer_id: int, token: string}
 */
function retailer(string $shop): array
{
    $user = AppUser::factory()->retailer()->create();
    $retailerId = (int) DB::table('retailer_profiles')->insertGetId([
        'app_user_id' => $user->id,
        'shop_name' => $shop,
        'activity_type_id' => test()->activityTypeId,
        'governorate_id' => test()->governorateId,
        'zone_id' => test()->zone->id,
        'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['user' => $user, 'retailer_id' => $retailerId, 'token' => $user->createToken('device', ['*'])->plainTextToken];
}

/**
 * A rep with an active profile and a device token. `rep_id` on a sub-order is the
 * app_users id, not the profile id — AcceptAssignment compares against getAuthIdentifier().
 *
 * @return array{user: AppUser, rep_id: int, token: string}
 */
function rep(): array
{
    $user = AppUser::factory()->rep()->create();
    DB::table('rep_profiles')->insert([
        'app_user_id' => $user->id,
        'channel_id' => test()->channel->id,
        'activity_type_id' => test()->activityTypeId,
        'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['user' => $user, 'rep_id' => (int) $user->id, 'token' => $user->createToken('device', ['*'])->plainTextToken];
}

function subOrder(int $retailerId, string $status, ?int $repId = null): int
{
    static $n = 0;
    $n++;

    $orderId = (int) DB::table('orders')->insertGetId([
        'retailer_id' => $retailerId, 'source' => 'app', 'order_no' => "ORD-ISO-{$n}",
        'status' => 'pending', 'currency' => 'SYP',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $id = (int) DB::table('sub_orders')->insertGetId([
        'order_id' => $orderId, 'channel_id' => test()->channel->id, 'retailer_id' => $retailerId,
        'zone_id' => test()->zone->id, 'source' => 'app', 'sub_order_no' => "SO-ISO-{$n}",
        'status' => $status, 'subtotal' => 250_000, 'discount' => 0, 'total' => 250_000,
        'rep_id' => $repId, 'currency_code' => 'SYP', 'fx_rate' => Money::FX_UNIT,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('sub_order_lines')->insert([
        'sub_order_id' => $id, 'product_id' => 1, 'qty' => 1,
        'unit_price' => 250_000, 'discount' => 0, 'line_total' => 250_000,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

/** An `assigned` sub-order with the pending assignment row the rep list reads. */
function assignedTo(int $retailerId, int $repId): int
{
    $id = subOrder($retailerId, 'assigned', $repId);
    DB::table('sub_order_assignments')->insert([
        'sub_order_id' => $id, 'rep_id' => $repId, 'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

function asToken(string $token): array
{
    return ['Authorization' => "Bearer {$token}", 'X-Idempotency-Key' => 'iso-'.uniqid()];
}

function assertNotFoundEnvelope(TestResponse $response): void
{
    $response->assertStatus(404)->assertJsonPath('error.code', 'not_found');
}

// ---------------------------------------------------------------- retailer: list

it('lists a retailer\'s own orders with a real app token', function () {
    $me = retailer('متجري');
    $id = subOrder($me['retailer_id'], 'pending');

    $this->getJson('/api/v1/app/retailer/orders', asToken($me['token']))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $id);
})->group('ordering', 'isolation');

it('never lists another retailer\'s order', function () {
    $me = retailer('متجري');
    $other = retailer('متجر غيري');
    $mine = subOrder($me['retailer_id'], 'pending');
    $theirs = subOrder($other['retailer_id'], 'pending');

    $ids = $this->getJson('/api/v1/app/retailer/orders', asToken($me['token']))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$mine])->not->toContain($theirs);
})->group('ordering', 'isolation');

// ---------------------------------------------------------------- retailer: show

it('never shows another retailer\'s order', function () {
    // BR-AD-19 proves the "works" half of this route; this is its isolation half.
    $me = retailer('متجري');
    $other = retailer('متجر غيري');
    $theirs = subOrder($other['retailer_id'], 'pending');

    assertNotFoundEnvelope(
        $this->getJson("/api/v1/app/retailer/orders/{$theirs}", asToken($me['token']))
    );
})->group('ordering', 'isolation');

// ---------------------------------------------------------------- retailer: tracking

it('tracks a retailer\'s own order with a real app token', function () {
    $me = retailer('متجري');
    $id = subOrder($me['retailer_id'], 'pending');
    DB::table('sub_order_events')->insert([
        'sub_order_id' => $id, 'stage' => 'pending', 'at' => now(),
    ]);

    $this->getJson("/api/v1/app/retailer/orders/{$id}/tracking", asToken($me['token']))
        ->assertOk()
        ->assertJsonPath('data.stages.0.key', 'pending');
})->group('ordering', 'isolation');

it('never tracks another retailer\'s order', function () {
    $me = retailer('متجري');
    $other = retailer('متجر غيري');
    $theirs = subOrder($other['retailer_id'], 'pending');

    assertNotFoundEnvelope(
        $this->getJson("/api/v1/app/retailer/orders/{$theirs}/tracking", asToken($me['token']))
    );
})->group('ordering', 'isolation');

// ---------------------------------------------------------------- retailer: reorder

it('reorders a retailer\'s own order with a real app token', function () {
    // Was a todo until BE-C12: it answered 500 channel_scope_required, because after the
    // acrossChannels() on SubOrder the next line reached CartSection — strict-scoped, no
    // tenant on /app/* — and threw. The whole cart surface was broken the same way. The
    // scope is now lifted on Cart::sections() and at the direct CartSection sites, with
    // the owner (the cart) as the isolation; AppCartSurfaceTest covers the rest of it.
    $me = retailer('متجري');
    $id = subOrder($me['retailer_id'], 'delivered');

    $this->postJson("/api/v1/app/retailer/orders/{$id}/reorder", [], asToken($me['token']))
        ->assertOk()
        ->assertJsonStructure(['data' => ['cart']]);
})->group('ordering', 'isolation');

it('never reorders another retailer\'s order', function () {
    // The owner check runs before the cart is touched, so this half is provable today even
    // though the "works" half above is not: a foreign id is 404 and writes nothing.
    $me = retailer('متجري');
    $other = retailer('متجر غيري');
    $theirs = subOrder($other['retailer_id'], 'delivered');

    assertNotFoundEnvelope(
        $this->postJson("/api/v1/app/retailer/orders/{$theirs}/reorder", [], asToken($me['token']))
    );
    expect(DB::table('cart_lines')->count())->toBe(0);
})->group('ordering', 'isolation');

// ---------------------------------------------------------------- retailer: cancel (tripwire)

it('never lets a retailer cancel another retailer\'s order', function () {
    // CancelSubOrder carries no retailer_id check at all — it is shared with the channel
    // route, where the tenant scope does that work. On /app/retailer/* there is no tenant,
    // so today the strict scope throws before anything is read: loud, and nothing changes.
    //
    // This test deliberately pins neither the 500 of today nor the 404 of a correct fix.
    // It pins the one thing that must survive both: the other retailer's order is not
    // cancelled. The failure it exists to catch is a future acrossChannels() added here
    // without a retailer_id beside it — that would answer 200 and cancel the order, and
    // the loud error would have become a silent leak.
    $me = retailer('متجري');
    $other = retailer('متجر غيري');
    $theirs = subOrder($other['retailer_id'], 'pending');

    $response = $this->postJson(
        "/api/v1/app/retailer/orders/{$theirs}/cancel",
        ['reason' => 'ليس طلبي'],
        asToken($me['token']),
    );

    expect($response->status())->toBeGreaterThanOrEqual(400)
        ->and(DB::table('sub_orders')->where('id', $theirs)->value('status'))->toBe('pending')
        ->and(DB::table('sub_order_events')->where('sub_order_id', $theirs)->count())->toBe(0);
})->group('ordering', 'isolation');

// ---------------------------------------------------------------- rep: assignments

it('lists a rep\'s own pending assignments with a real app token', function () {
    $me = rep();
    $shop = retailer('متجر الزبون');
    $id = assignedTo($shop['retailer_id'], $me['rep_id']);

    $this->getJson('/api/v1/app/rep/assignments', asToken($me['token']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.0.shop', 'متجر الزبون');
})->group('ordering', 'isolation');

it('never lists another rep\'s assignment', function () {
    // Isolation here is the assignment row, not sub_orders.rep_id: the ids are plucked
    // from sub_order_assignments where rep_id is the caller before SubOrder is read.
    $me = rep();
    $other = rep();
    $shop = retailer('متجر الزبون');
    $mine = assignedTo($shop['retailer_id'], $me['rep_id']);
    $theirs = assignedTo($shop['retailer_id'], $other['rep_id']);

    $ids = $this->getJson('/api/v1/app/rep/assignments', asToken($me['token']))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$mine])->not->toContain($theirs);
})->group('ordering', 'isolation');

// ---------------------------------------------------------------- rep: accept

it('accepts a rep\'s own assignment with a real app token', function () {
    $me = rep();
    $shop = retailer('متجر الزبون');
    $id = assignedTo($shop['retailer_id'], $me['rep_id']);

    $this->postJson("/api/v1/app/rep/assignments/{$id}/accept", [], asToken($me['token']))
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect(DB::table('sub_orders')->where('id', $id)->value('status'))->toBe('accepted')
        ->and(DB::table('sub_order_assignments')->where('sub_order_id', $id)->value('status'))->toBe('accepted');
})->group('ordering', 'isolation');

it('never lets a rep accept another rep\'s assignment', function () {
    $me = rep();
    $other = rep();
    $shop = retailer('متجر الزبون');
    $theirs = assignedTo($shop['retailer_id'], $other['rep_id']);

    assertNotFoundEnvelope(
        $this->postJson("/api/v1/app/rep/assignments/{$theirs}/accept", [], asToken($me['token']))
    );

    // Nothing moved: the sub-order is still assigned, to the other rep, and their
    // assignment row is still pending.
    $row = DB::table('sub_orders')->where('id', $theirs)->first(['status', 'rep_id']);
    expect($row->status)->toBe('assigned')
        ->and((int) $row->rep_id)->toBe($other['rep_id'])
        ->and(DB::table('sub_order_assignments')->where('sub_order_id', $theirs)->value('status'))->toBe('pending')
        ->and(DB::table('sub_order_events')->where('sub_order_id', $theirs)->count())->toBe(0);
})->group('ordering', 'isolation');

// ---------------------------------------------------------------- rep: reject

it('rejects a rep\'s own assignment with a real app token', function () {
    $me = rep();
    $shop = retailer('متجر الزبون');
    $id = assignedTo($shop['retailer_id'], $me['rep_id']);

    $this->postJson("/api/v1/app/rep/assignments/{$id}/reject", ['reason' => 'خارج منطقتي'], asToken($me['token']))
        ->assertOk()
        ->assertJsonPath('data.status', 'unassigned');

    $row = DB::table('sub_orders')->where('id', $id)->first(['status', 'rep_id']);
    expect($row->status)->toBe('confirmed')
        ->and($row->rep_id)->toBeNull()
        ->and(DB::table('sub_order_assignments')->where('sub_order_id', $id)->value('status'))->toBe('rejected');
})->group('ordering', 'isolation');

it('never lets a rep reject another rep\'s assignment', function () {
    $me = rep();
    $other = rep();
    $shop = retailer('متجر الزبون');
    $theirs = assignedTo($shop['retailer_id'], $other['rep_id']);

    assertNotFoundEnvelope(
        $this->postJson("/api/v1/app/rep/assignments/{$theirs}/reject", ['reason' => 'ليس لي'], asToken($me['token']))
    );

    $row = DB::table('sub_orders')->where('id', $theirs)->first(['status', 'rep_id']);
    expect($row->status)->toBe('assigned')
        ->and((int) $row->rep_id)->toBe($other['rep_id'])
        ->and(DB::table('sub_order_assignments')->where('sub_order_id', $theirs)->value('status'))->toBe('pending');
})->group('ordering', 'isolation');
