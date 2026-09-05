<?php

namespace Modules\Core\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Core\Http\ApiResponse;
use RuntimeException;
use Throwable;

/**
 * EP-CORE-001 (BE-F09).
 *
 * Unauthenticated, and a GET, so it carries no idempotency key either. It is the first
 * call the shared frontend kit makes, which is why it answers 200 even when a dependency
 * is down: the kit needs a readable envelope to show, not a transport error. `status` is
 * `ok` or `degraded`, and `checks` says which dependency decided that. Laravel's own
 * `/up` route is the one to point a load balancer at.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->probe(fn () => DB::connection()->select('select 1')),
            'cache' => $this->probe($this->cacheRoundTrip(...)),
            'queue' => $this->probe(fn () => Queue::connection()->size()),
        ];

        return ApiResponse::success([
            'status' => in_array('down', $checks, true) ? 'degraded' : 'ok',
            'app' => config('app.name'),
            'env' => config('app.env'),
            'checks' => $checks,
        ]);
    }

    private function probe(Closure $check): string
    {
        try {
            $check();

            return 'ok';
        } catch (Throwable) {
            // Deliberately swallowed. A health endpoint that 500s tells a client less
            // than one that names the dependency that failed.
            return 'down';
        }
    }

    /**
     * A write followed by a read. A cache that accepts a put and returns nothing is
     * reachable and useless, and connecting alone would not catch it.
     */
    private function cacheRoundTrip(): void
    {
        $key = 'health:'.Str::random(12);

        Cache::put($key, 'ok', 10);
        $seen = Cache::get($key);
        Cache::forget($key);

        if ($seen !== 'ok') {
            throw new RuntimeException('The cache did not return what it was given.');
        }
    }
}
