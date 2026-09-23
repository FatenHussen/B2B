<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Modules\Content\Presentation\Http\Controllers\AppContentController;
use Modules\Content\Presentation\Http\Controllers\ChannelContentController;
use Modules\Content\Presentation\Http\Controllers\PlatformContentController;
use Modules\Content\Presentation\Http\Controllers\PublicContentController;

/*
 * PA-12 / EP-AD-141A/B — the platform default intro. The prefix names the platform
 * guard (GuardTest). A channel that never set its own intro is the audience this
 * row is for; the channel dashboard still reads /channel/content/intro.
 */
/*
 * EP-PB-011 — the apps read the platform default before they hold a token.
 * GuardTest: /public/* is unguarded. Writes stay on /platform/content/intro.
 */
Route::middleware(['api', SubstituteBindings::class])
    ->prefix('api/v1/public')
    ->group(function (): void {
        Route::get('content/intro', [PublicContentController::class, 'showIntro']);
        Route::get('app-config', [PublicContentController::class, 'showAppConfig']);
    });

Route::middleware(['api', 'auth:platform', 'guard.tokenable:platform', SubstituteBindings::class])
    ->prefix('api/v1/platform/content')
    ->group(function (): void {
        Route::get('intro', [PlatformContentController::class, 'showIntro'])
            ->middleware('permission:ad.content.view');
        Route::put('intro', [PlatformContentController::class, 'updateIntro'])
            ->middleware('permission:ad.content.manage');
    });

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

Route::middleware(['api', SubstituteBindings::class, 'auth:app', 'guard.tokenable:app'])
    ->prefix('api/v1/app/content')
    ->group(function (): void {
        Route::get('home-blocks', [AppContentController::class, 'homeBlocks']);
    });
