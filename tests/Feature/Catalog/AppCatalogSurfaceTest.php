<?php

declare(strict_types=1);

/**
 * BE-C12 — the /app/* catalog routes with a product that has a brand and a category.
 *
 * Home, the product list, the product card, the brand card and the rep product list all
 * threw 500 channel_scope_required whenever a listed product had a brand: the root query
 * lifts the channel scope and filters by the retailer's channels, but `->with('brand')`
 * builds a fresh Brand query and Brand's own strict scope ran on it, with no tenant on
 * /app/*. Layer2Test never saw it because its products had no brand.
 *
 * Isolation on this surface is the shopping context — the active channels covering the
 * retailer's zone, or the rep's own channel — and it lives on the root query. The
 * relation cannot widen it: a product's brand is on the product's channel by
 * construction. The "other owner" here is a retailer in an uncovered zone and a rep on
 * another channel.
 */

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Catalog\Domain\Models\ProductSpec;
use Modules\Catalog\Domain\Models\ProductUnitFactor;
use Modules\Catalog\Domain\Models\ProductVariant;
use Modules\Catalog\Domain\Models\ProductVariantAxis;
use Modules\Catalog\Domain\Models\ProductVariantAxisValue;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\SaleUnit;
use Tests\Support\AppSurface;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

// ─── retailer ───────────────────────────────────────────────────────────────────────

it('shows home with a branded product in a covered zone', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::retailer($refs), ['*'], 'app');

    $this->getJson('/api/v1/app/retailer/home')->assertOk();
});

it('lists branded products and shows one', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId, $brandId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::retailer($refs), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/retailer/products')->assertOk();
    expect(collect($list->json('data'))->pluck('id')->all())->toContain($productId);

    $card = $this->getJson("/api/v1/app/retailer/products/{$productId}")->assertOk();
    expect($card->json('data'))->not->toHaveKey('supply_channel')
        ->and($card->json('data.brand.id') ?? $card->json('data.brand_id'))->toBe($brandId);
});

it('shows a brand card with the categories its products fall in', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [, $brandId, $categoryId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::retailer($refs), ['*'], 'app');

    $card = $this->getJson("/api/v1/app/retailer/brands/{$brandId}")->assertOk();

    expect(collect($card->json('data.categories'))->pluck('id')->all())->toContain($categoryId);
});

// ─── retailer: the other owner ──────────────────────────────────────────────────────

it('hides a branded product from a retailer in a zone the channel does not cover', function () {
    // The isolation is the shopping context on the root query, and lifting the scope on
    // the brand relation must not loosen it: out of coverage, the product is absent from
    // the list and 404 on the card, brand or no brand.
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId, $brandId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::retailer($refs, $refs['otherZone']), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/retailer/products')->assertOk();
    expect(collect($list->json('data'))->pluck('id')->all())->not->toContain($productId);

    $this->getJson("/api/v1/app/retailer/products/{$productId}")->assertNotFound();
    $this->getJson("/api/v1/app/retailer/brands/{$brandId}")->assertNotFound();
    $this->getJson('/api/v1/app/retailer/home')->assertOk();
});

// ─── rep ────────────────────────────────────────────────────────────────────────────

it('lists branded products to a rep on the channel, with the channel named', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId, $brandId, $categoryId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::rep($channel, $refs), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/rep/products')->assertOk();
    $row = collect($list->json('data'))->firstWhere('id', $productId);

    expect($row)->not->toBeNull()
        ->and($row['channel']['id'])->toBe($channel->id)
        ->and($row)->toHaveKeys(['image', 'brand', 'variants', 'sale_unit', 'created_at', 'has_offer'])
        ->and($row['sale_unit'])->toBe('قطعة')
        ->and($row['has_offer'])->toBeFalse()
        ->and($row['created_at'])->toBeString()
        ->and($row['variants'])->toBeArray();

    $byRoot = $this->getJson('/api/v1/app/rep/products?filter[root_category_id]='.$refs['root']->id)->assertOk();
    expect(collect($byRoot->json('data'))->firstWhere('id', $productId))->not->toBeNull();

    $byCategory = $this->getJson('/api/v1/app/rep/products?filter[category_id]='.$categoryId)->assertOk();
    expect(collect($byCategory->json('data'))->firstWhere('id', $productId))->not->toBeNull();

    $subs = $this->getJson('/api/v1/app/rep/categories?root_category_id='.$refs['root']->id)->assertOk();
    expect($subs->json('data.0.id'))->toBe($categoryId)
        ->and($subs->json('data.0.name'))->toBe('زيوت');

    $brands = $this->getJson('/api/v1/app/rep/brands')->assertOk();
    expect(collect($brands->json('data'))->firstWhere('id', $brandId)['name'])->toBeString();

    DB::table('products')->where('id', $productId)->update(['tracked' => true]);
    $available = $this->getJson('/api/v1/app/rep/products?filter[available_only]=1')->assertOk();
    expect(collect($available->json('data'))->firstWhere('id', $productId))->toBeNull();
});

