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
        ->and($user->hasPermissionTo('wh.stocktake.execute'))->toBeTrue()
        // `can()`, not `hasPermissionTo()`. This used to name `finance.view`, an interim
        // AccessMatrix name seeded on all four guards, so asking the warehouse guard for
        // it was a meaningful question. Now a channel code exists on the channel guard
        // only and `hasPermissionTo` throws PermissionDoesNotExist rather than answering;
        // `can()` routes through the gate, which returns false for a code off-guard.
        ->and($user->can('sc.finance.view'))->toBeFalse();
})->group('permissions');

it('gives a channel manager every channel catalog code including its own settings', function () {
    $user = ChannelUser::factory()->create();
    $user->assignRole('channel_manager');

    expect($user->hasPermissionTo('sc.settings.update'))->toBeTrue()
        ->and($user->hasPermissionTo('sc.orders.confirm'))->toBeTrue()
        // The whole point of the vocabulary batch: writing shared reference data is a
        // platform code, and a channel role does not reach it from its own guard.
        ->and($user->can('ad.refs.create'))->toBeFalse();
})->group('permissions');

it('limits an accountant to financial catalog abilities', function () {
    $user = ChannelUser::factory()->create();
    $user->assignRole('accountant');

    expect($user->hasPermissionTo('sc.finance.payment'))->toBeTrue()
        ->and($user->hasPermissionTo('sc.catalog.create'))->toBeFalse()
        ->and($user->hasPermissionTo('sc.finance.credit_note'))->toBeTrue()
        ->and($user->hasPermissionTo('sc.settings.update'))->toBeFalse();
})->group('permissions');

it('lets a platform admin bypass every ability via Gate::before', function () {
    $user = PlatformUser::factory()->create();
    $user->assignRole('platform_admin');

    // `Gate::before` in AppServiceProvider returns true for this role before any
    // permission is consulted, so a channel code it was never granted answers true and
    // so does a string that names nothing. Worth stating plainly: no `can()` assertion
    // anywhere proves a platform_admin's permissions are wired correctly.
    expect($user->can('sc.orders.confirm'))->toBeTrue()
        ->and($user->can('anything.not.even.defined'))->toBeTrue()
        ->and($user->hasPermissionTo('ad.refs.create'))->toBeTrue();
})->group('permissions');
