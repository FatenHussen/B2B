<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Notification\Presentation\Http\Controllers\ChannelNotificationController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel/notifications')
    ->group(function (): void {
        Route::post('/', [ChannelNotificationController::class, 'send'])->middleware('permission:sc.notify.send');
        Route::get('templates', [ChannelNotificationController::class, 'templates'])->middleware('permission:sc.notify.templates');
        Route::put('templates', [ChannelNotificationController::class, 'upsertTemplate'])->middleware('permission:sc.notify.templates');
        Route::get('log', [ChannelNotificationController::class, 'log'])->middleware('permission:sc.notify.view');
    });
