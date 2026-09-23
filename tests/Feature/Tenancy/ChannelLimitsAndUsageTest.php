<?php

declare(strict_types=1);

/**
 * BE-T12 — EP-AD-055 `PUT /platform/channels/{id}/limits`, plan enforcement.
 * BE-T11 — EP-AD-056 `GET /platform/channels/{id}/usage`.
 */

use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\AppSurface;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(ChannelPlanSeeder::class);
});

function limitsAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

function channelOnGrowth(): SupplyChannel
{
    $plan = ChannelPlan::query()->where('key', 'growth')->firstOrFail();
    $channel = SupplyChannel::factory()->create(['plan_id' => $plan->id]);
    $limitKeys = array_intersect_key($plan->limits, array_flip(ChannelLimits::KEYS));
    Tenant::as($channel->id, fn () => ChannelLimit::query()->create(['channel_id' => $channel->id] + $limitKeys));

    return $channel;
}

// ── BE-T12 ──────────────────────────────────────────────────────────────────────────

it('overrides a limit with a mandatory reason and answers the effective limits', function () {
    $admin = limitsAdmin();
    $channel = channelOnGrowth();

    $this->putJson("/api/v1/platform/channels/{$channel->id}/limits", [
        'limits' => ['reps' => 30, 'skus' => 8000],
        'temporary_until' => now()->addMonth()->toIso8601String(),
        'reason' => 'موسم رمضان',
    ])->assertOk()
        ->assertJsonPath('data.limits.reps', 30)
        ->assertJsonPath('data.limits.skus', 8000)
        ->assertJsonPath('data.limits.users', 25)
        ->assertJsonPath('data.limits.warehouses', 2)
        ->assertJsonPath('data.limits.storage_mb', 2048);

    expect(app(ChannelLimits::class)->cap($channel->id, 'reps'))->toBe(30)
        ->and(Tenant::as($channel->id, fn () => ChannelLimit::query()->first()?->reps))->toBe(20);

    $audit = AuditLog::query()->where('action', 'channel.limits.override')->where('subject_id', $channel->id)->sole();
    expect($audit->properties['reason'])->toBe('موسم رمضان')
        ->and($audit->properties['before']['reps'])->toBe(20)
        ->and($audit->properties['after']['reps'])->toBe(30)
        ->and((int) $audit->actor_id)->toBe($admin->id);
});

it('refuses an override without a reason', function () {
    limitsAdmin();
    $channel = channelOnGrowth();

    $this->putJson("/api/v1/platform/channels/{$channel->id}/limits", ['limits' => ['reps' => 30]])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect(app(ChannelLimits::class)->cap($channel->id, 'reps'))->toBe(20);
});

it('an expired override reverts without a scheduled job having to run on time', function () {
    // BE-T12 acceptance criterion 1. Nothing runs between the two reads but the clock.
    limitsAdmin();
    $channel = channelOnGrowth();

    Carbon::setTestNow('2026-09-17 10:00:00');
    $this->putJson("/api/v1/platform/channels/{$channel->id}/limits", [
        'limits' => ['reps' => 30],
        'temporary_until' => '2026-09-18T10:00:00+00:00',
        'reason' => 'ذروة مؤقتة',
    ])->assertOk()->assertJsonPath('data.limits.reps', 30);

    expect(app(ChannelLimits::class)->cap($channel->id, 'reps'))->toBe(30);

    Carbon::setTestNow('2026-09-18 10:00:01');
    expect(app(ChannelLimits::class)->cap($channel->id, 'reps'))->toBe(20);

    Carbon::setTestNow();
});

