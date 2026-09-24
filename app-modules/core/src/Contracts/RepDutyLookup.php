<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RepDutyLookup
{
    public function isOnDuty(int $repUserId): bool;

    /**
     * @return list<int>
     */
    public function zoneIds(int $repUserId): array;

    public function trackingEnabled(int $repUserId): bool;

    /**
     * Active, on-duty reps on the channel that cover the zone — for auto-assign (EP-SC-066).
     *
     * @return list<int> app_user ids, lightest load first when load is known
     */
    public function onDutyCoveringZone(int $channelId, int $zoneId): array;

    /**
     * @return list<int> on-duty active rep app_user ids for channel
     */
    public function onDutyForChannel(int $channelId): array;

    /**
     * @return array{on_duty: bool, tracking_enabled: bool}
     */
    public function setDuty(int $repUserId, bool $onDuty): array;
}
