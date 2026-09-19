<?php

declare(strict_types=1);

/**
 * A rep works only while the profile is `active`, and stops the moment it is not.
 *
 * Three doors, each tested on its own:
 *
 * 1. `rep.profile` (EnsureRepProfileActive) is appended to every `api/v1/app/*` route at
 *    boot, the way `retailer.profile` is. A rep in `pending_review`, `rejected` or
 *    `disabled` gets 403 on every operational route; session and logout stay open.
 * 2. `RepProfileLifecycle` revokes every token the rep holds on the transition to
 *    `rejected` or `disabled`, so the device in the field is signed out at once instead
 *    of at whatever request next happens to read the profile.
 * 3. `AssignSubOrders` asks `RepDirectory::isActiveInChannel()` before handing out work:
 *    a channel cannot assign a sub-order to its own disabled rep.
 *
 * Real bearer tokens where revocation is the point; `Sanctum::actingAs` never consults
 * the token table, so it could not tell a revoked token from a live one.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RepDutyState;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function repWithStatus(ProfileStatus $status): AppUser
{
    $refs = AppSurface::refs();
    $rep = AppSurface::rep(AppSurface::channel($refs), $refs);
    RepProfile::query()->where('app_user_id', $rep->id)->update(['status' => $status->value]);

    return $rep->fresh() ?? $rep;
}

function repManagerOf(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

/** A guard memoises its user for the life of the application; every request in a test shares it. */
function forgetGuardUsers(): void
{
    app('auth')->forgetGuards();
}

// ─── 1. the middleware ───────────────────────────────────────────────────────────────

it('holds a rep who is not active off every operational route with 403', function (ProfileStatus $status) {
    Sanctum::actingAs(repWithStatus($status), ['*'], 'app');

    CatalogAssert::error($this->getJson('/api/v1/app/rep/deliveries'), 403, 'insufficient_permission');
    CatalogAssert::error($this->getJson('/api/v1/app/rep/wallet'), 403, 'insufficient_permission');
    CatalogAssert::error($this->getJson('/api/v1/app/rep/customers'), 403, 'insufficient_permission');
})->with([
    'pending_review' => ProfileStatus::PendingReview,
    'rejected' => ProfileStatus::Rejected,
    'disabled' => ProfileStatus::Disabled,
]);

it('lets an active rep through and a not-yet-active rep see their session and sign out', function () {
    Sanctum::actingAs(repWithStatus(ProfileStatus::Active), ['*'], 'app');
    CatalogAssert::ok($this->getJson('/api/v1/app/rep/deliveries'));

    forgetGuardUsers();

    $pending = repWithStatus(ProfileStatus::PendingReview);
    $token = $pending->createToken('device', ['*'])->plainTextToken;
    $headers = ['Authorization' => 'Bearer '.$token];

    CatalogAssert::ok($this->getJson('/api/v1/app/session', $headers));
    CatalogAssert::ok($this->postJson('/api/v1/app/auth/logout', [], $headers));
});

it('attaches rep.profile to every app route, so no module can publish a rep route without it', function () {
    $appRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/app/'));

    expect($appRoutes)->not->toBeEmpty();

    foreach ($appRoutes as $route) {
        expect(in_array('rep.profile', $route->gatherMiddleware(), true))->toBeTrue($route->uri().' carries no rep.profile');
    }
})->group('arch');

// ─── 2. revocation on the transition ─────────────────────────────────────────────────

it('revokes every token the rep holds when the channel disables them', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $rep = AppSurface::rep($channel, $refs);
    $phone = $rep->createToken('phone', ['*'])->plainTextToken;
    $tablet = $rep->createToken('tablet', ['*'])->plainTextToken;

    CatalogAssert::ok($this->getJson('/api/v1/app/rep/deliveries', ['Authorization' => 'Bearer '.$phone]));

    forgetGuardUsers();
    Sanctum::actingAs(repManagerOf($channel), ['*'], 'channel');
    CatalogAssert::ok($this->postJson("/api/v1/channel/reps/{$rep->id}/disable", ['reason' => 'ترك العمل']));

    expect(DB::table('personal_access_tokens')
        ->where('tokenable_type', $rep->getMorphClass())
        ->where('tokenable_id', $rep->id)
        ->count())->toBe(0);

    // Not 403 from the profile check: the credential itself is gone, and the renderer
    // says so — a well-formed bearer that no longer resolves is `token_revoked`.
    forgetGuardUsers();
    CatalogAssert::error($this->getJson('/api/v1/app/rep/deliveries', ['Authorization' => 'Bearer '.$phone]), 401, 'token_revoked');
    forgetGuardUsers();
    CatalogAssert::error($this->getJson('/api/v1/app/session', ['Authorization' => 'Bearer '.$tablet]), 401, 'token_revoked');
});

it('revokes every token the rep holds when the channel rejects them', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $rep = AppSurface::rep($channel, $refs);
    RepProfile::query()->where('app_user_id', $rep->id)->update(['status' => ProfileStatus::PendingReview->value]);
    $token = $rep->createToken('phone', ['*'])->plainTextToken;

    // Pending: the session is the one thing a rep can still see.
    CatalogAssert::ok($this->getJson('/api/v1/app/session', ['Authorization' => 'Bearer '.$token]));

    forgetGuardUsers();
    Sanctum::actingAs(repManagerOf($channel), ['*'], 'channel');
    CatalogAssert::ok($this->postJson("/api/v1/channel/reps/{$rep->id}/reject", ['reason' => 'ناقص']));

    forgetGuardUsers();
    CatalogAssert::error($this->getJson('/api/v1/app/session', ['Authorization' => 'Bearer '.$token]), 401, 'token_revoked');
    expect(DB::table('personal_access_tokens')->where('tokenable_id', $rep->id)->count())->toBe(0);
});

// ─── 3. assignment ──────────────────────────────────────────────────────────────────

it('refuses to assign a sub-order to a disabled rep, and assigns it to an active one', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $shop = AppSurface::retailer($refs);
    $disabled = AppSurface::rep($channel, $refs);
    $active = AppSurface::rep($channel, $refs);
    RepProfile::query()->where('app_user_id', $disabled->id)->update(['status' => ProfileStatus::Disabled->value]);
    foreach ([$disabled, $active] as $rep) {
        RepDutyState::query()->create(['rep_user_id' => $rep->id, 'on_duty' => true, 'tracking_enabled' => false]);
    }
    $id = AppSurface::subOrder($channel, $refs, AppSurface::retailerId($shop), 'confirmed');

    Sanctum::actingAs(repManagerOf($channel), ['*'], 'channel');

    $refused = $this->postJson('/api/v1/channel/sub-orders/assign', ['sub_order_ids' => [$id], 'rep_id' => $disabled->id]);
    CatalogAssert::error($refused, 422, 'validation_failed');
    expect($refused->json('error.message'))->toBe(__('ordering.rep_not_active'))
        ->and(DB::table('sub_orders')->where('id', $id)->value('rep_id'))->toBeNull();

    $granted = $this->postJson('/api/v1/channel/sub-orders/assign', ['sub_order_ids' => [$id], 'rep_id' => $active->id]);
    CatalogAssert::ok($granted);
    expect($granted->json('data.assigned'))->toBe([$id])
        ->and((int) DB::table('sub_orders')->where('id', $id)->value('rep_id'))->toBe($active->id);
});
