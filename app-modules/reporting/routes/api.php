<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Reporting\Presentation\Http\Controllers\ChannelDashboardController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel')
    ->group(function (): void {
        Route::get('dashboard', [ChannelDashboardController::class, 'dashboard'])->middleware('permission:sc.dashboard.view');
        Route::get('reports/margins', [ChannelDashboardController::class, 'margins'])->middleware('permission:sc.reports.margins');
        Route::get('reports/{type}', [ChannelDashboardController::class, 'report'])->middleware('permission:sc.reports.view');
        Route::post('reports/{type}/export', [ChannelDashboardController::class, 'export'])->middleware('permission:sc.reports.export');
    });
