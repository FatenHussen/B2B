<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Inventory\Presentation\Http\Controllers\ChannelInventoryController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel/inventory')
    ->group(function (): void {
        Route::get('levels', [ChannelInventoryController::class, 'levels'])->middleware('permission:sc.inventory.view');
        Route::post('adjust', [ChannelInventoryController::class, 'adjust'])->middleware('permission:sc.inventory.adjust');
        Route::get('transfers', [ChannelInventoryController::class, 'transfers'])->middleware('permission:sc.inventory.transfer');
        Route::post('transfers', [ChannelInventoryController::class, 'transfer'])->middleware('permission:sc.inventory.transfer');
        Route::get('movements', [ChannelInventoryController::class, 'movements'])->middleware('permission:sc.inventory.view');
        Route::put('reorder-points', [ChannelInventoryController::class, 'reorderPoints'])->middleware('permission:sc.inventory.reorder');
    });
