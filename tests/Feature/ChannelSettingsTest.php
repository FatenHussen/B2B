<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('shows a channel manager their own channel', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'Mine']);
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $this->getJson('/api/v1/channel')->assertOk()->assertJsonPath('data.id', $channel->id);
});

it('lets a channel manager update their own channel but not its slug or status', function () {
    $channel = SupplyChannel::factory()->create(['slug' => 'original']);
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $this->putJson('/api/v1/channel', [
        'name' => 'Renamed',
        'slug' => 'hijacked',
        'status' => 'suspended',
    ])->assertOk()->assertJsonPath('data.name', 'Renamed');

    expect($channel->refresh()->slug)->toBe('original')
        ->and($channel->status)->toBe(ChannelStatus::Active);
});

it('never lets a channel manager reach another channel through this endpoint', function () {
    $ownChannel = SupplyChannel::factory()->create();
    $otherChannel = SupplyChannel::factory()->create(['name' => 'Not mine']);

    $manager = ChannelUser::factory()->forChannel($ownChannel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $response = $this->getJson('/api/v1/channel')->assertOk();

    expect($response->json('data.id'))->toBe($ownChannel->id)
        ->and($response->json('data.id'))->not->toBe($otherChannel->id);
});

it('rejects an app token on the channel route as a guard mismatch', function () {
    $retailer = AppUser::factory()->retailer()->create();

    // A real token, not `Sanctum::actingAs()`. actingAs seats a user on a guard without
    // resolving a credential, so it cannot present an app token to `auth:channel` at all
    // — under it this test asserted a mismatch it never performed. Since BE-C02 the
    // channel guard's rejection is re-read from the presented token and named: an app
    // holder is 403 `wrong_guard`, distinct from having no token at all.
    $token = $retailer->createToken('channel-settings', ['*'])->plainTextToken;

    $this->getJson('/api/v1/channel', ['Authorization' => 'Bearer '.$token])
        ->assertForbidden()
        ->assertJsonPath('error.code', 'wrong_guard');
});

it('rejects a request with no token at all as unauthenticated', function () {
    // The control for the test above: `wrong_guard` only means something if an absent
    // credential still answers `unauthenticated`.
    $this->getJson('/api/v1/channel')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});
