<?php

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Support\Presentation\Http\Controllers\PlatformSupportController;

Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', SubstituteBindings::class])
    ->prefix('api/v1/platform/support')
    ->group(function (): void {
        Route::get('search', [PlatformSupportController::class, 'search'])->middleware('permission:ad.support.search');
        Route::get('users/{type}/{id}', [PlatformSupportController::class, 'showUser'])->middleware('permission:ad.support.view_profile');
        Route::post('impersonate', [PlatformSupportController::class, 'impersonate'])->middleware('permission:ad.support.impersonate');
        Route::post('users/{id}/resend-otp', [PlatformSupportController::class, 'resendOtp'])->middleware('permission:ad.support.resend_otp');
        Route::post('users/{id}/revoke-sessions', [PlatformSupportController::class, 'revokeSessions'])->middleware('permission:ad.support.revoke_sessions');
        Route::get('tickets', [PlatformSupportController::class, 'tickets'])->middleware('permission:ad.support.tickets');
        Route::post('tickets', [PlatformSupportController::class, 'storeTicket'])->middleware('permission:ad.support.tickets');
        Route::patch('tickets/{id}', [PlatformSupportController::class, 'updateTicket'])->middleware('permission:ad.support.tickets');
        Route::post('users/{id}/disable', [PlatformSupportController::class, 'disableUser'])->middleware('permission:ad.support.disable_user');
        Route::post('users/{id}/reset-device', [PlatformSupportController::class, 'resetDevice'])->middleware('permission:ad.support.revoke_sessions');
    });
