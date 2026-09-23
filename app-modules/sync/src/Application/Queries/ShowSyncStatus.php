<?php

declare(strict_types=1);

namespace Modules\Sync\Application\Queries;

use Illuminate\Http\Request;
use Modules\Sync\Domain\Models\SyncCursor;
use Modules\Sync\Domain\Models\SyncOperation;

final class ShowSyncStatus
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(object $user, Request $request): array
    {
        $device = (string) $request->header('X-Device-Id', '');
        $userId = (int) $user->getAuthIdentifier();
        $cursor = $device === ''
            ? null
            : SyncCursor::query()->where('device_uuid', $device)->where('app_user_id', $userId)->first();

        $pending = SyncOperation::query()
            ->where('app_user_id', $userId)
            ->when($device !== '', fn ($q) => $q->where('device_uuid', $device))
            ->whereIn('status', ['pending', 'conflict'])
            ->count();

        $conflicts = SyncOperation::query()
            ->where('app_user_id', $userId)
            ->when($device !== '', fn ($q) => $q->where('device_uuid', $device))
            ->where('status', 'conflict')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (SyncOperation $op) => [
                'conflict_id' => (string) $op->id,
                'op_id' => $op->op_id,
                'type' => $op->type,
            ])
            ->all();

        return [
            'pending_server_side' => $pending,
            'last_pull_at' => $cursor?->pulled_at?->timezone('Asia/Damascus')->toIso8601String(),
            'last_push_at' => $cursor?->pushed_at?->timezone('Asia/Damascus')->toIso8601String(),
            'conflicts' => $conflicts,
        ];
    }
}
