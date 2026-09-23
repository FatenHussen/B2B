<?php

declare(strict_types=1);

/**
 * AP-04 / AP-05 / AP-06 — home blocks, loyalty wallet, public app-config.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Content\Domain\Models\HomeBlock;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Loyalty\Domain\Models\LoyaltyAccount;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('returns public app-config defaults and 426s a stale version', function () {
    $ok = $this->getJson('/api/v1/public/app-config?app=retailer&platform=android&version=1.0.0');
    CatalogAssert::ok($ok, ['min_supported_version', 'force_update', 'feature_flags', 'maintenance']);
    expect($ok->json('data.force_update'))->toBeFalse()
        ->and($ok->json('data.feature_flags.loyalty'))->toBeTrue();

    CatalogAssert::error(
        $this->getJson('/api/v1/public/app-config?app=retailer&platform=android&version=0.9.0'),
        426,
        'upgrade_required',
    );
});

it('returns home blocks for the caller audience', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $rep = AppSurface::rep($channel, $refs);

    Tenant::as($channel->id, function () use ($channel): void {
        HomeBlock::query()->create([
            'supply_channel_id' => $channel->id,
            'type' => 'banner',
            'title' => 'عرض',
            'payload' => ['image' => 'media_1', 'link' => ['type' => 'offer', 'target' => 44]],
            'order' => 1,
            'targeting' => [],
        ]);
    });

    Sanctum::actingAs($rep, ['*'], 'app');
    $blocks = $this->getJson('/api/v1/app/content/home-blocks');
    CatalogAssert::ok($blocks, ['banners', 'sliders']);
    expect($blocks->json('data.banners.0.id'))->toBeInt();
});

it('shows an empty loyalty wallet and redeems a reward when points cover the cost', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $retailer = AppSurface::retailer($refs);
    $retailerId = AppSurface::retailerId($retailer);

    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');
    $rewardId = (int) $this->postJson('/api/v1/channel/loyalty/rewards', [
        'name' => 'كرتون زيت',
        'points_cost' => 100,
        'stock' => 5,
        'expires_at' => now()->addYear()->toDateString(),
    ])->json('data.id');

    Tenant::as($channel->id, function () use ($channel, $retailerId): void {
        LoyaltyAccount::query()->create([
            'supply_channel_id' => $channel->id,
            'owner_kind' => 'retailer',
            'owner_id' => $retailerId,
            'balance' => 150,
            'tier' => 'bronze',
        ]);
    });

    app('auth')->forgetGuards();
    Sanctum::actingAs($retailer, ['*'], 'app');

    $wallet = $this->getJson('/api/v1/app/loyalty');
    CatalogAssert::ok($wallet, ['points', 'tier', 'history', 'rewards']);
    expect($wallet->json('data.points'))->toBe(150);

    $redeem = $this->postJson('/api/v1/app/loyalty/redeem', ['reward_id' => $rewardId]);
    CatalogAssert::ok($redeem);
    expect($redeem->json('data.redemption_no'))->toStartWith('LY-');
});
