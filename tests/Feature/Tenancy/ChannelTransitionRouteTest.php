<?php

declare(strict_types=1);

/**
 * BE-T13 — EP-AD-054, `POST /admin/channels/{id}/transition`.
 *
 * The lifecycle and its matrix are proved in ChannelLifecycleTest. This file proves the
 * route: that it reaches the lifecycle and nothing else, answers what the catalog says
 * (`status` and `allowed_next`), refuses with the codes the catalog names, is gated on
 * the permission the catalog names — `ad.channels.suspend`, and `ad.channels.archive`
 * when the target is `archived` — and that a suspension leaves an order in flight alone.
 *
 * The path is `/admin/channels`, beside its five siblings, and is MOVING to
 * `/platform/channels` with them. A sixth route on the catalog prefix while five sit on
 * the temporary one would split the single constant the frontend keeps them behind.
 *
 * One HTTP request per test unless the guards are forgotten in between, per the
 * guard-caching hazard recorded in CrossGuardTest.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Events\ChannelStatusChanged;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelEvent;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function transitionUrl(SupplyChannel $channel): string
{
    return "/api/v1/admin/channels/{$channel->id}/transition";
}

function channelAt(string $status): SupplyChannel
{
    return SupplyChannel::factory()->create(['status' => $status]);
}

function actingAsPlatformAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

/**
 * A platform user holding exactly the permissions given and no role — so `Gate::before`
 * does not answer for them, and the gate under test is the one that decides.
 *
 * @param  list<string>  $permissions
 */
function actingAsPlatformUserWith(array $permissions): PlatformUser
{
    $user = PlatformUser::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }
    Sanctum::actingAs($user, ['*'], 'platform');

    return $user;
}

// ─── the route works, and answers the catalog's shape ───────────────────────────────

