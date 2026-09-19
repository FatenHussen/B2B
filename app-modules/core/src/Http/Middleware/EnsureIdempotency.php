<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Domain\Models\IdempotencyKey;
use Modules\Core\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write-endpoint idempotency (ADR-05 / DOC-10 §5.4).
 *
 * Honour `X-Idempotency-Key`; accept legacy `Idempotency-Key`.
 * The key is required on every POST/PUT/PATCH/DELETE.
 *
 * A key names one caller's intent. The stored row is looked up by guard, user and key
 * together and answers nobody else (BE-C13) — which is why this middleware sits behind
 * `auth:*` in the priority list: it has to know who is asking before it can answer. On a
 * route with no guard every caller shares the anonymous principal; today each such write
 * is on the exemption list below, so no production route reaches that case.
 */
final class EnsureIdempotency
{
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), self::METHODS, true)) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        $key = $request->header('X-Idempotency-Key') ?: $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            return ApiResponse::error(
                'idempotency_key_required',
                'X-Idempotency-Key is required for write requests.',
                400,
            );
        }

        [$guard, $userId] = $this->principal($request);
        $hash = hash('sha256', $request->getMethod().'|'.$request->path().'|'.$request->getContent());
        $ttlHours = (int) config('core.idempotency_ttl_hours', 24);
        $lockSeconds = (int) config('core.idempotency_lock_seconds', 60);

        $row = $this->findLive($guard, $userId, $key, $ttlHours)
            ?? $this->claim($request, $guard, $userId, $key, $hash, $lockSeconds)
            // Lost the insert race to a concurrent request with the same principal and
            // key; the unique index kept exactly one row, and it is the winner's.
            ?? $this->findLive($guard, $userId, $key, $ttlHours);

        if ($row === null) {
            // Created and gone between two reads: the winner already failed and deleted
            // its row. Nothing to replay and nothing to take over; the client retries.
            return $this->inProgress();
        }

        if (! $row->wasRecentlyCreated) {
            $answer = $this->answerFrom($row, $hash, $lockSeconds);

            if ($answer !== null) {
                return $answer;
            }
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $row->forceFill([
                'response_body' => $response->getContent(),
                'status_code' => $response->getStatusCode(),
                'status' => 'complete',
                'locked_until' => null,
                'completed_at' => now(),
            ])->save();
        } else {
            $row->delete();
        }

        return $response;
    }

    /**
     * Credential-establishing endpoints carry no key (BE-C03 §5). The list lives in
     * config so the exemption is a readable contract rather than a condition buried
     * in this method; the frontend kit omits the header on exactly these paths.
     */
    private function isExempt(Request $request): bool
    {
        $exempt = config('core.idempotency_exempt', []);

        if (! is_array($exempt) || $exempt === []) {
            return false;
        }

        return $request->is(...array_map(strval(...), $exempt));
    }

    /**
     * Who is asking, as the pair a row is keyed by. This runs after `auth:*`, so on a
     * guarded route the user is the one that guard verified and the default driver is
     * that guard — `Authenticate` calls `shouldUse()` on success. A route with no guard
     * has no principal to offer; its callers share the anonymous pair.
     *
     * @return array{string, int}
     */
    private function principal(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [IdempotencyKey::ANONYMOUS_GUARD, IdempotencyKey::ANONYMOUS_USER_ID];
        }

        return [(string) Auth::getDefaultDriver(), (int) $user->getAuthIdentifier()];
    }

    private function findLive(string $guard, int $userId, string $key, int $ttlHours): ?IdempotencyKey
    {
        $row = IdempotencyKey::query()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();

        if ($row === null) {
            return null;
        }

        if ($row->created_at !== null && $row->created_at->lt(now()->subHours($ttlHours))) {
            $row->delete();

            return null;
        }

        return $row;
    }

    /**
     * Insert the `processing` row for this request, or null when the unique index
     * refused it because a concurrent request with the same principal and key got there
     * first. The index, not a read-then-write, is what makes two racing requests one
     * execution.
     */
    private function claim(Request $request, string $guard, int $userId, string $key, string $hash, int $lockSeconds): ?IdempotencyKey
    {
        try {
            return IdempotencyKey::create([
                'key' => $key,
                'guard' => $guard,
                'user_id' => $userId,
                'endpoint' => $request->getMethod().' '.$request->path(),
                'request_hash' => $hash,
                'status' => 'processing',
                'locked_until' => now()->addSeconds($lockSeconds),
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    /**
     * What a row that already existed means for this request: 409 when the body
     * differs or another worker still holds it, the stored response when it is
     * complete, or null when the row was abandoned mid-flight and this request has just
     * taken it over and should run.
     */
    private function answerFrom(IdempotencyKey $row, string $hash, int $lockSeconds): ?Response
    {
        if ($row->request_hash !== $hash) {
            return ApiResponse::error(
                'idempotency_key_conflict',
                'Idempotency-Key was reused with a different request.',
                409,
            );
        }

        if ($row->isComplete()) {
            return $this->replay($row);
        }

        if ($row->isLocked()) {
            return $this->inProgress();
        }

        // The lock lapsed: the worker holding this row died before it could finish, and
        // nothing will ever complete it. Take it over — once. Two retries can reach this
        // line together, and the conditional update lets exactly one of them through.
        $taken = IdempotencyKey::query()
            ->whereKey($row->getKey())
            ->where('status', 'processing')
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<=', now()))
            ->update(['locked_until' => now()->addSeconds($lockSeconds)]);

        return $taken === 1 ? null : $this->inProgress();
    }

    private function inProgress(): Response
    {
        return ApiResponse::error(
            'operation_in_progress',
            'A request with this idempotency key is still running.',
            409,
        );
    }

    private function replay(IdempotencyKey $stored): Response
    {
        return response($stored->response_body ?? '', $stored->status_code ?? 200)
            ->header('Content-Type', 'application/json')
            ->header('Idempotent-Replayed', 'true');
    }
}
