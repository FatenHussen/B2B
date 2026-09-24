<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function loyManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('updates loyalty rules and lists rewards', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(loyManager($channel), ['*'], 'channel');

    $put = $this->putJson('/api/v1/channel/loyalty/rules', [
        'retailer_rules' => [['event' => 'invoice_paid', 'points_per_1000' => 1]],
        'rep_rules' => [['event' => 'delivery_completed', 'points' => 5]],
        'tiers' => [
            ['name' => 'bronze', 'threshold' => 0, 'benefits' => []],
            ['name' => 'silver', 'threshold' => 1000, 'benefits' => ['priority_support']],
        ],
    ]);
    CatalogAssert::ok($put);

    $rules = $this->getJson('/api/v1/channel/loyalty/rules');
    CatalogAssert::ok($rules);
    expect($rules->json('data.tiers.1.name'))->toBe('silver');

    $reward = $this->postJson('/api/v1/channel/loyalty/rewards', [
        'name' => 'كرتون زيت',
        'points_cost' => 2000,
        'stock' => 40,
        'expires_at' => '2026-12-31',
    ]);
    CatalogAssert::ok($reward);

    $list = $this->getJson('/api/v1/channel/loyalty/rewards');
    CatalogAssert::ok($list);
    expect($list->json('data.0.points_cost'))->toBe(2000);
});

it('updates and stops a loyalty reward', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(loyManager($channel), ['*'], 'channel');

    $id = $this->postJson('/api/v1/channel/loyalty/rewards', [
        'name' => 'كرتون زيت',
        'points_cost' => 2000,
        'stock' => 40,
        'expires_at' => '2026-12-31',
    ])->json('data.id');

    $put = $this->putJson("/api/v1/channel/loyalty/rewards/{$id}", [
        'name' => 'كرتون زيت',
        'points_cost' => 1800,
        'stock' => 35,
        'expires_at' => '2026-12-31',
    ]);
    CatalogAssert::ok($put);
    expect($put->json('data.id'))->toBe($id);

    $stop = $this->patchJson("/api/v1/channel/loyalty/rewards/{$id}/stop", ['reason' => 'نفاد المخزون']);
    CatalogAssert::ok($stop);
    expect($stop->json('data.status'))->toBe('stopped');

    CatalogAssert::error(
        $this->putJson("/api/v1/channel/loyalty/rewards/{$id}", [
            'name' => 'كرتون زيت',
            'points_cost' => 1800,
            'stock' => 35,
        ]),
        409,
        'illegal_transition',
    );
});
