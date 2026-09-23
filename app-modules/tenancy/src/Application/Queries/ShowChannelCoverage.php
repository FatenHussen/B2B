<?php

declare(strict_types=1);

namespace Modules\Tenancy\Application\Queries;

use Modules\Core\Contracts\ReferenceDirectory;
use Modules\Core\Contracts\RepDirectory;
use Modules\Tenancy\Domain\Models\ChannelZoneLookup;
use Modules\Tenancy\Domain\Models\SupplyChannel;

final class ShowChannelCoverage
{
    public function __construct(
        private readonly RepDirectory $reps,
        private readonly ReferenceDirectory $refs,
    ) {}

    /**
     * @return array{zones: list<array<string, mixed>>, overlaps: list<array<string, mixed>>, zones_without_rep: list<int>}
     */
    public function __invoke(SupplyChannel $channel): array
    {
        $channelId = (int) $channel->id;

        // ChannelZoneLookup is deliberately unscoped (platform record about coverage).
        $rows = ChannelZoneLookup::query()
            ->where('supply_channel_id', $channelId)
            ->get(['zone_id']);

        $zoneIds = $rows->pluck('zone_id')->map(fn ($id) => (int) $id)->all();
        $zones = [];
        $withoutRep = [];

        foreach ($zoneIds as $zoneId) {
            $hasRep = $this->reps->countInZone($zoneId) > 0;
            $zones[] = [
                'id' => $zoneId,
                'name' => $this->refs->zoneName($zoneId) ?? (string) $zoneId,
                'has_rep' => $hasRep,
            ];
            if (! $hasRep) {
                $withoutRep[] = $zoneId;
            }
        }

        $overlaps = [];
        foreach ($zoneIds as $zoneId) {
            // Unscoped lookup: find other channels covering the same zone.
            $others = ChannelZoneLookup::query()
                ->where('zone_id', $zoneId)
                ->where('supply_channel_id', '!=', $channelId)
                ->pluck('supply_channel_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($others !== []) {
                $overlaps[] = [
                    'zone_id' => $zoneId,
                    'other_channel_ids' => $others,
                    'exclusive' => false,
                ];
            }
        }

        return [
            'zones' => $zones,
            'overlaps' => $overlaps,
            'zones_without_rep' => $withoutRep,
        ];
    }
}
