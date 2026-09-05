<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Fulfillment\Presentation\Http\Controllers\WarehouseController;

Route::middleware(['api', SubstituteBindings::class, 'auth:warehouse', 'guard.tokenable:warehouse', 'tenant'])
    ->prefix('api/v1/warehouse')
    ->group(function (): void {
        Route::get('queues', [WarehouseController::class, 'queues'])->middleware('permission:wh.queue.view');
        Route::get('picking-lists/{id}', [WarehouseController::class, 'picking'])->middleware('permission:wh.picking.execute');
        Route::post('picking-lists/{id}/scan', [WarehouseController::class, 'scan'])->middleware('permission:wh.picking.execute');
        Route::post('picking-lists/{id}/lines/{lineId}/manual', [WarehouseController::class, 'manual'])->middleware('permission:wh.picking.execute');
        Route::post('picking-lists/{id}/shortage', [WarehouseController::class, 'shortage'])->middleware('permission:wh.picking.shortage');
        Route::post('picking-lists/{id}/complete', [WarehouseController::class, 'completePick'])->middleware('permission:wh.picking.execute');
        Route::post('packing/{id}/verify', [WarehouseController::class, 'verifyPack'])->middleware('permission:wh.packing.execute');
        Route::post('packing/{id}/complete', [WarehouseController::class, 'completePack'])->middleware('permission:wh.packing.execute');
        Route::get('handovers/pending', [WarehouseController::class, 'pendingHandovers'])->middleware('permission:wh.handover.execute');
        Route::post('handovers', [WarehouseController::class, 'createHandover'])->middleware('permission:wh.handover.execute');
        Route::post('handovers/{id}/return-trip', [WarehouseController::class, 'returnTrip'])->middleware('permission:wh.handover.return_trip');
        Route::post('receiving', [WarehouseController::class, 'receiving'])->middleware('permission:wh.receiving.execute');
        Route::post('receiving/{id}/qc', [WarehouseController::class, 'qc'])->middleware('permission:wh.receiving.qc');
        Route::post('stocktakes', [WarehouseController::class, 'startStocktake'])->middleware('permission:wh.stocktake.execute');
        Route::post('stocktakes/{id}/lines', [WarehouseController::class, 'recordCount'])->middleware('permission:wh.stocktake.execute');
        Route::post('stocktakes/{id}/submit', [WarehouseController::class, 'submitStocktake'])->middleware('permission:wh.stocktake.execute');
        Route::post('stocktakes/{id}/approve', [WarehouseController::class, 'approveStocktake'])->middleware('permission:wh.stocktake.approve');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:rep'])
    ->prefix('api/v1/app/rep/warehouse-receipts')
    ->group(function (): void {
        Route::get('/', [WarehouseController::class, 'receipts']);
        Route::post('{handoverId}/confirm', [WarehouseController::class, 'confirm']);
    });
