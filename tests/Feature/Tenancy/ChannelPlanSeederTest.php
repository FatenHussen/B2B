<?php

declare(strict_types=1);

/**
 * BE-T04 — the two plans a channel can be created on, and the demo channel's way into
 * `active` now that a new row starts in `provisioning`.
 */

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Enums\ChannelStatus;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\SupplyChannel;

it('seeds starter and growth, and nothing else', function () {
    $this->seed(ChannelPlanSeeder::class);

    expect(ChannelPlan::query()->orderBy('id')->pluck('key')->all())->toBe(['starter', 'growth'])
        ->and(ChannelPlan::query()->where('is_active', false)->count())->toBe(0);
});

it('gives growth the limits the catalog shows for plan 2', function () {
    // EP-AD-051's example body, for a channel on plan_id 2 — the one EP-AD-050 calls
    // `growth`. Starter's limits are named nowhere; they are placeholders and not
    // pinned here on purpose.
    $this->seed(ChannelPlanSeeder::class);

    $growth = ChannelPlan::query()->where('key', 'growth')->sole();

    expect($growth->limits)->toBe([
        'users' => 25, 'warehouses' => 2, 'reps' => 20, 'skus' => 5000, 'storage_mb' => 2048, 'otp_monthly' => 20000,
    ]);
});

it('keeps every limit a whole number', function () {
    // Rule 7 and BE-T04 §3: limits are integers, on every plan.
    $this->seed(ChannelPlanSeeder::class);

    foreach (ChannelPlan::query()->get() as $plan) {
        foreach (['users', 'warehouses', 'reps', 'skus', 'storage_mb', 'otp_monthly'] as $key) {
            expect($plan->limits[$key])->toBeInt("{$plan->key}.{$key}");
        }
    }
});

it('is idempotent', function () {
    $this->seed(ChannelPlanSeeder::class);
    $this->seed(ChannelPlanSeeder::class);

    expect(ChannelPlan::query()->count())->toBe(2);
});

it('activates the demo channel through the lifecycle, once', function () {
    // The demo channel is created into `provisioning` like any other row and moved to
    // `active` by ChannelLifecycle with the seed's platform admin as actor — so even
    // seed data leaves a channel_events row, and re-seeding does not move it again.
    $this->seed(DatabaseSeeder::class);

    $channel = SupplyChannel::query()->where('slug', 'demo-channel')->sole();

    expect($channel->status)->toBe(ChannelStatus::Active)
        ->and(DB::table('channel_events')->where('channel_id', $channel->id)->count())->toBe(1)
        ->and(DB::table('channel_events')->where('channel_id', $channel->id)->value('reason'))->toBe('seed: demo channel');

    $this->seed(DatabaseSeeder::class);

    expect(DB::table('channel_events')->where('channel_id', $channel->id)->count())->toBe(1);
});
