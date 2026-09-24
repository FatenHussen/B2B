<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function zonesMapManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('returns coverage zones with polygons and delivery windows', function () {
    $channel = SupplyChannel::factory()->create();
    $polygon = [
        'type' => 'Polygon',
        'coordinates' => [[[36.27, 33.50], [36.30, 33.50], [36.30, 33.53], [36.27, 33.53], [36.27, 33.50]]],
    ];
    $mezze = Zone::factory()->create([
        'name' => 'المزة',
        'polygon' => $polygon,
    ]);
    $windows = [['day' => 'sun', 'start' => '09:00', 'end' => '17:00']];

    Tenant::as($channel->id, function () use ($mezze, $windows): void {
        ChannelZone::query()->create([
            'zone_id' => $mezze->id,
            'delivery_days' => ['sun', 'wed'],
            'delivery_windows' => $windows,
            'delivery_fee' => '0.00',
        ]);
    });

    Sanctum::actingAs(zonesMapManager($channel), ['*'], 'channel');

    $this->getJson('/api/v1/channel/zones/map')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.zone_id', $mezze->id)
        ->assertJsonPath('data.0.zone_name', 'المزة')
        ->assertJsonPath('data.0.polygon', $polygon)
        ->assertJsonPath('data.0.delivery_days', ['sun', 'wed'])
        ->assertJsonPath('data.0.delivery_windows', $windows);
})->group('reference');

it('accepts delivery_windows on upsert and returns null polygon when zone has none', function () {
    $channel = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create(['name' => 'كفرسوسة', 'polygon' => null]);
    Sanctum::actingAs(zonesMapManager($channel), ['*'], 'channel');

    $windows = [
        ['day' => 'mon', 'start' => '08:00', 'end' => '12:00'],
        ['day' => 'wed', 'start' => '14:00', 'end' => '18:00'],
    ];

    $this->postJson('/api/v1/channel/zones', [
        'zone_id' => $zone->id,
        'delivery_days' => ['mon', 'wed'],
        'delivery_windows' => $windows,
        'delivery_fee' => 0,
    ])->assertCreated();

    $this->getJson('/api/v1/channel/zones/map')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.zone_id', $zone->id)
        ->assertJsonPath('data.0.zone_name', 'كفرسوسة')
        ->assertJsonPath('data.0.polygon', null)
        ->assertJsonPath('data.0.delivery_windows', $windows);
})->group('reference');

it('rejects invalid delivery_windows day or time format', function () {
    $channel = SupplyChannel::factory()->create();
    $zone = Zone::factory()->create();
    Sanctum::actingAs(zonesMapManager($channel), ['*'], 'channel');

    $this->postJson('/api/v1/channel/zones', [
        'zone_id' => $zone->id,
        'delivery_windows' => [['day' => 'sunday', 'start' => '09:00', 'end' => '17:00']],
    ])->assertUnprocessable();

    $this->postJson('/api/v1/channel/zones', [
        'zone_id' => $zone->id,
        'delivery_windows' => [['day' => 'sun', 'start' => '9am', 'end' => '17:00']],
    ])->assertUnprocessable();
})->group('reference');
