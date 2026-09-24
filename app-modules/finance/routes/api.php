<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Finance\Presentation\Http\Controllers\ChannelFinanceController;
use Modules\Finance\Presentation\Http\Controllers\RepFinanceController;
use Modules\Finance\Presentation\Http\Controllers\RetailerFinanceController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel')
    ->group(function (): void {
        Route::get('invoices', [ChannelFinanceController::class, 'invoices'])->middleware('permission:sc.finance.view');
        Route::get('invoices/{id}', [ChannelFinanceController::class, 'showInvoice'])->middleware('permission:sc.finance.view');
        Route::post('invoices/{id}/credit-note', [ChannelFinanceController::class, 'creditNote'])->middleware('permission:sc.finance.credit_note');
        Route::post('invoices/{id}/void', [ChannelFinanceController::class, 'voidInvoice'])->middleware('permission:sc.finance.void_invoice');
        Route::post('payments', [ChannelFinanceController::class, 'payment'])->middleware('permission:sc.finance.payment');
        Route::post('reps/{id}/settle', [ChannelFinanceController::class, 'settle'])->middleware('permission:sc.reps.settle');
        Route::get('reps/{id}/wallet', [ChannelFinanceController::class, 'wallet'])->middleware('permission:sc.reps.wallet');
        Route::get('finance/aging', [ChannelFinanceController::class, 'aging'])->middleware('permission:sc.finance.aging');
        Route::put('retailers/{id}/credit', [ChannelFinanceController::class, 'credit'])->middleware('permission:sc.retailers.credit');
        Route::get('credit-approvals', [ChannelFinanceController::class, 'creditApprovals'])->middleware('permission:sc.retailers.credit');
        Route::post('credit-approvals/{id}/decide', [ChannelFinanceController::class, 'decideCreditApproval'])->middleware('permission:sc.retailers.credit');
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1')
    ->group(function (): void {
        // permission before app.kind (BF-09/BF-10).
        Route::post('app/receipts/reserve', [RepFinanceController::class, 'reserve'])
            ->middleware(['permission:rp.payment.collect', 'app.kind:rep']);
        Route::post('app/rep/payments', [RepFinanceController::class, 'collect'])
            ->middleware(['permission:rp.payment.collect', 'app.kind:rep']);
        Route::get('app/rep/wallet', [RepFinanceController::class, 'wallet'])
            ->middleware(['permission:rp.wallet.view', 'app.kind:rep']);
        Route::post('app/rep/wallet/withdrawals', [RepFinanceController::class, 'withdraw'])
            ->middleware(['permission:rp.payment.withdraw', 'app.kind:rep']);
        Route::get('app/rep/wallet/withdrawals', [RepFinanceController::class, 'withdrawals'])
            ->middleware(['permission:rp.wallet.view', 'app.kind:rep']);
        Route::get('app/rep/receivables', [RepFinanceController::class, 'receivables'])
            ->middleware(['permission:rp.wallet.view', 'app.kind:rep']);
    });

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app/retailer')
    ->group(function (): void {
        Route::post('payments', [RetailerFinanceController::class, 'payment'])
            ->middleware(['permission:rt.payment.record', 'app.kind:retailer']);
        Route::get('account/summary', [RetailerFinanceController::class, 'summary'])
            ->middleware('app.kind:retailer');
        Route::get('account/statement', [RetailerFinanceController::class, 'statement'])
            ->middleware(['permission:rt.account.statement', 'app.kind:retailer']);
        Route::post('account/statement/export', [RetailerFinanceController::class, 'exportStatement'])
            ->middleware(['permission:rt.account.statement', 'app.kind:retailer']);
        Route::get('debts', [RetailerFinanceController::class, 'debts'])
            ->middleware('app.kind:retailer');
    });
