<?php

declare(strict_types=1);

/**
 * Admin roster and cross-guard checks for `/admin/channels`. Create-shape and
 * EP-AD-051 acceptance criteria live in ChannelCreateRouteTest (BE-T04).
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Application\Jobs\ProvisionChannel;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(ChannelPlanSeeder::class);
});

it('lets a platform admin list every channel', function () {
    SupplyChannel::factory()->count(3)->create();

    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $this->getJson('/api/v1/admin/channels')->assertOk()->assertJsonCount(3, 'data');
});

it('lets a platform admin create a channel', function () {
    // Full EP-AD-051 body — ChannelCreateRouteTest owns the acceptance criteria; this
    // only proves the admin role still reaches the route after the permission gate moved
    // create off `role:platform_admin`.
    Queue::fake([ProvisionChannel::class]);

    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $governorate = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $governorate->id]);
    $activityTypeId = (int) DB::table('activity_types')->insertGetId([
        'name' => 'بقالة', 'order' => 0, 'status' => RefStatus::Active->value,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson('/api/v1/admin/channels', [
        'name' => 'Fresh Foods',
        'slug' => 'fresh-foods',
        'legal_form' => 'llc',
        'cr_number' => 'C999',
        'documents' => [],
        'governorate_ids' => [$governorate->id],
        'zone_ids' => [$zone->id],
        'activity_type_ids' => [$activityTypeId],
        'logo' => null,
        'plan_id' => ChannelPlan::query()->where('key', 'growth')->value('id'),
        'billing_cycle' => 'yearly',
        'trial_days' => 14,
        'limits' => [
            'users' => 25, 'warehouses' => 2, 'reps' => 20, 'skus' => 5000, 'storage_mb' => 2048,
        ],
        'custom_discount' => 0,
        'manager' => [
            'name' => 'Manager', 'phone' => '+963944000001',
            'email' => 'm@fresh.sy', 'invite_via' => 'whatsapp',
        ],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'provisioning');

    expect(SupplyChannel::query()->where('slug', 'fresh-foods')->exists())->toBeTrue();
});

/**
 * A real personal access token issued to a channel user, for the two tests below.
 * `Sanctum::actingAs()` sets a user on a guard directly and never resolves a token, so
 * it cannot measure what `auth:platform` does with a channel credential — under it these
 * two tests could not fail. The token is real, and each test sends exactly one request:
 * a guard keeps its resolved user for the life of the application, so a second request
 * here would answer from the first one's holder.
 */
function channelManagerBearer(): array
{
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    return ['Authorization' => 'Bearer '.$manager->createToken('admin-roster', ['*'])->plainTextToken];
}

it('blocks a channel manager from reading the admin roster even though they hold settings permissions', function () {
    // The holder carries every channel code in the catalog, and none of it matters here:
    // `auth:platform` rejects a channel token before any gate runs. Since BE-C02 the
    // answer names the reason, 403 `wrong_guard`, instead of 401 `unauthenticated`.
    // This route never depended on the permission vocabulary — it is `role:platform_admin`
    // behind a single-guard prefix, which is why the vocabulary batch did not touch it.
    $this->getJson('/api/v1/admin/channels', channelManagerBearer())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'wrong_guard');
});

it('blocks a channel manager from creating a channel', function () {
    $this->postJson('/api/v1/admin/channels', ['name' => 'x', 'slug' => 'x'], channelManagerBearer())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'wrong_guard');

    expect(SupplyChannel::where('slug', 'x')->exists())->toBeFalse();
});

it('lets a platform admin delete a channel', function () {
    $channel = SupplyChannel::factory()->create();

    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $this->deleteJson("/api/v1/admin/channels/{$channel->id}")->assertNoContent();

    expect(SupplyChannel::find($channel->id))->toBeNull();
});
