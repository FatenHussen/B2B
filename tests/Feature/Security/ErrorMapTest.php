<?php

declare(strict_types=1);

/**
 * BE-C02 — the DOC-08 status map, asserted rather than asserted about.
 *
 * Acceptance criterion 1 is "every code in the map has a feature test that triggers it".
 * Six codes had no implementation at all before this ticket: token_revoked, wrong_guard,
 * stale_version, ref_in_use, plan_limit_exceeded and upgrade_required.
 *
 * Two kinds of test live here, and the difference matters when reading them:
 *
 *  - The auth codes ride their real producer. wrong_guard is measured with a genuine
 *    personal access token issued to another guard's user, because Sanctum::actingAs()
 *    never exercises token resolution and so cannot see a cross-guard call at all.
 *  - The rest are raised as the exception their owning feature would raise. Four of them
 *    have no owning feature yet — plan limits are BE-T, optimistic locking and reference
 *    deletion are BE-R — so what is proven for those is the handler contract: the code,
 *    its status, and a JSON envelope carrying a human message. When the features land
 *    they inherit a mapping that is already pinned.
 *
 * One authenticated request per test. A guard caches its resolved user for the lifetime
 * of the application, so a second request in the same test reuses the first token's
 * holder and reports a bypass that is not there.
 */

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;

/** The table in CLAUDE.md, transcribed by hand so that it is an independent statement. */
const DOC08_STATUS_MAP = [
    'unauthenticated' => 401,
    'token_revoked' => 401,
    'wrong_guard' => 403,
    'insufficient_permission' => 403,
    'requires_2fa' => 403,
    'requires_password_confirm' => 403,
    'sod_violation' => 403,
    'not_found' => 404,
    'illegal_transition' => 409,
    'operation_in_progress' => 409,
    'stale_version' => 409,
    'idempotency_key_conflict' => 409,
    'validation_failed' => 422,
    'ref_in_use' => 422,
    'plan_limit_exceeded' => 423,
    'upgrade_required' => 426,
    'rate_limited' => 429,
    'maintenance_mode' => 503,
];

dataset('every error code', array_keys(DOC08_STATUS_MAP));

it('carries every code in the map and no code outside it', function () {
    $actual = [];

    foreach (ErrorCode::cases() as $case) {
        $actual[$case->value] = $case->status();
    }

    // Equality both ways: a missing code fails, and so does an invented one.
    expect($actual)->toBe(DOC08_STATUS_MAP);
})->group('security');

it('renders through the kernel with its status and a human message', function (string $code) {
    $error = ErrorCode::from($code);

    Route::middleware('api')->get('api/_test/error/'.$code, function () use ($error) {
        throw DomainException::of($error);
    });

    $response = $this->getJson('/api/_test/error/'.$code);

    $response->assertStatus($error->status())
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('error.code', $code)
        ->assertJsonMissingPath('data');

    // Not the key itself: the fallback in ErrorCode::message() returns the code when the
    // lang file has no line, so this is what proves all eighteen are translated.
    expect($response->json('error.message'))
        ->toBeString()
        ->not->toBe('')
        ->not->toBe($code);
})->with('every error code')->group('security');

it('answers unauthenticated when no token is presented at all', function () {
    $this->getJson('/api/v1/platform/me')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
})->group('security');

it('answers wrong_guard when a live token from another guard is presented', function () {
    $holder = ChannelUser::factory()->forChannel(SupplyChannel::factory()->create())->create();

    $this->getJson('/api/v1/platform/me', [
        'Authorization' => 'Bearer '.$holder->createToken('cross', ['*'])->plainTextToken,
    ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'wrong_guard');
})->group('security');

it('does not answer wrong_guard to the guard that owns the token', function () {
    $holder = PlatformUser::factory()->create();

    // The control. A route that refused everyone would make the assertion above look like
    // a working boundary while the endpoint is simply broken.
    $this->getJson('/api/v1/platform/me', [
        'Authorization' => 'Bearer '.$holder->createToken('own', ['*'])->plainTextToken,
    ])->assertOk();
})->group('security');

it('answers token_revoked when the token behind it was deleted', function () {
    $holder = PlatformUser::factory()->create();
    $plain = $holder->createToken('revoked', ['*'])->plainTextToken;

    PersonalAccessToken::query()->delete();

    $this->getJson('/api/v1/platform/me', ['Authorization' => 'Bearer '.$plain])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'token_revoked');
})->group('security');

it('answers token_revoked when the token has passed its expiry', function () {
    $holder = PlatformUser::factory()->create();
    $token = $holder->createToken('expired', ['*']);

    PersonalAccessToken::query()
        ->whereKey($token->accessToken->getKey())
        ->update(['expires_at' => now()->subMinute()]);

    $this->getJson('/api/v1/platform/me', ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'token_revoked');
})->group('security');

it('answers unauthenticated, not token_revoked, to a bearer that was never a token', function () {
    $this->getJson('/api/v1/platform/me', ['Authorization' => 'Bearer not-a-sanctum-token'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
})->group('security');

it('names the permission a 403 wanted', function () {
    $this->seed(RolesPermissionsSeeder::class);

    // A platform user holding no role at all: authentication succeeds, authorisation does not.
    $holder = PlatformUser::factory()->create();

    $this->getJson('/api/v1/platform/iam/roles', [
        'Authorization' => 'Bearer '.$holder->createToken('no-role', ['*'])->plainTextToken,
    ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'insufficient_permission')
        ->assertJsonPath('error.permission', 'ad.iam.view_catalog');
})->group('security');

it('answers rate_limited when a throttle trips', function () {
    Route::middleware(['api', 'throttle:1,1'])
        ->get('api/_test/throttled', fn () => response()->json(['ok' => true]));

    $this->getJson('/api/_test/throttled')->assertOk();

    $this->getJson('/api/_test/throttled')
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'rate_limited');
})->group('security');
