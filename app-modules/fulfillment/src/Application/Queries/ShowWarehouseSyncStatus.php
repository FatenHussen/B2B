<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Application\Queries;

use Illuminate\Http\Request;
use Modules\Core\Support\WarehouseScope;
use Modules\Fulfillment\Domain\Models\WarehouseSyncCursor;
use Modules\Fulfillment\Domain\Models\WarehouseSyncOperation;

final class ShowWarehouseSyncStatus
{
    /**
     * @return array{pending_server_side: int, last_push_at: string|null, conflicts: list<array{conflict_id: string, client_op_id: string, type: string, error: string|null}>}
     */
    public function __invoke(object $user, Request $request): array
    {
        $device = (string) $request->header('X-Device-Id', '');
        $userId = (int) $user->getAuthIdentifier();
        $warehouseId = WarehouseScope::currentId();

        $cursorQuery = WarehouseSyncCursor::query()
            ->where('warehouse_user_id', $userId)
            ->when($device !== '', fn ($q) => $q->where('device_uuid', $device))
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId));
        $cursor = $cursorQuery->first();

        $ops = WarehouseSyncOperation::query()
            ->where('warehouse_user_id', $userId)
            ->when($device !== '', fn ($q) => $q->where('device_uuid', $device))
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $pending = (clone $ops)->whereIn('status', ['pending', 'conflict'])->count();

        $conflicts = (clone $ops)
            ->where('status', 'conflict')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (WarehouseSyncOperation $op): array {
                $stored = is_array($op->server_result) ? $op->server_result : [];

                return [
                    'conflict_id' => (string) $op->id,
                    'client_op_id' => (string) $op->op_id,
                    'type' => (string) $op->type,
                    'error' => isset($stored['error']) && is_string($stored['error']) ? $stored['error'] : null,
                ];
            })
            ->all();

        return [
            'pending_server_side' => $pending,
            'last_push_at' => $cursor?->pushed_at?->timezone('Asia/Damascus')->toIso8601String(),
            'conflicts' => $conflicts,
        ];
    }
}
