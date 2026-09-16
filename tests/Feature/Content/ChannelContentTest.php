<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function cntManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('updates and reads the intro screen', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(cntManager($channel), ['*'], 'channel');

    $put = $this->putJson('/api/v1/channel/content/intro', [
        'enabled' => true,
        'text' => 'مرحباً بتجار دمشق',
        'media_type' => 'video',
        'media_id' => 'media_intro',
        'duration' => 8,
        'targeting' => ['activity_type_ids' => [3], 'zone_ids' => [12]],
    ]);
    CatalogAssert::ok($put);

    $get = $this->getJson('/api/v1/channel/content/intro');
    CatalogAssert::ok($get);
    expect($get->json('data.enabled'))->toBeTrue()
        ->and($get->json('data.duration'))->toBe(8);
});

it('creates a banner and returns stored stats of zero rather than invented CTR', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(cntManager($channel), ['*'], 'channel');

    $create = $this->postJson('/api/v1/channel/content/banners', [
        'media_type' => 'image',
        'media_id' => 'media_banner_2',
        'link' => ['type' => 'offer', 'target' => 44],
        'placements' => ['home_top'],
        'starts_at' => '2026-03-01T00:00:00+03:00',
        'ends_at' => '2026-03-31T23:59:59+03:00',
        'order' => 1,
        'weight' => 10,
    ]);
    CatalogAssert::ok($create);
    $id = $create->json('data.id');

    $stats = $this->getJson("/api/v1/channel/content/banners/{$id}/stats");
    CatalogAssert::ok($stats);
    expect($stats->json('data.impressions'))->toBe(0)
        ->and($stats->json('data.clicks'))->toBe(0)
        ->and($stats->json('data.ctr'))->toBe(0);
});

it('404s banner stats for another channel', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    Sanctum::actingAs(cntManager($foreign), ['*'], 'channel');
    $id = $this->postJson('/api/v1/channel/content/banners', [
        'media_type' => 'image',
        'media_id' => 'x',
        'placements' => ['home_top'],
    ])->json('data.id');

    Sanctum::actingAs(cntManager($own), ['*'], 'channel');
    CatalogAssert::error($this->getJson("/api/v1/channel/content/banners/{$id}/stats"), 404, 'not_found');
})->group('tenancy');

it('creates and lists sliders', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(cntManager($channel), ['*'], 'channel');

    $create = $this->postJson('/api/v1/channel/content/sliders', [
        'name' => 'الأكثر مبيعاً',
        'source' => 'algorithm',
        'algorithm' => 'best_selling',
        'placements' => ['home'],
        'items_count' => 12,
        'show_all_button' => true,
    ]);
    CatalogAssert::ok($create);

    $list = $this->getJson('/api/v1/channel/content/sliders');
    CatalogAssert::ok($list);
    expect($list->json('data.0.source'))->toBe('algorithm');
});
