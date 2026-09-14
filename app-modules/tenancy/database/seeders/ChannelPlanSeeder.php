<?php

declare(strict_types=1);

namespace Modules\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tenancy\Domain\Models\ChannelPlan;

/**
 * The two plans a channel can be created on (BE-T04). Idempotent: keyed on `key`,
 * re-runnable on every deploy, never duplicates.
 *
 * `growth` carries the limits the catalog itself shows for a channel on plan 2
 * (EP-AD-051's example body, EP-AD-050's `plan: {id: 2, key: growth}`). `starter` is
 * named by the sprint spec (§5.1, "starter|growth") but its limits are named nowhere —
 * the numbers below are placeholders, deliberately smaller than growth's, until
 * BE4-BIL01 fixes plans and their pricing. Nothing prices a plan here.
 */
class ChannelPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            'starter' => [
                'name' => 'Starter',
                'limits' => ['users' => 5, 'warehouses' => 1, 'reps' => 5, 'skus' => 1000, 'storage_mb' => 512],
            ],
            'growth' => [
                'name' => 'Growth',
                'limits' => ['users' => 25, 'warehouses' => 2, 'reps' => 20, 'skus' => 5000, 'storage_mb' => 2048],
            ],
        ];

        foreach ($plans as $key => $plan) {
            ChannelPlan::query()->updateOrCreate(
                ['key' => $key],
                ['name' => $plan['name'], 'limits' => $plan['limits'], 'is_active' => true],
            );
        }
    }
}
