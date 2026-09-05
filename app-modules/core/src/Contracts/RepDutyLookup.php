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
     * @return array{on_duty: bool, tracking_enabled: bool}
     */
    public function setDuty(int $repUserId, bool $onDuty): array;
}
