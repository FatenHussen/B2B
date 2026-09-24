<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Contracts\CreditGuard;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\Tenant;
use Modules\Finance\Domain\Models\CreditApprovalRequest;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\RetailerProfile;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * @return array{channel: SupplyChannel, manager: ChannelUser, retailer_id: int}
 */
function creditApprovalFixtures(): array
{
    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');

    $gov = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $gov->id]);
    $activity = ActivityType::query()->create([
        'name' => 'بقالة',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);

    $retailer = AppUser::factory()->retailer()->create(['status' => UserStatus::Active]);
    $profile = RetailerProfile::query()->create([
        'app_user_id' => $retailer->id,
        'shop_name' => 'محل '.$retailer->id,
        'activity_type_id' => $activity->id,
        'governorate_id' => $gov->id,
        'zone_id' => $zone->id,
        'status' => ProfileStatus::Active,
    ]);

    return [
        'channel' => $channel,
        'manager' => $manager,
        'retailer_id' => (int) $profile->id,
    ];
}

it('queues manual_approval, lists pending, approve then consumes on next assert', function () {
    $fx = creditApprovalFixtures();
    $channel = $fx['channel'];
    $retailerId = $fx['retailer_id'];

    Sanctum::actingAs($fx['manager'], ['*'], 'channel');
    CatalogAssert::ok($this->putJson('/api/v1/channel/retailers/'.$retailerId.'/credit', [
        'credit_limit' => 100000,
        'grace_days' => 0,
        'on_exceed' => 'manual_approval',
    ]));

    $guard = app(CreditGuard::class);
    $amount = 200000;

    $approvalId = 0;
    try {
        $guard->assertWithinLimit($retailerId, $channel->id, $amount);
        expect(false)->toBeTrue('expected credit_limit_exceeded');
    } catch (DomainException $e) {
        expect($e->errorCode)->toBe('credit_limit_exceeded')
            ->and($e->status)->toBe(423)
            ->and($e->details)->toHaveKeys(['approval_id', 'retailer_id', 'channel_id'])
            ->and($e->details['approval_id'])->toBeInt()
            ->and($e->details['retailer_id'])->toBe($retailerId)
            ->and($e->details['channel_id'])->toBe($channel->id);
        $approvalId = (int) $e->details['approval_id'];
    }

    $list = $this->getJson('/api/v1/channel/credit-approvals?filter[status]=pending');
    CatalogAssert::ok($list);
    expect($list->json('data'))->toHaveCount(1)
        ->and($list->json('data.0.id'))->toBe($approvalId)
        ->and($list->json('data.0.retailer_id'))->toBe($retailerId)
        ->and($list->json('data.0.amount_minor'))->toBe($amount)
        ->and($list->json('data.0.status'))->toBe('pending')
        ->and($list->json('data.0'))->toHaveKeys(['outstanding', 'credit_limit', 'created_at']);

    $decide = $this->postJson('/api/v1/channel/credit-approvals/'.$approvalId.'/decide', [
        'decision' => 'approve',
        'reason' => 'عميل موثوق',
    ]);
    CatalogAssert::ok($decide);
    expect($decide->json('data'))->toBe(['id' => $approvalId, 'status' => 'approved']);

    $guard->assertWithinLimit($retailerId, $channel->id, $amount);

    Tenant::as($channel->id, function () use ($approvalId): void {
        $row = CreditApprovalRequest::query()->whereKey($approvalId)->first();
        expect($row)->not->toBeNull()
            ->and($row->status)->toBe('consumed')
            ->and($row->consumed_at)->not->toBeNull();
    });
});

it('409s decide when the approval is not pending', function () {
    $fx = creditApprovalFixtures();
    $channel = $fx['channel'];
    $retailerId = $fx['retailer_id'];

    Sanctum::actingAs($fx['manager'], ['*'], 'channel');
    $this->putJson('/api/v1/channel/retailers/'.$retailerId.'/credit', [
        'credit_limit' => 100000,
        'grace_days' => 0,
        'on_exceed' => 'manual_approval',
    ])->assertOk();

    $approvalId = 0;
    try {
        app(CreditGuard::class)->assertWithinLimit($retailerId, $channel->id, 200000);
    } catch (DomainException $e) {
        $approvalId = (int) $e->details['approval_id'];
    }

    $this->postJson('/api/v1/channel/credit-approvals/'.$approvalId.'/decide', [
        'decision' => 'reject',
    ])->assertOk();

    CatalogAssert::error(
        $this->postJson('/api/v1/channel/credit-approvals/'.$approvalId.'/decide', [
            'decision' => 'approve',
        ]),
        409,
        'illegal_transition',
    );
});
