<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Application\Actions\CreateChannel;
use Modules\Tenancy\Application\Actions\DecideChannelApplication;
use Modules\Tenancy\Application\Services\ChannelApplicationLifecycle;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Enums\ChannelApplicationStatus;
use Modules\Tenancy\Domain\Models\ChannelApplication;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\SupplyChannel;

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

it('creates exactly one channel when the same application is approved twice', function () {
    $admin = appsAdmin();
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

    $before = SupplyChannel::query()->count();
    $payload = ['decision' => 'approve', 'reason' => 'مستندات السجل التجاري مكتملة', 'plan_id' => $planId];

    // The second approval arrives holding a copy of the row that still says
    // `under_review` — what two concurrent requests, a double click or a retry without an
    // idempotency key each see. The lifecycle decides on the row it re-reads under a
    // lock, not on the caller's copy, so the stale one is refused.
    $stale = ChannelApplication::query()->findOrFail($app->id);
    expect($stale->status)->toBe(ChannelApplicationStatus::UnderReview);

    $first = $this->postJson("/api/v1/platform/channel-applications/{$app->id}/decide", $payload)
        ->assertOk()
        ->json('data.channel_id');

    $action = app(DecideChannelApplication::class);
    expect(fn () => $action($stale, $payload, $admin))
        ->toThrow(DomainException::class);

    $this->postJson("/api/v1/platform/channel-applications/{$app->id}/decide", $payload)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'illegal_transition');

    expect(SupplyChannel::query()->count())->toBe($before + 1)
        ->and(ChannelApplication::query()->findOrFail($app->id)->channel_id)->toBe((int) $first);
});

it('leaves no channel behind when the decision fails after provisioning', function () {
    $admin = appsAdmin();
    $planId = (int) ChannelPlan::query()->where('key', 'growth')->value('id');

    $app = new ChannelApplication;
    $app->fill(['name' => 'شركة بردى', 'legal_form' => 'llc', 'cr_number' => 'C77881', 'documents' => [], 'contact' => []]);
    $app->status = ChannelApplicationStatus::UnderReview;
    $app->save();

    $before = SupplyChannel::query()->count();

    // Provisioning succeeds, then the decision write fails: the channel must roll back
    // with it, and the application must still be decidable.
    $lifecycle = app(ChannelApplicationLifecycle::class);
    $createChannel = app(CreateChannel::class);
    $plan = ChannelPlan::query()->findOrFail($planId);

    expect(fn () => $lifecycle->decide(
        $app,
        ChannelApplicationStatus::Provisioning,
        $admin,
        'x',
        function (ChannelApplication $locked) use ($createChannel, $plan, $admin): int {
            $createChannel([
                'name' => $locked->name,
                'slug' => 'barada-'.Str::lower(Str::random(6)),
                'legal_form' => 'llc',
                'cr_number' => 'C77881',
                'documents' => [],
                'plan_id' => (int) $plan->id,
                'billing_cycle' => 'monthly',
                'trial_days' => 0,
                'limits' => ['users' => 5, 'warehouses' => 1, 'reps' => 5, 'skus' => 100, 'storage_mb' => 100],
                'governorate_ids' => [],
                'activity_type_ids' => [],
                'zone_ids' => [],
            ], $admin);

            throw new RuntimeException('decision write failed');
        },
    ))->toThrow(RuntimeException::class);

    expect(SupplyChannel::query()->count())->toBe($before)
        ->and(ChannelApplication::query()->findOrFail($app->id)->status)->toBe(ChannelApplicationStatus::UnderReview);
});
