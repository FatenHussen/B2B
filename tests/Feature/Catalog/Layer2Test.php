<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\RetailerProductFavorite;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepProfileZone;
use Modules\Identity\Domain\Models\RepSourcedShop;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Identity\Domain\Models\RetailerProfileCategory;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function layer2ChannelManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

/**
 * @return array<string, mixed>
 */
function layer2Refs(): array
{
    $gov = Governorate::factory()->create();
    $zone12 = Zone::factory()->create(['governorate_id' => $gov->id, 'name' => 'Zone 12']);
    $zone13 = Zone::factory()->create(['governorate_id' => $gov->id, 'name' => 'Zone 13']);
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
    // `iso`, not `code`: BE-R08 retired the duplicate column. `is_base` is no longer
    // fillable either — it is never writable through the API and the seeded SYP row
    // already carries it — so the fallback only has to create the row, not own the ledger.
    $currency = Currency::query()->where('iso', 'SYP')->first()
        ?? Currency::query()->create([
            'iso' => 'SYP',
            'name' => 'SYP',
            'decimals' => 0,
        ]);

    return compact('gov', 'zone12', 'zone13', 'activity', 'root', 'unit', 'currency');
}

function layer2Cover(SupplyChannel $channel, Zone $zone): void
{
    // Through ChannelZone, the channel's own write path — ChannelZoneLookup is read
    // only now. Tenant::as supplies the channel the scope stamps onto the row.
    Tenant::as($channel->id, fn () => ChannelZone::query()->create(['zone_id' => $zone->id]));
}

function layer2Retailer(array $refs, Zone $zone): AppUser
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

function layer2Rep(SupplyChannel $channel, array $refs, Zone $zone): AppUser
{
    $user = AppUser::factory()->rep()->create(['status' => UserStatus::Active]);
    $profile = RepProfile::query()->create([
        'app_user_id' => $user->id,
        'channel_id' => $channel->id,
        'activity_type_id' => $refs['activity']->id,
        'status' => ProfileStatus::Active,
    ]);
    RepProfileZone::query()->create([
        'rep_profile_id' => $profile->id,
        'zone_id' => $zone->id,
    ]);

    return $user->fresh(['repProfile.zones']) ?? $user;
}

function layer2CategoryId(int $rootId): int
{
    return test()->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $rootId,
        'activity_type_ids' => [],
    ])->json('data.id');
}

/**
 * @param  array<string, mixed>  $overrides
 */
function layer2ProductPayload(array $refs, int $categoryId, Zone $zone, array $overrides = []): array
{
    return array_replace_recursive([
        'name_ar' => 'زيت دوار الشمس',
        'name_en' => 'Sunflower oil',
        'sku' => 'OIL-SUN-1L',
        'category_id' => $categoryId,
        'status' => ProductStatus::Active->value,
        'sale_unit_id' => $refs['unit']->id,
        'min_order_qty' => 1,
        'pricing' => [
            'type' => 'tiered',
            'base_price' => 12000,
            'currency_id' => $refs['currency']->id,
            'tiers' => [
                ['from' => 1, 'to' => 4, 'price' => 12000],
                ['from' => 5, 'to' => 9, 'price' => 11500],
                ['from' => 10, 'to' => null, 'price' => 11000],
            ],
        ],
        'availability' => [
            'zone_ids' => [$zone->id],
            'activity_type_ids' => [$refs['activity']->id],
            'lead_time_days' => 2,
        ],
    ], $overrides);
}

it('rejects a 6th category level with 422', function () {
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');

    $parent = $refs['root']->id;
    for ($level = 2; $level <= 5; $level++) {
        $parent = $this->postJson('/api/v1/channel/categories', [
            'name' => "L{$level}",
            'parent_id' => $parent,
        ])->json('data.id');
        expect($parent)->toBeInt();
    }

    CatalogAssert::error(
        $this->postJson('/api/v1/channel/categories', [
            'name' => 'L6',
            'parent_id' => $parent,
        ]),
        422,
        'validation_failed',
    );
});

