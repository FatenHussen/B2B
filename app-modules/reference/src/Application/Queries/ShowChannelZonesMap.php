<?php

declare(strict_types=1);

namespace Modules\Reference\Application\Queries;

use Modules\Reference\Domain\Models\ChannelZone;

/**
 * EP-SC-163 — zone map polygons joined from coverage + reference Zone.polygon.
 */
final class ShowChannelZonesMap
{
    /**
     * @return list<array{
     *     zone_id: int,
     *     zone_name: string|null,
     *     polygon: array<string, mixed>|null,
     *     delivery_days: list<string>|null,
     *     delivery_windows: list<array{day: string, start: string, end: string}>|null
     * }>
     */
    public function __invoke(): array
    {
        return ChannelZone::query()
            ->with('zone')
            ->orderBy('id')
            ->get()
            ->map(static function (ChannelZone $row): array {
                return [
                    'zone_id' => (int) $row->zone_id,
                    'zone_name' => $row->zone?->name,
                    'polygon' => $row->zone?->polygon,
                    'delivery_days' => $row->delivery_days,
                    'delivery_windows' => $row->delivery_windows,
                ];
            })
            ->values()
            ->all();
    }
}
