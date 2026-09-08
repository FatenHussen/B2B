<?php

declare(strict_types=1);

use Modules\Core\Contracts\OtpChannel;
use Modules\Core\Support\Tenant;
use Modules\Identity\Domain\Enums\ProfileStatus;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\ValueObjects\PhoneNumber;
use Modules\Identity\Infrastructure\Otp\FakeOtpChannel;
use Modules\Reference\Domain\Enums\RefStatus;
use Modules\Reference\Domain\Models\ActivityType;
use Modules\Reference\Domain\Models\ChannelZone;
use Modules\Reference\Domain\Models\Equipment;
use Modules\Reference\Domain\Models\Governorate;
use Modules\Reference\Domain\Models\RootCategory;
use Modules\Reference\Domain\Models\Zone;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(function () {
    $this->otp = app(OtpChannel::class);
    expect($this->otp)->toBeInstanceOf(FakeOtpChannel::class);
});

function registrationToken(string $phone = '+963933000000'): string
{
    $requested = test()->postJson('/api/v1/public/auth/request-otp', [
        'phone' => $phone,
        'purpose' => 'register',
        'client' => 'retailer-android',
    ])->assertOk();

    /** @var FakeOtpChannel $fake */
    $fake = test()->otp;

    $verified = test()->postJson('/api/v1/public/auth/verify-otp', [
        'otp_id' => $requested->json('data.otp_id'),
        'code' => $fake->codeFor(PhoneNumber::normalize($phone)),
        'device_id' => 'device-reg',
        'device_name' => 'Redmi',
        'platform' => 'android',
    ])->assertOk();

    expect($verified->json('data.is_new_user'))->toBeTrue();

    return $verified->json('data.token');
}

function seedRetailerRefs(): array
{
    $governorate = Governorate::factory()->create();
    $zone = Zone::factory()->create(['governorate_id' => $governorate->id]);
    $otherGov = Governorate::factory()->create();
    $foreignZone = Zone::factory()->create(['governorate_id' => $otherGov->id]);
    $activity = ActivityType::query()->create([
        'name' => 'بقالة',
        'icon' => 'grocery',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);
    $category = RootCategory::query()->create([
        'name' => 'مواد غذائية',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);
    $equipment = Equipment::query()->create([
        'name' => 'ثلاجة عرض',
        'order' => 1,
        'status' => RefStatus::Active,
    ]);

    return compact('governorate', 'zone', 'foreignZone', 'activity', 'category', 'equipment');
}

it('registers a retailer and returns a full token', function () {
    $refs = seedRetailerRefs();
    $token = registrationToken();

    $response = $this->postJson('/api/v1/app/retailer/register', [
        'owner_name' => 'أبو خالد',
        'shop_name' => 'بقالية النور',
        'activity_type_id' => $refs['activity']->id,
        'category_ids' => [$refs['category']->id],
        'equipment_ids' => [$refs['equipment']->id],
        'governorate_id' => $refs['governorate']->id,
        'zone_id' => $refs['zone']->id,
        'address' => 'المزة',
    ], ['Authorization' => "Bearer {$token}"]);

    CatalogAssert::ok($response, ['retailer', 'token'], 201);
    expect($response->json('data.retailer.status'))->toBe(ProfileStatus::PendingReview->value)
        ->and(AppUser::query()->where('phone', '+963933000000')->first()?->kind?->value)->toBe('retailer');
});

it('rejects a retailer zone outside the governorate', function () {
    $refs = seedRetailerRefs();
    $token = registrationToken();

    CatalogAssert::error(
        $this->postJson('/api/v1/app/retailer/register', [
            'owner_name' => 'أبو خالد',
            'shop_name' => 'بقالية النور',
            'activity_type_id' => $refs['activity']->id,
            'category_ids' => [$refs['category']->id],
            'governorate_id' => $refs['governorate']->id,
            'zone_id' => $refs['foreignZone']->id,
        ], ['Authorization' => "Bearer {$token}"]),
        422,
        'validation_failed',
    );
});

it('registers a rep inside channel coverage', function () {
    $refs = seedRetailerRefs();
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور', 'status' => 'active']);
    Tenant::as($channel->id, fn () => ChannelZone::query()->create(['zone_id' => $refs['zone']->id]));

    $token = registrationToken('+963966000000');

    $response = $this->postJson('/api/v1/app/rep/register', [
        'name' => 'أحمد العلي',
        'supply_channel_id' => $channel->id,
        'activity_type_id' => $refs['activity']->id,
        'zone_ids' => [$refs['zone']->id],
    ], ['Authorization' => "Bearer {$token}"]);

    CatalogAssert::ok($response, ['rep', 'token'], 201);
    expect($response->json('data.rep.status'))->toBe(ProfileStatus::PendingReview->value);
});

it('rejects a rep zone outside channel coverage', function () {
    $refs = seedRetailerRefs();
    $channel = SupplyChannel::factory()->create(['status' => 'active']);

    $token = registrationToken('+963966000001');

    CatalogAssert::error(
        $this->postJson('/api/v1/app/rep/register', [
            'name' => 'أحمد',
            'supply_channel_id' => $channel->id,
            'activity_type_id' => $refs['activity']->id,
            'zone_ids' => [$refs['zone']->id],
        ], ['Authorization' => "Bearer {$token}"]),
        422,
        'validation_failed',
    );
});

it('rejects a rep on a non-active channel', function () {
    $refs = seedRetailerRefs();
    $channel = SupplyChannel::factory()->suspended()->create();
    Tenant::as($channel->id, fn () => ChannelZone::query()->create(['zone_id' => $refs['zone']->id]));

    $token = registrationToken('+963966000002');

    CatalogAssert::error(
        $this->postJson('/api/v1/app/rep/register', [
            'name' => 'أحمد',
            'supply_channel_id' => $channel->id,
            'activity_type_id' => $refs['activity']->id,
            'zone_ids' => [$refs['zone']->id],
        ], ['Authorization' => "Bearer {$token}"]),
        409,
        'conflict',
    );
});

it('bootstraps the app session and logs out', function () {
    $user = AppUser::factory()->retailer()->create();
    $token = $user->createToken('device', ['*'])->plainTextToken;
    $auth = ['Authorization' => "Bearer {$token}"];

    CatalogAssert::ok($this->getJson('/api/v1/app/session', $auth), ['user', 'permissions', 'feature_flags']);

    $this->postJson('/api/v1/app/auth/logout', [], $auth)
        ->assertOk()
        ->assertJsonPath('data.success', true);

    $this->app['auth']->forgetGuards();

    // token_revoked, not unauthenticated: logout deleted the row behind this token, and
    // the client needs to tell "your session ended" from "you never had one".
    CatalogAssert::error($this->getJson('/api/v1/app/session', $auth), 401, 'token_revoked');
});
