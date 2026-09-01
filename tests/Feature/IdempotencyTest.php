<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Core\Domain\Models\IdempotencyKey;

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
