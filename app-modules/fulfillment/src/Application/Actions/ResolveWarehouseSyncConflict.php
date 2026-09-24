<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Application\Actions;

use Modules\Core\Domain\Enums\ErrorCode;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Support\WarehouseScope;
use Modules\Fulfillment\Domain\Models\WarehouseSyncOperation;

final class ResolveWarehouseSyncConflict
{
    public function __construct(private readonly PushWarehouseSyncOperations $push) {}

    /**
     * @param  array{conflict_id: string, resolution: string}  $data
     * @return array{success: true}
     */
    public function __invoke(object $user, array $data): array
    {
        $warehouseId = WarehouseScope::currentId();
        $query = WarehouseSyncOperation::query()
            ->where('warehouse_user_id', (int) $user->getAuthIdentifier())
            ->where('status', 'conflict')
            ->where(function ($q) use ($data): void {
                $q->whereKey($data['conflict_id'])->orWhere('op_id', $data['conflict_id']);
            });
        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        $row = $query->first();
        if ($row === null) {
            throw DomainException::of(ErrorCode::NotFound);
        }

        if (in_array($data['resolution'], ['server_wins', 'keep_server'], true)) {
            $row->status = 'discarded';
            $row->server_result = array_merge(
                is_array($row->server_result) ? $row->server_result : [],
                ['status' => 'discarded', 'resolution' => 'server_wins'],
            );
            $row->save();

            return ['success' => true];
        }

        $payload = is_array($row->payload) ? $row->payload : [];
        $applied = $this->push->dispatch($user, (string) $row->type, $payload);
        $row->status = $applied['status'];
        $row->server_result = $applied;
        $row->save();

        return ['success' => true];
    }
}
