<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Core\Contracts\PricingEngine;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Identity\Domain\Models\RetailerProfileCategory;
use Modules\Pricing\Domain\Enums\AdjustmentMode;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Enums\PriceListType;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Promotion\Domain\Models\OfferGroup;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * @return array<string, mixed>
 */
function offerEngineRefs(): array
{
    $gov = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $gov->id]);
    $activity = ActivityType::query()->create([
        'name' => 'بقالة',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);
    $root = RootCategory::query()->create([
        'name' => 'غذائية',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);
    $unit = SaleUnit::query()->create([
        'name' => 'قطعة',
        'abbr' => 'pcs',
        'default_factor' => 1,
        'status' => RefStatus::Active,
    ]);
    $currency = Currency::query()->where('iso', 'SYP')->first()
        ?? Currency::query()->create(['iso' => 'SYP', 'name' => 'SYP', 'decimals' => 0]);

    return compact('gov', 'zone', 'activity', 'root', 'unit', 'currency');
}

function offerEngineManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

function offerEngineCover(SupplyChannel $channel, Zone $zone): void
{
    Tenant::as($channel->id, function () use ($channel, $zone): void {
        ChannelZone::query()->create([
            'supply_channel_id' => $channel->id,
            'zone_id' => $zone->id,
        ]);
    });
}

function offerEngineRetailer(array $refs, Zone $zone): AppUser
{
    $user = AppUser::factory()->retailer()->create(['status' => UserStatus::Active]);
    $profile = RetailerProfile::query()->create([
        'app_user_id' => $user->id,
        'shop_name' => 'محل '.$user->id,
        'activity_type_id' => $refs['activity']->id,
        'governorate_id' => $refs['gov']->id,
        'zone_id' => $zone->id,
        'status' => ProfileStatus::Active,
    ]);
    RetailerProfileCategory::query()->create([
        'retailer_profile_id' => $profile->id,
        'root_category_id' => $refs['root']->id,
    ]);

    return $user->fresh(['retailerProfile.categories']) ?? $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function offerEngineProductPayload(array $refs, int $categoryId, Zone $zone, array $overrides = []): array
{
    return array_replace_recursive([
        'name_ar' => 'منتج',
        'name_en' => 'Product',
        'sku' => 'SKU-'.uniqid(),
        'category_id' => $categoryId,
        'status' => ProductStatus::Active->value,
        'sale_unit_id' => $refs['unit']->id,
        'min_order_qty' => 1,
        'pricing' => [
            'type' => 'simple',
            'base_price' => 10000,
            'currency_id' => $refs['currency']->id,
            'tiers' => [],
        ],
        'availability' => [
            'zone_ids' => [$zone->id],
            'activity_type_ids' => [$refs['activity']->id],
            'lead_time_days' => 2,
        ],
    ], $overrides);
}

it('applies product_discount, buy_x_get_y, invoice_discount and gift on quote', function () {
    $refs = offerEngineRefs();
    $channel = SupplyChannel::factory()->create();
    offerEngineCover($channel, $refs['zone']);

    Sanctum::actingAs(offerEngineManager($channel), ['*'], 'channel');

    $cat = $this->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $refs['root']->id,
        'activity_type_ids' => [],
    ])->json('data.id');

    $oil = $this->postJson('/api/v1/channel/products', offerEngineProductPayload($refs, $cat, $refs['zone'], [
        'sku' => 'OIL',
        'pricing' => ['type' => 'simple', 'base_price' => 10000, 'currency_id' => $refs['currency']->id, 'tiers' => []],
    ]))->assertCreated()->json('data.id');

    $sugar = $this->postJson('/api/v1/channel/products', offerEngineProductPayload($refs, $cat, $refs['zone'], [
        'sku' => 'SUG',
        'pricing' => ['type' => 'simple', 'base_price' => 5000, 'currency_id' => $refs['currency']->id, 'tiers' => []],
    ]))->assertCreated()->json('data.id');

    $chips = $this->postJson('/api/v1/channel/products', offerEngineProductPayload($refs, $cat, $refs['zone'], [
        'sku' => 'CHIP',
        'pricing' => ['type' => 'simple', 'base_price' => 2000, 'currency_id' => $refs['currency']->id, 'tiers' => []],
    ]))->assertCreated()->json('data.id');

    $this->postJson('/api/v1/channel/offers', [
        'name' => 'خصم منتج',
        'type' => 'product_discount',
        'components' => [['product_id' => $oil, 'qty' => 1]],
        'rewards' => ['discount_percent' => 10],
        'targeting' => ['scope' => 'all'],
        'status' => 'active',
        'priority' => 1,
        'stackable' => true,
    ])->assertCreated();

    $this->postJson('/api/v1/channel/offers', [
        'name' => '10+1',
        'type' => 'buy_x_get_y',
        'components' => [['product_id' => $sugar, 'qty' => 10]],
        'rules' => ['buy_qty' => 10, 'get_qty' => 1],
        'rewards' => ['product_id' => $sugar, 'qty' => 1],
        'targeting' => ['scope' => 'all'],
        'status' => 'active',
        'priority' => 2,
        'stackable' => true,
    ])->assertCreated();

    $this->postJson('/api/v1/channel/offers', [
        'name' => 'فاتورة',
        'type' => 'invoice_discount',
        'components' => [],
        'rewards' => ['discount_amount' => 5000],
        'targeting' => ['scope' => 'all'],
        'constraints' => ['min_invoice_value' => 50000],
        'status' => 'active',
        'priority' => 3,
        'stackable' => true,
    ])->assertCreated();

    $this->postJson('/api/v1/channel/offers', [
        'name' => 'هدية',
        'type' => 'gift',
        'components' => [['product_id' => $oil, 'qty' => 1]],
        'rules' => ['buy_qty' => 1, 'get_qty' => 1],
        'rewards' => ['product_id' => $chips, 'qty' => 1],
        'targeting' => ['scope' => 'all'],
        'status' => 'active',
        'priority' => 4,
        'stackable' => true,
    ])->assertCreated();

    Sanctum::actingAs(offerEngineRetailer($refs, $refs['zone']), ['*'], 'app');

    $quote = $this->postJson('/api/v1/app/pricing/quote', [
        'lines' => [
            ['product_id' => $oil, 'qty' => 2],
            ['product_id' => $sugar, 'qty' => 10],
        ],
        'zone_id' => $refs['zone']->id,
    ])->assertOk();

    $lines = $quote->json('data.lines');
    expect($lines)->toHaveCount(3);

    $oilLine = collect($lines)->firstWhere('product_id', $oil);
    expect($oilLine['discount'])->toBeGreaterThan(0)
        ->and($oilLine['offer_id'])->not->toBeNull();

    $sugarLine = collect($lines)->firstWhere('product_id', $sugar);
    expect($sugarLine['discount'])->toBeGreaterThanOrEqual(5000);

    $gift = collect($lines)->first(
        fn ($l) => ($l['gift'] ?? false) === true || ($l['applied_rule']['type'] ?? null) === 'gift',
    );
    expect($gift)->not->toBeNull()
        ->and($gift['product_id'])->toBe($chips)
        ->and($gift['unit_price'])->toBe(0)
        ->and($gift['line_total'])->toBe(0);

    expect($quote->json('data.subtotal'))->toBe(array_sum(array_column($lines, 'line_total')));
});

