<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
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

it('reads applied_count from redemptions and does not invent sales figures', function () {
    $channel = SupplyChannel::factory()->create();
    $offer = offerWithRedemptions($channel->id, 7);
    Sanctum::actingAs(offerPerfManager($channel), ['*'], 'channel');

    $response = $this->getJson("/api/v1/channel/offers/{$offer->id}/performance");
    CatalogAssert::ok($response);

    expect($response->json('data.applied_count'))->toBe(7)
        ->and($response->json('data.linked_sales'))->toBe(0)
        ->and($response->json('data.discount_given'))->toBe(0)
        ->and($response->json('data.net_margin'))->toBe(0)
        ->and($response->json('data.by_zone'))->toBe([]);
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
