<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Loyalty\Presentation\Http\Controllers\ChannelLoyaltyController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel/loyalty')
    ->group(function (): void {
        Route::get('rules', [ChannelLoyaltyController::class, 'rules'])->middleware('permission:sc.loyalty.manage');
        Route::put('rules', [ChannelLoyaltyController::class, 'updateRules'])->middleware('permission:sc.loyalty.manage');
        Route::get('rewards', [ChannelLoyaltyController::class, 'rewards'])->middleware('permission:sc.loyalty.manage');
        Route::post('rewards', [ChannelLoyaltyController::class, 'storeReward'])->middleware('permission:sc.loyalty.manage');
    });
