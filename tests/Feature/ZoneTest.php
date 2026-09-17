<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * Two retailers, one rep and five sub-orders in the given zone.
 *
 * Written straight to the tables of Identity and Ordering because neither module ships a
 * factory for these, and because the point of the assertion is that the contracts read
 * real rows: a double would pass whatever the implementations did.
 *
 * The five sub-orders are three open and two terminal — `delivered` and `cancelled` —
 * so the expected count of 3 distinguishes a working negation from a plain row count.
 *
 * @return array{retailers: list<int>, reps: list<int>, sub_orders: list<int>}
 */
function zoneFixtures(int $zoneId): array
{
    $channel = SupplyChannel::factory()->create();
    $governorate = Governorate::factory()->create();
    $activityTypeId = DB::table('activity_types')->insertGetId([
        'name' => 'بقالة', 'order' => 0, 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $retailers = [];
    foreach (range(1, 2) as $i) {
        $user = AppUser::factory()->retailer()->create();
        $retailers[] = (int) DB::table('retailer_profiles')->insertGetId([
            'app_user_id' => $user->id,
            'shop_name' => "shop-{$zoneId}-{$i}",
            'activity_type_id' => $activityTypeId,
            'governorate_id' => $governorate->id,
            'zone_id' => $zoneId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $repUser = AppUser::factory()->rep()->create();
    $repProfileId = (int) DB::table('rep_profiles')->insertGetId([
        'app_user_id' => $repUser->id,
        'channel_id' => $channel->id,
        'activity_type_id' => $activityTypeId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('rep_profile_zones')->insert([
        'rep_profile_id' => $repProfileId,
        'zone_id' => $zoneId,
    ]);

    $orderId = (int) DB::table('orders')->insertGetId([
        'retailer_id' => $retailers[0],
        'source' => 'app',
        'order_no' => 'O-'.$zoneId.'-'.uniqid(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $subOrders = [];
    // Three open, two terminal.
    foreach (['pending', 'confirmed', 'on_the_way', 'delivered', 'cancelled'] as $i => $status) {
        $subOrders[] = (int) DB::table('sub_orders')->insertGetId([
            'order_id' => $orderId,
            'channel_id' => $channel->id,
            'retailer_id' => $retailers[0],
            'zone_id' => $zoneId,
            'source' => 'app',
            'sub_order_no' => 'S-'.$zoneId.'-'.$i.'-'.uniqid(),
            'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return ['retailers' => $retailers, 'reps' => [$repProfileId], 'sub_orders' => $subOrders];
}

it('filters zones by governorate', function () {
    $damascus = Governorate::factory()->create();
    $aleppo = Governorate::factory()->create();
    Zone::factory()->count(2)->create(['governorate_id' => $damascus->id]);
    Zone::factory()->create(['governorate_id' => $aleppo->id]);

    Sanctum::actingAs(AppUser::factory()->retailer()->create(), ['*'], 'app');

    $this->getJson("/api/v1/zones?governorate_id={$damascus->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets a platform admin create a zone', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();

    $this->postJson('/api/v1/platform/refs/zones', [
        'governorate_id' => $governorate->id,
        'name' => 'Mazzeh',
    ])->assertCreated()
        ->assertJsonPath('data.status', ZoneStatus::Active->value);
});

it('refuses a platform user without ad.refs.create creating a zone', function () {
    // This test read `assertCreated` until the vocabulary batch, and passed. Zones are
    // shared reference data owned by the platform; the gate is `ad.refs.create`. Under
    // `/platform/refs` a channel token fails the guard before the gate, so the gate is
    // measured with a platform user who holds `ad.refs.view` and nothing more.
    $viewer = PlatformUser::factory()->create();
    $viewer->givePermissionTo('ad.refs.view');
    Sanctum::actingAs($viewer, ['*'], 'platform');

    $governorate = Governorate::factory()->create();

    $this->postJson('/api/v1/platform/refs/zones', [
        'governorate_id' => $governorate->id,
        'name' => 'Mazzeh',
    ])->assertForbidden()
        ->assertJsonPath('error.permission', 'ad.refs.create');

    expect(Zone::where('name', 'Mazzeh')->exists())->toBeFalse();
});

it('no longer lets PUT change a zone status', function () {
    // This test read `assertOk` and asserted the status had changed, which made PUT a
    // second way to disable a zone — no reason recorded, no impact shown. EP-AD-042B's
    // body never carried `status`; only EP-AD-034 moves it. The field is now dropped by
    // validation and by $fillable, so the rename succeeds and the status stands.
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $zone = Zone::factory()->create(['name' => 'Old']);

    $this->putJson("/api/v1/platform/refs/zones/{$zone->id}", ['name' => 'New', 'status' => 'disabled', 'reason' => 'إعادة تسمية'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.status', 'active');

    expect($zone->refresh()->status)->toBe(ZoneStatus::Active);
});

it('refuses a platform user without ad.refs.update updating a zone', function () {
    $viewer = PlatformUser::factory()->create();
    $viewer->givePermissionTo('ad.refs.view');
    Sanctum::actingAs($viewer, ['*'], 'platform');

    $zone = Zone::factory()->create(['name' => 'Untouched']);

    $this->putJson("/api/v1/platform/refs/zones/{$zone->id}", ['name' => 'Hijacked', 'reason' => 'probe'])
        ->assertForbidden()
        ->assertJsonPath('error.permission', 'ad.refs.update');

    expect($zone->refresh()->name)->toBe('Untouched');
});

it('has no route that deletes a zone', function () {
    // `it('deletes a zone')` never existed here, but DELETE did, and it hard deleted.
    // Rule 12 and no `ad.refs.delete` in DOC-08. 405, not 404: the path still serves GET
    // and PUT, so Laravel reports the method as unallowed.
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $zone = Zone::factory()->create();

    $this->deleteJson("/api/v1/platform/refs/zones/{$zone->id}")->assertStatus(405);

    expect(Zone::find($zone->id))->not->toBeNull();
});

it('disables a zone and returns the three impact counts computed live', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $zone = Zone::factory()->create();
    $other = Zone::factory()->create();

    // Real rows in the real tables of the two other modules, not doubles: the point is
    // that the contracts read what Identity and Ordering actually store.
    [$inZone, $elsewhere] = [zoneFixtures($zone->id), zoneFixtures($other->id)];

    $this->patchJson("/api/v1/platform/refs/zones/{$zone->id}/status", [
        'status' => 'disabled',
        'reason' => 'إعادة ترسيم الحدود',
    ])->assertOk()
        ->assertJsonPath('data.status', 'disabled')
        ->assertJsonPath('data.affected.retailers', 2)
        ->assertJsonPath('data.affected.reps', 1)
        // 3 open of 5: delivered, cancelled and rejected are terminal per DOC-01 §4.6.3.
        ->assertJsonPath('data.affected.open_orders', 3);

    expect($zone->refresh()->status)->toBe(ZoneStatus::Inactive)
        // Counts are scoped to the zone asked about, not global.
        ->and($elsewhere)->not->toBeEmpty()
        ->and($inZone)->not->toBeEmpty();
});

it('speaks disabled and stores inactive, in both directions', function () {
    // The contract test for the boundary translation. The column keeps `inactive`; the
    // API only ever says `disabled`. Unifying them needs a data migration — deferred.
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $zone = Zone::factory()->create();

    $this->patchJson("/api/v1/platform/refs/zones/{$zone->id}/status", [
        'status' => 'disabled',
        'reason' => 'اختبار المفردات',
    ])->assertOk()->assertJsonPath('data.status', 'disabled');

    // Stored spelling, read straight from the column.
    expect(DB::table('zones')->where('id', $zone->id)->value('status'))->toBe('inactive');

    // And read back out through the contract vocabulary, never the stored one.
    $this->getJson("/api/v1/platform/refs/zones/{$zone->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'disabled');
});

it('refuses the stored spelling that the contract does not define', function () {
    // `inactive` is what the column holds, and it is not a word EP-AD-034 accepts.
    // Refused like any other unknown value rather than let in through the back door.
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $zone = Zone::factory()->create();

    $this->patchJson("/api/v1/platform/refs/zones/{$zone->id}/status", [
        'status' => 'inactive',
        'reason' => 'المفردة المخزَّنة',
    ])->assertStatus(422);

    expect($zone->refresh()->status)->toBe(ZoneStatus::Active);
});

it('refuses a zone status change with no reason', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $zone = Zone::factory()->create();

    $this->patchJson("/api/v1/platform/refs/zones/{$zone->id}/status", ['status' => 'disabled'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($zone->refresh()->status)->toBe(ZoneStatus::Active);
});

it('never lets a channel manager disable a zone', function () {
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    $zone = Zone::factory()->create();
    $token = $manager->createToken('zone-disable-probe', ['*'])->plainTextToken;

    $this->patchJson("/api/v1/platform/refs/zones/{$zone->id}/status", [
        'status' => 'disabled',
        'reason' => 'محاولة',
    ], ['Authorization' => 'Bearer '.$token])->assertForbidden();

    expect($zone->refresh()->status)->toBe(ZoneStatus::Active);
});

it('pins the zone status route to 034 so it cannot drift to the 043 family', function () {
    // BE-R03 acceptance criterion. EP-AD-034 is a documented exception: every other ref
    // status route is in the 043 family, and this one is not. The catalog is the source
    // of truth for the path, so the assertion reads it rather than restating it.
    // Read from the generated catalog rather than the PHP source: the source files call
    // an `ep()` helper defined in generate.php, which does a great deal more than define
    // it. b2b-api.catalog.json is that same contract, already resolved.
    $endpoints = collect(
        json_decode((string) file_get_contents(base_path('docs/api/b2b-api.catalog.json')), true)['endpoints']
    );

    $entry = $endpoints->firstWhere('code', 'EP-AD-034');

    expect($entry)->not->toBeNull()
        ->and($entry['method'])->toBe('PATCH')
        ->and($entry['path'])->toBe('/platform/refs/zones/{id}/status')
        ->and($entry['permission'])->toBe('ad.refs.disable');

    // The exception itself: the zone status path belongs to 034 and to nothing in the
    // 043 family. 043A and 043B are real — governorates and activity types — so this
    // asserts that none of them has taken over the zone path, not that 043 is absent.
    $zoneStatusCodes = $endpoints
        ->where('path', '/platform/refs/zones/{id}/status')
        ->pluck('code')
        ->all();

    expect($zoneStatusCodes)->toBe(['EP-AD-034']);

    // And the live route matches the catalog on method, path and permission. The route
    // moved under /platform/refs with BE-R01, so the path is pinned as well now.
    $route = collect(Route::getRoutes())->first(
        fn ($r) => $r->uri() === 'api/v1/platform/refs/zones/{zone}/status'
    );

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('PATCH')
        ->and($route->gatherMiddleware())->toContain('permission:ad.refs.disable');
});
