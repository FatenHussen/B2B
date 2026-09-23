<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\HealthController;
use Modules\Core\Presentation\Http\Controllers\PlatformSettingsController;

Route::middleware('api')->prefix('api/v1')->group(function () {
    Route::get('health', HealthController::class);
});

Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', SubstituteBindings::class])
    ->prefix('api/v1/platform/settings')
    ->group(function (): void {
        Route::get('profile', [PlatformSettingsController::class, 'profile'])->middleware('permission:ad.settings.view');
        Route::put('profile', [PlatformSettingsController::class, 'updateProfile'])->middleware('permission:ad.settings.update');
        Route::get('security', [PlatformSettingsController::class, 'security'])->middleware('permission:ad.settings.security');
        Route::put('security', [PlatformSettingsController::class, 'updateSecurity'])->middleware('permission:ad.settings.security');
        Route::get('backup', [PlatformSettingsController::class, 'backup'])->middleware('permission:ad.settings.view');
        Route::put('backup', [PlatformSettingsController::class, 'updateBackup'])->middleware('permission:ad.settings.update');
        Route::get('channel-defaults', [PlatformSettingsController::class, 'channelDefaults'])->middleware('permission:ad.settings.view');
        Route::put('channel-defaults', [PlatformSettingsController::class, 'updateChannelDefaults'])->middleware('permission:ad.settings.update');
    });
