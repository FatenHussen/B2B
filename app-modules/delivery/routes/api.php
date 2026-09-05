<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Delivery\Presentation\Http\Controllers\DeliveryController;

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:rep'])
    ->prefix('api/v1/app/rep')
    ->group(function (): void {
        Route::get('deliveries', [DeliveryController::class, 'index']);
        Route::get('deliveries/{id}', [DeliveryController::class, 'show']);
        Route::patch('deliveries/{id}/lines/{lineId}', [DeliveryController::class, 'patchLine']);
        Route::post('deliveries/{id}/complete', [DeliveryController::class, 'complete']);
        Route::post('deliveries/{id}/postpone', [DeliveryController::class, 'postpone']);
        Route::post('deliveries/{id}/fail', [DeliveryController::class, 'fail']);
        Route::post('locations/ping', [DeliveryController::class, 'ping']);
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:retailer'])
    ->prefix('api/v1/app/retailer')
    ->group(function (): void {
        Route::get('receipts/{subOrderId}', [DeliveryController::class, 'receipt']);
        Route::patch('receipts/{id}/lines/{lineId}', [DeliveryController::class, 'patchReceiptLine']);
        Route::post('receipts/{id}/confirm', [DeliveryController::class, 'confirmReceipt']);
        Route::post('reps/{id}/rate', [DeliveryController::class, 'rate']);
    });
