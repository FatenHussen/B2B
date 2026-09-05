<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('lets a platform admin list every channel', function () {
    SupplyChannel::factory()->count(3)->create();

    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $this->getJson('/api/v1/admin/channels')->assertOk()->assertJsonCount(3, 'data');
});

it('lets a platform admin create a channel', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $this->postJson('/api/v1/admin/channels', [
        'name' => 'Fresh Foods',
        'slug' => 'fresh-foods',
    ])->assertCreated()->assertJsonPath('data.slug', 'fresh-foods');
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
    // The holder does carry `settings.view` — 66 interim AccessMatrix names are seeded
    // on the channel guard and channel_manager holds all of them. It never matters here:
    // `auth:platform` rejects a channel token before any gate runs. Since BE-C02 the
    // answer names the reason, 403 `wrong_guard`, instead of 401 `unauthenticated`.
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