it('rejects a duplicate sku inside a channel and allows it on another channel', function () {
    $refs = layer2Refs();
    $channelA = SupplyChannel::factory()->create();
    $channelB = SupplyChannel::factory()->create();

    Sanctum::actingAs(layer2ChannelManager($channelA), ['*'], 'channel');
    $cat = layer2CategoryId($refs['root']->id);
    CatalogAssert::ok($this->postJson(
        '/api/v1/channel/products',
        layer2ProductPayload($refs, $cat, $refs['zone12']),
    ), ['id', 'sku'], 201);

    CatalogAssert::error(
        $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12'], [
            'name_ar' => 'نسخة',
        ])),
        422,
        'validation_failed',
    );

    Sanctum::actingAs(layer2ChannelManager($channelB), ['*'], 'channel');
    $catB = layer2CategoryId($refs['root']->id);
    CatalogAssert::ok($this->postJson(
        '/api/v1/channel/products',
        layer2ProductPayload($refs, $catB, $refs['zone12']),
    ), ['id', 'sku'], 201);
})->group('tenancy');

it('hides a zone-13 product from a zone-12 retailer and never leaks supply_channel', function () {
    // Rewritten in BE-C12. Until then both products here had no brand, and that is the
    // only reason this passed: `->with('brand')` on the retailer routes ran no query for
    // a null brand_id, so the strict Brand scope — which throws with no tenant on /app/*
    // — was never reached. With a brand the routes answered 500 while this stayed green.
    // The brand is on the products now, so the test claims what it looks like it claims.
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create();
    layer2Cover($channel, $refs['zone12']);
    layer2Cover($channel, $refs['zone13']);

    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');
    $cat = layer2CategoryId($refs['root']->id);
    $brandId = $this->postJson('/api/v1/channel/brands', ['name_ar' => 'ماركة', 'name_en' => 'Brand'])->json('data.id');
    $visibleId = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12'], [
        'sku' => 'VIS-12', 'brand_id' => $brandId,
    ]))->json('data.id');
    $hiddenId = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone13'], [
        'sku' => 'HID-13', 'brand_id' => $brandId,
        'name_ar' => 'مخفي',
    ]))->json('data.id');

    $retailer = layer2Retailer($refs, $refs['zone12']);
    Sanctum::actingAs($retailer, ['*'], 'app');

    $list = $this->getJson('/api/v1/app/retailer/products')->assertOk();
    $ids = collect($list->json('data'))->pluck('id')->all();
    expect($ids)->toContain($visibleId)->not->toContain($hiddenId);

    $card = $this->getJson("/api/v1/app/retailer/products/{$visibleId}")->assertOk();
    expect($card->json('data'))->not->toHaveKey('supply_channel')
        ->and($card->json('data.price.value'))->toBeInt();

    $this->getJson("/api/v1/app/retailer/products/{$hiddenId}")->assertNotFound();
})->group('browse');

it('shows channel on rep products and writes integer prices via the pricing writer', function () {
    // Rewritten in BE-C12: the product now carries a brand. Without one this test was
    // green while GET /app/rep/products answered 500 for every real catalog — see the
    // note on the retailer test above.
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    layer2Cover($channel, $refs['zone12']);

    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');
    $cat = layer2CategoryId($refs['root']->id);
    $brandId = $this->postJson('/api/v1/channel/brands', ['name_ar' => 'ماركة', 'name_en' => 'Brand'])->json('data.id');
    $productId = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12'], ['brand_id' => $brandId]))
        ->json('data.id');

    $rep = layer2Rep($channel, $refs, $refs['zone12']);
    Sanctum::actingAs($rep, ['*'], 'app');

    $list = $this->getJson('/api/v1/app/rep/products')->assertOk();
    $row = collect($list->json('data'))->firstWhere('id', $productId);
    expect($row['channel']['id'])->toBe($channel->id)
        ->and($row['channel']['name'])->toBe('شركة النور')
        ->and($row['price']['value'])->toBeInt();
});

