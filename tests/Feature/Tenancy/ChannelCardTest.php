<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\ChannelUserChannel;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function cardAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

it('lists channel users warehouses and updates coverage', function () {
    cardAdmin();
    $channel = SupplyChannel::factory()->create();
    $user = ChannelUser::factory()->create(['name' => 'محمد علي']);
    ChannelUserChannel::query()->create([
        'channel_user_id' => $user->id,
        'channel_id' => $channel->id,
        'is_default' => true,
    ]);

    Tenant::as($channel->id, fn () => Warehouse::query()->create([
        'channel_id' => $channel->id,
        'name' => 'المستودع الرئيسي',
        'status' => WarehouseStatus::Active,
    ]));

    $this->getJson("/api/v1/platform/channels/{$channel->id}/users")
        ->assertOk()
        ->assertJsonPath('data.0.id', $user->id)
        ->assertJsonPath('data.0.name', 'محمد علي');

    $this->getJson("/api/v1/platform/channels/{$channel->id}/warehouses")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'المستودع الرئيسي');

    $gov = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $gov->id]);

    $this->putJson("/api/v1/platform/channels/{$channel->id}/coverage", [
        'zone_ids' => [$zone->id],
        'reason' => 'توسيع التغطية',
    ])->assertOk()->assertJsonPath('data.id', $channel->id);

    $this->putJson("/api/v1/platform/channels/{$channel->id}/coverage", [
        'zone_ids' => [$zone->id],
    ])->assertStatus(422);

    $this->getJson('/api/v1/platform/channels/999999/coverage')->assertNotFound();
});
