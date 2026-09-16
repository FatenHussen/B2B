<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Finance\Presentation\Http\Controllers\ChannelFinanceController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel')
    ->group(function (): void {
        Route::get('invoices', [ChannelFinanceController::class, 'invoices'])->middleware('permission:sc.finance.view');
        Route::post('invoices/{id}/credit-note', [ChannelFinanceController::class, 'creditNote'])->middleware('permission:sc.finance.credit_note');
        Route::post('invoices/{id}/void', [ChannelFinanceController::class, 'voidInvoice'])->middleware('permission:sc.finance.void_invoice');
        Route::post('payments', [ChannelFinanceController::class, 'payment'])->middleware('permission:sc.finance.payment');
        Route::post('reps/{id}/settle', [ChannelFinanceController::class, 'settle'])->middleware('permission:sc.reps.settle');
        Route::get('finance/aging', [ChannelFinanceController::class, 'aging'])->middleware('permission:sc.finance.aging');
        Route::put('retailers/{id}/credit', [ChannelFinanceController::class, 'credit'])->middleware('permission:sc.retailers.credit');
    });
