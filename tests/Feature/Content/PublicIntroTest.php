<?php

declare(strict_types=1);

/**
 * EP-PB-011 — GET /public/content/intro.
 *
 * First-run splash for the apps. Same singleton EP-AD-141B writes. No auth.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\PlatformUser;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('returns the vacant default without a bearer', function () {
    $get = $this->getJson('/api/v1/public/content/intro');

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

it('reads back the platform default after the back office writes it', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $this->putJson('/api/v1/platform/content/intro', [
        'enabled' => true,
        'text' => 'مرحباً بك في شبكة التوزيع',
        'media_type' => 'video',
        'media_id' => 'media_intro_default',
        'duration' => 8,
        'targeting' => ['activity_type_ids' => [], 'zone_ids' => []],
    ])->assertOk();

    Sanctum::actingAs(null);

    $get = $this->getJson('/api/v1/public/content/intro');
    CatalogAssert::ok($get);
    expect($get->json('data.enabled'))->toBeTrue()
        ->and($get->json('data.text'))->toBe('مرحباً بك في شبكة التوزيع')
        ->and($get->json('data.media_type'))->toBe('video')
        ->and($get->json('data.media_id'))->toBe('media_intro_default')
        ->and($get->json('data.duration'))->toBe(8);
});
