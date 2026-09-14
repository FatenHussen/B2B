<?php

declare(strict_types=1);

/**
 * BE-A10 — temporary grants expire on the clock, with no cleanup job.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Access\Domain\Enums\TempGrantStatus;
use Modules\Access\Domain\Models\TempGrant;
use Modules\Access\Infrastructure\GuardUserLocator;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId(0);
});

it('an expired grant stops conferring access without any job having to run on time', function () {
    // The conferring path is Gate::after + isLive(). Approving must not givePermissionTo —
    // that would answer in Gate::before forever, and expiry would never be seen.
    $grantee = ChannelUser::factory()->create();
    $permission = 'sc.finance.void_invoice';

    expect($grantee->can($permission))->toBeFalse();

    $grant = TempGrant::query()->create([
        'grantee_type' => app(GuardUserLocator::class)->morphAlias($grantee),
        'grantee_id' => $grantee->id,
        'permission' => $permission,
        'status' => TempGrantStatus::Active,
        'duration_minutes' => 60,
        'granted_until' => now()->addHour(),
        'requester_id' => 1,
        'reason' => 'تصحيح فاتورة مكررة بعد حادث مزامنة — تذكرة SUP-441',
    ]);

    expect($grantee->can($permission))->toBeTrue();

    $grant->forceFill(['granted_until' => now()->subMinute()])->save();

    expect($grantee->fresh()->can($permission))->toBeFalse()
        ->and($grant->fresh()->isLive())->toBeFalse();
});

it('a grant longer than 240 minutes is refused with 422', function () {
    $admin = PlatformUser::factory()->create();
    $admin->assignRole('platform_admin');
    Sanctum::actingAs($admin, ['*'], 'platform');

    $grantee = ChannelUser::factory()->create();

    $this->postJson('/api/v1/platform/iam/temp-grants', [
        'user_id' => $grantee->id,
        'permission' => 'sc.finance.void_invoice',
        'duration_minutes' => 241,
        'reason' => 'تصحيح فاتورة مكررة بعد حادث مزامنة — تذكرة SUP-441',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');

    expect(TempGrant::query()->count())->toBe(0);
});

it('a live temp grant confers until granted_until without a Spatie direct permission', function () {
    $grantee = ChannelUser::factory()->create();
    $permission = 'sc.finance.void_invoice';

    TempGrant::query()->create([
        'grantee_type' => app(GuardUserLocator::class)->morphAlias($grantee),
        'grantee_id' => $grantee->id,
        'permission' => $permission,
        'status' => TempGrantStatus::Active,
        'duration_minutes' => 30,
        'granted_until' => now()->addMinutes(30),
        'requester_id' => 1,
        'reason' => 'تصحيح فاتورة مكررة بعد حادث مزامنة — تذكرة SUP-441',
    ]);

    expect($grantee->can($permission))->toBeTrue()
        ->and($grantee->hasPermissionTo($permission))->toBeFalse();
});
