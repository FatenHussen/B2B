<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Content\Presentation\Http\Controllers\ChannelContentController;

Route::middleware(['api', SubstituteBindings::class, 'auth:channel', 'guard.tokenable:channel', 'tenant'])
    ->prefix('api/v1/channel/content')
    ->group(function (): void {
        Route::get('intro', [ChannelContentController::class, 'showIntro'])->middleware('permission:sc.content.intro');
        Route::put('intro', [ChannelContentController::class, 'updateIntro'])->middleware('permission:sc.content.intro');
        Route::get('banners', [ChannelContentController::class, 'banners'])->middleware('permission:sc.content.banners');
        Route::post('banners', [ChannelContentController::class, 'storeBanner'])->middleware('permission:sc.content.banners');
        Route::get('banners/{id}/stats', [ChannelContentController::class, 'bannerStats'])->middleware('permission:sc.content.banners');
        Route::get('sliders', [ChannelContentController::class, 'sliders'])->middleware('permission:sc.content.sliders');
        Route::post('sliders', [ChannelContentController::class, 'storeSlider'])->middleware('permission:sc.content.sliders');
    });
