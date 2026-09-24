<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Modules\Identity\Presentation\Http\Controllers\AppAuthController;
use Modules\Identity\Presentation\Http\Controllers\ChannelAuthController;
use Modules\Identity\Presentation\Http\Controllers\ChannelOpsExtrasController;
use Modules\Identity\Presentation\Http\Controllers\ChannelRepOpsController;
use Modules\Identity\Presentation\Http\Controllers\PlatformAuthController;
use Modules\Identity\Presentation\Http\Controllers\PlatformOpsController;
use Modules\Identity\Presentation\Http\Controllers\PublicAuthController;
use Modules\Identity\Presentation\Http\Controllers\RepFieldController;
use Modules\Identity\Presentation\Http\Controllers\RetailerGroupController;
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
            Route::post('request-otp', [PlatformAuthController::class, 'requestStepUpOtp']);
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

    // PA-05, PA-10.
    Route::middleware(['auth:platform', 'guard.tokenable:platform', SubstituteBindings::class])->prefix('platform')->group(function (): void {
        Route::post('channels/{id}/manager/reset', [PlatformOpsController::class, 'resetManager'])
            ->middleware('permission:ad.channels.update');
        Route::get('team', [PlatformOpsController::class, 'team'])->middleware('permission:ad.team.view');
        Route::post('team/invites', [PlatformOpsController::class, 'invite'])->middleware('permission:ad.team.invite');
        Route::get('team/invites', [PlatformOpsController::class, 'invites'])->middleware('permission:ad.team.view');
        Route::post('team/{id}/disable', [PlatformOpsController::class, 'disable'])->middleware('permission:ad.team.delete');
        Route::put('team/{id}', [PlatformOpsController::class, 'updateMember'])->middleware('permission:ad.team.update');
        Route::delete('team/{id}', [PlatformOpsController::class, 'deleteMember'])
            ->middleware(['permission:ad.team.delete', RequirePasswordConfirmation::class]);
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
        Route::get('app/rep/customers/{id}', [RepFieldController::class, 'showCustomer']);
        Route::post('app/rep/customers', [RepFieldController::class, 'storeCustomer']);
        Route::get('app/rep/zones', [RepFieldController::class, 'zones']);
        Route::post('app/rep/zones', [RepFieldController::class, 'requestZone']);
        Route::get('app/rep/zones/{id}/shops', [RepFieldController::class, 'shops']);
        Route::get('app/rep/home', [RepFieldController::class, 'home']);
        Route::patch('app/rep/status', [RepFieldController::class, 'status']);
        Route::patch('app/rep/profile', [RepFieldController::class, 'updateProfile']);
    });

    Route::middleware(['auth:channel', 'guard.tokenable:channel', 'tenant'])->prefix('channel')->group(function (): void {
        Route::get('reps', [ChannelRepOpsController::class, 'index'])->middleware('permission:sc.reps.view');
        Route::get('reps/live', [ChannelOpsExtrasController::class, 'repsLive'])->middleware('permission:sc.reps.track');
        Route::get('reps/{id}/live', [ChannelOpsExtrasController::class, 'repLive'])->middleware('permission:sc.reps.view');
        Route::get('reps/{id}', [ChannelRepOpsController::class, 'show'])->middleware('permission:sc.reps.view');
        Route::post('reps/{id}/approve', [ChannelRepOpsController::class, 'approve'])->middleware('permission:sc.reps.update');
        Route::post('reps/{id}/reject', [ChannelRepOpsController::class, 'reject'])->middleware('permission:sc.reps.update');
        Route::post('reps/{id}/disable', [ChannelRepOpsController::class, 'disable'])->middleware('permission:sc.reps.disable');
        Route::get('rep-zone-requests', [ChannelRepOpsController::class, 'zoneRequests'])->middleware('permission:sc.reps.view');
        Route::post('rep-zone-requests/{id}/decide', [ChannelRepOpsController::class, 'decideZoneRequest'])->middleware('permission:sc.reps.update');
        Route::get('rep-sourced-shops', [ChannelRepOpsController::class, 'sourcedShops'])->middleware('permission:sc.reps.view');
        Route::post('rep-sourced-shops/{id}/decide', [ChannelRepOpsController::class, 'decideSourcedShop'])->middleware('permission:sc.reps.update');
        Route::get('retailers', [ChannelRepOpsController::class, 'retailers'])->middleware('permission:sc.retailers.view');
        Route::get('retailers/{id}', [ChannelOpsExtrasController::class, 'showRetailer'])->middleware('permission:sc.retailers.view');
        Route::get('zones/coverage', [ChannelOpsExtrasController::class, 'zoneCoverage'])->middleware('permission:sc.zones.view');
        Route::get('users', [ChannelOpsExtrasController::class, 'users'])->middleware('permission:sc.iam.users_view');
        Route::post('users/invite', [ChannelOpsExtrasController::class, 'invite'])->middleware('permission:sc.iam.users_manage');
        Route::get('retailer-groups', [RetailerGroupController::class, 'index'])->middleware('permission:sc.retailers.groups');
        Route::post('retailer-groups', [RetailerGroupController::class, 'store'])->middleware('permission:sc.retailers.groups');
        Route::put('retailer-groups/{id}', [RetailerGroupController::class, 'update'])->middleware('permission:sc.retailers.groups');
        Route::delete('retailer-groups/{id}', [RetailerGroupController::class, 'destroy'])->middleware('permission:sc.retailers.groups');
    });
});
