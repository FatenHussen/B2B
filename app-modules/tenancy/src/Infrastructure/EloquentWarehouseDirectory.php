<?php

declare(strict_types=1);

namespace Modules\Tenancy\Infrastructure;

use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\Warehouse;

/**
 * Every method here runs `acrossChannels()`, and none of them is a leak.
 *
 * `Warehouse` carries `BelongsToChannel` as of rule 10, but this directory is one of the
 * things that *establishes* which channel a caller belongs to — warehouse device login
 * reads a warehouse to discover its channel, before any tenant exists. Scoping these
 * reads would not narrow them, it would throw MissingChannelScopeException on login.
 *
 * The two methods that could leak do not, because each takes the channel as an argument
 * and filters on it explicitly: `belongsToChannel` and `defaultIdForChannel`. `find` and
 * `channelId` are lookups by primary key that return the channel rather than assume it —
 * which is exactly what the caller needs to decide where the request belongs.
 */
final class EloquentWarehouseDirectory implements WarehouseDirectory
{
    public function find(int $warehouseId): ?array
    {
        $row = Warehouse::query()->acrossChannels()->whereKey($warehouseId)->first(['id', 'name', 'channel_id']);

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
        $id = Warehouse::query()->acrossChannels()->whereKey($warehouseId)->value('channel_id');

        return $id !== null ? (int) $id : null;
    }

    public function belongsToChannel(int $warehouseId, int $channelId): bool
    {
        return Warehouse::query()
            ->acrossChannels()
            ->whereKey($warehouseId)
            ->where('channel_id', $channelId)
            ->exists();
    }

    public function defaultIdForChannel(int $channelId): ?int
    {
        $id = Warehouse::query()
            ->acrossChannels()
            ->where('channel_id', $channelId)
            ->where('status', WarehouseStatus::Active)
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
