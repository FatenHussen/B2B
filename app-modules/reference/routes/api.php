<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Reference\Presentation\Http\Controllers\ChannelZoneController;
use Modules\Reference\Presentation\Http\Controllers\GovernorateController;
use Modules\Reference\Presentation\Http\Controllers\ZoneController;

/*
 * Shared reference data. No prefix here names a guard, so the four are listed
 * explicitly: every authenticated client may read governorates and zones, and
 * `can:settings.*` decides who may write. This replaces `auth:sanctum`, which named a
 * guard config/auth.php never defines.
 *
 * The public snapshot EP-PB-001 (`GET /api/v1/public/refs`) is deliberately absent: it
 * is a contract path and belongs to the separate route-migration ticket.
 */
Route::middleware(['api', 'auth:platform,channel,warehouse,app', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1')
    ->group(function () {
        Route::get('governorates', [GovernorateController::class, 'index']);
        Route::get('governorates/{governorate}', [GovernorateController::class, 'show']);
        Route::post('governorates', [GovernorateController::class, 'store'])->middleware('can:settings.create');
        Route::put('governorates/{governorate}', [GovernorateController::class, 'update'])->middleware('can:settings.update');
        Route::delete('governorates/{governorate}', [GovernorateController::class, 'destroy'])->middleware('can:settings.delete');

        Route::get('zones', [ZoneController::class, 'index']);
        Route::get('zones/{zone}', [ZoneController::class, 'show']);
        Route::post('zones', [ZoneController::class, 'store'])->middleware('can:settings.create');
        Route::put('zones/{zone}', [ZoneController::class, 'update'])->middleware('can:settings.update');
        Route::delete('zones/{zone}', [ZoneController::class, 'destroy'])->middleware('can:settings.delete');
    });

/*
 * Channel coverage rows. The prefix names the guard.
 */
Route::middleware(['api', 'auth:channel', 'tenant', SubstituteBindings::class])
    ->prefix('api/v1/channel')
    ->group(function () {
        Route::middleware('can:settings.view')->group(function () {
            Route::get('zones', [ChannelZoneController::class, 'index']);
            Route::post('zones', [ChannelZoneController::class, 'store'])->middleware('can:settings.update');
            Route::delete('zones/{channelZone}', [ChannelZoneController::class, 'destroy'])->middleware('can:settings.delete');
        });
    });
