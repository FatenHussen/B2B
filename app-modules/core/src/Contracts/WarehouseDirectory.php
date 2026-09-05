<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface WarehouseDirectory
{
    /**
     * @return array{id: int, name: string, channel_id: int}|null
     */
    public function find(int $warehouseId): ?array;

    public function channelId(int $warehouseId): ?int;

    public function belongsToChannel(int $warehouseId, int $channelId): bool;

    public function defaultIdForChannel(int $channelId): ?int;
}
