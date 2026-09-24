<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Presentation\Http\Controllers\ChannelApplicationController;
use Modules\Tenancy\Presentation\Http\Controllers\ChannelSettingsController;
use Modules\Tenancy\Presentation\Http\Controllers\PlatformFeatureController;
use Modules\Tenancy\Presentation\Http\Controllers\PlatformPlanController;
use Modules\Tenancy\Presentation\Http\Controllers\SupplyChannelController;

/*
 * The path prefix names the guard, not the module. Tenancy serves two audiences, so it
 * publishes two groups instead of one `auth:sanctum` group that served neither.
 */

/*
 * Platform back office — EP-AD-050..056, 058, 062 on the catalog's path. The nine routes
 * sat on two prefixes until PA-01 (2026-09-19): seven on a temporary `/admin/channels`
 * and two here. One group, one constant on the frontend. Every gate is the permission
 * the catalog names, so a 403 carries `error.permission`; the platform_admin role still
 * passes every one of them through Gate::before.
 */
Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/platform/channels')
    ->group(function () {
        // EP-AD-050.
        Route::get('/', [SupplyChannelController::class, 'index'])
            ->middleware('permission:ad.channels.view');

        // EP-AD-051 (BE-T04).
        Route::post('/', [SupplyChannelController::class, 'store'])
            ->middleware('permission:ad.channels.create');

        // EP-AD-052 (BE-T06).
        Route::get('{supplyChannel}', [SupplyChannelController::class, 'show'])
            ->middleware('permission:ad.channels.view');

        // EP-AD-062 (BE-T06). Same permission name as retry-provisioning; reason is required.
        Route::put('{supplyChannel}', [SupplyChannelController::class, 'update'])
            ->middleware('permission:ad.channels.update');

        // EP-AD-058 / BF-05. Dual-gated soft delete: archived ≥ 30 days + password +
        // Identity step-up OTP (TOTP or phone) + typed name + second approver.
        Route::delete('{supplyChannel}', [SupplyChannelController::class, 'destroy'])
            ->middleware('permission:ad.channels.delete');

        // EP-AD-053 (BE-T05). Catalog permission is ad.channels.update ("إعادة التجهيز").
        Route::post('{supplyChannel}/retry-provisioning', [SupplyChannelController::class, 'retryProvisioning'])
            ->middleware('permission:ad.channels.update');

        // EP-AD-054 (BE-T13). `ad.channels.archive` is checked in the action, where the
        // target is known.
        Route::post('{supplyChannel}/transition', [SupplyChannelController::class, 'transition'])
            ->middleware('permission:ad.channels.suspend');

        // EP-AD-055 (BE-T12). `ad.billing.assign_plan` is what the catalog names for a limits override.
        Route::put('{supplyChannel}/limits', [SupplyChannelController::class, 'overrideLimits'])
            ->middleware('permission:ad.billing.assign_plan');

        // EP-AD-056 (BE-T11).
        Route::get('{supplyChannel}/usage', [SupplyChannelController::class, 'usage'])
            ->middleware('permission:ad.channels.view');

        // PA-03 — EP-AD-063, 065A/B, 066.
        Route::get('{supplyChannel}/users', [SupplyChannelController::class, 'users'])
            ->middleware('permission:ad.channels.view');
        Route::get('{supplyChannel}/coverage', [SupplyChannelController::class, 'coverage'])
            ->middleware('permission:ad.channels.view');
        Route::put('{supplyChannel}/coverage', [SupplyChannelController::class, 'updateCoverage'])
            ->middleware('permission:ad.channels.update');
        Route::get('{supplyChannel}/warehouses', [SupplyChannelController::class, 'warehouses'])
            ->middleware('permission:ad.channels.view');

        // PA-08 — EP-AD-067.
        Route::get('{supplyChannel}/features', [PlatformFeatureController::class, 'forChannel'])
            ->middleware('permission:ad.features.view');
    });

// PA-08 — EP-AD-110A/B, 111, 114.
Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/platform/features')
    ->group(function () {
        Route::get('/', [PlatformFeatureController::class, 'index'])
            ->middleware('permission:ad.features.view');
        Route::post('/', [PlatformFeatureController::class, 'store'])
            ->middleware('permission:ad.features.manage');
        Route::post('{key}/override', [PlatformFeatureController::class, 'override'])
            ->middleware('permission:ad.features.override');
        Route::delete('{key}/override', [PlatformFeatureController::class, 'clearOverride'])
            ->middleware('permission:ad.features.override');
    });

// PA-02 — EP-AD-100A/B/C/D.
Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/platform/plans')
    ->group(function () {
        Route::get('/', [PlatformPlanController::class, 'index'])
            ->middleware('permission:ad.billing.plans');

        Route::post('/', [PlatformPlanController::class, 'store'])
            ->middleware('permission:ad.billing.plans');

        Route::get('{plan}', [PlatformPlanController::class, 'show'])
            ->middleware('permission:ad.billing.plans');

        Route::put('{plan}', [PlatformPlanController::class, 'update'])
            ->middleware('permission:ad.billing.plans');
    });

// PA-04 — EP-AD-060, 061.
Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/platform/channel-applications')
    ->group(function () {
        Route::get('/', [ChannelApplicationController::class, 'index'])
            ->middleware('permission:ad.channels.view');

        Route::post('{channelApplication}/decide', [ChannelApplicationController::class, 'decide'])
            ->middleware('permission:ad.channels.create');
    });

// A channel manager reading and editing their own channel.
Route::middleware(['api', 'auth:channel', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/channel')
    ->group(function () {
        // `sc.settings.*` from DOC-08, replacing the interim `settings.*` names that were
        // seeded on every guard at once. These two are the only exact one-to-one swaps in
        // the batch; DOC-08 defines a third, `sc.settings.audit`, that no route uses yet.
        Route::get('/', [ChannelSettingsController::class, 'show'])->middleware('can:sc.settings.view');
        Route::put('/', [ChannelSettingsController::class, 'update'])->middleware('can:sc.settings.update');
        Route::get('warehouses', [ChannelSettingsController::class, 'warehouses'])->middleware('permission:sc.inventory.view');
    });
