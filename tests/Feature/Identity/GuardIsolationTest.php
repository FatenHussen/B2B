<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Tests\Support\CatalogAssert;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('rejects an app token on platform routes', function () {
    $user = AppUser::factory()->retailer()->create();
    $token = $user->createToken('device', ['*'])->plainTextToken;

    CatalogAssert::error(
        $this->getJson('/api/v1/platform/auth/me', ['Authorization' => "Bearer {$token}"]),
        401,
        'unauthenticated',
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
        401,
        'unauthenticated',
    );
});

it('rejects a channel token on platform me', function () {
    $channel = SupplyChannel::factory()->create();
    $user = ChannelUser::factory()->forChannel($channel)->create();
    $token = $user->createToken('session', ['*'])->plainTextToken;

    CatalogAssert::error(
        $this->getJson('/api/v1/platform/me', ['Authorization' => "Bearer {$token}"]),
        401,
        'unauthenticated',
    );
});

it('rejects a warehouse token on app session', function () {
    $user = WarehouseUser::factory()->create();
    $token = $user->createToken('device', ['*'])->plainTextToken;

    CatalogAssert::error(
        $this->getJson('/api/v1/app/session', ['Authorization' => "Bearer {$token}"]),
        401,
        'unauthenticated',
    );
});
