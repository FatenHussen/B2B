<?php

declare(strict_types=1);

/**
 * BE-F04 requirement 3, asserted where it is actually decided.
 *
 * A namespace cannot tell you which middleware a route was registered with, so this
 * reads the booted route table instead of the source tree.
 *
 * The path prefix names the guard, not the module. `/api/v1/platform/*` and
 * `/api/v1/admin/*` belong to the platform guard even when a Tenancy controller answers
 * them; `/api/v1/channel/*` belongs to the channel guard even when Reference answers it.
 *
 * `/auth/` is the documented exception: login, OTP and device-login are what you call
 * before you hold a token.
 */

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

const GUARD_PREFIXES = [
    'platform' => ['api/v1/platform', 'api/v1/admin'],
    'channel' => ['api/v1/channel'],
    'warehouse' => ['api/v1/warehouse'],
    'app' => ['api/v1/app'],
];

const KNOWN_GUARDS = ['platform', 'channel', 'warehouse', 'app'];

/**
 * Routes whose URI sits under one of $prefixes, matching the collection root itself
 * (`api/v1/channel`) as well as everything below it.
 *
 * @param  list<string>  $prefixes
 * @return Collection<int, RoutingRoute>
 */
function routesUnder(array $prefixes): Collection
{
    return collect(Route::getRoutes())
        ->filter(function (RoutingRoute $r) use ($prefixes): bool {
            foreach ($prefixes as $prefix) {
                if ($r->uri() === $prefix || str_starts_with($r->uri(), $prefix.'/')) {
                    return true;
                }
            }

            return false;
        });
}

/**
 * Every guard named in an `auth:` middleware on a route, e.g. `auth:platform,channel`.
 *
 * @return list<string>
 */
function guardsOn(RoutingRoute $route): array
{
    $guards = [];

    foreach ($route->gatherMiddleware() as $middleware) {
        if (! is_string($middleware) || ! str_starts_with($middleware, 'auth:')) {
            continue;
        }

        foreach (explode(',', substr($middleware, strlen('auth:'))) as $guard) {
            $guards[] = trim($guard);
        }
    }

    return $guards;
}

/**
 * @return Collection<int, string>
 */
function unguardedRoutesFor(string $guard): Collection
{
    return routesUnder(GUARD_PREFIXES[$guard])
        ->reject(fn (RoutingRoute $r) => str_contains($r->uri(), '/auth/'))
        ->reject(fn (RoutingRoute $r) => in_array($guard, guardsOn($r), true))
        ->map->uri()
        ->unique()
        ->values();
}

/**
 * @return Collection<int, string>
 */
function routesUsingUnknownGuard(): Collection
{
    return collect(Route::getRoutes())
        ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), 'api/v1/'))
        ->filter(fn (RoutingRoute $r) => array_diff(guardsOn($r), KNOWN_GUARDS) !== [])
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

it('authenticates api/v1 with the four guards and nothing else', function () {
    // `auth:sanctum` names a guard config/auth.php never defines. Sanctum injects one at
    // runtime with provider => null, so it resolves instead of erroring and then 401s
    // every caller. Four user tables, four guards — a fifth is always a mistake.
    expect(routesUsingUnknownGuard()->all())->toBe([]);
})->group('arch');

it('actually catches a route registered without its guard', function () {
    Route::middleware(['api', 'auth:platform'])
        ->get('api/v1/channel/arch-fixture-leak', fn () => null);
    Route::getRoutes()->refreshNameLookups();

    expect(unguardedRoutesFor('channel'))->toContain('api/v1/channel/arch-fixture-leak');
})->group('arch');

it('actually catches a route on a guard that is not one of the four', function () {
    Route::middleware(['api', 'auth:sanctum'])
        ->get('api/v1/platform/arch-fixture-unknown-guard', fn () => null);
    Route::getRoutes()->refreshNameLookups();

    expect(routesUsingUnknownGuard())->toContain('api/v1/platform/arch-fixture-unknown-guard');
})->group('arch');

it('registers each guard prefix at all', function () {
    // Guards the guard test: an empty route table would make every rule above pass.
    foreach (KNOWN_GUARDS as $guard) {
        expect(routesUnder(GUARD_PREFIXES[$guard])->count())
            ->toBeGreaterThan(0, "no route is registered under api/v1/{$guard}/");
    }
})->group('arch');
