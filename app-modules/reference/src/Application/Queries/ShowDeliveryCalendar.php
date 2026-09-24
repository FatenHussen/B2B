<?php

declare(strict_types=1);

namespace Modules\Reference\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\Reference\Domain\Models\ChannelZone;

/**
 * EP-SC-161 — weekly delivery calendar derived from channel_zone.delivery_days.
 * week_start is always Sunday in Asia/Damascus; no new table.
 */
final class ShowDeliveryCalendar
{
    /** @var list<string> */
    private const WEEKDAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /**
     * @return array{
     *     week_start: string,
     *     days: list<array{date: string, weekday: string, zones: list<array{zone_id: int, zone_name: string|null}>}>
     * }
     */
    public function __invoke(?string $week = null): array
    {
        $weekStart = $this->resolveWeekStart($week);
        $coverage = ChannelZone::query()
            ->with('zone')
            ->orderBy('id')
            ->get();

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $weekStart->addDays($offset);
            $weekday = self::WEEKDAYS[$date->dayOfWeek];
            $zones = [];
            foreach ($coverage as $row) {
                $deliveryDays = $row->delivery_days ?? [];
                if (! in_array($weekday, $deliveryDays, true)) {
                    continue;
                }
                $zones[] = [
                    'zone_id' => (int) $row->zone_id,
                    'zone_name' => $row->zone?->name,
                ];
            }
            $days[] = [
                'date' => $date->toDateString(),
                'weekday' => $weekday,
                'zones' => $zones,
            ];
        }

        return [
            'week_start' => $weekStart->toDateString(),
            'days' => $days,
        ];
    }

    private function resolveWeekStart(?string $week): CarbonImmutable
    {
        $anchor = CarbonImmutable::now('Asia/Damascus')->startOfDay();
        if (is_string($week) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) === 1) {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $week, 'Asia/Damascus');
            if ($parsed instanceof CarbonImmutable && $parsed->format('Y-m-d') === $week) {
                $anchor = $parsed->startOfDay();
            }
        }

        return $anchor->startOfWeek(CarbonImmutable::SUNDAY);
    }
}
