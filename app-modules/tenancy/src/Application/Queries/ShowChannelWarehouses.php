<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Queries;

use Modules\Core\Support\Tenant;
use Modules\Tenancy\Domain\Models\SupplyChannel;
use Modules\Tenancy\Domain\Models\Warehouse;

final class ShowChannelWarehouses
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(SupplyChannel $channel): array
    {
        return Tenant::as((int) $channel->id, function () use ($channel): array {
            return Warehouse::query()
                ->where('channel_id', $channel->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Warehouse $row) => [
                    'id' => $row->id,
                    'name' => $row->name,
                    'status' => $row->status->value,
                    'served_zone_ids' => [],
                ])
                ->all();
        });
    }
}
