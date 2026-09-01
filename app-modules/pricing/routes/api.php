<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Pricing\Presentation\Http\Controllers\PricingController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel')
    ->group(function (): void {
        Route::get('price-lists', [PricingController::class, 'lists'])->middleware('permission:sc.pricing.view');
        Route::post('price-lists', [PricingController::class, 'storeList'])->middleware('permission:sc.pricing.update');
        Route::put('products/{id}/pricing', [PricingController::class, 'replaceProduct'])->middleware('permission:sc.pricing.update');
        Route::post('price-lists/{id}/schedule', [PricingController::class, 'schedule'])->middleware('permission:sc.pricing.schedule');
        Route::post('pricing/bulk-update', [PricingController::class, 'bulk'])->middleware('permission:sc.pricing.update');
        Route::get('pricing/change-log', [PricingController::class, 'changeLog'])->middleware('permission:sc.pricing.view');
        Route::put('reps/{id}/discount-cap', [PricingController::class, 'discountCap'])->middleware('permission:sc.reps.update');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app')
    ->group(function (): void {
        Route::post('pricing/quote', [PricingController::class, 'quote']);
    });
