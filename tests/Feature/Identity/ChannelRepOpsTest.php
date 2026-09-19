<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\WalletTransaction;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\RepSourcedShopStatus;
use Modules\Identity\Domain\Enums\RepZoneRequestStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RepProfile;
use Modules\Identity\Domain\Models\RepSourcedShop;
use Modules\Identity\Domain\Models\RepZoneRequest;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function channelRepManager(SupplyChannel $channel): ChannelUser
{
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->assignRole('channel_manager');

    return $user;
}

/**
 * @return array{rep_id: int, profile_id: int, activity_id: int, zone_id: int, gov_id: int}
 */
function channelRepPerson(SupplyChannel $channel, ProfileStatus $status = ProfileStatus::PendingReview): array
{
    $gov = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $gov->id]);
    $activity = ActivityType::query()->create([
        'name' => 'بقالة',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);

    $rep = AppUser::factory()->rep()->create(['status' => UserStatus::Pending]);
    $profile = RepProfile::query()->create([
        'app_user_id' => $rep->id,
        'channel_id' => $channel->id,
        'activity_type_id' => $activity->id,
        'status' => $status,
    ]);

    return [
        'rep_id' => (int) $rep->id,
        'profile_id' => (int) $profile->id,
        'activity_id' => (int) $activity->id,
        'zone_id' => (int) $zone->id,
        'gov_id' => (int) $gov->id,
    ];
}

it('lists reps scoped to the channel', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $mine = channelRepPerson($own);
    channelRepPerson($foreign);

    Sanctum::actingAs(channelRepManager($own), ['*'], 'channel');
    $response = $this->getJson('/api/v1/channel/reps');
    CatalogAssert::ok($response);

    $ids = $response->json('data.*.id');
    expect($ids)->toContain($mine['rep_id'])
        ->and(count($ids))->toBe(1);
});

it('shows a rep and 404s a foreign channel rep', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $mine = channelRepPerson($own, ProfileStatus::Active);
    $theirs = channelRepPerson($foreign, ProfileStatus::Active);

    Sanctum::actingAs(channelRepManager($own), ['*'], 'channel');
    $ok = $this->getJson('/api/v1/channel/reps/'.$mine['rep_id']);
    CatalogAssert::ok($ok);
    expect($ok->json('data.status'))->toBe('active');

    CatalogAssert::error(
        $this->getJson('/api/v1/channel/reps/'.$theirs['rep_id']),
        404,
        'not_found',
    );
})->group('tenancy');

it('approves a pending rep', function () {
    $channel = SupplyChannel::factory()->create();
    $person = channelRepPerson($channel, ProfileStatus::PendingReview);

    Sanctum::actingAs(channelRepManager($channel), ['*'], 'channel');
    $response = $this->postJson('/api/v1/channel/reps/'.$person['rep_id'].'/approve', [
        'reason' => 'مكتمل',
    ]);
    CatalogAssert::ok($response);
    expect($response->json('data.status'))->toBe('active');
});

it('rejects a pending rep with reason', function () {
    $channel = SupplyChannel::factory()->create();
    $person = channelRepPerson($channel);

    Sanctum::actingAs(channelRepManager($channel), ['*'], 'channel');
    $response = $this->postJson('/api/v1/channel/reps/'.$person['rep_id'].'/reject', [
        'reason' => 'ناقص',
    ]);
    CatalogAssert::ok($response);
    expect($response->json('data.status'))->toBe('rejected');
});

it('disables an active rep', function () {
    $channel = SupplyChannel::factory()->create();
    $person = channelRepPerson($channel, ProfileStatus::Active);

    Sanctum::actingAs(channelRepManager($channel), ['*'], 'channel');
    $response = $this->postJson('/api/v1/channel/reps/'.$person['rep_id'].'/disable', [
        'reason' => 'ترك العمل',
    ]);
    CatalogAssert::ok($response);
    expect($response->json('data.status'))->toBe('disabled');
});

it('forbids approve without sc.reps.update', function () {
    $channel = SupplyChannel::factory()->create();
    $person = channelRepPerson($channel);
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $user->givePermissionTo('sc.reps.view');

    Sanctum::actingAs($user, ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/reps/'.$person['rep_id'].'/approve', []),
        403,
        'insufficient_permission',
    );
});

