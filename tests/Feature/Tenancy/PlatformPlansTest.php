<?php

declare(strict_types=1);

/**
 * PA-02 — EP-AD-100A/B/C/D platform plan CRUD.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Reference\Domain\Models\Currency;
use Modules\Tenancy\Database\Seeders\ChannelPlanSeeder;
use Modules\Tenancy\Domain\Models\ChannelLimit;
use Modules\Tenancy\Domain\Models\ChannelPlan;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
});

function plansUrl(?int $id = null): string
{
    return $id === null
        ? '/api/v1/platform/plans'
        : "/api/v1/platform/plans/{$id}";
}

function actingAsPlansAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

function currencyIdForPlans(): int
{
    $id = Currency::query()->value('id');
    if ($id !== null) {
        return (int) $id;
    }

    return (int) Currency::query()->create([
        'iso' => 'SYP',
        'name' => 'ليرة',
        'symbol' => 'ل.س',
        'decimals' => 0,
        'is_display_currency' => true,
    ])->id;
}

/**
 * @return array<string, mixed>
 */
function catalogPlanBody(int $currencyId, string $key = 'enterprise'): array
{
    return [
        'name' => 'مؤسسي',
        'key' => $key,
        'price_monthly' => 75000000,
        'price_yearly' => 750000000,
        'currency_id' => $currencyId,
        'limits' => [
            'users' => 100,
            'warehouses' => 10,
            'reps' => 80,
            'skus' => 20000,
            'storage_mb' => 10240,
            'otp_monthly' => 100000,
        ],
        'features' => ['loyalty', 'multi_warehouse', 'custom_sso'],
        'on_exceed' => 'warn',
        'trial_days' => 30,
        'is_public' => false,
    ];
}

it('lists plans with catalog shape', function () {
    currencyIdForPlans();
    $this->seed(ChannelPlanSeeder::class);
    actingAsPlansAdmin();

    $this->getJson(plansUrl())
        ->assertOk()
        ->assertJsonPath('data.0.key', 'starter')
        ->assertJsonStructure([
            'data' => [['id', 'name', 'key', 'price_monthly', 'price_yearly', 'currency_id', 'limits', 'features', 'on_exceed', 'trial_days', 'is_public']],
        ]);
});

it('creates a plan and shows the catalog shape', function () {
    $currencyId = currencyIdForPlans();
    actingAsPlansAdmin();

    $create = $this->postJson(plansUrl(), catalogPlanBody($currencyId))
        ->assertCreated()
        ->json('data');

    expect($create)->toHaveKey('id');

    $this->getJson(plansUrl((int) $create['id']))
        ->assertOk()
        ->assertJsonPath('data.key', 'enterprise')
        ->assertJsonPath('data.price_monthly', 75000000)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.limits.otp_monthly', 100000)
        ->assertJsonPath('data.features.2', 'custom_sso');
});

it('rejects a duplicate plan key with 422', function () {
    $currencyId = currencyIdForPlans();
    $this->seed(ChannelPlanSeeder::class);
    actingAsPlansAdmin();

    $this->postJson(plansUrl(), catalogPlanBody($currencyId, 'growth'))
        ->assertStatus(422);
});

it('requires reason on update and audits it', function () {
    currencyIdForPlans();
    $this->seed(ChannelPlanSeeder::class);
    actingAsPlansAdmin();

    $plan = ChannelPlan::query()->where('key', 'growth')->sole();

    $this->putJson(plansUrl((int) $plan->id), [
        'price_monthly' => 28000000,
        'reason' => 'تعديل سعر بعد مراجعة الإيراد',
    ])->assertOk()->assertJsonPath('data.id', $plan->id);

    expect(AuditLog::query()->where('action', 'plan.updated')->where('subject_id', $plan->id)->exists())->toBeTrue();
});

it('updating plan limits does not rewrite existing channel_limits', function () {
    currencyIdForPlans();
    $this->seed(ChannelPlanSeeder::class);
    actingAsPlansAdmin();

    $plan = ChannelPlan::query()->where('key', 'growth')->sole();
    $channel = SupplyChannel::factory()->create(['plan_id' => $plan->id]);

    $limitKeys = array_intersect_key($plan->limits, array_flip(ChannelLimits::KEYS));
    Tenant::as($channel->id, fn () => ChannelLimit::query()->create(['channel_id' => $channel->id] + $limitKeys));

    $this->putJson(plansUrl((int) $plan->id), [
        'limits' => [
            'users' => 30,
            'warehouses' => 2,
            'reps' => 25,
            'skus' => 6000,
            'storage_mb' => 2048,
            'otp_monthly' => 25000,
        ],
        'reason' => 'رفع الحدود للنمو',
    ])->assertOk();

    $limits = Tenant::as($channel->id, fn () => ChannelLimit::query()->where('channel_id', $channel->id)->sole());
    expect($limits->users)->toBe(25)
        ->and($limits->reps)->toBe(20)
        ->and($limits->skus)->toBe(5000);

    $plan->refresh();
    expect($plan->limits['users'])->toBe(30);
});

it('forbids plan routes without permission', function () {
    currencyIdForPlans();
    $user = PlatformUser::factory()->create();
    Sanctum::actingAs($user, ['*'], 'platform');

    $this->getJson(plansUrl())->assertForbidden();
});
