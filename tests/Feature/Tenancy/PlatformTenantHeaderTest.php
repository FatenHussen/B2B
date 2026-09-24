<?php

declare(strict_types=1);

/**
 * BF-07: `X-Channel-Id` must not switch the tenant for platform users.
 *
 * A former ResolveTenant branch honoured the header for anyone with role
 * `platform_admin`. No documented client sends it; DocsLast/platform.md says do not.
 * Closing the branch is pinned here so it cannot return silently.
 */

use Illuminate\Http\Request;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Http\Middleware\ResolveTenant;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Domain\Models\FeatureFlag;
use Modules\Tenancy\Domain\Models\FeatureFlagOverride;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('does not set the tenant from X-Channel-Id for platform_admin', function () {
    $user = PlatformUser::factory()->create();
    $user->assignRole('platform_admin');

    Tenant::forget();

    $request = Request::create('/api/v1/platform/features', 'GET');
    $request->headers->set('X-Channel-Id', '999');
    $request->setUserResolver(fn () => $user);

    (new ResolveTenant)->handle($request, function () {
        expect(Tenant::currentId())->toBeNull();

        return response('ok');
    });
})->group('tenancy');

it('lists feature overrides across channels even when X-Channel-Id is present', function () {
    $user = PlatformUser::factory()->create();
    $user->assignRole('platform_admin');

    $a = SupplyChannel::factory()->create();
    $b = SupplyChannel::factory()->create();

    $flag = FeatureFlag::query()->create([
        'key' => 'bf07_cross_channel',
        'enabled_globally' => true,
        'rollout_percent' => 100,
        'scopes' => [],
    ]);

    FeatureFlagOverride::query()->create([
        'feature_key' => $flag->key,
        'channel_id' => $a->id,
        'enabled' => false,
        'reason' => 'a',
        'actor_id' => $user->id,
    ]);
    FeatureFlagOverride::query()->create([
        'feature_key' => $flag->key,
        'channel_id' => $b->id,
        'enabled' => true,
        'reason' => 'b',
        'actor_id' => $user->id,
    ]);

    $token = $user->createToken('bf07', ['*'])->plainTextToken;

    $response = $this->getJson('/api/v1/platform/features', [
        'Authorization' => 'Bearer '.$token,
        'X-Channel-Id' => (string) $a->id,
    ]);

    $response->assertOk();

    $row = collect($response->json('data'))->firstWhere('key', $flag->key);
    expect($row)->not->toBeNull()
        ->and($row['channel_ids'])->toContain($a->id)
        ->and($row['channel_ids'])->toContain($b->id);
})->group('tenancy');
