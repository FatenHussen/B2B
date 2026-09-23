<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Enums\ChannelApplicationStatus;
use Modules\Tenancy\Domain\Models\ChannelApplication;
use Modules\Tenancy\Domain\Models\ChannelPlan;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    $this->seed(ChannelPlanSeeder::class);
});

function appsAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

it('lists and rejects a channel application', function () {
    appsAdmin();

    $app = new ChannelApplication;
    $app->fill([
        'name' => 'شركة بردى',
        'legal_form' => 'llc',
        'cr_number' => 'C77881',
        'documents' => [],
        'contact' => [],
    ]);
    $app->status = ChannelApplicationStatus::UnderReview;
    $app->save();

    $this->getJson('/api/v1/platform/channel-applications?filter[status]=under_review')
        ->assertOk()
        ->assertJsonPath('data.0.id', $app->id);

    $this->postJson("/api/v1/platform/channel-applications/{$app->id}/decide", [
        'decision' => 'reject',
        'reason' => 'مستندات ناقصة',
    ])->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.channel_id', null);

    $this->postJson("/api/v1/platform/channel-applications/{$app->id}/decide", [
        'decision' => 'reject',
        'reason' => 'مرة ثانية',
    ])->assertStatus(409);
});

it('approves an application into a provisioning channel', function () {
    appsAdmin();
    $planId = (int) ChannelPlan::query()->where('key', 'growth')->value('id');

    $app = new ChannelApplication;
    $app->fill([
        'name' => 'شركة بردى',
        'legal_form' => 'llc',
        'cr_number' => 'C77881',
        'documents' => [],
        'contact' => ['governorate_ids' => [], 'activity_type_ids' => [], 'zone_ids' => []],
    ]);
    $app->status = ChannelApplicationStatus::UnderReview;
    $app->save();

    $this->postJson("/api/v1/platform/channel-applications/{$app->id}/decide", [
        'decision' => 'approve',
        'reason' => 'مستندات السجل التجاري مكتملة',
        'plan_id' => $planId,
        'trial_days' => 14,
    ])->assertOk()
        ->assertJsonPath('data.status', 'provisioning')
        ->assertJsonStructure(['data' => ['application_id', 'channel_id', 'status']]);
});
