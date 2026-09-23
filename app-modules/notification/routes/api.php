<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Notification\Presentation\Http\Controllers\AppNotificationController;
use Modules\Notification\Presentation\Http\Controllers\ChannelNotificationController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel/notifications')
    ->group(function (): void {
        Route::post('/', [ChannelNotificationController::class, 'send'])->middleware('permission:sc.notify.send');
        Route::get('templates', [ChannelNotificationController::class, 'templates'])->middleware('permission:sc.notify.templates');
        Route::put('templates', [ChannelNotificationController::class, 'upsertTemplate'])->middleware('permission:sc.notify.templates');
        Route::get('log', [ChannelNotificationController::class, 'log'])->middleware('permission:sc.notify.view');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app')
    ->group(function (): void {
        Route::get('notifications', [AppNotificationController::class, 'index']);
        Route::post('notifications/read-all', [AppNotificationController::class, 'readAll']);
        Route::delete('notifications', [AppNotificationController::class, 'clear']);
        Route::post('devices/push-token', [AppNotificationController::class, 'pushToken']);
    });
