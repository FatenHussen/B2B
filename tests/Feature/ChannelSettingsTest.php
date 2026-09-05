<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
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
        ->and($channel->status)->toBe('active');
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

it('rejects a user with no channel', function () {
    $retailer = AppUser::factory()->retailer()->create();
    Sanctum::actingAs($retailer, ['*'], 'app');

    // Actual behaviour, not the documented one. An app token on a channel route is a
    // guard mismatch, and nothing in the codebase yet distinguishes that from being
    // unauthenticated: `auth:channel` simply finds no channel user and 401s. DOC-08
    // specifies 403 `wrong_guard` here; implementing it is BE-C02.
    $this->getJson('/api/v1/channel')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'unauthenticated');
});
