<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Modules\Core\Domain\Models\IdempotencyKey;
use Modules\Core\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write-endpoint idempotency (ADR-05 / DOC-10 §5.4).
 *
 * Honour `X-Idempotency-Key`; accept legacy `Idempotency-Key`.
 * The key is required on every POST/PUT/PATCH/DELETE.
 */
final class EnsureIdempotency
{
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), self::METHODS, true)) {
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

        $hash = hash('sha256', $request->getMethod().'|'.$request->path().'|'.$request->getContent());
        $ttlHours = (int) config('core.idempotency_ttl_hours', 24);

        if ($stored = $this->findLive($key, $ttlHours)) {
            if ($stored->request_hash && $stored->request_hash !== $hash) {
                return ApiResponse::error(
                    'idempotency_key_conflict',
                    'Idempotency-Key was reused with a different request.',
                    409,
                );
            }

            if ($stored->isProcessing()) {
                return ApiResponse::error(
                    'operation_in_progress',
                    'A request with this idempotency key is still running.',
                    409,
                );
            }

            return $this->replay($stored);
        }

        try {
            IdempotencyKey::create([
                'key' => $key,
                'user_id' => $request->user()?->getAuthIdentifier(),
                'endpoint' => $request->getMethod().' '.$request->path(),
                'request_hash' => $hash,
                'status' => 'processing',
                'created_at' => now(),
            ]);
        } catch (QueryException) {
            if ($stored = $this->findLive($key, $ttlHours)) {
                if ($stored->isProcessing()) {
                    return ApiResponse::error(
                        'operation_in_progress',
                        'A request with this idempotency key is still running.',
                        409,
                    );
                }

                return $this->replay($stored);
            }
        }

        $response = $next($request);

        $row = IdempotencyKey::query()->where('key', $key)->first();

        if ($row && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $row->forceFill([
                'response_body' => $response->getContent(),
                'status_code' => $response->getStatusCode(),
                'status' => 'complete',
                'completed_at' => now(),
            ])->save();
        } elseif ($row) {
            $row->delete();
        }

        return $response;
    }

    private function findLive(string $key, int $ttlHours): ?IdempotencyKey
    {
        $row = IdempotencyKey::query()->where('key', $key)->first();

        if ($row === null) {
            return null;
        }

        if ($row->created_at !== null && $row->created_at->lt(now()->subHours($ttlHours))) {
            $row->delete();

            return null;
        }

        return $row;
    }

    private function replay(IdempotencyKey $stored): Response
    {
        return response($stored->response_body ?? '', $stored->status_code ?? 200)
            ->header('Content-Type', 'application/json')
            ->header('Idempotent-Replayed', 'true');
    }
}
