<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Presentation\Http\Controllers\ChannelSettingsController;
use Modules\Tenancy\Presentation\Http\Controllers\SupplyChannelController;

/*
 * The path prefix names the guard, not the module. Tenancy serves two audiences, so it
 * publishes two groups instead of one `auth:sanctum` group that served neither.
 */

// Platform back office.
Route::middleware(['api', 'auth:platform', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/admin/channels')
    ->group(function () {
        Route::middleware('role:platform_admin')->group(function () {
            Route::get('/', [SupplyChannelController::class, 'index']);
            Route::post('/', [SupplyChannelController::class, 'store']);
            Route::get('{supplyChannel}', [SupplyChannelController::class, 'show']);
            Route::put('{supplyChannel}', [SupplyChannelController::class, 'update']);
            Route::delete('{supplyChannel}', [SupplyChannelController::class, 'destroy']);
        });

        // EP-AD-054 (BE-T13). On `/admin/channels` beside its five siblings, and MOVING
        // to `/platform/channels/{id}/transition` with them: one route on the catalog
        // prefix while five sit on the temporary one would split the single constant
        // the frontend keeps them behind.
        //
        // Gated on the permission the catalog names, not the role the siblings use, so
        // the 403 carries `error.permission`. `ad.channels.archive` is checked in the
        // action, where the target is known.
        Route::post('{supplyChannel}/transition', [SupplyChannelController::class, 'transition'])
            ->middleware('permission:ad.channels.suspend');
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
    });