it('keeps retailer favorites and shortages private to that retailer', function () {
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create();
    layer2Cover($channel, $refs['zone12']);

    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');
    $cat = layer2CategoryId($refs['root']->id);
    $productId = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12']))
        ->json('data.id');

    $a = layer2Retailer($refs, $refs['zone12']);
    $b = layer2Retailer($refs, $refs['zone12']);

    Sanctum::actingAs($a, ['*'], 'app');
    $this->postJson("/api/v1/app/retailer/products/{$productId}/favorite")->assertOk()
        ->assertJsonPath('data.is_favorite', true);
    $shortageId = $this->postJson('/api/v1/app/retailer/shortages', [
        'product_id' => $productId,
        'note' => 'ناقص',
    ])->json('data.id');

    Sanctum::actingAs($b, ['*'], 'app');
    $this->getJson('/api/v1/app/retailer/shortages')->assertOk()->assertJsonCount(0, 'data');
    expect(RetailerProductFavorite::query()->where('retailer_id', $b->retailerProfile->id)->count())->toBe(0);

    $detail = $this->getJson("/api/v1/app/retailer/products/{$productId}")->assertOk();
    expect($detail->json('data.is_favorite'))->toBeFalse();
});

it('does not create two shops for a repeated client_op_id on the same rep', function () {
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create();
    layer2Cover($channel, $refs['zone12']);
    $rep = layer2Rep($channel, $refs, $refs['zone12']);
    Sanctum::actingAs($rep, ['*'], 'app');

    $payload = [
        'shop_name' => 'محل المندوب',
        'owner_name' => 'أبو علي',
        'phone' => '+963944000001',
        'zone_id' => $refs['zone12']->id,
        'activity_type_id' => $refs['activity']->id,
        'client_op_id' => 'op_shop_local_1',
    ];

    $first = $this->postJson('/api/v1/app/rep/customers', $payload);
    CatalogAssert::ok($first, ['id', 'status'], 201);

    $second = $this->postJson('/api/v1/app/rep/customers', $payload);
    CatalogAssert::ok($second, ['id', 'status'], 201);
    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(RepSourcedShop::query()->count())->toBe(1);
});

it('quotes qty 6 on 1-4 / 5-9 / 10+ tiers at 11500 with qty_tier', function () {
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create();
    layer2Cover($channel, $refs['zone12']);

    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');
    $cat = layer2CategoryId($refs['root']->id);
    $productId = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12']))
        ->json('data.id');

    $retailer = layer2Retailer($refs, $refs['zone12']);
    Sanctum::actingAs($retailer, ['*'], 'app');

    $quote = $this->postJson('/api/v1/app/pricing/quote', [
        'lines' => [['product_id' => $productId, 'qty' => 6]],
        'zone_id' => $refs['zone12']->id,
        'unit_price' => 1,
    ])->assertOk();

    expect($quote->json('data.lines.0.unit_price'))->toBe(11500)
        ->and($quote->json('data.lines.0.applied_rule.type'))->toBe('qty_tier')
        ->and($quote->json('data.lines.0.discount'))->toBe(0)
        ->and($quote->json('data.lines.0.line_total'))->toBe(69000)
        ->and($quote->json('data.currency'))->toBe('SYP');
});

it('applies a zone percent list after the qty tier using integers only', function () {
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create();
    layer2Cover($channel, $refs['zone12']);
    layer2Cover($channel, $refs['zone13']);

    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');
    $cat = layer2CategoryId($refs['root']->id);
    $productId = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12']))
        ->json('data.id');

    $this->postJson('/api/v1/channel/price-lists', [
        'name' => 'Zone 12 -5%',
        'type' => 'zone',
        'zone_ids' => [$refs['zone12']->id],
        'adjustment' => ['mode' => 'percent', 'value' => -5],
        'reason' => 'promo',
    ])->assertCreated();

    $retailer12 = layer2Retailer($refs, $refs['zone12']);
    Sanctum::actingAs($retailer12, ['*'], 'app');
    $quote12 = $this->postJson('/api/v1/app/pricing/quote', [
        'lines' => [['product_id' => $productId, 'qty' => 6]],
        'zone_id' => $refs['zone12']->id,
    ])->assertOk();
    expect($quote12->json('data.lines.0.unit_price'))->toBe(10925);

    $retailer13 = layer2Retailer($refs, $refs['zone13']);
    Sanctum::actingAs($retailer13, ['*'], 'app');
    CatalogAssert::error(
        $this->postJson('/api/v1/app/pricing/quote', [
            'lines' => [['product_id' => $productId, 'qty' => 6]],
            'zone_id' => $refs['zone13']->id,
        ]),
        422,
        'product_not_available',
    );
})->group('browse');

