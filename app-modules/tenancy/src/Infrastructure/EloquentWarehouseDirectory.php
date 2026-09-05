<?php

declare(strict_types=1);

namespace Modules\Tenancy\Infrastructure;

use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\Warehouse;

final class EloquentWarehouseDirectory implements WarehouseDirectory
{
    public function find(int $warehouseId): ?array
    {
        $row = Warehouse::query()->whereKey($warehouseId)->first(['id', 'name', 'channel_id']);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'name' => $row->name,
            'channel_id' => (int) $row->channel_id,
        ];
    }

    public function channelId(int $warehouseId): ?int
    {
        $id = Warehouse::query()->whereKey($warehouseId)->value('channel_id');

        return $id !== null ? (int) $id : null;
    }

    public function belongsToChannel(int $warehouseId, int $channelId): bool
    {
        return Warehouse::query()
            ->whereKey($warehouseId)
            ->where('channel_id', $channelId)
            ->exists();
    }

    public function defaultIdForChannel(int $channelId): ?int
    {
        $id = Warehouse::query()
            ->where('channel_id', $channelId)
            ->where('status', WarehouseStatus::Active)
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
