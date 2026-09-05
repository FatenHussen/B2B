<?php

declare(strict_types=1);

use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/*
 * 403 wrong_guard, not 401. A live token held by another guard's user is not an
 * anonymous request, and BE-C02 stopped reporting it as one. The boundary itself is
 * unchanged: no crossing has ever reached an endpoint. Tests\Feature\Security
 * \CrossGuardTest walks all eighteen of them.
 */

it('rejects an app token on platform routes', function () {
    $user = AppUser::factory()->retailer()->create();
    $token = $user->createToken('device', ['*'])->plainTextToken;

    CatalogAssert::error(
        $this->getJson('/api/v1/platform/auth/me', ['Authorization' => "Bearer {$token}"]),
        403,
        'wrong_guard',
    );
});

it('rejects a platform token on app retailer register', function () {
    $admin = PlatformUser::factory()->create();
    $token = $admin->createToken('session', ['*'])->plainTextToken;

    CatalogAssert::error(
        $this->postJson('/api/v1/app/retailer/register', [
            'owner_name' => 'Test',
            'shop_name' => 'Shop',
            'activity_type_id' => 1,
            'category_ids' => [1],
            'governorate_id' => 1,
            'zone_id' => 1,
        ], ['Authorization' => "Bearer {$token}"]),
        403,
        'wrong_guard',
    );
});

it('rejects a channel token on platform me', function () {
    $channel = SupplyChannel::factory()->create();
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $token = $user->createToken('session', ['*'])->plainTextToken;

    CatalogAssert::error(
        $this->getJson('/api/v1/platform/me', ['Authorization' => "Bearer {$token}"]),
        403,
        'wrong_guard',
    );
});

it('rejects a warehouse token on app session', function () {
    $user = WarehouseUser::factory()->create();
    $token = $user->createToken('device', ['*'])->plainTextToken;

    CatalogAssert::error(
        $this->getJson('/api/v1/app/session', ['Authorization' => "Bearer {$token}"]),
        403,
        'wrong_guard',
    );
});
