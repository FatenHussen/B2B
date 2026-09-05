<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Modules\Identity\Presentation\Http\Controllers\AppAuthController;
use Modules\Identity\Presentation\Http\Controllers\ChannelAuthController;
use Modules\Identity\Presentation\Http\Controllers\PlatformAuthController;
use Modules\Identity\Presentation\Http\Controllers\PublicAuthController;
use Modules\Identity\Presentation\Http\Controllers\RepFieldController;
use Modules\Identity\Presentation\Http\Controllers\WarehouseAuthController;
use Modules\Identity\Presentation\Http\Middleware\RequirePasswordConfirmation;

Route::middleware(['api', SubstituteBindings::class])->prefix('api/v1')->group(function (): void {
    Route::prefix('public/auth')->group(function (): void {
        Route::post('request-otp', [PublicAuthController::class, 'requestOtp']);
        Route::post('verify-otp', [PublicAuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [PublicAuthController::class, 'resendOtp']);
    });

    Route::prefix('platform/auth')->group(function (): void {
        Route::post('login', [PlatformAuthController::class, 'login']);
        Route::post('2fa/verify', [PlatformAuthController::class, 'verifyTwoFactor']);

        Route::middleware(['auth:platform', 'guard.tokenable:platform'])->group(function (): void {
            Route::post('logout', [PlatformAuthController::class, 'logout']);
            Route::get('me', [PlatformAuthController::class, 'me']);
            Route::post('confirm-password', [PlatformAuthController::class, 'confirmPassword']);
            Route::get('sessions', [PlatformAuthController::class, 'sessions']);
            Route::delete('sessions/{id}', [PlatformAuthController::class, 'revokeSession']);
        });
    });

    Route::middleware(['auth:platform', 'guard.tokenable:platform'])->prefix('platform/me')->group(function (): void {
        Route::get('/', [PlatformAuthController::class, 'me']);
        Route::put('/', [PlatformAuthController::class, 'updateProfile']);
        Route::put('password', [PlatformAuthController::class, 'changePassword'])->middleware(RequirePasswordConfirmation::class);
        Route::post('2fa/enable', [PlatformAuthController::class, 'enableTwoFactor']);
        Route::post('2fa/confirm', [PlatformAuthController::class, 'confirmTwoFactor']);
        Route::get('2fa/recovery-codes', [PlatformAuthController::class, 'recoveryCodes']);
        Route::get('api-tokens', [PlatformAuthController::class, 'apiTokens']);
        Route::post('api-tokens', [PlatformAuthController::class, 'createApiToken']);
        Route::delete('api-tokens/{id}', [PlatformAuthController::class, 'deleteApiToken']);
    });

    Route::prefix('channel/auth')->group(function (): void {
        Route::post('request-otp', [ChannelAuthController::class, 'requestOtp']);
        Route::post('verify-otp', [ChannelAuthController::class, 'verifyOtp']);
    });

    Route::post('warehouse/auth/device-login', [WarehouseAuthController::class, 'deviceLogin']);

    Route::middleware(['auth:app', 'guard.tokenable:app', CheckForAnyAbility::class.':registration'])->group(function (): void {
        Route::post('app/retailer/register', [AppAuthController::class, 'registerRetailer']);
        Route::post('app/rep/register', [AppAuthController::class, 'registerRep']);
    });

    Route::middleware(['auth:app', 'guard.tokenable:app'])->group(function (): void {
        Route::get('app/session', [AppAuthController::class, 'session']);
        Route::post('app/auth/logout', [AppAuthController::class, 'logout']);
    });

    Route::middleware(['auth:app', 'guard.tokenable:app', 'app.kind:rep'])->group(function (): void {
        Route::get('app/rep/customers', [RepFieldController::class, 'customers']);
        Route::post('app/rep/customers', [RepFieldController::class, 'storeCustomer']);
        Route::post('app/rep/zones', [RepFieldController::class, 'requestZone']);
        Route::get('app/rep/zones/{id}/shops', [RepFieldController::class, 'shops']);
        Route::patch('app/rep/status', [RepFieldController::class, 'status']);
    });
});
