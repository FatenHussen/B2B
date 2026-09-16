<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('exposes the rep commercial limits on session', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $rep = AppSurface::rep($channel, $refs);
    Sanctum::actingAs($rep, ['*'], 'app');

    $empty = $this->getJson('/api/v1/app/session');
    CatalogAssert::ok($empty, ['user', 'permissions', 'feature_flags', 'commercial_limits']);
    expect($empty->json('data.commercial_limits.max_discount_percent'))->toBe(0)
        ->and($empty->json('data.commercial_limits.max_cash_hold'))->toBe(0);

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    app('auth')->forgetGuards();
    Sanctum::actingAs($manager, ['*'], 'channel');
    $this->putJson('/api/v1/channel/reps/'.$rep->id.'/discount-cap', [
        'max_discount_percent' => 5,
        'max_cash_hold' => 500000,
    ])->assertOk();

    app('auth')->forgetGuards();
    Sanctum::actingAs($rep, ['*'], 'app');
    $capped = $this->getJson('/api/v1/app/session');
    CatalogAssert::ok($capped);
    expect($capped->json('data.commercial_limits.max_discount_percent'))->toBe(5)
        ->and($capped->json('data.commercial_limits.max_cash_hold'))->toBe(500000);
});
