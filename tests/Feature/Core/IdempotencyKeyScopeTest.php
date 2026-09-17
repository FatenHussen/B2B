<?php

declare(strict_types=1);

/**
 * Does a stored idempotent response belong to the caller who created it?
 *
 * `EnsureIdempotency` is appended to the `api` middleware group, so it runs BEFORE
 * `auth:platform` — it reads and writes `idempotency_keys` while the request is still
 * anonymous. `findLive()` looks the row up by `key` alone, `idempotency_keys.key` is
 * globally unique, and `user_id` is written on create but read by nothing. Those three
 * facts together decide whether one user's 200 can be served to another caller.
 *
 * This file only measures. It is deliberately read-only about production code: if an
 * assertion here fails, the fix belongs in `EnsureIdempotency`, never at route level.
 */

use Laravel\Sanctum\Sanctum;
use Modules\Access\Database\Seeders\RolesPermissionsSeeder;
use Modules\Core\Domain\Models\IdempotencyKey;
use Modules\Identity\Domain\Models\PlatformUser;
use Modules\Tenancy\Domain\Models\SupplyChannel;

beforeEach(fn () => $this->seed(RolesPermissionsSeeder::class));

/**
 * A real bearer token, not `Sanctum::actingAs`. The acting-as helper sets the resolved
 * user on the guard and never consults a token, which is exactly the layer under test:
 * case (a) has to reach the middleware with no credential at all.
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

/** The body is identical in every call below, so `request_hash` can never be what differs. */
function renameBody(): array
{
    return [
        'name' => 'شركة النور للتوزيع',
        'legal_form' => 'llc',
        'reason' => 'تصحيح الاسم التجاري',
    ];
}

it('user A stores a 200 under key K', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $userA = idempotentAdmin();

    $this->withHeaders([
        'Authorization' => 'Bearer '.platformBearer($userA),
        'X-Idempotency-Key' => 'K-shared',
    ])->putJson("/api/v1/admin/channels/{$channel->id}", renameBody())
        ->assertOk();

    $row = IdempotencyKey::query()->where('key', 'K-shared')->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('complete')
        ->and($row->status_code)->toBe(200)
        // Stored against A. Whether anything ever reads it back is the question below.
        ->and($row->user_id)->toBe($userA->id);
});

it('(a) the same key and body with no token is rejected as unauthenticated and does not replay', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $userA = idempotentAdmin();

    $this->withHeaders([
        'Authorization' => 'Bearer '.platformBearer($userA),
        'X-Idempotency-Key' => 'K-anon',
    ])->putJson("/api/v1/admin/channels/{$channel->id}", renameBody())
        ->assertOk();

    // No Authorization header at all. Idempotency runs before auth, so this request
    // reaches the middleware anonymously with a key that already holds a stored 200.
    $replay = $this->withHeaders([
        'X-Idempotency-Key' => 'K-anon',
    ])->putJson("/api/v1/admin/channels/{$channel->id}", renameBody());

    expect($replay->getStatusCode())->toBe(401);
    expect($replay->headers->get('Idempotent-Replayed'))->toBeNull();
});

it('(b) the same key and body as another platform user does not return user A stored response', function () {
    $channel = SupplyChannel::factory()->create(['name' => 'شركة النور']);
    $userA = idempotentAdmin();
    $userB = idempotentAdmin();

    $first = $this->withHeaders([
        'Authorization' => 'Bearer '.platformBearer($userA),
        'X-Idempotency-Key' => 'K-cross',
    ])->putJson("/api/v1/admin/channels/{$channel->id}", renameBody());

    $first->assertOk();

    $second = $this->withHeaders([
        'Authorization' => 'Bearer '.platformBearer($userB),
        'X-Idempotency-Key' => 'K-cross',
    ])->putJson("/api/v1/admin/channels/{$channel->id}", renameBody());

    // B must not be handed the response the platform computed for A. Either B executes
    // their own request, or they are refused — but they never receive A's stored body
    // as though the platform had answered them.
    expect($second->headers->get('Idempotent-Replayed'))->toBeNull();

    $row = IdempotencyKey::query()->where('key', 'K-cross')->first();
    expect($row?->user_id)->toBe($userA->id);
});
