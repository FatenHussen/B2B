<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Catalog\Domain\Models\Product;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\SaleUnit;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;
use Tests\Support\CatalogAssert;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    Storage::fake('public');
});

/**
 * @return array<string, mixed>
 */
function catalogGapRefs(): array
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

function catalogGapManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

function catalogGapCover(SupplyChannel $channel, Zone $zone): void
{
    Tenant::as($channel->id, function () use ($channel, $zone): void {
        ChannelZone::query()->create([
            'supply_channel_id' => $channel->id,
            'zone_id' => $zone->id,
        ]);
    });
}

it('updates a category and rejects disable when active products exist', function () {
    $refs = catalogGapRefs();
    $channel = SupplyChannel::factory()->create();
    catalogGapCover($channel, $refs['zone']);
    Sanctum::actingAs(catalogGapManager($channel), ['*'], 'channel');

    $catId = $this->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $refs['root']->id,
        'description' => 'أولية',
        'order' => 1,
    ])->assertCreated()->json('data.id');

    $this->putJson("/api/v1/channel/categories/{$catId}", [
        'name' => 'زيوت نباتية',
        'description' => 'محدثة',
        'icon' => 'oil',
        'order' => 3,
        'activity_type_ids' => [$refs['activity']->id],
    ])->assertOk()->assertJsonPath('data.id', $catId);

    $tree = $this->getJson('/api/v1/channel/categories/tree')->assertOk();
    $node = collect($tree->json('data'))
        ->flatMap(fn ($r) => $r['children'] ?? [])
        ->firstWhere('id', $catId);
    expect($node['description'])->toBe('محدثة')
        ->and($node['icon'])->toBe('oil');

    $productId = $this->postJson('/api/v1/channel/products', [
        'name_ar' => 'زيت',
        'sku' => 'OIL-1',
        'category_id' => $catId,
        'status' => ProductStatus::Active->value,
        'sale_unit_id' => $refs['unit']->id,
        'pricing' => [
            'type' => 'simple',
            'base_price' => 1000,
            'currency_id' => $refs['currency']->id,
            'tax_percent' => 5,
            'tiers' => [],
        ],
        'availability' => [
            'zone_ids' => [$refs['zone']->id],
            'activity_type_ids' => [$refs['activity']->id],
        ],
    ])->assertCreated()->json('data.id');

    CatalogAssert::error(
        $this->patchJson("/api/v1/channel/categories/{$catId}/status", [
            'status' => 'disabled',
            'reason' => 'دمج',
        ]),
        422,
        'ref_in_use',
    );

    Tenant::as($channel->id, function () use ($productId): void {
        Product::query()->whereKey($productId)->update(['status' => ProductStatus::Draft->value]);
    });

    $this->patchJson("/api/v1/channel/categories/{$catId}/status", [
        'status' => 'disabled',
        'reason' => 'دمج',
    ])->assertOk()->assertJsonPath('data.status', 'disabled');
});

it('creates a channel-owned root category without parent_id', function () {
    $refs = catalogGapRefs();
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(catalogGapManager($channel), ['*'], 'channel');

    $id = $this->postJson('/api/v1/channel/categories', [
        'name' => 'جذر قناة',
        'description' => 'بدون أب',
    ])->assertCreated()->json('data.id');

    $tree = $this->getJson('/api/v1/channel/categories/tree')->assertOk()->json('data');
    expect(collect($tree)->pluck('id')->all())->toContain($id);
});

it('uploads an image and returns a media_id', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(catalogGapManager($channel), ['*'], 'channel');

    $file = UploadedFile::fake()->image('logo.jpg', 640, 640);

    $response = $this->post('/api/v1/channel/media/upload', [
        'type' => 'image',
        'file' => $file,
    ], ['Accept' => 'application/json']);

    if ($response->status() !== 201) {
        dump($response->status(), $response->json());
    }

    $response->assertCreated();
    expect($response->json('data.media_id'))->not->toBeEmpty()
        ->and($response->json('data.type'))->toBe('image')
        ->and($response->json('data.url'))->not->toBeEmpty();
});

it('accepts dimensions tax and opening stock on product create', function () {
    $refs = catalogGapRefs();
    $channel = SupplyChannel::factory()->create();
    catalogGapCover($channel, $refs['zone']);
    Sanctum::actingAs(catalogGapManager($channel), ['*'], 'channel');

    $warehouse = Tenant::as($channel->id, fn () => Warehouse::query()->create([
        'channel_id' => $channel->id,
        'name' => 'رئيسي',
        'status' => WarehouseStatus::Active,
    ]));

    $catId = $this->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $refs['root']->id,
    ])->assertCreated()->json('data.id');

    $productId = $this->postJson('/api/v1/channel/products', [
        'name_ar' => 'زيت',
        'sku' => 'OIL-DIM',
        'category_id' => $catId,
        'status' => 'active',
        'sale_unit_id' => $refs['unit']->id,
        'length_mm' => 200,
        'width_mm' => 80,
        'height_mm' => 80,
        'long_description' => '<p>وصف غني</p>',
        'pricing' => [
            'type' => 'simple',
            'base_price' => 12000,
            'currency_id' => $refs['currency']->id,
            'tax_percent' => 8,
            'tiers' => [],
        ],
        'inventory' => [
            'tracked' => true,
            'opening_stock' => [['warehouse_id' => $warehouse->id, 'qty' => 25]],
        ],
        'availability' => [
            'zone_ids' => [$refs['zone']->id],
            'activity_type_ids' => [$refs['activity']->id],
        ],
    ])->assertCreated()->json('data.id');

    $show = $this->getJson("/api/v1/channel/products/{$productId}")->assertOk();
    expect($show->json('data.length_mm'))->toBe(200)
        ->and($show->json('data.width_mm'))->toBe(80)
        ->and($show->json('data.height_mm'))->toBe(80)
        ->and($show->json('data.long_description'))->toBe('<p>وصف غني</p>')
        ->and($show->json('data.pricing.tax_percent'))->toBe(8);

    $stock = Tenant::as($channel->id, fn () => app(\Modules\Core\Contracts\StockLedger::class)
        ->snapshot((int) $warehouse->id, (int) $productId, null));
    expect($stock['on_hand'])->toBe(25);
});
