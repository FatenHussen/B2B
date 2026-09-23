<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\PlatformBilling\Presentation\Http\Controllers\PlatformBillingController;

Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', SubstituteBindings::class])
    ->prefix('api/v1/platform')
    ->group(function (): void {
        Route::get('subscriptions', [PlatformBillingController::class, 'subscriptions'])
            ->middleware('permission:ad.billing.view');
        Route::post('channels/{id}/plan', [PlatformBillingController::class, 'assignPlan'])
            ->middleware('permission:ad.billing.assign_plan');
        Route::post('channels/bulk-plan/preview', [PlatformBillingController::class, 'bulkPreview'])
            ->middleware('permission:ad.billing.assign_plan');
        Route::post('channels/bulk-plan', [PlatformBillingController::class, 'bulkApply'])
            ->middleware('permission:ad.billing.assign_plan');
        Route::get('platform-invoices', [PlatformBillingController::class, 'invoices'])
            ->middleware('permission:ad.billing.view');
        Route::post('platform-invoices/{id}/waive', [PlatformBillingController::class, 'waive'])
            ->middleware('permission:ad.billing.waive');
        Route::post('platform-invoices/{id}/credit-note', [PlatformBillingController::class, 'creditNote'])
            ->middleware('permission:ad.billing.invoice');
        Route::get('dunning', [PlatformBillingController::class, 'dunning'])
            ->middleware('permission:ad.billing.dunning');
        Route::get('billing/revenue', [PlatformBillingController::class, 'revenue'])
            ->middleware('permission:ad.billing.view');
    });
