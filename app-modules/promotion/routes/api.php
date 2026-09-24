<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Promotion\Presentation\Http\Controllers\OfferController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel')
    ->group(function (): void {
        Route::get('offers', [OfferController::class, 'index'])->middleware('permission:sc.offers.view');
        Route::post('offers', [OfferController::class, 'store'])->middleware('permission:sc.offers.create');
        Route::get('offers/{id}', [OfferController::class, 'show'])->middleware('permission:sc.offers.view');
        Route::put('offers/{id}', [OfferController::class, 'update'])->middleware('permission:sc.offers.create');
        Route::patch('offers/{id}/stop', [OfferController::class, 'stop'])->middleware('permission:sc.offers.stop');
        Route::patch('offers/{id}/activate', [OfferController::class, 'activate'])->middleware('permission:sc.offers.create');
        Route::get('offers/{id}/performance', [OfferController::class, 'performance'])->middleware('permission:sc.offers.view');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app')
    ->group(function (): void {
        Route::get('offers', [OfferController::class, 'appIndex']);
        Route::get('offers/{id}', [OfferController::class, 'appShow']);
    });
