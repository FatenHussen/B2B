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
    });

// A channel manager reading and editing their own channel.
Route::middleware(['api', 'auth:channel', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/channel')
    ->group(function () {
        Route::get('/', [ChannelSettingsController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [ChannelSettingsController::class, 'update'])->middleware('can:settings.update');
    });
