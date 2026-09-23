<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Sync\Presentation\Http\Controllers\AppSyncController;

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app/sync')
    ->group(function (): void {
        Route::get('pull', [AppSyncController::class, 'pull']);
        Route::post('push', [AppSyncController::class, 'push']);
        Route::get('status', [AppSyncController::class, 'status']);
        Route::post('resolve-conflict', [AppSyncController::class, 'resolve']);
    });
