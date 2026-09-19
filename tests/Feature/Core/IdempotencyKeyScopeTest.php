<?php

declare(strict_types=1);

/**
 * Does a stored idempotent response belong to the caller who created it? (BE-C13)
 *
 * Before BE-C13, `EnsureIdempotency` was only appended to the `api` middleware group, so
 * it ran BEFORE `auth:platform` — it read and wrote `idempotency_keys` while the request
 * was still anonymous. `findLive()` looked the row up by `key` alone, `idempotency_keys.key`
 * was globally unique, and `user_id` was written on create but read by nothing. Cases (1)
 * and (2) below measured that: a 200 stored by user A was served to a caller with no
 * token, and to user B, each with `Idempotent-Replayed: true`.
 *
 * Now the middleware sits behind `auth:*` in the priority list and a row is keyed by
 * (guard, user_id, key). The cases below are the contract of that scope: who may replay,
 * who may not, what a stuck row does, and what the eight exempt paths still get.
 *
 * Two request shapes are used on purpose. The real routes (`PUT /admin/channels/{id}`,
 * `PUT /platform/me/password`) prove the order of the real pipeline; the two `_scope`
 * routes registered in `beforeEach` are the smallest write behind each guard, so a case
 * about the scope itself is not also a case about a controller.
 *
 * Every header is passed per request, never through `withHeaders()`: that helper merges
 * into `defaultHeaders`, which persists for the whole test, so a "no token" request made
 * after one with a token would still carry it.
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Models\IdempotencyKey;
use Modules\Identity\Domain\Models\ChannelUser;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(function () {
    $this->seed(RolesPermissionsSeeder::class);

    // The smallest write behind each guard. Each answers who it saw and a counter that
    // only moves when the handler actually runs — a replay leaves it where it was.
    foreach (['platform', 'channel'] as $guard) {
        Route::post("/api/_scope/{$guard}", fn (Request $request) => response()->json([
            'data' => [
                'guard' => $guard,
                'user' => $request->user()?->getAuthIdentifier(),
                'n' => Cache::increment('scope_counter'),
            ],
        ]))->middleware(['api', "auth:{$guard}"]);
    }
});

/**
 * A real bearer token, not `Sanctum::actingAs`. The acting-as helper sets the resolved
 * user on the guard and never consults a token, which is exactly the layer under test:
 * case (1) has to reach the middleware with no credential at all.
 */
function platformBearer(PlatformUser $user): string
{
    return $user->createToken('test', ['*'])->plainTextToken;
}

function idempotentAdmin(): PlatformUser
{
    $user = PlatformUser::factory()->create();
    $user->assignRole('platform_admin');

    return $user;
}

/**
 * The body is identical in every call below, so `request_hash` can never be what differs.
 *
 * @return array<string, string>
 */
function renameBody(): array
{
    return [
        'name' => 'شركة النور للتوزيع',
        'legal_form' => 'llc',
        'reason' => 'تصحيح الاسم التجاري',
    ];
}

/**
 * A Sanctum guard memoises the user it resolved for the life of the application, and
 * every request in one test shares that application. Without this, the second request
 * in a test is authenticated as whoever the first one was — a no-token request would
 * pass `auth:platform` on A's cached user, and B's token would resolve to A.
 */
function forgetResolvedUsers(): void
{
    app('auth')->forgetGuards();
}

it('user A stores a 200 under key K, against A and the guard that verified A', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $userA = idempotentAdmin();

    $this->putJson("/api/v1/platform/channels/{$channel->id}", renameBody(), [
        'Authorization' => 'Bearer '.platformBearer($userA),
        'X-Idempotency-Key' => 'K-shared',
    ])->assertOk();

    $row = IdempotencyKey::query()->where('key', 'K-shared')->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('complete')
        ->and($row->status_code)->toBe(200)
        ->and($row->guard)->toBe('platform')
        ->and($row->user_id)->toBe($userA->id)
        // A finished row holds no lock.
        ->and($row->locked_until)->toBeNull();
});

it('(1) the same key and body with no token is rejected as unauthenticated and does not replay', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $userA = idempotentAdmin();

    $this->putJson("/api/v1/platform/channels/{$channel->id}", renameBody(), [
        'Authorization' => 'Bearer '.platformBearer($userA),
        'X-Idempotency-Key' => 'K-anon',
    ])->assertOk();

    forgetResolvedUsers();

    // No Authorization header at all, with a key that holds a stored 200. Authentication
    // now runs first, so this never reaches the middleware.
    $replay = $this->putJson("/api/v1/platform/channels/{$channel->id}", renameBody(), [
        'X-Idempotency-Key' => 'K-anon',
    ]);

    expect($replay->getStatusCode())->toBe(401)
        ->and($replay->json('error.code'))->toBe('unauthenticated')
        ->and($replay->headers->get('Idempotent-Replayed'))->toBeNull();
});

