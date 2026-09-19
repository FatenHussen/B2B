<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Core\Domain\Models\IdempotencyKey;
use Modules\Identity\Domain\Models\AppUser;

describe('X-Idempotency-Key', function () {
    beforeEach(function () {
        Route::post('/api/_test/idem', function () {
            return response()->json(['n' => Cache::increment('idem_counter')]);
        })->middleware(['api']);
    });

    it('rejects a write without an idempotency key', function () {
        $this->withoutIdempotencyKey()
            ->postJson('/api/_test/idem')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'idempotency_key_required');
    });

    it('replays the first response for a repeated key without re-running the handler', function () {
        $first = $this->postJson('/api/_test/idem', [], ['X-Idempotency-Key' => 'k-1'])
            ->assertOk()
            ->json('n');

        $second = $this->postJson('/api/_test/idem', [], ['X-Idempotency-Key' => 'k-1'])
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');

        expect($second->json('n'))->toBe($first)
            ->and(IdempotencyKey::count())->toBe(1);
    });

    it('runs independently for different keys', function () {
        $a = $this->postJson('/api/_test/idem', [], ['X-Idempotency-Key' => 'k-a'])->json('n');
        $b = $this->postJson('/api/_test/idem', [], ['X-Idempotency-Key' => 'k-b'])->json('n');

        expect($b)->not->toBe($a)
            ->and(IdempotencyKey::count())->toBe(2);
    });

    it('rejects the same key reused for a different payload', function () {
        $this->postJson('/api/_test/idem', ['x' => 1], ['X-Idempotency-Key' => 'k-c'])->assertOk();

        $this->postJson('/api/_test/idem', ['x' => 2], ['X-Idempotency-Key' => 'k-c'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'idempotency_key_conflict');
    });

    it('accepts the legacy Idempotency-Key header', function () {
        $this->postJson('/api/_test/idem', [], ['Idempotency-Key' => 'k-legacy'])
            ->assertOk();

        expect(IdempotencyKey::where('key', 'k-legacy')->exists())->toBeTrue();
    });
});

/**
 * BE-C03 §5. The credential-establishing endpoints are exempt: a client cannot hold an
 * idempotency key before it holds a session, and replaying an OTP request must mint a
 * fresh code rather than replay the stored response.
 *
 * This list is the contract the frontend kit omits the header on. It is repeated here
 * rather than read from config so that the test states the expected set independently —
 * a test fed by the value it is checking proves nothing.
 */
const IDEMPOTENCY_EXEMPT_PATHS = [
    'api/v1/public/auth/request-otp',
    'api/v1/public/auth/verify-otp',
    'api/v1/public/auth/resend-otp',
    'api/v1/platform/auth/login',
    'api/v1/platform/auth/2fa/verify',
    'api/v1/channel/auth/request-otp',
    'api/v1/channel/auth/verify-otp',
    'api/v1/warehouse/auth/device-login',
];

dataset('exempt write paths', IDEMPOTENCY_EXEMPT_PATHS);

describe('the idempotency exemption list', function () {
    it('lets an exempt path through with no key and stores nothing', function (string $path) {
        $this->withoutIdempotencyKey()
            ->postJson('/'.$path)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        /*
         * 422 and not 400 is the whole assertion. `EnsureIdempotency` is in the `api`
         * group, so it runs on every one of these routes ahead of the form request: an
         * empty body reaching validation can only mean the request passed through it.
         * Asserting merely "not 400" would also pass on a 404, which is how an exemption
         * aimed at a path that has since moved would go unnoticed.
         */
        expect(IdempotencyKey::count())->toBe(0);
    })->with('exempt write paths');

    it('still demands a key on a write that is not exempt', function () {
        // A real credential, because the middleware now runs behind `auth:*` (BE-C13):
        // with no token this route answers 401 before the key is ever looked for. Until
        // BE-C13 this test sent nothing and got its 400 — the order it pinned was the bug.
        $token = AppUser::factory()->create()->createToken('registration', ['registration'])->plainTextToken;

        $this->withoutIdempotencyKey()
            ->postJson('/api/v1/app/retailer/register', [], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'idempotency_key_required');

        expect(IdempotencyKey::count())->toBe(0);
    });

    it('exempts those paths and no others', function () {
        // Widening the list silently would let a write lose its replay protection with
        // nothing to notice, and would desync this repository from the frontend kit.
        expect(config('core.idempotency_exempt'))->toBe(IDEMPOTENCY_EXEMPT_PATHS);
    });
});
