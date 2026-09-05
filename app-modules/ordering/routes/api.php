<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Ordering\Presentation\Http\Controllers\ChannelSubOrderController;
use Modules\Ordering\Presentation\Http\Controllers\RepOrderingController;
use Modules\Ordering\Presentation\Http\Controllers\RetailerOrderingController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel/sub-orders')
    ->group(function (): void {
        Route::get('/', [ChannelSubOrderController::class, 'index'])->middleware('permission:sc.orders.view');
        Route::post('bulk-confirm', [ChannelSubOrderController::class, 'bulkConfirm'])->middleware('permission:sc.orders.confirm');
        Route::post('assign', [ChannelSubOrderController::class, 'assign'])->middleware('permission:sc.orders.assign');
        Route::get('{id}', [ChannelSubOrderController::class, 'show'])->middleware('permission:sc.orders.view');
        Route::post('{id}/confirm', [ChannelSubOrderController::class, 'confirm'])->middleware('permission:sc.orders.confirm');
        Route::post('{id}/reject', [ChannelSubOrderController::class, 'reject'])->middleware('permission:sc.orders.reject');
        Route::patch('{id}/lines', [ChannelSubOrderController::class, 'editLines'])->middleware('permission:sc.orders.edit_lines');
        Route::post('{id}/reassign', [ChannelSubOrderController::class, 'reassign'])->middleware('permission:sc.orders.reassign');
        Route::post('{id}/schedule', [ChannelSubOrderController::class, 'schedule'])->middleware('permission:sc.orders.schedule');
        Route::post('{id}/cancel', [ChannelSubOrderController::class, 'cancel'])->middleware('permission:sc.orders.cancel');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:retailer'])
    ->prefix('api/v1/app/retailer')
    ->group(function (): void {
        Route::get('cart', [RetailerOrderingController::class, 'cart']);
        Route::post('cart/lines', [RetailerOrderingController::class, 'addLine']);
        Route::patch('cart/lines/{id}', [RetailerOrderingController::class, 'updateLine']);
        Route::delete('cart/lines/{id}', [RetailerOrderingController::class, 'deleteLine']);
        Route::patch('cart/sections/{ref}', [RetailerOrderingController::class, 'updateSection']);
        Route::post('cart/submit', [RetailerOrderingController::class, 'submit']);
        Route::get('orders', [RetailerOrderingController::class, 'orders']);
        Route::get('orders/{id}', [RetailerOrderingController::class, 'show']);
        Route::post('orders/{id}/cancel', [RetailerOrderingController::class, 'cancel']);
        Route::post('orders/{id}/reorder', [RetailerOrderingController::class, 'reorder']);
        Route::get('orders/{id}/tracking', [RetailerOrderingController::class, 'tracking']);
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app', 'app.kind:rep'])
    ->prefix('api/v1/app/rep')
    ->group(function (): void {
        Route::post('cart/lines', [RepOrderingController::class, 'addLine']);
        Route::get('cart', [RepOrderingController::class, 'cart']);
        Route::post('cart/sections/{retailer_id}/submit', [RepOrderingController::class, 'submit']);
        Route::get('assignments', [RepOrderingController::class, 'assignments']);
        Route::post('assignments/{id}/accept', [RepOrderingController::class, 'accept']);
        Route::post('assignments/{id}/reject', [RepOrderingController::class, 'reject']);
        Route::get('scheduled-orders', [RepOrderingController::class, 'scheduled']);
    });
