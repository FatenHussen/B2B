<?php

declare(strict_types=1);

namespace Modules\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Tenancy\Domain\Enums\PlanOnExceed;
use Modules\Tenancy\Domain\Enums\PlanStatus;
use Modules\Tenancy\Domain\Models\ChannelPlan;

/**
 * The two plans a channel can be created on. Idempotent on `key`.
 *
 * Pricing numbers for growth match EP-AD-100A's catalog example; starter is smaller
 * until PA-06 wires live subscriptions.
 */
class ChannelPlanSeeder extends Seeder
{
    public function run(): void
    {
        $currencyId = DB::table('currencies')->orderBy('id')->value('id');

        $plans = [
            'starter' => [
                'name' => 'Starter',
                'price_monthly' => 10_000_000,
                'price_yearly' => 100_000_000,
                'limits' => [
                    'users' => 5,
                    'warehouses' => 1,
                    'reps' => 5,
                    'skus' => 1000,
                    'storage_mb' => 512,
                    'otp_monthly' => 5000,
                ],
                'features' => [],
                'on_exceed' => PlanOnExceed::Warn,
                'trial_days' => 14,
                'is_public' => true,
            ],
            'growth' => [
                'name' => 'Growth',
                'price_monthly' => 25_000_000,
                'price_yearly' => 250_000_000,
                'limits' => [
                    'users' => 25,
                    'warehouses' => 2,
                    'reps' => 20,
                    'skus' => 5000,
                    'storage_mb' => 2048,
                    'otp_monthly' => 20000,
                ],
                'features' => ['loyalty', 'multi_warehouse'],
                'on_exceed' => PlanOnExceed::Block,
                'trial_days' => 14,
                'is_public' => true,
            ],
        ];

        foreach ($plans as $key => $plan) {
            $row = ChannelPlan::query()->firstOrNew(['key' => $key]);
            $row->fill([
                'name' => $plan['name'],
                'price_monthly' => $plan['price_monthly'],
                'price_yearly' => $plan['price_yearly'],
                'currency_id' => $currencyId !== null ? (int) $currencyId : null,
                'limits' => $plan['limits'],
                'features' => $plan['features'],
                'on_exceed' => $plan['on_exceed'],
                'trial_days' => $plan['trial_days'],
                'is_public' => $plan['is_public'],
                'is_active' => true,
            ]);
            if (! $row->exists) {
                $row->status = PlanStatus::Active;
            } elseif ($row->status === null) {
                $row->status = PlanStatus::Active;
            }
            $row->save();
        }
    }
}
