<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Application\Actions;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\InvalidFields;
use Modules\Core\Support\Tenant;
use Modules\Core\Support\WarehouseScope;
use Modules\Fulfillment\Application\WarehouseWorkspace;
use Modules\Fulfillment\Domain\Models\WarehouseSyncCursor;
use Modules\Fulfillment\Domain\Models\WarehouseSyncOperation;

final class PushWarehouseSyncOperations
{
    public const TYPES = [
        'pick.scan',
        'pick.manual',
        'pick.shortage',
        'pick.complete',
        'pack.verify',
        'pack.complete',
    ];

    public function __construct(private readonly WarehouseWorkspace $workspace) {}

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

        $warehouseId = WarehouseScope::currentId();
        $channelId = Tenant::currentId();
        if ($warehouseId === null || $channelId === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        $userId = (int) $user->getAuthIdentifier();
        $rateKey = 'wh-sync-push:'.$deviceUuid;
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            throw DomainException::of(ErrorCode::RateLimited);
        }
        RateLimiter::hit($rateKey, 60);

        $results = [];
        foreach ($ops as $op) {
            $results[] = $this->one($user, $userId, $warehouseId, $channelId, $deviceUuid, $op);
        }

        WarehouseSyncCursor::query()->updateOrCreate(
            ['device_uuid' => $deviceUuid],
            [
                'warehouse_user_id' => $userId,
                'warehouse_id' => $warehouseId,
                'channel_id' => $channelId,
                'pushed_at' => now(),
            ],
        );

        return ['results' => $results];
    }

    /**
     * @param  array{client_op_id: string, type: string, payload: array<string, mixed>, created_at?: string|null}  $op
     * @return array{client_op_id: string, status: string, server_id: int|null, error: string|null}
     */
    private function one(
        object $user,
        int $userId,
        int $warehouseId,
        int $channelId,
        string $deviceUuid,
        array $op,
    ): array {
        $opId = (string) $op['client_op_id'];
        $existing = WarehouseSyncOperation::query()
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
        $applied = $this->dispatch($user, $type, $payload);

        $row = WarehouseSyncOperation::query()->create([
            'device_uuid' => $deviceUuid,
            'warehouse_user_id' => $userId,
            'warehouse_id' => $warehouseId,
            'channel_id' => $channelId,
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
    public function dispatch(object $user, string $type, array $payload): array
    {
        if (! in_array($type, self::TYPES, true)) {
            return ['status' => 'failed', 'server_id' => null, 'error' => 'unknown_type'];
        }

        $listId = (int) ($payload['picking_list_id'] ?? $payload['id'] ?? 0);
        if ($listId < 1) {
            return ['status' => 'failed', 'server_id' => null, 'error' => 'validation_failed'];
        }

        try {
            match ($type) {
                'pick.scan' => $this->workspace->scan(
                    $listId,
                    (string) ($payload['barcode'] ?? ''),
                    (int) ($payload['qty'] ?? 0),
                ),
                'pick.manual' => $this->workspace->manual(
                    $listId,
                    (int) ($payload['line_id'] ?? 0),
                    (int) ($payload['qty'] ?? 0),
                ),
                'pick.shortage' => $this->workspace->shortage(
                    $listId,
                    (int) ($payload['line_id'] ?? 0),
                    (int) ($payload['qty_available'] ?? 0),
                    (string) ($payload['reason'] ?? ''),
                ),
                'pick.complete' => $this->workspace->completePick($listId, $user),
                'pack.verify' => $this->workspace->verifyPack(
                    $listId,
                    is_array($payload['scans'] ?? null) ? $payload['scans'] : [],
                ),
                'pack.complete' => $this->workspace->completePack($listId, $payload),
            };

            return ['status' => 'applied', 'server_id' => $listId, 'error' => null];
        } catch (DomainException $e) {
            $status = $e->status === 409 ? 'conflict' : 'failed';

            return ['status' => $status, 'server_id' => null, 'error' => $e->errorCode];
        } catch (\Throwable) {
            return ['status' => 'failed', 'server_id' => null, 'error' => 'failed'];
        }
    }
}
