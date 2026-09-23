<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Notification\Presentation\Http\Controllers\AppNotificationController;
use Modules\Notification\Presentation\Http\Controllers\ChannelNotificationController;
use Modules\Notification\Presentation\Http\Controllers\PlatformNotificationController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel/notifications')
    ->group(function (): void {
        Route::post('/', [ChannelNotificationController::class, 'send'])->middleware('permission:sc.notify.send');
        Route::get('templates', [ChannelNotificationController::class, 'templates'])->middleware('permission:sc.notify.templates');
        Route::put('templates', [ChannelNotificationController::class, 'upsertTemplate'])->middleware('permission:sc.notify.templates');
        Route::get('log', [ChannelNotificationController::class, 'log'])->middleware('permission:sc.notify.view');
    });

Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', SubstituteBindings::class])
    ->prefix('api/v1/platform')
    ->group(function (): void {
        Route::post('notifications/broadcast', [PlatformNotificationController::class, 'broadcast'])->middleware('permission:ad.notify.broadcast');
        Route::post('notifications', [PlatformNotificationController::class, 'store'])->middleware('permission:ad.notify.send');
        Route::post('notifications/preview', [PlatformNotificationController::class, 'preview'])->middleware('permission:ad.notify.send');
        Route::get('notifications/templates', [PlatformNotificationController::class, 'templates'])->middleware('permission:ad.notify.view');
        Route::put('notifications/templates', [PlatformNotificationController::class, 'updateTemplates'])->middleware('permission:ad.notify.send');
        Route::get('notifications/log', [PlatformNotificationController::class, 'log'])->middleware('permission:ad.notify.view');
        Route::get('notifications/campaigns', [PlatformNotificationController::class, 'campaigns'])->middleware('permission:ad.notify.view');
        Route::get('notifications/campaigns/{id}', [PlatformNotificationController::class, 'showCampaign'])->middleware('permission:ad.notify.view');
        Route::post('channels/notify-managers', [PlatformNotificationController::class, 'notifyManagers'])->middleware('permission:ad.notify.send');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app')
    ->group(function (): void {
        Route::get('notifications', [AppNotificationController::class, 'index']);
        Route::post('notifications/read-all', [AppNotificationController::class, 'readAll']);
        Route::delete('notifications', [AppNotificationController::class, 'clear']);
        Route::post('devices/push-token', [AppNotificationController::class, 'pushToken']);
    });
