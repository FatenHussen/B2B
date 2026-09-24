<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Queries;

use Modules\Core\Contracts\RepDirectory;
use Modules\Core\Contracts\RepDutyLookup;
use Modules\Core\Contracts\RepLiveLocation;
use Modules\Core\Support\Tenant;

final class ListChannelRepsLive
{
    public function __construct(
        private readonly RepDutyLookup $duty,
        private readonly RepLiveLocation $live,
        private readonly RepDirectory $reps,
    ) {}

    /**
     * @return list<array{rep_id: int, name: string|null, zone_ids: list<int>, lat: float|null, lng: float|null, at: string|null, on_duty: bool}>
     */
    public function __invoke(): array
    {
        $channelId = (int) Tenant::currentId();
        $out = [];

        foreach ($this->duty->onDutyForChannel($channelId) as $repId) {
            $latest = $this->live->latest($repId);
            if ($latest === null || ! $latest['on_duty']) {
                continue;
            }

            $out[] = [
                'rep_id' => $repId,
                'name' => $this->reps->displayName($repId),
                'zone_ids' => $this->duty->zoneIds($repId),
                'lat' => $latest['lat'],
                'lng' => $latest['lng'],
                'at' => $latest['at'],
                'on_duty' => true,
            ];
        }

        return $out;
    }
}
