<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function channelManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('lets a channel add coverage for a zone', function () {
    $channel = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();
    Sanctum::actingAs(channelManager($channel), ['*'], 'channel');

    $this->postJson('/api/v1/channel/zones', [
        'zone_id' => $zone->id,
        'delivery_days' => ['sun', 'tue'],
        'delivery_fee' => 2.5,
    ])->assertCreated()
        ->assertJsonPath('data.zone_id', $zone->id)
        ->assertJsonPath('data.delivery_fee', '2.50');

    expect(ChannelZone::withoutGlobalScope('channel')->where('supply_channel_id', $channel->id)->count())->toBe(1);
})->group('tenancy');

it('upserts coverage instead of duplicating it', function () {
    $channel = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();
    Sanctum::actingAs(channelManager($channel), ['*'], 'channel');

    $this->postJson('/api/v1/channel/zones', ['zone_id' => $zone->id, 'delivery_fee' => 1])->assertCreated();
    $this->postJson('/api/v1/channel/zones', ['zone_id' => $zone->id, 'delivery_fee' => 3])
        ->assertCreated()
        ->assertJsonPath('data.delivery_fee', '3.00');

    expect(ChannelZone::withoutGlobalScope('channel')->where('supply_channel_id', $channel->id)->count())->toBe(1);
})->group('tenancy');

it('never shows one channel a coverage row that belongs to another', function () {
    $channelA = SupplyChannel::factory()->create();
    $channelB = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();

    Sanctum::actingAs(channelManager($channelA), ['*'], 'channel');
    $this->postJson('/api/v1/channel/zones', ['zone_id' => $zone->id])->assertCreated();

    Sanctum::actingAs(channelManager($channelB), ['*'], 'channel');
    $this->getJson('/api/v1/channel/zones')->assertOk()->assertJsonCount(0, 'data');
})->group('tenancy');

it('cannot delete another channel coverage row by id', function () {
    $channelA = SupplyChannel::factory()->create();
    $channelB = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();

    Sanctum::actingAs(channelManager($channelA), ['*'], 'channel');
    $coverageId = $this->postJson('/api/v1/channel/zones', ['zone_id' => $zone->id])
        ->json('data.id');

    Sanctum::actingAs(channelManager($channelB), ['*'], 'channel');
    $this->deleteJson("/api/v1/channel/zones/{$coverageId}")->assertNotFound();

    expect(ChannelZone::withoutGlobalScope('channel')->find($coverageId))->not->toBeNull();
})->group('tenancy');
