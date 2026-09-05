<?php

declare(strict_types=1);

namespace Modules\Inventory\Infrastructure;

use Modules\Core\Contracts\WarehouseLocationDirectory;
use Modules\Inventory\Domain\Models\WarehouseLocation;

final class EloquentWarehouseLocationDirectory implements WarehouseLocationDirectory
{
    public function find(int $locationId): ?array
    {
        $location = WarehouseLocation::query()->find($locationId);

        return $location === null ? null : $this->toArray($location);
    }

    public function orderedForWarehouse(int $warehouseId): array
    {
        return WarehouseLocation::query()
            ->where('warehouse_id', $warehouseId)
            ->orderBy('aisle')
            ->orderBy('shelf')
            ->get()
            ->map(fn (WarehouseLocation $location): array => $this->toArray($location))
            ->all();
    }

    /**
     * @return array{id: int, warehouse_id: int, aisle: string|null, shelf: string|null, code: string|null}
     */
    private function toArray(WarehouseLocation $location): array
    {
        return [
            'id' => (int) $location->getKey(),
            'warehouse_id' => (int) $location->getAttribute('warehouse_id'),
            'aisle' => $location->getAttribute('aisle'),
            'shelf' => $location->getAttribute('shelf'),
            'code' => $location->getAttribute('code'),
        ];
    }
}
