<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface WarehouseDirectory
{
    /**
     * @return array{id: int, name: string}|null
     */
    public function find(int $warehouseId): ?array;
}
