<?php

declare(strict_types=1);

namespace Modules\Tenancy\Infrastructure;

use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Tenancy\Domain\Models\Warehouse;

final class EloquentWarehouseDirectory implements WarehouseDirectory
{
    public function find(int $warehouseId): ?array
    {
        $row = Warehouse::query()->whereKey($warehouseId)->first(['id', 'name']);

        if ($row === null) {
            return null;
        }

        return ['id' => (int) $row->id, 'name' => $row->name];
    }
}
