<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\OfferConsumption;
use Modules\Core\Contracts\RetailerDirectory;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Pricing\Domain\Enums\AdjustmentMode;
use Modules\Pricing\Domain\Enums\PriceListStatus;
use Modules\Pricing\Domain\Enums\PriceListType;
use Modules\Pricing\Domain\Models\PriceList;
use Modules\Pricing\Domain\Models\ProductBasePrice;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Enums\OfferType;
use Modules\Promotion\Domain\Enums\TargetingScope;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\OfferRedemption;
use Modules\Promotion\Domain\Models\OfferRetailerRedemption;
use Modules\Promotion\Domain\Models\OfferReward;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Currency;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function prodCompleteManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

it('CRUDs retailer groups and exposes membership via groupIds', function () {
    $gov = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $gov->id]);
    $channel = SupplyChannel::factory()->create();
    Tenant::as($channel->id, fn () => ChannelZone::query()->create([
        'supply_channel_id' => $channel->id,
        'zone_id' => $zone->id,
    ]));

    $activity = ActivityType::query()->create([
        'name' => 'بقالة',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);
    $retailer = AppUser::factory()->retailer()->create(['status' => UserStatus::Active]);
    $profile = RetailerProfile::query()->create([
        'app_user_id' => $retailer->id,
        'shop_name' => 'محل VIP',
        'activity_type_id' => $activity->id,
        'governorate_id' => $gov->id,
        'zone_id' => $zone->id,
        'status' => ProfileStatus::Active,
    ]);

    Sanctum::actingAs(prodCompleteManager($channel), ['*'], 'channel');

    $created = $this->postJson('/api/v1/channel/retailer-groups', [
        'name' => 'VIP',
        'retailer_ids' => [$profile->id],
    ]);
    $created->assertCreated();
    $groupId = (int) $created->json('data.id');

    CatalogAssert::ok($this->getJson('/api/v1/channel/retailer-groups'));
    expect(app(RetailerDirectory::class)->groupIds((int) $profile->id))->toBe([$groupId]);

    Tenant::as($channel->id, function () use ($channel, $groupId): void {
        PriceList::query()->create([
            'supply_channel_id' => $channel->id,
            'name' => 'مجموعة VIP',
            'type' => PriceListType::Group,
            'group_id' => $groupId,
            'adjustment_mode' => AdjustmentMode::Percent,
            'adjustment_value' => -10,
            'status' => PriceListStatus::Active,
        ]);
    });

    CatalogAssert::error(
        $this->deleteJson("/api/v1/channel/retailer-groups/{$groupId}"),
        422,
        'ref_in_use',
    );
});

it('creates scheduled offers when starts_at is in the future', function () {
    $channel = SupplyChannel::factory()->create();
    Sanctum::actingAs(prodCompleteManager($channel), ['*'], 'channel');

    $response = $this->postJson('/api/v1/channel/offers', [
        'name' => 'مجدول',
        'type' => 'invoice_discount',
        'rewards' => ['discount_percent' => 5],
        'targeting' => ['scope' => 'all'],
        'constraints' => ['starts_at' => now('Asia/Damascus')->addDays(3)->toIso8601String()],
        'status' => 'active',
    ]);
    $response->assertCreated();

    $offer = Tenant::as($channel->id, fn () => Offer::query()->find((int) $response->json('data.id')));
    expect($offer?->status)->toBe(OfferStatus::Scheduled);
});