it('(2) the same key and body as another platform user does not return user A stored response', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $userA = idempotentAdmin();
    $userB = idempotentAdmin();

    $this->putJson("/api/v1/platform/channels/{$channel->id}", renameBody(), [
        'Authorization' => 'Bearer '.platformBearer($userA),
        'X-Idempotency-Key' => 'K-cross',
    ])->assertOk();

    forgetResolvedUsers();

    $second = $this->putJson("/api/v1/platform/channels/{$channel->id}", renameBody(), [
        'Authorization' => 'Bearer '.platformBearer($userB),
        'X-Idempotency-Key' => 'K-cross',
    ]);

    // B must not be handed the response the platform computed for A. B's own request
    // runs, and is remembered against B — the same key is two rows, one per principal.
    $second->assertOk();
    expect($second->headers->get('Idempotent-Replayed'))->toBeNull();

    $rows = IdempotencyKey::query()->where('key', 'K-cross')->orderBy('user_id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('user_id')->all())->toBe([$userA->id, $userB->id])
        ->and($rows->pluck('guard')->unique()->all())->toBe(['platform']);
});

it('(2b) the same key and body as another channel user does not return the first channel user stored response', function () {
    // The case that needs the ORDER, not just the scope. Before `auth:channel` has run,
    // `$request->user()` asks the default guard — `platform` — which knows nothing of a
    // channel token: both channel users would have been anonymous, one principal, and B
    // would have read A's row. On the platform guard the scope alone happens to hide
    // that, because the default guard is the one that verifies platform tokens.
    $userA = ChannelUser::factory()->create();
    $userB = ChannelUser::factory()->create();

    $this->postJson('/api/_scope/channel', [], [
        'Authorization' => 'Bearer '.$userA->createToken('t', ['*'])->plainTextToken,
        'X-Idempotency-Key' => 'K-channel-cross',
    ])->assertOk()->assertJsonPath('data.user', $userA->id);

    forgetResolvedUsers();

    $this->postJson('/api/_scope/channel', [], [
        'Authorization' => 'Bearer '.$userB->createToken('t', ['*'])->plainTextToken,
        'X-Idempotency-Key' => 'K-channel-cross',
    ])->assertOk()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('data.user', $userB->id)
        ->assertJsonPath('data.n', 2);

    $rows = IdempotencyKey::query()->where('key', 'K-channel-cross')->orderBy('user_id')->get();

    expect($rows->map(fn (IdempotencyKey $r) => [$r->guard, $r->user_id])->all())
        ->toBe([['channel', $userA->id], ['channel', $userB->id]]);
});

it('(3) the same key from two users with different bodies is not a conflict for the second', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $userA = idempotentAdmin();
    $userB = idempotentAdmin();

    $this->putJson("/api/v1/platform/channels/{$channel->id}", renameBody(), [
        'Authorization' => 'Bearer '.platformBearer($userA),
        'X-Idempotency-Key' => 'K-two-bodies',
    ])->assertOk();

    forgetResolvedUsers();

    // A different body under the same key is `idempotency_key_conflict` for the SAME
    // caller (case 8). For another caller it is simply their first request: the keys
    // two users chose never met, and the hash of one has nothing to say about the other.
    $this->putJson("/api/v1/platform/channels/{$channel->id}", ['reason' => 'سبب آخر تماماً'] + renameBody(), [
        'Authorization' => 'Bearer '.platformBearer($userB),
        'X-Idempotency-Key' => 'K-two-bodies',
    ])->assertOk()
        ->assertHeaderMissing('Idempotent-Replayed');
});

it('(4) user 5 on the platform guard and user 5 on the channel guard do not collide on one key', function () {
    // The same primary key on two user tables: without the guard in the row's identity,
    // these two are one principal.
    $platformFive = PlatformUser::factory()->create(['id' => 5]);
    $channelFive = ChannelUser::factory()->create(['id' => 5]);

    $this->postJson('/api/_scope/platform', [], [
        'Authorization' => 'Bearer '.$platformFive->createToken('t', ['*'])->plainTextToken,
        'X-Idempotency-Key' => 'K-five',
    ])->assertOk()
        ->assertJsonPath('data.guard', 'platform')
        ->assertJsonPath('data.user', 5)
        ->assertJsonPath('data.n', 1);

    forgetResolvedUsers();

    // Not a replay of the platform user's response, not a 409 against their row: the
    // channel user's own handler ran, and the counter moved.
    $this->postJson('/api/_scope/channel', [], [
        'Authorization' => 'Bearer '.$channelFive->createToken('t', ['*'])->plainTextToken,
        'X-Idempotency-Key' => 'K-five',
    ])->assertOk()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('data.guard', 'channel')
        ->assertJsonPath('data.user', 5)
        ->assertJsonPath('data.n', 2);

    $rows = IdempotencyKey::query()->where('key', 'K-five')->orderBy('guard')->get();

    expect($rows->map(fn (IdempotencyKey $r) => [$r->guard, $r->user_id])->all())
        ->toBe([['channel', 5], ['platform', 5]]);
});

