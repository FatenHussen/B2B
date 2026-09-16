<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\ValueObjects\Money;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Promotion\Domain\Enums\OfferStatus;
use Modules\Promotion\Domain\Enums\OfferType;
use Modules\Promotion\Domain\Enums\TargetingScope;
use Modules\Promotion\Domain\Models\Offer;
use Modules\Promotion\Domain\Models\OfferRedemption;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function offerPerfManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

function offerWithRedemptions(int $channelId, int $applied): Offer
{
    return Tenant::as($channelId, function () use ($channelId, $applied): Offer {
        $offer = Offer::query()->create([
            'supply_channel_id' => $channelId,
            'name' => 'أداء',
            'type' => OfferType::ProductDiscount,
            'status' => OfferStatus::Active,
            'targeting_scope' => TargetingScope::All,
            'stackable' => false,
            'priority' => 0,
        ]);
        OfferRedemption::query()->create([
            'offer_id' => $offer->id,
            'applied_count' => $applied,
            'qty_consumed' => $applied,
        ]);

        return $offer;
    });
}

it('reads applied_count from redemptions and sales figures from order lines', function () {
    $channel = SupplyChannel::factory()->create();
    $offer = offerWithRedemptions($channel->id, 7);

    $orderId = (int) DB::table('orders')->insertGetId([
        'retailer_id' => 481,
        'source' => 'app',
        'order_no' => 'ORD-PERF',
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
        'sub_order_no' => 'SO-PERF',
        'status' => 'pending',
        'subtotal' => 50000,
        'discount' => 2000,
        'total' => 48000,
        'currency_code' => 'SYP',
        'fx_rate' => Money::FX_UNIT,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('sub_order_lines')->insert([
        'sub_order_id' => $subId,
        'product_id' => 1,
        'qty' => 2,
        'unit_price' => 25000,
        'discount' => 2000,
        'line_total' => 48000,
        'offer_id' => $offer->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Sanctum::actingAs(offerPerfManager($channel), ['*'], 'channel');

    $response = $this->getJson("/api/v1/channel/offers/{$offer->id}/performance");
    CatalogAssert::ok($response);

    expect($response->json('data.applied_count'))->toBe(7)
        ->and($response->json('data.linked_sales'))->toBe(48000)
        ->and($response->json('data.discount_given'))->toBe(2000)
        ->and($response->json('data.net_margin'))->toBe(0)
        ->and($response->json('data.retailers_count'))->toBe(1)
        ->and($response->json('data.by_zone.0.zone_id'))->toBe(12)
        ->and($response->json('data.conversion_rate'))->toBe(0);
});

it('404s another channel offer on performance', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $theirs = offerWithRedemptions($foreign->id, 3);

    Sanctum::actingAs(offerPerfManager($own), ['*'], 'channel');

    CatalogAssert::error(
        $this->getJson("/api/v1/channel/offers/{$theirs->id}/performance"),
        404,
        'not_found',
    );
})->group('tenancy');