it('writes a change log row per product on bulk percent update', function () {
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');
    $cat = layer2CategoryId($refs['root']->id);
    $a = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12'], ['sku' => 'A-1']))->json('data.id');
    $b = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12'], ['sku' => 'B-1']))->json('data.id');

    $this->postJson('/api/v1/channel/pricing/bulk-update', [
        'product_ids' => [$a, $b],
        'mode' => 'percent',
        'value' => -5,
        'reason' => 'cut',
    ])->assertOk()->assertJsonPath('data.affected_count', 2);

    $log = $this->getJson('/api/v1/channel/pricing/change-log')->assertOk();
    expect($log->json('data'))->toHaveCount(2);
});

it('shows a zone-12 offer only to matching retailers with company null and honest zeros', function () {
    $refs = layer2Refs();
    $channel = SupplyChannel::factory()->create();
    layer2Cover($channel, $refs['zone12']);
    layer2Cover($channel, $refs['zone13']);

    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');
    $cat = layer2CategoryId($refs['root']->id);
    $productId = $this->postJson('/api/v1/channel/products', layer2ProductPayload($refs, $cat, $refs['zone12'], [
        'pricing' => [
            'type' => 'simple',
            'base_price' => 12000,
            'currency_id' => $refs['currency']->id,
            'tiers' => [],
        ],
    ]))->json('data.id');

    $offerId = $this->postJson('/api/v1/channel/offers', [
        'name' => '10+1',
        'type' => 'buy_x_get_y',
        'components' => [['product_id' => $productId, 'qty' => 10]],
        'rules' => ['buy_qty' => 10, 'get_qty' => 1],
        'rewards' => ['product_id' => $productId, 'qty' => 1],
        'targeting' => [
            'scope' => 'zones',
            'activity_type_ids' => [$refs['activity']->id],
            'zone_ids' => [$refs['zone12']->id],
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

    $this->getJson("/api/v1/channel/offers/{$offerId}/performance")
        ->assertOk()
        ->assertJsonPath('data.applied_count', 0)
        ->assertJsonPath('data.linked_sales', 0);

    $r12 = layer2Retailer($refs, $refs['zone12']);
    Sanctum::actingAs($r12, ['*'], 'app');
    $list = $this->getJson('/api/v1/app/offers')->assertOk();
    expect(collect($list->json('data'))->pluck('id'))->toContain($offerId)
        ->and($list->json('data.0.company'))->toBeNull()
        ->and($list->json('data.0.price_after'))->toBe(108000)
        ->and($list->json('data.0.sold_count'))->toBe(0);

    $r13 = layer2Retailer($refs, $refs['zone13']);
    Sanctum::actingAs($r13, ['*'], 'app');
    $this->getJson('/api/v1/app/offers')->assertOk()->assertJsonMissing(['id' => $offerId]);
    $this->getJson("/api/v1/app/offers/{$offerId}")->assertNotFound();

    Sanctum::actingAs(layer2ChannelManager($channel), ['*'], 'channel');
    $this->patchJson("/api/v1/channel/offers/{$offerId}/stop", ['reason' => 'ended'])->assertOk();

    Sanctum::actingAs($r12, ['*'], 'app');
    expect(collect($this->getJson('/api/v1/app/offers')->json('data'))->pluck('id'))->not->toContain($offerId);
});
