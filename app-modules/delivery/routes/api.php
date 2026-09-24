<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Delivery\Presentation\Http\Controllers\DeliveryController;

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app/rep')
    ->group(function (): void {
        // permission before app.kind so a cross-kind caller gets the permission key (BF-09).
        Route::get('deliveries', [DeliveryController::class, 'index'])
            ->middleware(['permission:rp.delivery.deliver', 'app.kind:rep']);
        Route::get('deliveries/{id}', [DeliveryController::class, 'show'])
            ->middleware(['permission:rp.delivery.deliver', 'app.kind:rep']);
        Route::patch('deliveries/{id}/lines/{lineId}', [DeliveryController::class, 'patchLine'])
            ->middleware(['permission:rp.delivery.deliver', 'app.kind:rep']);
        Route::post('deliveries/{id}/complete', [DeliveryController::class, 'complete'])
            ->middleware(['permission:rp.delivery.deliver', 'app.kind:rep']);
        Route::post('deliveries/{id}/postpone', [DeliveryController::class, 'postpone'])
            ->middleware(['permission:rp.delivery.postpone', 'app.kind:rep']);
        Route::post('deliveries/{id}/fail', [DeliveryController::class, 'fail'])
            ->middleware(['permission:rp.delivery.deliver', 'app.kind:rep']);
        Route::post('locations/ping', [DeliveryController::class, 'ping'])
            ->middleware('app.kind:rep');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app/retailer')
    ->group(function (): void {
        Route::get('receipts/{subOrderId}', [DeliveryController::class, 'receipt'])
            ->middleware(['permission:rt.receive.confirm', 'app.kind:retailer']);
        Route::patch('receipts/{id}/lines/{lineId}', [DeliveryController::class, 'patchReceiptLine'])
            ->middleware(['permission:rt.receive.confirm', 'app.kind:retailer']);
        Route::post('receipts/{id}/confirm', [DeliveryController::class, 'confirmReceipt'])
            ->middleware(['permission:rt.receive.confirm', 'app.kind:retailer']);
        Route::post('reps/{id}/rate', [DeliveryController::class, 'rate'])
            ->middleware('app.kind:retailer');
    });