it('applies a group price list when group_ids are passed to the engine', function () {
    $refs = offerEngineRefs();
    $channel = SupplyChannel::factory()->create();
    offerEngineCover($channel, $refs['zone']);

    Sanctum::actingAs(offerEngineManager($channel), ['*'], 'channel');
    $cat = $this->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $refs['root']->id,
        'activity_type_ids' => [],
    ])->json('data.id');

    $productId = $this->postJson('/api/v1/channel/products', offerEngineProductPayload($refs, $cat, $refs['zone'], [
        'pricing' => ['type' => 'simple', 'base_price' => 10000, 'currency_id' => $refs['currency']->id, 'tiers' => []],
    ]))->assertCreated()->json('data.id');

    Tenant::as($channel->id, function () use ($channel): void {
        PriceList::query()->create([
            'supply_channel_id' => $channel->id,
            'name' => 'جملة',
            'type' => PriceListType::Group,
            'status' => PriceListStatus::Active,
            'group_id' => 77,
            'adjustment_mode' => AdjustmentMode::Percent,
            'adjustment_value' => -10,
        ]);
    });

    $engine = app(PricingEngine::class);
    $plain = $engine->quoteLine($productId, 1, $refs['zone']->id, null, $channel->id, []);
    $grouped = $engine->quoteLine($productId, 1, $refs['zone']->id, null, $channel->id, [77]);

    expect($plain['unit_price'])->toBe(10000)
        ->and($grouped['unit_price'])->toBe(9000)
        ->and($grouped['applied_rule']['type'])->toBe('group_list');
});

it('persists offer group targeting on create', function () {
    $refs = offerEngineRefs();
    $channel = SupplyChannel::factory()->create();
    offerEngineCover($channel, $refs['zone']);
    Sanctum::actingAs(offerEngineManager($channel), ['*'], 'channel');

    $g1 = $this->postJson('/api/v1/channel/retailer-groups', ['name' => 'G1', 'retailer_ids' => []])
        ->assertCreated()
        ->json('data.id');
    $g2 = $this->postJson('/api/v1/channel/retailer-groups', ['name' => 'G2', 'retailer_ids' => []])
        ->assertCreated()
        ->json('data.id');

    $cat = $this->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $refs['root']->id,
        'activity_type_ids' => [],
    ])->json('data.id');

    $productId = $this->postJson('/api/v1/channel/products', offerEngineProductPayload($refs, $cat, $refs['zone']))
        ->assertCreated()
        ->json('data.id');

    $offerId = $this->postJson('/api/v1/channel/offers', [
        'name' => 'مجموعة',
        'type' => 'product_discount',
        'components' => [['product_id' => $productId, 'qty' => 1]],
        'rewards' => ['discount_percent' => 5],
        'targeting' => ['scope' => 'groups', 'group_ids' => [$g1, $g2]],
        'status' => 'active',
    ])->assertCreated()->json('data.id');

    expect(OfferGroup::query()->where('offer_id', $offerId)->count())->toBe(2);
});
