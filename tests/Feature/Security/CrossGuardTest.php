<?php

declare(strict_types=1);

/**
 * A measurement, not a fix. Nothing in production changes because of this file.
 *
 * Four guards, four user tables. This presents a real personal access token issued to one
 * guard's user against a route that belongs to another guard, and records what the API
 * actually answers — not what DOC-08 says it should. Every assertion below is pinned to
 * observed behaviour so the suite stays green and this file works as a baseline: the day
 * BE-C02 lands, these tests go red and name every crossing whose answer changed.
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
 * answer.
 */
const CROSSINGS = [
    // target guard, uri, token guard, observed status, observed error.code
    ['platform', 'api/v1/platform/me', 'channel', 401, 'unauthenticated'],
    ['platform', 'api/v1/platform/me', 'warehouse', 401, 'unauthenticated'],
    ['platform', 'api/v1/platform/me', 'app', 401, 'unauthenticated'],

    ['platform', 'api/v1/admin/channels', 'channel', 401, 'unauthenticated'],
    ['platform', 'api/v1/admin/channels', 'warehouse', 401, 'unauthenticated'],
    ['platform', 'api/v1/admin/channels', 'app', 401, 'unauthenticated'],

    ['channel', 'api/v1/channel/brands', 'platform', 401, 'unauthenticated'],
    ['channel', 'api/v1/channel/brands', 'warehouse', 401, 'unauthenticated'],
    ['channel', 'api/v1/channel/brands', 'app', 401, 'unauthenticated'],

    ['channel', 'api/v1/channel', 'platform', 401, 'unauthenticated'],
    ['channel', 'api/v1/channel', 'warehouse', 401, 'unauthenticated'],
    ['channel', 'api/v1/channel', 'app', 401, 'unauthenticated'],

    ['warehouse', 'api/v1/warehouse/queues', 'platform', 401, 'unauthenticated'],
    ['warehouse', 'api/v1/warehouse/queues', 'channel', 401, 'unauthenticated'],
    ['warehouse', 'api/v1/warehouse/queues', 'app', 401, 'unauthenticated'],

    ['app', 'api/v1/app/offers', 'platform', 401, 'unauthenticated'],
    ['app', 'api/v1/app/offers', 'channel', 401, 'unauthenticated'],
    ['app', 'api/v1/app/offers', 'warehouse', 401, 'unauthenticated'],
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

    // SHOULD BE per CLAUDE.md: 403. "A token presented to the wrong guard returns 403
    // wrong_guard." IS: 401. `auth:<guard>` cannot tell a foreign token from no token —
    // it finds no user of its own provider and reports the request unauthenticated.
    // Pinned to the observed value on purpose. Changing it is BE-C02, not this ticket.
    expect($response->getStatusCode())->toBe($status);

    // SHOULD BE per CLAUDE.md: error.code === 'wrong_guard', which is in the DOC-08 table
    // under 403. IS: 'unauthenticated'. The string wrong_guard appears nowhere in the
    // codebase — see the arithmetic test at the bottom of this file.
    expect($response->json('error.code'))->toBe($code);
})->with('crossings')->group('security');

it('lets the owning guard through, so the 401s above mean something', function (string $uri, string $guard) {
    $response = $this->getJson('/'.$uri, ($this->bearer)($guard));

    // SHOULD BE and IS: not 401. A route that 401s for everyone would make every crossing
    // above look secure while the endpoint is simply broken — which is exactly how the
    // auth:sanctum defect hid for so long. The owning guard authenticates here; the
    // response may still be 200, or a business answer such as 403 profile_incomplete or
    // 404 not_found, and any of those proves authentication succeeded.
    expect($response->getStatusCode())->not->toBe(401);
})->with('own guard controls')->group('security');

it('counts how many crossings answer 403, 401, or something else', function () {
    $statuses = array_column(CROSSINGS, 3);

    // SHOULD BE per CLAUDE.md: 18 / 0 / 0 — every crossing a 403.
    // IS: 0 / 18 / 0. Recorded 2026-09-05.
    expect(count(array_filter($statuses, fn (int $s) => $s === 403)))->toBe(0)
        ->and(count(array_filter($statuses, fn (int $s) => $s === 401)))->toBe(18)
        ->and(count(array_filter($statuses, fn (int $s) => $s !== 401 && $s !== 403)))->toBe(0);
})->group('security');

it('counts how many crossings reached the endpoint', function () {
    $reached = array_filter(array_column(CROSSINGS, 3), fn (int $s) => $s >= 200 && $s < 300);

    // SHOULD BE and IS: zero. A 2xx here would be a live authorisation bypass — a token
    // from one guard reading another guard's data — and would outrank BE-C02 entirely.
    // The guard boundary holds; only the status code it reports is wrong.
    expect($reached)->toBe([]);
})->group('security');

it('counts how many crossings answer wrong_guard', function () {
    $codes = array_column(CROSSINGS, 4);

    // SHOULD BE per CLAUDE.md: 18. IS: 0. `wrong_guard` is specified in the DOC-08 status
    // table but is not implemented anywhere, so no route can return it. This assertion is
    // the proof, and it is the first thing BE-C02 must flip.
    expect(count(array_filter($codes, fn (string $c) => $c === 'wrong_guard')))->toBe(0)
        ->and(array_unique($codes))->toBe(['unauthenticated']);
})->group('security');
