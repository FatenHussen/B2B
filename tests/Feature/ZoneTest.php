<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reference\Domain\Enums\ZoneStatus;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

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

it('lets a channel manager create a zone', function () {
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $governorate = Governorate::factory()->create();

    $this->postJson('/api/v1/zones', [
        'governorate_id' => $governorate->id,
        'name' => 'Mazzeh',
    ])->assertCreated()
        ->assertJsonPath('data.status', ZoneStatus::Active->value);
});

it('updates zone status', function () {
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $zone = Zone::factory()->create();

    $this->putJson("/api/v1/zones/{$zone->id}", ['status' => ZoneStatus::Inactive->value])
        ->assertOk()
        ->assertJsonPath('data.status', ZoneStatus::Inactive->value);
});
