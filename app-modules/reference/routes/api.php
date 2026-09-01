<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Reference\Presentation\Http\Controllers\ChannelZoneController;
use Modules\Reference\Presentation\Http\Controllers\GovernorateController;
use Modules\Reference\Presentation\Http\Controllers\ZoneController;

Route::middleware(['api', 'auth:sanctum', 'tenant', SubstituteBindings::class])->prefix('api/v1')->group(function () {
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

    Route::middleware('can:settings.view')->prefix('channel')->group(function () {
        Route::get('zones', [ChannelZoneController::class, 'index']);
        Route::post('zones', [ChannelZoneController::class, 'store'])->middleware('can:settings.update');
        Route::delete('zones/{channelZone}', [ChannelZoneController::class, 'destroy'])->middleware('can:settings.delete');
    });
});
