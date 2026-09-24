<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Catalog\Domain\Enums\ProductStatus;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Promotion\Domain\Enums\OfferStatus;
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

/**
 * @return array<string, mixed>
 */
function offerEditorRefs(): array
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

function offerEditorManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('shows, updates and activates a channel offer', function () {
    $refs = offerEditorRefs();
    $channel = SupplyChannel::factory()->create();
    Tenant::as($channel->id, function () use ($channel, $refs): void {
        ChannelZone::query()->create([
            'supply_channel_id' => $channel->id,
            'zone_id' => $refs['zone']->id,
        ]);
    });

    Sanctum::actingAs(offerEditorManager($channel), ['*'], 'channel');

    $cat = $this->postJson('/api/v1/channel/categories', [
        'name' => 'زيوت',
        'parent_id' => $refs['root']->id,
        'activity_type_ids' => [],
    ])->json('data.id');

    $productId = $this->postJson('/api/v1/channel/products', [
        'name_ar' => 'زيت',
        'name_en' => 'Oil',
        'sku' => 'OIL-'.uniqid(),
        'category_id' => $cat,
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
            'zone_ids' => [$refs['zone']->id],
            'activity_type_ids' => [$refs['activity']->id],
            'lead_time_days' => 2,
        ],
    ])->assertCreated()->json('data.id');

    $offerId = $this->postJson('/api/v1/channel/offers', [
        'name' => 'اشترِ 10 واحصل على 1',
        'type' => 'buy_x_get_y',
        'description' => 'على زيت',
        'components' => [['product_id' => $productId, 'qty' => 10]],
        'rules' => ['buy_qty' => 10, 'get_qty' => 1],
        'rewards' => ['product_id' => $productId, 'qty' => 1],
        'targeting' => [
            'scope' => 'zones',
            'activity_type_ids' => [$refs['activity']->id],
            'zone_ids' => [$refs['zone']->id],
            'group_ids' => [],
            'retailer_ids' => [],
        ],
        'constraints' => [
            'starts_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addMonth()->toIso8601String(),
            'total_qty' => 500,
            'per_retailer_max' => 3,
            'per_order_max' => 1,
            'min_invoice_value' => 0,
            'min_items' => 1,
        ],
        'stackable' => false,
        'priority' => 10,
        'status' => 'active',
    ])->assertCreated()->json('data.id');

    $show = $this->getJson("/api/v1/channel/offers/{$offerId}");
    CatalogAssert::ok($show);
    expect($show->json('data.id'))->toBe($offerId)
        ->and($show->json('data.name'))->toBe('اشترِ 10 واحصل على 1')
        ->and($show->json('data.components.0.product_id'))->toBe($productId)
        ->and($show->json('data.targeting.zone_ids.0'))->toBe($refs['zone']->id);

    $put = $this->putJson("/api/v1/channel/offers/{$offerId}", [
        'name' => 'عرض محدّث',
        'type' => 'buy_x_get_y',
        'description' => 'محدّث',
        'components' => [['product_id' => $productId, 'qty' => 8]],
        'rules' => ['buy_qty' => 8, 'get_qty' => 1],
        'rewards' => ['product_id' => $productId, 'qty' => 1],
        'targeting' => [
            'scope' => 'zones',
            'activity_type_ids' => [$refs['activity']->id],
            'zone_ids' => [$refs['zone']->id],
        ],
        'constraints' => [
            'starts_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addMonth()->toIso8601String(),
            'total_qty' => 400,
        ],
        'stackable' => false,
        'priority' => 5,
    ]);
    CatalogAssert::ok($put);
    expect($put->json('data.id'))->toBe($offerId);
    expect($this->getJson("/api/v1/channel/offers/{$offerId}")->json('data.name'))->toBe('عرض محدّث');

    $this->patchJson("/api/v1/channel/offers/{$offerId}/stop", ['reason' => 'نفاد'])->assertOk();

    $activate = $this->patchJson("/api/v1/channel/offers/{$offerId}/activate", ['reason' => 'إعادة']);
    CatalogAssert::ok($activate);
    expect($activate->json('data.status'))->toBe(OfferStatus::Active->value);

    CatalogAssert::error($this->getJson('/api/v1/channel/offers/999999'), 404, 'not_found');
});

it('rejects update of a stopped offer with illegal_transition', function () {
    $refs = offerEditorRefs();
    $channel = SupplyChannel::factory()->create();
    Tenant::as($channel->id, function () use ($channel, $refs): void {
        ChannelZone::query()->create([
            'supply_channel_id' => $channel->id,
            'zone_id' => $refs['zone']->id,
        ]);
    });
    Sanctum::actingAs(offerEditorManager($channel), ['*'], 'channel');

    $offerId = $this->postJson('/api/v1/channel/offers', [
        'name' => 'مسودة',
        'type' => 'product_discount',
        'rules' => ['tiers' => [['from' => 1, 'to' => 10, 'percent' => 5]]],
        'status' => 'draft',
    ])->assertCreated()->json('data.id');

    $this->patchJson("/api/v1/channel/offers/{$offerId}/stop", ['reason' => 'stop'])->assertOk();

    CatalogAssert::error(
        $this->putJson("/api/v1/channel/offers/{$offerId}", [
            'name' => 'لا',
            'type' => 'product_discount',
            'rules' => ['tiers' => [['from' => 1, 'to' => 10, 'percent' => 5]]],
        ]),
        409,
        'illegal_transition',
    );
});
