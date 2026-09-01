<?php

declare(strict_types=1);

use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Access\Domain\PermissionCatalog;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\WarehouseUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

it('seeds catalog permissions on the matching guard', function () {
    $platformCodes = PermissionCatalog::codesForSystem('platform');

    expect(Permission::query()->where('guard_name', 'platform')->whereIn('name', $platformCodes)->count())
        ->toBe(count($platformCodes))
        ->and(Role::query()->where('guard_name', 'platform')->where('name', 'platform_admin')->exists())
        ->toBeTrue();
})->group('permissions');

it('grants a warehouse keeper warehouse catalog codes', function () {
    $user = WarehouseUser::factory()->create();
    $user->assignRole('warehouse_keeper');
    $codes = PermissionCatalog::codesForSystem('warehouse');

    expect($codes)->not->toBeEmpty()
        ->and($user->hasPermissionTo($codes[0]))->toBeTrue()
        ->and($user->hasPermissionTo('inventory.update'))->toBeTrue()
        ->and($user->hasPermissionTo('finance.view'))->toBeFalse();
})->group('permissions');

it('gives a channel manager channel catalog control including legacy settings', function () {
    $user = ChannelUser::factory()->create();
    $user->assignRole('channel_manager');

    expect($user->hasPermissionTo('settings.update'))->toBeTrue()
        ->and($user->hasPermissionTo('sc.orders.confirm'))->toBeTrue();
})->group('permissions');

it('limits an accountant to financial catalog abilities', function () {
    $user = ChannelUser::factory()->create();
    $user->assignRole('accountant');

    expect($user->hasPermissionTo('sc.finance.payment'))->toBeTrue()
        ->and($user->hasPermissionTo('sc.catalog.create'))->toBeFalse()
        ->and($user->hasPermissionTo('finance.update'))->toBeTrue();
})->group('permissions');

it('lets a platform admin bypass every ability via Gate::before', function () {
    $user = PlatformUser::factory()->create();
    $user->assignRole('platform_admin');

    expect($user->can('settings.delete'))->toBeTrue()
        ->and($user->can('anything.not.even.defined'))->toBeTrue();
})->group('permissions');