it('moves a channel and answers its status and allowed_next', function () {
    $channel = channelAt('active');
    $admin = actingAsPlatformAdmin();

    $this->postJson(transitionUrl($channel), ['to_status' => 'suspended', 'reason' => 'تأخر سداد فاتورة المنصة'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonPath('data.allowed_next', ['active', 'archived']);

    expect($channel->fresh()->status)->toBe(ChannelStatus::Suspended);

    $event = ChannelEvent::query()->where('channel_id', $channel->id)->sole();
    expect($event->actor_type)->toBe(PlatformUser::class)
        ->and((int) $event->actor_id)->toBe($admin->id)
        ->and($event->reason)->toBe('تأخر سداد فاتورة المنصة');
});

it('walks the whole path through the route: provisioning → active → suspended → archived', function () {
    $channel = channelAt('provisioning');
    actingAsPlatformAdmin();

    foreach (['active', 'suspended', 'archived'] as $next) {
        $this->postJson(transitionUrl($channel), ['to_status' => $next, 'reason' => "إلى {$next}"])
            ->assertOk()
            ->assertJsonPath('data.status', $next);
    }

    expect($channel->fresh()->status)->toBe(ChannelStatus::Archived)
        ->and(ChannelEvent::query()->where('channel_id', $channel->id)->count())->toBe(3);
});

it('dispatches ChannelStatusChanged once for a transition made through the route', function () {
    Event::fake([ChannelStatusChanged::class]);
    $channel = channelAt('active');
    actingAsPlatformAdmin();

    $this->postJson(transitionUrl($channel), ['to_status' => 'suspended', 'reason' => 'إيقاف'])->assertOk();

    Event::assertDispatchedTimes(ChannelStatusChanged::class, 1);
});

// ─── acceptance criterion: a transition absent from allowed_next returns 409 ────────

it('a transition absent from allowed_next returns 409 illegal_transition', function () {
    $channel = channelAt('active');
    actingAsPlatformAdmin();

    $this->postJson(transitionUrl($channel), ['to_status' => 'provisioning', 'reason' => 'رجوع'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'illegal_transition')
        ->assertJsonPath('error.details.from', 'active')
        ->assertJsonPath('error.details.to', 'provisioning')
        ->assertJsonPath('error.details.allowed_next', ['suspended']);

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active)
        ->and(ChannelEvent::query()->where('channel_id', $channel->id)->count())->toBe(0);
});

it('returns 409 when an active channel is archived through the route', function () {
    // BE-T01 §2 at the HTTP boundary. This is the one edge a client would draw by
    // intuition, so it is refused here by name rather than left to the dataset.
    $channel = channelAt('active');
    actingAsPlatformAdmin();

    $this->postJson(transitionUrl($channel), ['to_status' => 'archived', 'reason' => 'أرشفة مباشرة'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'illegal_transition');

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active);
});

it('answers allowed_next without archived once a channel becomes active', function () {
    // The transition answer, not the resource: a client that just activated a channel
    // draws its next buttons from this response, and `archived` must not be among them.
    $channel = channelAt('provisioning');
    actingAsPlatformAdmin();

    $allowed = $this->postJson(transitionUrl($channel), ['to_status' => 'active', 'reason' => 'اكتمل التجهيز'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->json('data.allowed_next');

    expect($allowed)->toBe(['suspended'])
        ->and($allowed)->not->toContain('archived');
});

// ─── validation, existence, guard ───────────────────────────────────────────────────

it('rejects a status that is not a channel status with 422', function () {
    $channel = channelAt('active');
    actingAsPlatformAdmin();

    $this->postJson(transitionUrl($channel), ['to_status' => 'deleted', 'reason' => 'x'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active);
});

it('requires a reason', function () {
    // A transition without a reason is not recorded, and one that is not recorded does
    // not happen — so the route refuses it before the lifecycle is reached.
    $channel = channelAt('active');
    actingAsPlatformAdmin();

    $this->postJson(transitionUrl($channel), ['to_status' => 'suspended'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active);
});

it('answers 404 for a channel that does not exist', function () {
    actingAsPlatformAdmin();

    $this->postJson('/api/v1/admin/channels/999999/transition', ['to_status' => 'suspended', 'reason' => 'x'])
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('refuses a channel token with 403 wrong_guard', function () {
    $channel = channelAt('active');
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    $this->postJson(transitionUrl($channel), ['to_status' => 'suspended', 'reason' => 'x'], [
        'Authorization' => 'Bearer '.$manager->createToken('cross', ['*'])->plainTextToken,
    ])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'wrong_guard');

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active);
});

// ─── the gate is the permission the catalog names, per target ───────────────────────

it('names ad.channels.suspend on a 403 for a platform user without it', function () {
    $channel = channelAt('active');
    actingAsPlatformUserWith([]);

    $this->postJson(transitionUrl($channel), ['to_status' => 'suspended', 'reason' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.channels.suspend');

    expect($channel->fresh()->status)->toBe(ChannelStatus::Active);
});

it('lets ad.channels.suspend suspend and lift a suspension without the admin role', function () {
    // DOC-08: `ad.channels.suspend` is "إيقاف قناة أو رفع الإيقاف" — both directions.
    // The holder has no role, so this passes on the permission and nothing else.
    $channel = channelAt('active');
    actingAsPlatformUserWith(['ad.channels.suspend']);

    $this->postJson(transitionUrl($channel), ['to_status' => 'suspended', 'reason' => 'إيقاف'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    $this->postJson(transitionUrl($channel), ['to_status' => 'active', 'reason' => 'رفع الإيقاف'])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('requires ad.channels.archive to archive, and names it on the 403', function () {
    // EP-AD-054: "Permission is ad.channels.suspend / ad.channels.archive depending on
    // target." Suspend alone does not reach `archived`.
    $channel = channelAt('suspended');
    actingAsPlatformUserWith(['ad.channels.suspend']);

    $this->postJson(transitionUrl($channel), ['to_status' => 'archived', 'reason' => 'أرشفة'])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.channels.archive');

    expect($channel->fresh()->status)->toBe(ChannelStatus::Suspended)
        ->and(ChannelEvent::query()->where('channel_id', $channel->id)->count())->toBe(0);
});

// ─── acceptance criterion: existing orders continue to progress after suspension ────

it('existing orders continue to progress after suspension', function () {
    // BR-AD-14 through real routes: the platform suspends the channel, then the rep whose
    // delivery is in flight accepts it — and the acceptance goes through. The freeze is
    // for new orders; an order already on its way is not the channel's to lose.
    $channel = channelAt('active');
    $governorate = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $governorate->id]);
    $activityTypeId = (int) DB::table('activity_types')->insertGetId([
        'name' => 'بقالة', 'order' => 0, 'status' => RefStatus::Active->value,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $retailer = AppUser::factory()->retailer()->create();
    $retailerId = (int) DB::table('retailer_profiles')->insertGetId([
        'app_user_id' => $retailer->id, 'shop_name' => 'متجر', 'activity_type_id' => $activityTypeId,
        'governorate_id' => $governorate->id, 'zone_id' => $zone->id, 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $rep = AppUser::factory()->rep()->create();
    DB::table('rep_profiles')->insert([
        'app_user_id' => $rep->id, 'channel_id' => $channel->id, 'activity_type_id' => $activityTypeId,
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $orderId = (int) DB::table('orders')->insertGetId([
        'retailer_id' => $retailerId, 'source' => 'app', 'order_no' => 'ORD-FRZ', 'status' => 'assigned',
        'currency' => 'SYP', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $subOrderId = (int) DB::table('sub_orders')->insertGetId([
        'order_id' => $orderId, 'channel_id' => $channel->id, 'retailer_id' => $retailerId,
        'zone_id' => $zone->id, 'source' => 'app', 'sub_order_no' => 'SO-FRZ', 'status' => 'assigned',
        'subtotal' => 100_000, 'discount' => 0, 'total' => 100_000, 'rep_id' => $rep->id,
        'currency_code' => 'SYP', 'fx_rate' => Money::FX_UNIT,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('sub_order_assignments')->insert([
        'sub_order_id' => $subOrderId, 'rep_id' => $rep->id, 'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    actingAsPlatformAdmin();
    $this->postJson(transitionUrl($channel), ['to_status' => 'suspended', 'reason' => 'تأخر سداد'])->assertOk();

    // A different guard, but the same application: forget the resolved users so the
    // rep's token is read rather than the admin answered for.
    $this->app['auth']->forgetGuards();

    $this->postJson("/api/v1/app/rep/assignments/{$subOrderId}/accept", [], [
        'Authorization' => 'Bearer '.$rep->createToken('duty', ['*'])->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted');

    expect(DB::table('sub_orders')->where('id', $subOrderId)->value('status'))->toBe('accepted')
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Suspended);
});

it('freezes a new order for a suspended channel even from a cart filled before the suspension', function () {
    // Requirement 2's other half. The freeze today is a synchronous read of
    // `supply_channels.status` — `activeIdsCoveringZone()` drops a suspended channel from
    // the retailer's shopping context, so nothing new can be added to a cart for it. But
    // `SubmitRetailerCart` and `SubmitRepCartSection` never re-check the section's channel
    // at submission, so a cart filled before the suspension still becomes a sub-order.
    //
    // Closing it is one `ChannelDirectory::isActive()` per section in Ordering's two
    // submit actions, which is outside BE-T13's working rules, and the catalog names no
    // error code for the refusal (EP-RT-025 lists 423 credit_limit_exceeded and 409
    // offer_no_longer_valid). Both are the owner's decisions; this stays red until made.
})->todo(note: 'Ordering does not re-check channel status at submission; fix and error code awaiting a decision.');
