<?php

declare(strict_types=1);

/**
 * PA-12 — EP-AD-141A/B, `GET/PUT /platform/content/intro`.
 *
 * The platform default intro is a singleton the back office edits. A channel that
 * never set its own intro is who this row is for later; the channel dashboard
 * still reads `/channel/content/intro` and is a different store.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

function platformIntroAdmin(): PlatformUser
{
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    return $admin;
}

/**
 * @param  list<string>  $permissions
 */
function platformIntroUserWith(array $permissions): PlatformUser
{
    $user = PlatformUser::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo($permission);
    }
    Sanctum::actingAs($user, ['*'], 'platform');

    return $user;
}

/**
 * @return array<string, mixed>
 */
function platformIntroBody(): array
{
    return [
        'enabled' => true,
        'text' => 'مرحباً في منصة التوزيع',
        'media_type' => 'video',
        'media_id' => 'media_intro_default',
        'duration' => 8,
        'targeting' => ['activity_type_ids' => [3], 'zone_ids' => []],
    ];
}

it('returns the vacant default when no intro has been saved', function () {
    platformIntroAdmin();

    $get = $this->getJson('/api/v1/platform/content/intro');
    CatalogAssert::ok($get);
    expect($get->json('data'))->toBe([
        'enabled' => false,
        'text' => null,
        'media_type' => null,
        'media_id' => null,
        'duration' => 0,
        'targeting' => ['activity_type_ids' => [], 'zone_ids' => []],
    ]);
});

it('updates the default intro and reads it back in the catalog shape', function () {
    platformIntroAdmin();

    $put = $this->putJson('/api/v1/platform/content/intro', platformIntroBody());
    CatalogAssert::ok($put);
    expect($put->json('data'))->toBe(['enabled' => true]);

    $get = $this->getJson('/api/v1/platform/content/intro');
    CatalogAssert::ok($get);
    expect($get->json('data.enabled'))->toBeTrue()
        ->and($get->json('data.text'))->toBe('مرحباً في منصة التوزيع')
        ->and($get->json('data.media_type'))->toBe('video')
        ->and($get->json('data.media_id'))->toBe('media_intro_default')
        ->and($get->json('data.duration'))->toBe(8)
        ->and($get->json('data.targeting.activity_type_ids'))->toBe([3])
        ->and($get->json('data.targeting.zone_ids'))->toBe([]);
});

it('replaces the singleton on a second write', function () {
    platformIntroAdmin();

    $this->putJson('/api/v1/platform/content/intro', platformIntroBody())->assertOk();
    $this->putJson('/api/v1/platform/content/intro', [
        'enabled' => false,
        'text' => 'نسخة ثانية',
        'media_type' => 'image',
        'media_id' => 'media_intro_v2',
        'duration' => 2,
        'targeting' => ['activity_type_ids' => [], 'zone_ids' => [12]],
    ])->assertOk();

    $get = $this->getJson('/api/v1/platform/content/intro');
    expect($get->json('data.enabled'))->toBeFalse()
        ->and($get->json('data.text'))->toBe('نسخة ثانية')
        ->and($get->json('data.media_type'))->toBe('image')
        ->and($get->json('data.duration'))->toBe(2)
        ->and($get->json('data.targeting.zone_ids'))->toBe([12]);
});

it('rejects a write that omits enabled or names an unknown media_type', function () {
    platformIntroAdmin();

    CatalogAssert::error(
        $this->putJson('/api/v1/platform/content/intro', [
            'text' => 'مرحباً',
            'media_type' => 'video',
        ]),
        422,
        'validation_failed',
    );

    CatalogAssert::error(
        $this->putJson('/api/v1/platform/content/intro', [
            'enabled' => true,
            'media_type' => 'gif',
        ]),
        422,
        'validation_failed',
    );
});

it('names ad.content.view on a 403 for a platform user without it', function () {
    platformIntroUserWith([]);

    $this->getJson('/api/v1/platform/content/intro')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.content.view');
});

it('names ad.content.manage on a 403 when the caller can only view', function () {
    platformIntroUserWith(['ad.content.view']);

    $this->putJson('/api/v1/platform/content/intro', platformIntroBody())
        ->assertForbidden()
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.content.manage');
});

it('lets a caller with only ad.content.manage write', function () {
    platformIntroUserWith(['ad.content.manage']);

    CatalogAssert::ok($this->putJson('/api/v1/platform/content/intro', platformIntroBody()));
});

it('does not leak the platform default onto a channel intro that was never set', function () {
    platformIntroAdmin();
    $this->putJson('/api/v1/platform/content/intro', platformIntroBody())->assertOk();

    $channel = SupplyChannel::factory()->create();
    $manager = ChannelUser::factory()->forChannel($channel)->create();
    $manager->assignRole('channel_manager');
    Sanctum::actingAs($manager, ['*'], 'channel');

    $get = $this->getJson('/api/v1/channel/content/intro');
    CatalogAssert::ok($get);
    expect($get->json('data.enabled'))->toBeFalse()
        ->and($get->json('data.media_id'))->toBeNull();
});