it('reads a channel-scoped rep wallet matching the ledger', function () {
    $channel = SupplyChannel::factory()->create();
    $person = channelRepPerson($channel, ProfileStatus::Active);
    Tenant::as($channel->id, function () use ($channel, $person): void {
        WalletTransaction::query()->create([
            'supply_channel_id' => $channel->id,
            'rep_id' => $person['rep_id'],
            'type' => 'collected',
            'amount' => 400_000,
        ]);
        WalletTransaction::query()->create([
            'supply_channel_id' => $channel->id,
            'rep_id' => $person['rep_id'],
            'type' => 'settled',
            'amount' => 100_000,
        ]);
    });

    Sanctum::actingAs(channelRepManager($channel), ['*'], 'channel');
    $response = $this->getJson('/api/v1/channel/reps/'.$person['rep_id'].'/wallet');
    CatalogAssert::ok($response);
    expect($response->json('data.net_balance'))->toBe(300_000);
});

it('approves a pending zone request onto the profile', function () {
    $channel = SupplyChannel::factory()->create();
    $person = channelRepPerson($channel, ProfileStatus::Active);
    $request = RepZoneRequest::query()->create([
        'rep_id' => $person['profile_id'],
        'zone_id' => $person['zone_id'],
        'note' => 'طلب',
        'status' => RepZoneRequestStatus::PendingApproval,
    ]);

    Sanctum::actingAs(channelRepManager($channel), ['*'], 'channel');
    $list = $this->getJson('/api/v1/channel/rep-zone-requests?filter[status]=pending_approval');
    CatalogAssert::ok($list);
    expect($list->json('data.*.id'))->toContain($request->id);

    $decide = $this->postJson('/api/v1/channel/rep-zone-requests/'.$request->id.'/decide', [
        'decision' => 'approve',
    ]);
    CatalogAssert::ok($decide);
    expect($decide->json('data.status'))->toBe('approved');

    $profile = RepProfile::query()->findOrFail($person['profile_id']);
    expect($profile->zoneIds())->toContain($person['zone_id']);
});

it('approves a pending sourced shop and activates the retailer', function () {
    $channel = SupplyChannel::factory()->create();
    $person = channelRepPerson($channel, ProfileStatus::Active);
    $placeholder = AppUser::factory()->create(['status' => UserStatus::Pending, 'kind' => null]);
    $retailer = RetailerProfile::query()->create([
        'app_user_id' => $placeholder->id,
        'shop_name' => 'محل ميداني',
        'activity_type_id' => $person['activity_id'],
        'governorate_id' => $person['gov_id'],
        'zone_id' => $person['zone_id'],
        'status' => ProfileStatus::PendingReview,
    ]);
    $shop = RepSourcedShop::query()->create([
        'rep_id' => $person['profile_id'],
        'shop_name' => 'محل ميداني',
        'owner_name' => 'مالك',
        'phone' => '+963988111111',
        'zone_id' => $person['zone_id'],
        'activity_type_id' => $person['activity_id'],
        'client_op_id' => 'op_test_1',
        'status' => RepSourcedShopStatus::PendingSync,
        'retailer_id' => $retailer->id,
    ]);

    Sanctum::actingAs(channelRepManager($channel), ['*'], 'channel');
    $decide = $this->postJson('/api/v1/channel/rep-sourced-shops/'.$shop->id.'/decide', [
        'decision' => 'approve',
        'reason' => 'تحقق',
    ]);
    CatalogAssert::ok($decide);
    expect($decide->json('data.status'))->toBe('linked');
    expect(RetailerProfile::query()->findOrFail($retailer->id)->status)->toBe(ProfileStatus::Active);
});

it('404s zone decide for another channel', function () {
    $own = SupplyChannel::factory()->create();
    $foreign = SupplyChannel::factory()->create();
    $theirs = channelRepPerson($foreign, ProfileStatus::Active);
    $request = RepZoneRequest::query()->create([
        'rep_id' => $theirs['profile_id'],
        'zone_id' => $theirs['zone_id'],
        'status' => RepZoneRequestStatus::PendingApproval,
    ]);

    Sanctum::actingAs(channelRepManager($own), ['*'], 'channel');
    CatalogAssert::error(
        $this->postJson('/api/v1/channel/rep-zone-requests/'.$request->id.'/decide', [
            'decision' => 'reject',
            'reason' => 'لا',
        ]),
        404,
        'not_found',
    );
})->group('tenancy');
