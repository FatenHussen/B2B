<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\ChannelDirectory;
use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\WarehouseDirectory;
use Modules\Core\Support\Tenant;

final class ShowChannelZoneCoverage
{
    public function __construct(
        private readonly ChannelDirectory $channels,
        private readonly RepDirectory $reps,
        private readonly WarehouseDirectory $warehouses,
    ) {}

    /**
     * @return array{without_reps: list<int>, without_warehouse: list<int>}
     */
    public function __invoke(): array
    {
        $channelId = (int) Tenant::currentId();
        $zoneIds = $this->channels->zoneIds($channelId);
        $withoutReps = [];
        foreach ($zoneIds as $zoneId) {
            if ($this->reps->countInZone($zoneId) === 0) {
                $withoutReps[] = $zoneId;
            }
        }

        $hasDefaultWarehouse = $this->warehouses->defaultIdForChannel($channelId) !== null;

        return [
            'without_reps' => $withoutReps,
            'without_warehouse' => $hasDefaultWarehouse ? [] : $zoneIds,
        ];
    }
}