it('blocks quote when per_retailer_max is already consumed', function () {
    $channel = SupplyChannel::factory()->create();

    $offer = Tenant::as($channel->id, function () use ($channel): Offer {
        $offer = Offer::query()->create([
            'supply_channel_id' => $channel->id,
            'name' => 'حد تاجر',
            'type' => OfferType::InvoiceDiscount,
            'targeting_scope' => TargetingScope::All,
            'per_retailer_max' => 1,
            'priority' => 50,
            'stackable' => false,
            'rules' => [],
            'min_invoice_value' => 0,
        ]);
        $offer->forceFill(['status' => OfferStatus::Active])->save();
        OfferReward::query()->create(['offer_id' => $offer->id, 'discount_percent' => 10]);
        OfferRedemption::query()->create(['offer_id' => $offer->id, 'applied_count' => 1, 'qty_consumed' => 1]);
        OfferRetailerRedemption::query()->create([
            'offer_id' => $offer->id,
            'retailer_id' => 481,
            'applied_count' => 1,
            'qty_consumed' => 1,
        ]);

        return $offer;
    });

    $applicator = app(\Modules\Core\Contracts\OfferApplicator::class);
    $quoted = $applicator->apply([
        'lines' => [[
            'product_id' => 1,
            'qty' => 1,
            'unit_price' => 10000,
            'discount' => 0,
            'line_total' => 10000,
        ]],
        'subtotal' => 10000,
        'currency' => 'SYP',
    ], [
        'zone_id' => 12,
        'retailer_id' => 481,
        'channel_id' => $channel->id,
    ]);

    expect($quoted['lines'][0]['discount'])->toBe(0)
        ->and($quoted['lines'][0]['offer_id'] ?? null)->toBeNull();

    unset($offer);
});

it('computes conversion_rate and net_margin from views and cost_price', function () {
    $channel = SupplyChannel::factory()->create();
    $offer = Tenant::as($channel->id, function () use ($channel): Offer {
        $offer = Offer::query()->create([
            'supply_channel_id' => $channel->id,
            'name' => 'هامش',
            'type' => OfferType::ProductDiscount,
            'targeting_scope' => TargetingScope::All,
            'stackable' => false,
            'priority' => 0,
        ]);
        $offer->forceFill(['status' => OfferStatus::Active])->save();
        OfferRedemption::query()->create([
            'offer_id' => $offer->id,
            'applied_count' => 1,
            'qty_consumed' => 1,
        ]);

        return $offer;
    });

    app(OfferConsumption::class)->recordView((int) $offer->id, 481);
    app(OfferConsumption::class)->recordView((int) $offer->id, 482);

    $currency = Currency::query()->where('iso', 'SYP')->first()
        ?? Currency::query()->create(['iso' => 'SYP', 'name' => 'SYP', 'decimals' => 0]);

    Tenant::as($channel->id, function () use ($channel, $currency): void {
        ProductBasePrice::query()->create([
            'supply_channel_id' => $channel->id,
            'product_id' => 9001,
            'currency_id' => $currency->id,
            'type' => 'simple',
            'base_price' => 10000,
            'cost_price' => 6000,
        ]);
    });

    $orderId = (int) DB::table('orders')->insertGetId([
        'retailer_id' => 481,
        'source' => 'app',
        'order_no' => 'ORD-M-'.uniqid(),
        'status' => 'pending',
        'currency' => 'SYP',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $subId = (int) DB::table('sub_orders')->insertGetId([
        'order_id' => $orderId,
        'channel_id' => $channel->id,
        'retailer_id' => 481,
        'zone_id' => 12,
        'source' => 'retailer_app',
        'sub_order_no' => 'SO-M-'.uniqid(),
        'status' => 'pending',
        'subtotal' => 10000,
        'discount' => 1000,
        'total' => 9000,
        'currency_code' => 'SYP',
        'fx_rate' => Money::FX_UNIT,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('sub_order_lines')->insert([
        'sub_order_id' => $subId,
        'product_id' => 9001,
        'qty' => 1,
        'unit_price' => 10000,
        'discount' => 1000,
        'line_total' => 9000,
        'offer_id' => $offer->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs(prodCompleteManager($channel), ['*'], 'channel');
    $response = $this->getJson("/api/v1/channel/offers/{$offer->id}/performance");
    CatalogAssert::ok($response);

    expect($response->json('data.net_margin'))->toBe(3000)
        ->and($response->json('data.conversion_rate'))->toBe(5000);
});
