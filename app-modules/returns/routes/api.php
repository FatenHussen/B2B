<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Returns\Presentation\Http\Controllers\ReturnsController;

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:retailer'])
    ->prefix('api/v1/app/retailer')
    ->group(function (): void {
        Route::get('return-requests', [ReturnsController::class, 'retailerIndex']);
        Route::post('return-requests', [ReturnsController::class, 'createForRetailer']);
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:rep'])
    ->prefix('api/v1/app/rep')
    ->group(function (): void {
        Route::post('return-requests', [ReturnsController::class, 'createForRep']);
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel/return-requests')
    ->group(function (): void {
        Route::get('/', [ReturnsController::class, 'channelIndex'])->middleware('permission:sc.returns.view');
        Route::post('{id}/decide', [ReturnsController::class, 'decide'])->middleware('permission:sc.returns.decide');
        Route::post('{id}/escalate', [ReturnsController::class, 'escalate'])->middleware('permission:sc.returns.decide');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:warehouse', 'guard.tokenable:warehouse', 'tenant'])
    ->prefix('api/v1/warehouse')
    ->group(function (): void {
        Route::post('returns/{id}/sort', [ReturnsController::class, 'sort'])->middleware('permission:wh.returns.sort');
    });
