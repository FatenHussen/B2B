<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Pricing\Domain\Enums\AdjustmentMode;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Enums\PriceListType;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\PriceListZone;
use Tests\Support\AppSurface;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('stops at the first matching price list — retailer wins without stacking zone', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $cat = $this->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $refs['root']->id,
        'activity_type_ids' => [],
    ])->json('data.id');

    $productId = $this->postJson('/api/v1/channel/products', [
        'name_ar' => 'زيت',
        'sku' => 'PRICE-STOP-1',
        'category_id' => $cat,
        'status' => 'active',
        'sale_unit_id' => $refs['unit']->id,
        'pricing' => [
            'type' => 'simple',
            'base_price' => 10000,
            'currency_id' => $refs['currency']->id,
            'tiers' => [],
        ],
        'availability' => [
            'zone_ids' => [$refs['zone']->id],
            'activity_type_ids' => [$refs['activity']->id],
        ],
    ])->assertCreated()->json('data.id');

    Tenant::as($channel->id, function () use ($channel, $refs): void {
        $zoneList = PriceList::query()->create([
            'supply_channel_id' => $channel->id,
            'name' => 'منطقة',
            'type' => PriceListType::Zone,
            'status' => PriceListStatus::Active,
            'adjustment_mode' => AdjustmentMode::Percent,
            'adjustment_value' => -20,
        ]);
        PriceListZone::query()->create([
            'price_list_id' => $zoneList->id,
            'zone_id' => $refs['zone']->id,
        ]);

        PriceList::query()->create([
            'supply_channel_id' => $channel->id,
            'name' => 'تاجر',
            'type' => PriceListType::Retailer,
            'status' => PriceListStatus::Active,
            'retailer_id' => 481,
            'adjustment_mode' => AdjustmentMode::Percent,
            'adjustment_value' => -5,
        ]);
    });

    $engine = app(PricingEngine::class);
    $quoted = $engine->quoteLine($productId, 1, $refs['zone']->id, 481, $channel->id, []);

    // Stop-at-first: retailer −5% on base = 9500, not zone then retailer stacking.
    expect($quoted['unit_price'])->toBe(9500)
        ->and($quoted['applied_rule']['type'])->toBe('retailer_list');
});
