<?php

declare(strict_types=1);

/**
 * BE-F04 requirement 3, asserted where it is actually decided.
 *
 * A namespace cannot tell you which middleware a route was registered with, so this
 * reads the booted route table instead of the source tree. Four guards, four user
 * tables: a route under a guard's URL prefix is behind that guard or it is a leak.
 *
 * `/auth/` is the documented exception: login, OTP and device-login are what you call
 * before you hold a token.
 */

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * @return Collection<int, string>
 */
function unguardedRoutesFor(string $guard): Collection
{
    return collect(Route::getRoutes())
        ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), "api/v1/{$guard}/"))
        ->reject(fn (RoutingRoute $r) => str_contains($r->uri(), '/auth/'))
        ->reject(fn (RoutingRoute $r) => in_array("auth:{$guard}", $r->gatherMiddleware(), true))
        ->map->uri()
        ->unique()
        ->values();
}

it('puts every platform route behind the platform guard', function () {
    expect(unguardedRoutesFor('platform')->all())->toBe([]);
})->group('arch');

it('puts every channel route behind the channel guard', function () {
    expect(unguardedRoutesFor('channel')->all())->toBe([]);
})->group('arch');

it('puts every warehouse route behind the warehouse guard', function () {
    expect(unguardedRoutesFor('warehouse')->all())->toBe([]);
})->group('arch');

it('puts every app route behind the app guard', function () {
    expect(unguardedRoutesFor('app')->all())->toBe([]);
})->group('arch');

it('actually catches a route registered without its guard', function () {
    Route::middleware(['api', 'auth:platform'])
        ->get('api/v1/channel/arch-fixture-leak', fn () => null);
    Route::getRoutes()->refreshNameLookups();

    expect(unguardedRoutesFor('channel'))->toContain('api/v1/channel/arch-fixture-leak');
})->group('arch');

it('registers each guard prefix at all', function () {
    // Guards the guard test: an empty route table would make every rule above pass.
    foreach (['platform', 'channel', 'warehouse', 'app'] as $guard) {
        $count = collect(Route::getRoutes())
            ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), "api/v1/{$guard}/"))
            ->count();

        expect($count)->toBeGreaterThan(0, "no route is registered under api/v1/{$guard}/");
    }
})->group('arch');
