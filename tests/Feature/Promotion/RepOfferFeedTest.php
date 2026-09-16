<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Tests\Support\AppSurface;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('shows the channel offer feed to a rep on that channel', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $offerId = (int) $this->postJson('/api/v1/channel/offers', [
        'name' => '10+1 مندوب',
        'type' => 'buy_x_get_y',
        'components' => [['product_id' => $productId, 'qty' => 10]],
        'rules' => ['buy_qty' => 10, 'get_qty' => 1],
        'rewards' => ['product_id' => $productId, 'qty' => 1],
        'targeting' => [
            'scope' => 'zones',
            'activity_type_ids' => [$refs['activity']->id],
            'zone_ids' => [$refs['zone']->id],
        ],
        'constraints' => [
            'starts_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addMonth()->toIso8601String(),
            'total_qty' => 500,
        ],
        'status' => 'active',
        'priority' => 10,
        'stackable' => false,
    ])->json('data.id');
    expect($offerId)->toBeGreaterThan(0);

    $rep = AppSurface::rep($channel, $refs);
    app('auth')->forgetGuards();
    Sanctum::actingAs($rep, ['*'], 'app');

    $list = $this->getJson('/api/v1/app/offers');
    CatalogAssert::ok($list);
    $ids = [];
    foreach ($list->json('data') as $row) {
        $ids[] = $row['id'];
    }
    expect($ids)->toContain($offerId);

    $show = $this->getJson("/api/v1/app/offers/{$offerId}");
    CatalogAssert::ok($show);
    expect($show->json('data.id'))->toBe($offerId)
        ->and($show->json('data.company'))->toBeNull();
});
