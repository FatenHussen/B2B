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

it('blocks a channel manager from the admin roster even though they hold settings permissions', function () {
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $this->getJson('/api/v1/admin/channels')->assertForbidden();
    $this->postJson('/api/v1/admin/channels', ['name' => 'x', 'slug' => 'x'])->assertForbidden();
});

it('lets a platform admin delete a channel', function () {
    $channel = SupplyChannel::factory()->create();

    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $this->deleteJson("/api/v1/admin/channels/{$channel->id}")->assertNoContent();

    expect(SupplyChannel::find($channel->id))->toBeNull();
});
