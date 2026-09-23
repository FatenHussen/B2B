<?php

declare(strict_types=1);

namespace Modules\Sync\Application\Actions;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Contracts\SyncOperationHandler;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Sync\Domain\Models\SyncCursor;
use Modules\Sync\Domain\Models\SyncOperation;

final class PushSyncOperations
{
    /**
     * @param  iterable<SyncOperationHandler>  $handlers
     */
    public function __construct(private readonly iterable $handlers) {}

    /**
     * @param  array{operations: list<array{client_op_id: string, type: string, payload: array<string, mixed>, created_at?: string|null}>}  $data
     * @return array{results: list<array{client_op_id: string, status: string, server_id: int|null, error: string|null}>}
     */
    public function __invoke(object $user, array $data, ?string $deviceUuid): array
    {
        if ($deviceUuid === null || $deviceUuid === '') {
            InvalidFields::throw(['device_uuid' => 'validation.required']);
        }

        $ops = $data['operations'];
        if (count($ops) > 500) {
            throw DomainException::of(ErrorCode::ValidationFailed);
        }

        $userId = (int) $user->getAuthIdentifier();
        $rateKey = 'sync-push:'.$deviceUuid;
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            throw DomainException::of(ErrorCode::RateLimited);
        }
        RateLimiter::hit($rateKey, 60);

        $results = [];
        foreach ($ops as $op) {
            $results[] = $this->one($user, $userId, $deviceUuid, $op);
        }

        SyncCursor::query()->updateOrCreate(
            ['device_uuid' => $deviceUuid],
            ['app_user_id' => $userId, 'pushed_at' => now()],
        );

        return ['results' => $results];
    }

    /**
     * @param  array{client_op_id: string, type: string, payload: array<string, mixed>, created_at?: string|null}  $op
     * @return array{client_op_id: string, status: string, server_id: int|null, error: string|null}
     */
    private function one(object $user, int $userId, string $deviceUuid, array $op): array
    {
        $opId = (string) $op['client_op_id'];
        $existing = SyncOperation::query()
            ->where('device_uuid', $deviceUuid)
            ->where('op_id', $opId)
            ->first();
        if ($existing !== null) {
            $stored = is_array($existing->server_result) ? $existing->server_result : [];

            return [
                'client_op_id' => $opId,
                'status' => $existing->status === 'applied' ? 'duplicate' : (string) $existing->status,
                'server_id' => isset($stored['server_id']) ? (int) $stored['server_id'] : null,
                'error' => isset($stored['error']) && is_string($stored['error']) ? $stored['error'] : null,
            ];
        }

        $type = (string) $op['type'];
        $payload = is_array($op['payload'] ?? null) ? $op['payload'] : [];
        $applied = $this->dispatch($user, $type, $payload, $opId);

        $row = SyncOperation::query()->create([
            'device_uuid' => $deviceUuid,
            'app_user_id' => $userId,
            'op_id' => $opId,
            'type' => $type,
            'payload' => $payload,
            'client_ts' => $op['created_at'] ?? null,
            'status' => $applied['status'],
            'server_result' => $applied,
        ]);

        return [
            'client_op_id' => $opId,
            'status' => (string) $row->status,
            'server_id' => $applied['server_id'],
            'error' => $applied['error'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, server_id: int|null, error: string|null}
     */
    private function dispatch(object $user, string $type, array $payload, string $opId): array
    {
        foreach ($this->handlers as $handler) {
            if (! $handler->handles($type)) {
                continue;
            }
            try {
                return $handler->apply($user, $type, $payload, $opId);
            } catch (DomainException $e) {
                $status = $e->status === 409 ? 'conflict' : 'failed';

                return ['status' => $status, 'server_id' => null, 'error' => $e->errorCode];
            } catch (\Throwable $e) {
                return ['status' => 'failed', 'server_id' => null, 'error' => 'failed'];
            }
        }

        return ['status' => 'failed', 'server_id' => null, 'error' => 'unknown_type'];
    }
}
