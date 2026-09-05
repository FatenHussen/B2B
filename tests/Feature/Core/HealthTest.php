<?php

declare(strict_types=1);

/**
 * BE-F09 — EP-CORE-001.
 *
 * Two acceptance criteria: it answers without a Bearer token, and it is the first
 * endpoint the shared frontend kit calls successfully. Both are about a client that
 * holds nothing yet, which is why the degraded case still answers 200 with a readable
 * envelope rather than a transport error the kit would have to special-case.
 */

use Illuminate\Support\Facades\Cache;

it('answers without a bearer token and without an idempotency key', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok');
});

it('reports database, cache and queue reachability', function () {
    $response = $this->getJson('/api/v1/health')->assertOk();

    expect($response->json('data.checks'))->toBe([
        'database' => 'ok',
        'cache' => 'ok',
        'queue' => 'ok',
    ]);
});

it('reports degraded and names the dependency that is down', function () {
    Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('no connection'));

    $response = $this->getJson('/api/v1/health')->assertOk();

    // Still 200, still the success envelope: the kit reads `status`, not the HTTP code.
    expect($response->json('data.status'))->toBe('degraded')
        ->and($response->json('data.checks.cache'))->toBe('down')
        ->and($response->json('data.checks.database'))->toBe('ok')
        ->and($response->json('data.checks.queue'))->toBe('ok');
});

it('reports degraded when the cache accepts a write and loses it', function () {
    // Reachable and useless. A connection check alone would call this healthy.
    Cache::shouldReceive('put')->once()->andReturnTrue();
    Cache::shouldReceive('get')->once()->andReturnNull();
    Cache::shouldReceive('forget')->once()->andReturnTrue();

    $response = $this->getJson('/api/v1/health')->assertOk();

    expect($response->json('data.status'))->toBe('degraded')
        ->and($response->json('data.checks.cache'))->toBe('down');
});
