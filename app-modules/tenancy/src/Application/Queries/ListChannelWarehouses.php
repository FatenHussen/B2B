<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Queries;

use Modules\Tenancy\Domain\Enums\WarehouseStatus;
use Modules\Tenancy\Domain\Models\Warehouse;

final class ListChannelWarehouses
{
    /**
     * @return list<array{id: int, name: string, status: string}>
     */
    public function __invoke(): array
    {
        return Warehouse::query()
            ->orderBy('id')
            ->get(['id', 'name', 'status'])
            ->map(fn (Warehouse $row) => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'status' => $row->status instanceof WarehouseStatus
                    ? $row->status->value
                    : (string) $row->status,
            ])
            ->all();
    }
}
