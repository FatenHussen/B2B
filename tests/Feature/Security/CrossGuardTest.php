<?php

declare(strict_types=1);

/**
 * The guard boundary, measured end to end.
 *
 * Four guards, four user tables. This presents a real personal access token issued to one
 * guard's user against a route that belongs to another guard, and records what the API
 * answers. It was written before BE-C02 as a baseline pinned to observed behaviour — 401
 * `unauthenticated` on all eighteen crossings, because `auth:<guard>` cannot tell a
 * foreign token from no token at all. BE-C02 has landed, so the table below now records
 * what CLAUDE.md always specified: 403 `wrong_guard`.
 *
 * Two hazards this file is shaped around, both of which silently fake a passing result:
 *
 *  1. `Sanctum::actingAs()` sets a user on a guard directly and never exercises token
 *     resolution, so it cannot measure cross-guard behaviour at all. Real bearer tokens
 *     are used throughout.
 *  2. A guard caches its resolved user for the lifetime of the application. Two requests
 *     in one test would reuse the first token's holder, which reports 200 for every
 *     crossing and looks exactly like a catastrophic auth bypass. Hence one request per
 *     test — the dataset gives each crossing its own fresh application.
 */

use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Identity\Domain\Models\AppUser;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Identity\Domain\Models\WarehouseUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/**
 * One protected route per guard, plus a second for platform and channel: `platform/me`
 * and `channel/brands` carry `guard.tokenable`, while `admin/channels` and `channel` do
 * not. Both wirings are measured so the table shows whether that middleware changes the
 * answer — it does not, and never did: both throw the same AuthenticationException, and
 * the handler names the code from the token, not from the middleware that rejected it.
 */
const CROSSINGS = [
    // target guard, uri, token guard, observed status, observed error.code
    ['platform', 'api/v1/platform/me', 'channel', 403, 'wrong_guard'],
    ['platform', 'api/v1/platform/me', 'warehouse', 403, 'wrong_guard'],
    ['platform', 'api/v1/platform/me', 'app', 403, 'wrong_guard'],

    ['platform', 'api/v1/admin/channels', 'channel', 403, 'wrong_guard'],
    ['platform', 'api/v1/admin/channels', 'warehouse', 403, 'wrong_guard'],
    ['platform', 'api/v1/admin/channels', 'app', 403, 'wrong_guard'],

    ['channel', 'api/v1/channel/brands', 'platform', 403, 'wrong_guard'],
    ['channel', 'api/v1/channel/brands', 'warehouse', 403, 'wrong_guard'],
    ['channel', 'api/v1/channel/brands', 'app', 403, 'wrong_guard'],

    ['channel', 'api/v1/channel', 'platform', 403, 'wrong_guard'],
    ['channel', 'api/v1/channel', 'warehouse', 403, 'wrong_guard'],
    ['channel', 'api/v1/channel', 'app', 403, 'wrong_guard'],

    ['warehouse', 'api/v1/warehouse/queues', 'platform', 403, 'wrong_guard'],
    ['warehouse', 'api/v1/warehouse/queues', 'channel', 403, 'wrong_guard'],
    ['warehouse', 'api/v1/warehouse/queues', 'app', 403, 'wrong_guard'],

    ['app', 'api/v1/app/offers', 'platform', 403, 'wrong_guard'],
    ['app', 'api/v1/app/offers', 'channel', 403, 'wrong_guard'],
    ['app', 'api/v1/app/offers', 'warehouse', 403, 'wrong_guard'],
];

/** The same six routes reached by their own guard, proving each one is alive. */
const OWN_GUARD_CONTROLS = [
    ['platform', 'api/v1/platform/me'],
    ['platform', 'api/v1/admin/channels'],
    ['channel', 'api/v1/channel/brands'],
    ['channel', 'api/v1/channel'],
    ['warehouse', 'api/v1/warehouse/queues'],
    ['app', 'api/v1/app/offers'],
];

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);

    $channel = SupplyChannel::factory()->create();

    $platform = PlatformUser::factory()->create();
    $platform->assignRole('platform_admin');

    $channelUser = ChannelUser::factory()->forChannel($channel)->create();
    $channelUser->assignRole('channel_manager');

    $warehouse = WarehouseUser::factory()->create();
    $warehouse->assignRole('warehouse_keeper');

    $this->holders = [
        'platform' => $platform,
        'channel' => $channelUser,
        'warehouse' => $warehouse,
        'app' => AppUser::factory()->retailer()->create(),
    ];

    $this->bearer = fn (string $guard) => [
        'Authorization' => 'Bearer '.$this->holders[$guard]->createToken('cross-guard', ['*'])->plainTextToken,
    ];
});

