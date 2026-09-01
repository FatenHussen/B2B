<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Tenancy\Presentation\Http\Controllers\ChannelSettingsController;
use Modules\Tenancy\Presentation\Http\Controllers\SupplyChannelController;

Route::middleware(['api', 'auth:sanctum', 'tenant', SubstituteBindings::class])->prefix('api/v1')->group(function () {
    Route::middleware('role:platform_admin')->prefix('admin/channels')->group(function () {
        Route::get('/', [SupplyChannelController::class, 'index']);
        Route::post('/', [SupplyChannelController::class, 'store']);
        Route::get('{supplyChannel}', [SupplyChannelController::class, 'show']);
        Route::put('{supplyChannel}', [SupplyChannelController::class, 'update']);
        Route::delete('{supplyChannel}', [SupplyChannelController::class, 'destroy']);
    });

    Route::prefix('channel')->group(function () {
        Route::get('/', [ChannelSettingsController::class, 'show'])->middleware('can:settings.view');
        Route::put('/', [ChannelSettingsController::class, 'update'])->middleware('can:settings.update');
    });
});
