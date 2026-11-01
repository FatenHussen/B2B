<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Catalog\Domain\Enums\BrandStatus;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Brand;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function summaryManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

/**
 * @return array{activity: ActivityType, root: RootCategory, unit: SaleUnit, currency: Currency}
 */
function summaryRefs(): array
{
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

    return compact('activity', 'root', 'unit', 'currency');
}

/**
 * @param  array{activity: ActivityType, root: RootCategory, unit: SaleUnit, currency: Currency}  $refs
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function summaryProduct(array $refs, int $categoryId, array $overrides = []): array
{
    return array_replace_recursive([
        'name_ar' => 'زيت دوار الشمس',
        'sku' => 'OIL-SUN-1L',
        'category_id' => $categoryId,
        'status' => ProductStatus::Active->value,
        'sale_unit_id' => $refs['unit']->id,
        'pricing' => [
            'type' => 'simple',
            'base_price' => 12000,
            'currency_id' => $refs['currency']->id,
            'tiers' => [],
        ],
        'availability' => [
            'zone_ids' => [],
            'activity_type_ids' => [$refs['activity']->id],
            'retailer_group_ids' => [],
            'lead_time_days' => 1,
        ],
    ], $overrides);
}

it('summarises this channel catalog and ignores another channel', function () {
    $refs = summaryRefs();
    $channel = SupplyChannel::factory()->create();
    $other = SupplyChannel::factory()->create();
    Sanctum::actingAs(summaryManager($channel), ['*'], 'channel');

    $categoryId = (int) $this->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $refs['root']->id,
    ])->assertCreated()->json('data.id');

    $first = (int) $this->postJson(
        '/api/v1/channel/products',
        summaryProduct($refs, $categoryId, ['sku' => 'SUM-1', 'barcode' => null]),
    )->assertCreated()->json('data.id');

    $second = (int) $this->postJson(
        '/api/v1/channel/products',
        summaryProduct($refs, $categoryId, ['sku' => 'SUM-2', 'name_ar' => 'أرز', 'barcode' => null]),
    )->assertCreated()->json('data.id');

    $this->postJson('/api/v1/channel/products/bulk', [
        'product_ids' => [$second],
        'action' => 'disable',
    ])->assertOk();

    $brand = Tenant::as($channel->id, fn () => Brand::query()->create([
        'name_ar' => 'الخير',
        'status' => BrandStatus::Active,
        'order' => 1,
    ]));
    Tenant::as($channel->id, fn () => Product::query()->whereIn('id', [$first, $second])->update([
        'brand_id' => $brand->id,
    ]));

    Sanctum::actingAs(summaryManager($other), ['*'], 'channel');
    $otherCategory = (int) $this->postJson('/api/v1/channel/categories', [
        'name' => 'ألبان',
        'parent_id' => $refs['root']->id,
    ])->assertCreated()->json('data.id');
    $this->postJson(
        '/api/v1/channel/products',
        summaryProduct($refs, $otherCategory, ['sku' => 'OTHER-1', 'barcode' => null]),
    )->assertCreated();

    Sanctum::actingAs(summaryManager($channel), ['*'], 'channel');
    $data = $this->getJson('/api/v1/channel/catalog/summary')->assertOk()->json('data');

    expect($data['counts']['products'])->toBe(2)
        ->and($data['counts']['active'])->toBe(1)
        ->and($data['counts']['stopped'])->toBe(1)
        ->and($data['counts']['active_share_pct'])->toBe(50)
        ->and($data['counts']['categories'])->toBe(1)
        ->and($data['counts']['brands'])->toBe(1)
        ->and($data['counts']['products_change_pct'])->toBeNull()
        ->and($data['by_category'])->toBe([['bucket' => 'root', 'id' => $refs['root']->id, 'name' => 'غذائية', 'count' => 2]])
        ->and($data['by_brand'])->toBe([['id' => $brand->id, 'name' => 'الخير', 'count' => 2]])
        ->and($data['by_status'])->toBe([
            ['status' => 'active', 'count' => 1],
            ['status' => 'disabled', 'count' => 1],
        ]);

    $newest = $data['recent_products'][0];
    expect($newest['name_ar'])->toBe('أرز')
        ->and($newest['brand'])->toBe('الخير')
        ->and($newest['category'])->toBe('زيوت')
        ->and($newest['price'])->toBe(12000)
        ->and($newest['status'])->toBe('disabled');

    $changes = collect($data['recent_changes']);
    $created = $changes->first(fn (array $row): bool => $row['action'] === 'catalog.product.create'
        && $row['subject_type'] === 'product'
        && $row['subject_id'] === $first);

    expect($changes->pluck('action'))->toContain('catalog.product.create')
        ->and($changes->firstWhere('action', 'catalog.product.bulk')['bulk_action'])->toBe('disable')
        ->and($created['actor_name'])->not->toBeNull()
        ->and($created['subject_name'])->toBe('زيت دوار الشمس');
});

it('refuses the catalog summary without sc.catalog.view', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(ChannelUser::factory()->forChannel($channel)->create(), ['*'], 'channel');

    $this->getJson('/api/v1/channel/catalog/summary')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'sc.catalog.view');
});
