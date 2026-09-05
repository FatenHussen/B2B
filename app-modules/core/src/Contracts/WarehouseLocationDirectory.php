<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Read access to warehouse locations for modules that do not own them.
 *
 * Inventory owns `warehouse_locations`. Fulfillment needs an aisle and a shelf to print
 * on a picking line, which is a read of someone else's table — so it asks through here
 * and receives plain data. Returning the Eloquent model would hand out a live query
 * builder across a module boundary and re-create the shared schema the modules exist to
 * avoid.
 *
 * @phpstan-type WarehouseLocationData array{
 *     id: int,
 *     warehouse_id: int,
 *     aisle: string|null,
 *     shelf: string|null,
 *     code: string|null,
 * }
 */
interface WarehouseLocationDirectory
{
    /**
     * @return WarehouseLocationData|null
     */
    public function find(int $locationId): ?array;

    /**
     * Every location of one warehouse, in picking order: aisle, then shelf.
     *
     * @return list<WarehouseLocationData>
     */
    public function orderedForWarehouse(int $warehouseId): array;
}
