<?php

declare(strict_types=1);

/**
 * Smoke coverage for the remaining PA surfaces landed 2026-09-23.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

function platformSmokeAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create(['password' => 'password']);
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

it('serves features team settings dashboard and subscriptions', function () {
    $this->seed(ChannelPlanSeeder::class);
    platformSmokeAdmin();

    $this->getJson('/api/v1/platform/features')->assertOk();
    $this->postJson('/api/v1/platform/features', [
        'key' => 'offline_orders',
        'description' => 'offline',
        'enabled_globally' => true,
        'rollout_percent' => 100,
        'scopes' => ['retailer'],
    ])->assertCreated();

    $this->getJson('/api/v1/platform/team')->assertOk();
    $this->getJson('/api/v1/platform/settings/profile')->assertOk();
    $this->getJson('/api/v1/platform/dashboard')->assertOk();
    $this->getJson('/api/v1/platform/subscriptions')->assertOk();
    $this->getJson('/api/v1/platform/system/queues')->assertOk();
    $this->getJson('/api/v1/platform/support/tickets')->assertOk();
    $this->getJson('/api/v1/platform/content/legal')->assertOk();
    $this->getJson('/api/v1/platform/app-versions')->assertOk();
    $this->getJson('/api/v1/platform/notifications/campaigns')->assertOk();

    $channel = SupplyChannel::factory()->create([
        'plan_id' => ChannelPlan::query()->where('key', 'growth')->value('id'),
    ]);

    $this->postJson("/api/v1/platform/channels/{$channel->id}/plan", [
        'plan_id' => $channel->plan_id,
        'cycle' => 'monthly',
        'reason' => 'تعيين باقة للنمو',
    ])->assertOk()->assertJsonStructure(['data' => ['subscription_id', 'status']]);
});