it('423 names which limit was hit so the client can explain it', function () {
    // BE-T12 acceptance criterion 2, on the rep limit, where a rep is added: registration.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    Tenant::as($channel->id, fn () => ChannelLimit::query()->create([
        'channel_id' => $channel->id, 'users' => 5, 'warehouses' => 1, 'reps' => 1, 'skus' => 100, 'storage_mb' => 128,
    ]));
    AppSurface::rep($channel, $refs); // the one rep the plan allows

    $newcomer = AppUser::factory()->create(['kind' => null, 'status' => 'pending']);
    $token = $newcomer->createToken('reg', ['registration'])->plainTextToken;

    $this->postJson('/api/v1/app/rep/register', [
        'name' => 'مندوب ثانٍ',
        'supply_channel_id' => $channel->id,
        'activity_type_id' => $refs['activity']->id,
        'zone_ids' => [$refs['zone']->id],
    ], ['Authorization' => 'Bearer '.$token])
        ->assertStatus(423)
        ->assertJsonPath('error.code', 'plan_limit_exceeded')
        ->assertJsonPath('error.details.limit', 'reps')
        ->assertJsonPath('error.details.max', 1)
        ->assertJsonPath('error.details.used', 1);

    expect(RepProfile::query()->where('channel_id', $channel->id)->count())->toBe(1);
});

it('the SKU cap answers 423 plan_limit_exceeded naming skus', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    Tenant::as($channel->id, fn () => ChannelLimit::query()->create([
        'channel_id' => $channel->id, 'users' => 5, 'warehouses' => 1, 'reps' => 5, 'skus' => 1, 'storage_mb' => 128,
    ]));
    AppSurface::productWithBrand($this, $channel, $refs, 'FIRST-1');

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $this->postJson('/api/v1/channel/products', [
        'name_ar' => 'ثانٍ', 'name_en' => 'Second', 'sku' => 'SECOND-1',
        'status' => 'draft', 'sale_unit_id' => $refs['unit']->id, 'min_order_qty' => 1,
    ])->assertStatus(423)
        ->assertJsonPath('error.code', 'plan_limit_exceeded')
        ->assertJsonPath('error.details.limit', 'skus');
});

it('names ad.billing.assign_plan on a 403 for a platform user without it', function () {
    $user = PlatformUser::factory()->create();
    $user->givePermissionTo('ad.channels.view');
    Sanctum::actingAs($user, ['*'], 'platform');
    $channel = channelOnGrowth();

    $this->putJson("/api/v1/platform/channels/{$channel->id}/limits", ['limits' => ['reps' => 30], 'reason' => 'x'])
        ->assertForbidden()
        ->assertJsonPath('error.permission', 'ad.billing.assign_plan');
});

// ── BE-T11 ──────────────────────────────────────────────────────────────────────────

it('a channel with no activity returns thirty zero days, not an error and not an empty body', function () {
    limitsAdmin();
    $channel = channelOnGrowth();

    $response = $this->getJson("/api/v1/platform/channels/{$channel->id}/usage")->assertOk();

    $series = $response->json('data.series');
    expect($series)->toHaveCount(30)
        ->and(collect((array) $series)->every(fn (array $d) => $d['orders'] === 0 && $d['gmv'] === 0))->toBeTrue()
        ->and($series[29]['day'])->toBe(Carbon::today('Asia/Damascus')->toDateString());

    $response->assertJsonPath('data.limit_usage.reps.used', 0)
        ->assertJsonPath('data.limit_usage.reps.limit', 20)
        ->assertJsonPath('data.limit_usage.skus.limit', 5000)
        ->assertJsonPath('data.failed_jobs', 0)
        ->assertJsonPath('data.sync_status', 'healthy');
});

it('counts real orders and reps into the series and the limit usage', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $retailer = AppSurface::retailer($refs);
    AppSurface::rep($channel, $refs);
    AppSurface::subOrder($channel, $refs, AppSurface::retailerId($retailer), 'confirmed');
    AppSurface::subOrder($channel, $refs, AppSurface::retailerId($retailer), 'delivered');

    limitsAdmin();

    $response = $this->getJson("/api/v1/platform/channels/{$channel->id}/usage")->assertOk();
    $today = collect((array) $response->json('data.series'))->last();

    expect($today['orders'])->toBe(2)
        ->and($today['gmv'])->toBe(24000);

    $response->assertJsonPath('data.limit_usage.reps.used', 1);
});