it('(5) a processing row whose lock has lapsed is taken over and the retry runs', function () {
    $user = PlatformUser::factory()->create();
    $headers = [
        'Authorization' => 'Bearer '.platformBearer($user),
        'X-Idempotency-Key' => 'K-abandoned',
    ];

    $this->postJson('/api/_scope/platform', [], $headers)->assertOk()->assertJsonPath('data.n', 1);

    // The worker that held this row died before writing the completion. Its lock has
    // already lapsed; the request hash is the real one, so the retry is the same request.
    IdempotencyKey::query()->where('key', 'K-abandoned')->firstOrFail()->forceFill([
        'status' => 'processing',
        'locked_until' => now()->subSecond(),
        'response_body' => null,
        'status_code' => null,
        'completed_at' => null,
    ])->save();

    $this->postJson('/api/_scope/platform', [], $headers)
        ->assertOk()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('data.n', 2);

    $row = IdempotencyKey::query()->where('key', 'K-abandoned')->firstOrFail();

    expect($row->status)->toBe('complete')
        ->and($row->locked_until)->toBeNull()
        ->and(json_decode((string) $row->response_body, true)['data']['n'])->toBe(2)
        ->and(IdempotencyKey::query()->where('key', 'K-abandoned')->count())->toBe(1);
});

it('(6) the eight exempt paths still pass with no token and no key', function () {
    // The move behind `auth:*` changes nothing here: an exempt path carries no guard, so
    // there is nothing to wait for, and the middleware lets it through as before. 422 is
    // the proof — an empty body reached the form request.
    $paths = config('core.idempotency_exempt');

    expect($paths)->toHaveCount(8);

    foreach ($paths as $path) {
        $response = $this->withoutIdempotencyKey()->postJson('/'.$path);

        expect($response->getStatusCode())->toBe(422, "{$path} answered {$response->getStatusCode()}")
            ->and($response->json('error.code'))->toBe('validation_failed', $path);
    }

    expect(IdempotencyKey::count())->toBe(0);
});

it('(7) a 403 requires_password_confirm frees the key; after confirming, the same key runs once and then replays', function () {
    $user = PlatformUser::factory()->create(['password' => 'password']);
    $headers = [
        'Authorization' => 'Bearer '.platformBearer($user),
        'X-Idempotency-Key' => 'K-change-password',
    ];
    $body = [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ];

    // 1. Not confirmed yet. The 403 is not stored — a failure is never replayed.
    $this->putJson('/api/v1/platform/me/password', $body, $headers)
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'requires_password_confirm');

    expect(IdempotencyKey::query()->where('key', 'K-change-password')->exists())->toBeFalse();

    // 2. Confirm, under its own key (the base TestCase adds one when none is given).
    $this->postJson('/api/v1/platform/auth/confirm-password', ['password' => 'password'], [
        'Authorization' => $headers['Authorization'],
    ])->assertOk();

    // 3. The client retries the original intent with the original key: it runs.
    $this->putJson('/api/v1/platform/me/password', $body, $headers)
        ->assertOk()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('data.updated', true);

    $hashAfterChange = $user->refresh()->password;

    // 4. Replayed from the row, not executed again. A second execution could not even
    //    succeed — `current_password` is no longer the password — and bcrypt would have
    //    written a different hash; the stored 200 comes back and the hash is untouched.
    $this->putJson('/api/v1/platform/me/password', $body, $headers)
        ->assertOk()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.updated', true);

    expect($user->refresh()->password)->toBe($hashAfterChange);
});

it('(8) the same user, the same key and a different body after a success is idempotency_key_conflict', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $userA = idempotentAdmin();
    $headers = [
        'Authorization' => 'Bearer '.platformBearer($userA),
        'X-Idempotency-Key' => 'K-reused',
    ];

    $this->putJson("/api/v1/platform/channels/{$channel->id}", renameBody(), $headers)
        ->assertOk();

    $this->putJson("/api/v1/platform/channels/{$channel->id}", ['reason' => 'سبب آخر تماماً'] + renameBody(), $headers)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'idempotency_key_conflict');
});

it('(9) a held processing row answers operation_in_progress until locked_until, then the retry runs', function () {
    $user = PlatformUser::factory()->create();
    $headers = [
        'Authorization' => 'Bearer '.platformBearer($user),
        'X-Idempotency-Key' => 'K-in-flight',
    ];

    $this->postJson('/api/_scope/platform', [], $headers)->assertOk()->assertJsonPath('data.n', 1);

    // Another worker is running this request right now and holds the row for the
    // configured lock.
    $lockSeconds = (int) config('core.idempotency_lock_seconds');
    IdempotencyKey::query()->where('key', 'K-in-flight')->firstOrFail()->forceFill([
        'status' => 'processing',
        'locked_until' => now()->addSeconds($lockSeconds),
        'response_body' => null,
        'status_code' => null,
        'completed_at' => null,
    ])->save();

    $this->postJson('/api/_scope/platform', [], $headers)
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'operation_in_progress');

    // The handler did not run behind the 409.
    expect(Cache::get('scope_counter'))->toBe(1);

    // The lock lapses and the worker never came back.
    $this->travel($lockSeconds + 1)->seconds();

    $this->postJson('/api/_scope/platform', [], $headers)
        ->assertOk()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('data.n', 2);

    expect(IdempotencyKey::query()->where('key', 'K-in-flight')->firstOrFail()->status)->toBe('complete');
});