it('shows a product on the rep channel and 404s one on another channel', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $otherChannel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    DB::table('product_variants')->insert([
        'product_id' => $productId,
        'sku' => 'OIL-SUN-1L-PCS',
        'combination' => json_encode(['حبة']),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Sanctum::actingAs(AppSurface::rep($channel, $refs), ['*'], 'app');

    $card = $this->getJson("/api/v1/app/rep/products/{$productId}")->assertOk();
    expect($card->json('data.id'))->toBe($productId)
        ->and($card->json('data.variants.0.label'))->toBe('حبة')
        ->and($card->json('data.variants.0.attributes'))->toBe([['attribute' => null, 'value' => 'حبة']])
        ->and($card->json('data.variants.0.price'))->toBe(12000)
        ->and($card->json('data.attributes_map'))->toBe([['attribute' => null, 'values' => ['حبة']]])
        ->and($card->json('data.category.name'))->toBe('زيوت')
        ->and($card->json('data.sale_unit.name'))->toBe('قطعة')
        ->and($card->json('data.images'))->toBeArray()
        ->and($card->json('data'))->toHaveKey('long_description');

    app('auth')->forgetGuards();
    Sanctum::actingAs(AppSurface::rep($otherChannel, $refs), ['*'], 'app');
    $this->getJson("/api/v1/app/rep/products/{$productId}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('offers only axis values that an active variant carries, priced per variant', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs, 'OIL-AXES');
    $carton = SaleUnit::query()->create([
        'name' => 'كرتونة',
        'abbr' => 'ctn',
        'default_factor' => 1,
        'status' => RefStatus::Active,
    ]);
    ProductUnitFactor::query()->create([
        'product_id' => $productId,
        'from_unit_id' => $carton->id,
        'to_unit_id' => $refs['unit']->id,
        'factor' => 12,
    ]);
    ProductSpec::query()->create([
        'product_id' => $productId,
        'key' => 'بلد المنشأ',
        'value' => 'سوريا',
        'order' => 0,
    ]);
    $volume = ProductVariantAxis::query()->create(['product_id' => $productId, 'name' => 'الحجم', 'order' => 0]);
    ProductVariantAxisValue::query()->create(['axis_id' => $volume->id, 'value' => '1 لتر', 'order' => 0]);
    ProductVariantAxisValue::query()->create(['axis_id' => $volume->id, 'value' => '3 لتر', 'order' => 1]);
    $pack = ProductVariantAxis::query()->create(['product_id' => $productId, 'name' => 'نوع العبوة', 'order' => 1]);
    ProductVariantAxisValue::query()->create(['axis_id' => $pack->id, 'value' => 'عبوة بلاستيك', 'order' => 0]);
    ProductVariantAxisValue::query()->create(['axis_id' => $pack->id, 'value' => 'عبوة زجاج', 'order' => 1]);
    ProductVariant::query()->create([
        'product_id' => $productId,
        'sku' => 'OIL-AXES-1L-PL',
        'combination' => ['الحجم' => '1 لتر', 'نوع العبوة' => 'عبوة بلاستيك'],
        'status' => 'active',
        'price_override' => 41200,
    ]);
    ProductVariant::query()->create([
        'product_id' => $productId,
        'sku' => 'OIL-AXES-1L-GL',
        'combination' => ['الحجم' => '1 لتر', 'نوع العبوة' => 'عبوة زجاج'],
        'status' => 'disabled',
        'price_override' => 50000,
    ]);
    Sanctum::actingAs(AppSurface::rep($channel, $refs), ['*'], 'app');

    $card = $this->getJson("/api/v1/app/rep/products/{$productId}")->assertOk();

    expect($card->json('data.attributes_map'))->toBe([
        ['attribute' => 'الحجم', 'values' => ['1 لتر']],
        ['attribute' => 'نوع العبوة', 'values' => ['عبوة بلاستيك']],
    ])
        ->and($card->json('data.variants'))->toHaveCount(1)
        ->and($card->json('data.variants.0.price'))->toBe(41200)
        ->and($card->json('data.variants.0.attributes'))->toBe([
            ['attribute' => 'الحجم', 'value' => '1 لتر'],
            ['attribute' => 'نوع العبوة', 'value' => 'عبوة بلاستيك'],
        ])
        ->and($card->json('data.specs.0'))->toBe(['key' => 'بلد المنشأ', 'value' => 'سوريا'])
        ->and($card->json('data.unit_factors.0.factor'))->toBe(12)
        ->and($card->json('data.unit_factors.0.from_unit.name'))->toBe('كرتونة')
        ->and($card->json('data.unit_factors.0.to_unit.name'))->toBe('قطعة');
});

it('hides a branded product from a rep on another channel', function () {
    $refs = AppSurface::refs();
    $channel = AppSurface::channel($refs);
    $otherChannel = AppSurface::channel($refs);
    [$productId] = AppSurface::productWithBrand($this, $channel, $refs);
    Sanctum::actingAs(AppSurface::rep($otherChannel, $refs), ['*'], 'app');

    $list = $this->getJson('/api/v1/app/rep/products')->assertOk();

    expect(collect($list->json('data'))->pluck('id')->all())->not->toContain($productId);
});