dataset('crossings', function () {
    foreach (CROSSINGS as [$target, $uri, $tokenGuard, $status, $code]) {
        yield "{$tokenGuard} token on {$uri} (owned by {$target})" => [$uri, $tokenGuard, $status, $code];
    }
});

dataset('own guard controls', function () {
    foreach (OWN_GUARD_CONTROLS as [$guard, $uri]) {
        yield "{$guard} token on {$uri}" => [$uri, $guard];
    }
});

it('answers a cross-guard token', function (string $uri, string $tokenGuard, int $status, string $code) {
    $response = $this->getJson('/'.$uri, ($this->bearer)($tokenGuard));

    // 403, per CLAUDE.md: "A token presented to the wrong guard returns 403 wrong_guard."
    // The guard itself still cannot see the difference — it asks its own provider for a
    // user and gets none. `Modules\Core\Http\AuthFailureCode` re-reads the presented
    // token after the guard has given up and tells a foreign holder from an absent one.
    expect($response->getStatusCode())->toBe($status);

    // The code is the contract. A client distinguishes "sign in" from "you are signed in
    // to the wrong app" on this string alone, which is why 401 for both was a defect the
    // frontend could not work around.
    expect($response->json('error.code'))->toBe($code);
})->with('crossings')->group('security');

it('lets the owning guard through, so the 403s above mean something', function (string $uri, string $guard) {
    $response = $this->getJson('/'.$uri, ($this->bearer)($guard));

    // SHOULD BE and IS: not 401 and not 403 wrong_guard. A route that refused everyone
    // would make every crossing above look secure while the endpoint is simply broken —
    // which is exactly how the auth:sanctum defect hid for so long. The owning guard
    // authenticates here; the response may still be 200, or a business answer such as
    // 403 profile_incomplete or 404 not_found, and any of those proves it succeeded.
    expect($response->getStatusCode())->not->toBe(401)
        ->and($response->json('error.code'))->not->toBe('wrong_guard');
})->with('own guard controls')->group('security');

it('counts how many crossings answer 403, 401, or something else', function () {
    $statuses = array_column(CROSSINGS, 3);

    // SHOULD BE per CLAUDE.md and IS: 18 / 0 / 0 — every crossing a 403.
    // Was 0 / 18 / 0 until BE-C02. Recorded 2026-09-05.
    expect(count(array_filter($statuses, fn (int $s) => $s === 403)))->toBe(18)
        ->and(count(array_filter($statuses, fn (int $s) => $s === 401)))->toBe(0)
        ->and(count(array_filter($statuses, fn (int $s) => $s !== 401 && $s !== 403)))->toBe(0);
})->group('security');

it('counts how many crossings reached the endpoint', function () {
    $reached = array_filter(array_column(CROSSINGS, 3), fn (int $s) => $s >= 200 && $s < 300);

    // SHOULD BE and IS: zero. A 2xx here would be a live authorisation bypass — a token
    // from one guard reading another guard's data. The boundary held even while the
    // status code it reported was wrong; BE-C02 changed the report, not the boundary.
    expect($reached)->toBe([]);
})->group('security');

it('counts how many crossings answer wrong_guard', function () {
    $codes = array_column(CROSSINGS, 4);

    // SHOULD BE per CLAUDE.md and IS: 18. Was 0 — `wrong_guard` was specified in the
    // DOC-08 status table and implemented nowhere, so no route could return it. This
    // assertion was the proof, and it was the first thing BE-C02 had to flip.
    expect(count(array_filter($codes, fn (string $c) => $c === 'wrong_guard')))->toBe(18)
        ->and(array_unique($codes))->toBe(['wrong_guard']);
})->group('security');
