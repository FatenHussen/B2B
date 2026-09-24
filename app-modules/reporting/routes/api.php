<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Reporting\Presentation\Http\Controllers\ChannelDashboardController;
use Modules\Reporting\Presentation\Http\Controllers\PlatformDashboardController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel')
    ->group(function (): void {
        Route::get('dashboard', [ChannelDashboardController::class, 'dashboard'])->middleware('permission:sc.dashboard.view');
        Route::get('reports/margins', [ChannelDashboardController::class, 'margins'])->middleware('permission:sc.reports.margins');
        Route::get('reports/{type}', [ChannelDashboardController::class, 'report'])->middleware('permission:sc.reports.view');
        Route::post('reports/{type}/export', [ChannelDashboardController::class, 'export'])->middleware('permission:sc.reports.export');
        Route::get('jobs/{id}', [ChannelDashboardController::class, 'job'])->middleware('permission:sc.reports.view');
    });

Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', SubstituteBindings::class])
    ->prefix('api/v1/platform')
    ->group(function (): void {
        Route::get('dashboard', [PlatformDashboardController::class, 'dashboard'])->middleware('permission:ad.dashboard.view');
        Route::get('dashboard/alerts', [PlatformDashboardController::class, 'alerts'])->middleware('permission:ad.dashboard.view');
        Route::get('dashboard/cards/{key}', [PlatformDashboardController::class, 'card'])->middleware('permission:ad.dashboard.view');
        Route::get('dashboard/charts/{key}', [PlatformDashboardController::class, 'chart'])->middleware('permission:ad.dashboard.view');
        Route::get('reports/{type}', [PlatformDashboardController::class, 'report'])->middleware('permission:ad.reports.view');
        Route::post('reports/{type}/export', [PlatformDashboardController::class, 'exportReport'])->middleware('permission:ad.reports.export');
        Route::get('exports/{jobId}', [PlatformDashboardController::class, 'exportStatus'])->middleware('permission:ad.reports.view');
        Route::post('channels/{id}/export', [PlatformDashboardController::class, 'exportChannel'])->middleware('permission:ad.channels.export');
        Route::post('channels/export', [PlatformDashboardController::class, 'exportChannels'])->middleware('permission:ad.channels.export');
    });
